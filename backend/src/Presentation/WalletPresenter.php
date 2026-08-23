<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Shop\Wallet;

final class WalletPresenter
{
    /**
     * @return array{balance: int}
     */
    public static function toArray(Wallet $wallet): array
    {
        return [
            'balance' => $wallet->getBalance(),
        ];
    }
}
