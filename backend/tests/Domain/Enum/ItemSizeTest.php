<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enum;

use App\Domain\Enum\ItemSize;
use PHPUnit\Framework\TestCase;

final class ItemSizeTest extends TestCase
{
    public function testPriceMultiplierReflectsSize(): void
    {
        self::assertSame(1.0, ItemSize::ONE_HAND->priceMultiplier());
        self::assertSame(1.75, ItemSize::TWO_HAND->priceMultiplier());
    }

    public function testSlotCostReflectsSize(): void
    {
        self::assertSame(1, ItemSize::ONE_HAND->slotCost());
        self::assertSame(2, ItemSize::TWO_HAND->slotCost());
    }
}
