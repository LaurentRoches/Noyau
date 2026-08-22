<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Shop\Wallet;
use App\Presentation\WalletPresenter;
use PHPUnit\Framework\TestCase;

final class WalletPresenterTest extends TestCase
{
    public function testItPresentsAWalletToArray(): void
    {
        $wallet = new Wallet(42);

        $result = WalletPresenter::toArray($wallet);

        self::assertSame([
            'balance' => 42,
        ], $result);
    }
}
