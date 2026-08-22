<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

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

    public function testItAppliesOpenShopAction(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);
        $applier = new GameRunActionApplier();

        $applier->apply($gameRun, GameRunActionType::OPEN_SHOP, []);

        self::assertNotNull($gameRun->getCurrentShop());
    }

    public function testItAppliesPurchaseAction(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);
        $applier = new GameRunActionApplier();

        $applier->apply($gameRun, GameRunActionType::OPEN_SHOP, []);
        $shop = $gameRun->getCurrentShop();
        $price = $shop->getOffers()[0]->getPrice();
        $goldBefore = $gameRun->getWallet()->getBalance();

        $applier->apply($gameRun, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        self::assertTrue($shop->getOffers()[0]->isPurchased());
        self::assertSame($goldBefore - $price, $gameRun->getWallet()->getBalance());
    }

    public function testItAppliesSwapAction(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);
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

    public function testItAppliesResolveRoundAction(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);
        $applier = new GameRunActionApplier();

        $applier->apply($gameRun, GameRunActionType::RESOLVE_ROUND, []);

        self::assertSame(2, $gameRun->getCurrentRound());
        self::assertSame(1, $gameRun->getVictories() + $gameRun->getDefeats());
    }
}
