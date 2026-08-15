<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Rarity: string
{
    case COMMON = 'COMMON';
    case RARE = 'RARE';
    case LEGENDARY = 'LEGENDARY';

    public function statMultiplier(): float
    {
        return match ($this) {
            self::COMMON => 1.0,
            self::RARE => 1.5,
            self::LEGENDARY => 2.5,
        };
    }

    public function dropRateModifier(): float
    {
        return match ($this) {
            self::COMMON => 1.0,
            self::RARE => 0.25,
            self::LEGENDARY => 0.015,
        };
    }

    public function basePrice(): int
    {
        return match ($this) {
            self::COMMON => 10,
            self::RARE => 25,
            self::LEGENDARY => 50,
        };
    }
}
