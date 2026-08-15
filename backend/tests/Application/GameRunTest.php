<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Factory\ShopFactory;
use App\Application\GameRun;
use App\Domain\Model\Vestige;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class GameRunTest extends TestCase
{
    private function createGameRun(): GameRun
    {
        $vestige = new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            startingGold: 20,
            startingIncome: 5
        );

        $itemRepository = new JsonItemRepository(__DIR__ . '/../../config/game/items.json');
        $shopFactory = new ShopFactory($itemRepository);
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));

        return new GameRun($vestige, $shopFactory, $randomizer);
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
}
