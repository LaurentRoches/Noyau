<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\HeroRosterFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Application\GameRun;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Enum\ItemSize;
use App\Domain\Model\Vestige;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class GameRunTest extends TestCase
{
    private function createGameRun(int $startingGold = 20): GameRun
    {
        $vestige = new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            startingGold: $startingGold,
            startingIncome: 5
        );

        $configPath = __DIR__ . '/../../config/game';
        $itemRepository = new JsonItemRepository($configPath . '/items.json');
        $heroRepository = new JsonHeroRepository($configPath . '/heroes.json');

        $combatBoardFactory = new CombatBoardFactory(
            new JsonVestigeRepository($configPath . '/vestiges.json'),
            $heroRepository,
            $itemRepository,
            new HeroSkillDecorator(),
        );

        $opponentFactory = new ScriptedOpponentFactory(
            $combatBoardFactory,
            $itemRepository,
            $heroRepository,
            new JsonScriptedOpponentRepository($configPath . '/scripted_opponent.json'),
        );

        return new GameRun(
            $vestige,
            new ShopFactory($itemRepository),
            $opponentFactory,
            new HeroRosterFactory($heroRepository),
            $combatBoardFactory,
            new Simulator(maxTicks: 200),
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
    }

    public function testInitializesWalletWithVestigeStartingGold(): void
    {
        $gameRun = $this->createGameRun();

        self::assertSame(20, $gameRun->getWallet()->getBalance());
    }

    public function testRecordVictoryCreditsWalletWithRewardAndIncome(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();

        self::assertSame(35, $gameRun->getWallet()->getBalance());
    }

    public function testRecordDefeatCreditsWalletWithIncomeOnly(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordDefeat();

        self::assertSame(25, $gameRun->getWallet()->getBalance());
    }

    public function testVictoryAndDefeatCountersCanBeRead(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();
        $gameRun->recordVictory();
        $gameRun->recordDefeat();

        self::assertSame(2, $gameRun->getVictories());
        self::assertSame(1, $gameRun->getDefeats());
    }

    public function testRunIsOverAfterTenVictories(): void
    {
        $gameRun = $this->createGameRun();

        for ($i = 0; $i < 10; $i++) {
            $gameRun->recordVictory();
        }

        self::assertTrue($gameRun->isOver());
        self::assertTrue($gameRun->hasWon());
    }

    public function testRunIsOverAfterThreeDefeats(): void
    {
        $gameRun = $this->createGameRun();

        for ($i = 0; $i < 3; $i++) {
            $gameRun->recordDefeat();
        }

        self::assertTrue($gameRun->isOver());
        self::assertFalse($gameRun->hasWon());
    }

    public function testRunIsNotOverBeforeThresholds(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();
        $gameRun->recordDefeat();

        self::assertFalse($gameRun->isOver());
    }

    public function testCurrentRoundStartsAtOne(): void
    {
        $gameRun = $this->createGameRun();

        self::assertSame(1, $gameRun->getCurrentRound());
    }

    public function testCurrentRoundIncrementsAfterVictory(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();

        self::assertSame(2, $gameRun->getCurrentRound());
    }

    public function testCurrentRoundIncrementsAfterDefeat(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordDefeat();

        self::assertSame(2, $gameRun->getCurrentRound());
    }

    public function testOpenShopGeneratesShopWithFourOffers(): void
    {
        $gameRun = $this->createGameRun();

        $shop = $gameRun->openShop();

        self::assertCount(4, $shop->getOffers());
        self::assertSame($shop, $gameRun->getCurrentShop());
    }

    public function testPurchaseItemAddsToInventoryAndDebitsWallet(): void
    {
        $gameRun = $this->createGameRun();
        $gameRun->openShop();
        $balanceBefore = $gameRun->getWallet()->getBalance();

        $offer = $gameRun->getCurrentShop()->getOffers()[0];
        $item = $gameRun->purchaseItem(0);

        self::assertSame($offer->getItem(), $item);
        self::assertCount(1, $gameRun->getInventory()->getItems());
        self::assertSame($balanceBefore - $offer->getPrice(), $gameRun->getWallet()->getBalance());
    }

    public function testPurchaseItemThrowsWhenNoShopIsOpen(): void
    {
        $gameRun = $this->createGameRun();

        $this->expectException(\LogicException::class);
        $gameRun->purchaseItem(0);
    }

    public function testPlayRoundBuildsBoardsRunsSimulationAndAdvancesRound(): void
    {
        $gameRun = $this->createGameRun();

        $result = $gameRun->playRound();

        self::assertInstanceOf(SimulationResult::class, $result);
        self::assertSame(2, $gameRun->getCurrentRound());
    }

    public function testPlayRoundThrowsWhenRunIsAlreadyOver(): void
    {
        $gameRun = $this->createGameRun();

        for ($i = 0; $i < 10; $i++) {
            $gameRun->recordVictory();
        }

        self::assertTrue($gameRun->isOver());

        $this->expectException(\LogicException::class);
        $gameRun->playRound();
    }

    public function testPlayRoundOpensANewShopWhenRunIsNotOver(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->playRound();

        self::assertNotNull($gameRun->getCurrentShop());
    }

    public function testPlayRoundDoesNotOpenANewShopWhenThisRoundEndsTheRun(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->openShop();

        // Deux défaites déjà enregistrées ; sans le moindre objet en inventaire,
        // ce round sera nécessairement une défaite aussi (aucun dégât possible
        // côté joueur face à un adversaire scripté équipé) — la 3e défaite
        // termine le run.
        $gameRun->recordDefeat();
        $gameRun->recordDefeat();

        $gameRun->playRound();

        self::assertTrue($gameRun->isOver());
        self::assertNull($gameRun->getCurrentShop());
    }

    public function testSwapWithStashExchangesItemsBetweenBoardAndStash(): void
    {
        $gameRun = $this->createGameRun(startingGold: 1000);

        $purchased = 0;
        $attempts = 0;
        while ($purchased < 7 && $attempts < 30) {
            $shop = $gameRun->openShop();
            $attempts++;

            $oneHandIndex = null;
            foreach ($shop->getOffers() as $index => $offer) {
                if ($offer->getItem()->size === ItemSize::ONE_HAND) {
                    $oneHandIndex = $index;
                    break;
                }
            }

            if ($oneHandIndex === null) {
                continue;
            }

            $gameRun->purchaseItem($oneHandIndex);
            $purchased++;
        }

        self::assertSame(7, $purchased, 'Expected to purchase 7 ONE_HAND items within 30 shop attempts.');
        self::assertNotEmpty($gameRun->getStash()->getItems(), 'Expected stash to contain at least one item after 7 purchases.');

        $inventoryIndex = 0;
        $stashIndex = 0;
        $heroId = $gameRun->getInventory()->getItems()[$inventoryIndex]->heroId;

        $boardAssignedItemBefore = $gameRun->getInventory()->getItems()[$inventoryIndex];
        $stashItemBefore = $gameRun->getStash()->getItems()[$stashIndex];

        $gameRun->swapWithStash($inventoryIndex, $stashIndex, $heroId);

        self::assertSame($stashItemBefore, $gameRun->getInventory()->getItems()[$inventoryIndex]->item);
        self::assertSame($heroId, $gameRun->getInventory()->getItems()[$inventoryIndex]->heroId);
        self::assertSame($boardAssignedItemBefore->item, $gameRun->getStash()->getItems()[$stashIndex]);
    }

    public function testGetLastCombatResultReturnsNullBeforeAnyRoundIsPlayed(): void
    {
        $gameRun = $this->createGameRun();

        self::assertNull($gameRun->getLastCombatResult());
    }

    public function testGetLastCombatResultReturnsTheResultOfTheMostRecentRound(): void
    {
        $gameRun = $this->createGameRun();

        $result = $gameRun->playRound();

        self::assertSame($result, $gameRun->getLastCombatResult());
    }

    public function testPlayRoundClearsTheShopWhenThisRoundEndsTheRun(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordDefeat();
        $gameRun->recordDefeat();

        $gameRun->playRound();

        self::assertTrue($gameRun->isOver());
        self::assertNull($gameRun->getCurrentShop());
    }
}
