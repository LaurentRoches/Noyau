<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Shop\ShopOffer;
use PHPUnit\Framework\TestCase;

final class ShopOfferTest extends TestCase
{
    private function createDummyItem(Rarity $rarity = Rarity::COMMON, ItemSize $size = ItemSize::ONE_HAND): Item
    {
        return new Item(
            id: 'stiletto',
            name: 'Stiletto',
            rarity: $rarity,
            affinity: 'neutral',
            size: $size,
            cooldownTicks: 10,
            effects: [],
        );
    }

    public function testShopOfferIsCreatedAsAvailableWithResolvedPrice(): void
    {
        $item = $this->createDummyItem(Rarity::RARE);

        $offer = new ShopOffer($item);

        self::assertSame($item, $offer->getItem());
        self::assertSame(25, $offer->getPrice());
        self::assertFalse($offer->isPurchased());
    }

    public function testMarkAsPurchasedChangesStateToPurchased(): void
    {
        $offer = new ShopOffer($this->createDummyItem());

        $offer->markAsPurchased();

        self::assertTrue($offer->isPurchased());
    }

    public function testMarkAsPurchasedThrowsExceptionWhenAlreadyPurchased(): void
    {
        $offer = new ShopOffer($this->createDummyItem());
        $offer->markAsPurchased();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Offer has already been purchased.');

        $offer->markAsPurchased();
    }

    public function testShopOfferPriceReflectsRarityAndSize(): void
    {
        $item = $this->createDummyItem(Rarity::COMMON, ItemSize::TWO_HAND);

        $offer = new ShopOffer($item);

        self::assertSame(18, $offer->getPrice()); // 10 × 1.75 = 17.5 → round → 18
    }
}
