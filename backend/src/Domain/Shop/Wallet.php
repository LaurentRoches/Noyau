<?php

declare(strict_types=1);

namespace App\Domain\Shop;

final class Wallet
{
    public function __construct(
        private int $balance,
    ) {
        if ($this->balance < 0) {
            throw new \InvalidArgumentException(sprintf(
                'Initial balance cannot be negative, %d given.',
                $this->balance
            ));
        }
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function canAfford(int $amount): bool
    {
        $this->assertPositiveAmount($amount);

        return $amount <= $this->balance;
    }

    public function credit(int $amount): void
    {
        $this->assertPositiveAmount($amount);

        $this->balance += $amount;
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'Amount must be strictly positive, %d given.',
                $amount
            ));
        }
    }

    public function spend(int $amount): void
    {
        if (!$this->canAfford($amount)) {
            throw new \LogicException(sprintf(
                'Insufficient balance to spend %d gold, current balance is %d.',
                $amount,
                $this->balance
            ));
        }

        $this->balance -= $amount;
    }
}
