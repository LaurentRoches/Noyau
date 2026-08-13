<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enum;

use App\Domain\Enum\Rarity;
use PHPUnit\Framework\TestCase;

final class RarityTest extends TestCase
{
    public function testDropRateModifierReflectsRarity(): void
    {
        self::assertSame(1.0, Rarity::COMMON->dropRateModifier());
        self::assertSame(0.25, Rarity::RARE->dropRateModifier());
        self::assertSame(0.015, Rarity::LEGENDARY->dropRateModifier());
    }

    public function testStatMultiplierReflectRarity(): void
    {
        self::assertSame(1.0, Rarity::COMMON->statMultiplier());
        self::assertSame(1.5, Rarity::RARE->statMultiplier());
        self::assertSame(2.5, Rarity::LEGENDARY->statMultiplier());
    }

    public function testBasePriceReturnsExpectedValueForEachRarity(): void
    {
        self::assertSame(10, Rarity::COMMON->basePrice());
        self::assertSame(25, Rarity::RARE->basePrice());
        self::assertSame(50, Rarity::LEGENDARY->basePrice());
    }
}
