<?php

namespace App\Tests\Domain;

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
}
