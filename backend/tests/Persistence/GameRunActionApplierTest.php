<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Application\RoundOutcome;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Persistence\GameRunActionApplier;
use App\Persistence\GameRunActionType;
use App\Tests\Support\CreatesRealGameRun;
use PHPUnit\Framework\TestCase;

final class GameRunActionApplierTest extends TestCase
{
    use CreatesRealGameRun;

    public function testItAppliesChooseHeroAction(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);
        $applier = new GameRunActionApplier();
        $offer = $gameRun->getPendingHeroOffer();
        $heroId = $offer->candidates[0]->id;

        $applier->apply($gameRun, GameRunActionType::CHOOSE_HERO, ['heroId' => $heroId]);

        self::assertCount(1, $gameRun->getRoster());
        self::assertSame($heroId, $gameRun->getRoster()[0]->id);
        self::assertNull($gameRun->getPendingHeroOffer());
        self::assertNotNull($gameRun->getCurrentShop());
    }

    public function testItAppliesOpenShopAction(): void
    {
        $gameRun = $this->createRealGameRunReadyToPlay(seed: 42);
        $applier = new GameRunActionApplier();

        $applier->apply($gameRun, GameRunActionType::OPEN_SHOP, []);

        self::assertNotNull($gameRun->getCurrentShop());
    }

    public function testItAppliesPurchaseAction(): void
    {
        $gameRun = $this->createRealGameRunReadyToPlay(seed: 42);
        $applier = new GameRunActionApplier();

        // La boutique est déjà ouverte automatiquement par chooseHero().
        $shop = $gameRun->getCurrentShop();
        $price = $shop->getOffers()[0]->getPrice();
        $goldBefore = $gameRun->getWallet()->getBalance();

        $applier->apply($gameRun, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        self::assertTrue($shop->getOffers()[0]->isPurchased());
        self::assertSame($goldBefore - $price, $gameRun->getWallet()->getBalance());
    }

    public function testItAppliesSwapAction(): void
    {
        $gameRun = $this->createRealGameRunReadyToPlay(seed: 42);
        $applier = new GameRunActionApplier();
        $heroId = $gameRun->getRoster()[0]->id;

        $inventoryItem = new Item(
            id: 'inventory_item',
            name: 'Inventory Item',
            rarity: Rarity::COMMON,
            affinity: 'physical',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 100,
            effects: [],
        );
        $stashItem = new Item(
            id: 'stash_item',
            name: 'Stash Item',
            rarity: Rarity::COMMON,
            affinity: 'physical',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 100,
            effects: [],
        );

        $gameRun->getInventory()->add($inventoryItem, $heroId);
        $gameRun->getStash()->add($stashItem);

        $applier->apply($gameRun, GameRunActionType::SWAP, [
            'inventoryIndex' => 0,
            'stashIndex' => 0,
            'heroId' => $heroId,
        ]);

        self::assertSame('stash_item', $gameRun->getInventory()->getItems()[0]->item->id);
        self::assertSame($heroId, $gameRun->getInventory()->getItems()[0]->heroId);
        self::assertSame('inventory_item', $gameRun->getStash()->getItems()[0]->id);
    }

    /**
     * Le rejeu applique l'issue enregistrée — il ne resimule pas (D-18 volet 1,
     * E-11).
     *
     * L'assertion qui porte le contrat est la dernière : un `SimulationResult`
     * présent après coup signifierait que le moteur a tourné, donc que le
     * journal dépend encore de lui.
     */
    public function testItAppliesResolveRoundActionFromTheRecordedOutcome(): void
    {
        $gameRun = $this->createRealGameRunReadyToPlay(seed: 42);
        $applier = new GameRunActionApplier();

        $applier->apply($gameRun, GameRunActionType::RESOLVE_ROUND, ['outcome' => RoundOutcome::VICTORY->value]);

        self::assertSame(2, $gameRun->getCurrentRound());
        self::assertSame(1, $gameRun->getVictories());
        self::assertNull($gameRun->getLastCombatResult(), 'Aucun combat ne doit avoir été simulé au rejeu.');
    }

    /**
     * Un journal antérieur à l'enregistrement de l'issue est refusé, pas
     * rattrapé.
     *
     * Le rattraper voudrait dire resimuler, c'est-à-dire exactement le défaut
     * que ce commit ferme. `LogicException` et non `InvalidArgumentException` :
     * la charge utile n'est pas malformée du fait du client — elle est écrite
     * par le serveur —, c'est l'état du journal qui est en conflit avec le code
     * qui le relit. Le `Router` en fait un 409.
     */
    public function testItRefusesAResolveRoundActionWithoutARecordedOutcome(): void
    {
        $gameRun = $this->createRealGameRunReadyToPlay(seed: 42);
        $applier = new GameRunActionApplier();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('without a recorded outcome');

        $applier->apply($gameRun, GameRunActionType::RESOLVE_ROUND, []);
    }
}
