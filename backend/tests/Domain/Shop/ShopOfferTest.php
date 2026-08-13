<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop;

use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Shop\ShopOffer;
use PHPUnit\Framework\TestCase;

final class ShopOfferTest extends TestCase
{
    private function createDummyItem(Rarity $rarity = Rarity::COMMON): Item
    {
        return new Item(
            id: 'stiletto',
            name: 'Stiletto',
            rarity: $rarity,
            affinity: 'neutral',
            cooldownTicks: 10,
            effects: [],
        );
    }

    public function testShopOfferIsCreatedAsAvailableWithResolvedPrice(): void
    {
        $item = $this->createDummyItem(Rarity::RARE);

        $offer = new ShopOffer($item);

        $this->assertSame($item, $offer->getItem());
        $this->assertSame(25, $offer->getPrice());
        $this->assertFalse($offer->isPurchased());
    }

    public function testMarkAsPurchasedChangesStateToPurchased(): void
    {
        $offer = new ShopOffer($this->createDummyItem());

        $offer->markAsPurchased();

        $this->assertTrue($offer->isPurchased());
    }

    public function testMarkAsPurchasedThrowsExceptionWhenAlreadyPurchased(): void
    {
        $offer = new ShopOffer($this->createDummyItem());
        $offer->markAsPurchased();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Offer has already been purchased.');

        $offer->markAsPurchased();
    }
}
