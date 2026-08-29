<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum ItemSize: string
{
    case ONE_HAND = 'ONE_HAND';
    case TWO_HAND = 'TWO_HAND';

    public function slotCost(): int
    {
        return match ($this) {
            self::ONE_HAND => 1,
            self::TWO_HAND => 2,
        };
    }

    public function priceMultiplier(): float
    {
        return match ($this) {
            self::ONE_HAND => 1.0,
            self::TWO_HAND => 1.75,
        };
    }
}
