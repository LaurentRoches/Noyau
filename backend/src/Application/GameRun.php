<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Factory\ShopFactory;
use App\Domain\Model\Vestige;
use App\Domain\Shop\Shop;
use App\Domain\Shop\Wallet;
use Random\Randomizer;

final class GameRun
{
    private const int VICTORIES_TO_WIN = 10;
    private const int DEFEATS_TO_LOSE = 3;
    private const int VICTORY_REWARD = 10;

    private Wallet $wallet;
    private readonly int $income;
    private int $victories = 0;
    private int $defeats = 0;
    private int $currentRound = 1;
    private ?Shop $currentShop = null;

    public function __construct(
        Vestige $vestige,
        private readonly ShopFactory $shopFactory,
        private readonly Randomizer $randomizer,
    ) {
        $this->wallet = new Wallet($vestige->startingGold);
        $this->income = $vestige->startingIncome;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function getCurrentRound(): int
    {
        return $this->currentRound;
    }

    public function recordVictory(): void
    {
        $this->victories++;
        $this->wallet->credit(self::VICTORY_REWARD);
        $this->creditIncome();
        $this->currentRound++;
    }

    public function recordDefeat(): void
    {
        $this->defeats++;
        $this->creditIncome();
        $this->currentRound++;
    }

    public function creditIncome(): void
    {
        if ($this->income > 0) {
            $this->wallet->credit($this->income);
        }
    }

    public function isOver(): bool
    {
        return $this->hasWon() || $this->defeats >= self::DEFEATS_TO_LOSE;
    }

    public function hasWon(): bool
    {
        return $this->victories >= self::VICTORIES_TO_WIN;
    }

    public function openShop(): Shop
    {
        $this->currentShop = $this->shopFactory->createShop($this->randomizer);

        return $this->currentShop;
    }

    public function getCurrentShop(): ?Shop
    {
        return $this->currentShop;
    }
}
