<?php

declare(strict_types=1);

namespace Tests\Domain\Shop;

use App\Domain\Shop\Wallet;
use PHPUnit\Framework\TestCase;

final class WalletTest extends TestCase
{
    public function testConstructorSetsInitialBalance(): void
    {
        $wallet = new Wallet(100);

        self::assertSame(100, $wallet->getBalance());
    }

    public function testConstructorThrowsExceptionForNegativeInitialBalance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Initial balance cannot be negative, -1 given.');

        new Wallet(-1);
    }

    public function testCanAffordReturnsTrueWhenBalanceIsSufficientOrEqual(): void
    {
        $wallet = new Wallet(100);

        self::assertTrue($wallet->canAfford(50));
        self::assertTrue($wallet->canAfford(100));
    }

    public function testCanAffordReturnsFalseWhenBalanceIsInsufficient(): void
    {
        $wallet = new Wallet(100);

        self::assertFalse($wallet->canAfford(101));
    }

    public function testCanAffordThrowsExceptionForNonPositiveAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be strictly positive, 0 given.');

        $wallet = new Wallet(100);
        $wallet->canAfford(0);
    }

    public function testCreditIncreasesBalance(): void
    {
        $wallet = new Wallet(100);

        $wallet->credit(50);

        self::assertSame(150, $wallet->getBalance());
    }

    public function testCreditThrowsExceptionForNonPositiveAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be strictly positive, 0 given.');

        $wallet = new Wallet(100);
        $wallet->credit(0);
    }

    public function testSpendDecreasesBalance(): void
    {
        $wallet = new Wallet(100);

        $wallet->spend(40);

        self::assertSame(60, $wallet->getBalance());
    }

    public function testSpendThrowsExceptionForNonPositiveAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be strictly positive, 0 given.');

        $wallet = new Wallet(100);
        $wallet->spend(0);
    }

    public function testSpendThrowsExceptionWhenBalanceIsInsufficient(): void
    {
        $wallet = new Wallet(100);

        try {
            $wallet->spend(150);
            self::fail('Une LogicException aurait dû être levée.');
        } catch (\LogicException $e) {
            self::assertSame('Insufficient balance to spend 150 gold, current balance is 100.', $e->getMessage());
            self::assertSame(100, $wallet->getBalance()); // Vérification de l'atomicité
        }
    }
}
