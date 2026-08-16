<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Application\GameRun;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Model\Vestige;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
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

        $combatBoardFactory = new CombatBoardFactory(
            new JsonVestigeRepository($configPath . '/vestiges.json'),
            new JsonHeroRepository($configPath . '/heroes.json'),
            $itemRepository,
        );

        return new GameRun(
            $vestige,
            new ShopFactory($itemRepository),
            new ScriptedOpponentFactory($combatBoardFactory, $itemRepository),
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

    public function testSwapWithStashExchangesItemsBetweenBoardAndStash(): void
    {
        $gameRun = $this->createGameRun(startingGold: 1000);

        for ($i = 0; $i < 7; $i++) {
            $gameRun->openShop();
            $gameRun->purchaseItem(0);
        }

        $boardItemBefore = $gameRun->getInventory()->getItems()[0];
        $stashItemBefore = $gameRun->getStash()->getItems()[0];

        $gameRun->swapWithStash(0, 0);

        self::assertSame($stashItemBefore, $gameRun->getInventory()->getItems()[0]);
        self::assertSame($boardItemBefore, $gameRun->getStash()->getItems()[0]);
    }
}
