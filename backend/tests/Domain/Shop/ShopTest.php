<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop;

use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Shop\Shop;
use App\Domain\Shop\ShopOffer;
use App\Domain\Shop\Wallet;
use PHPUnit\Framework\TestCase;

final class ShopTest extends TestCase
{
    private function createDummyItem(string $id = 'stiletto', Rarity $rarity = Rarity::COMMON): Item
    {
        return new Item(
            id: $id,
            name: ucfirst($id),
            rarity: $rarity,
            affinity: 'neutral',
            cooldownTicks: 10,
            effects: [],
        );
    }

    public function testPurchaseSuccessfullyDebitsWalletMarksOfferAndReturnsItem(): void
    {
        $item = $this->createDummyItem('stiletto', Rarity::COMMON); // Prix: 10
        $offer = new ShopOffer($item);
        $shop = new Shop([$offer]);
        $wallet = new Wallet(50);

        $purchasedItem = $shop->purchase(0, $wallet);

        $this->assertSame($item, $purchasedItem);
        $this->assertTrue($offer->isPurchased());
        $this->assertSame(40, $wallet->getBalance());
    }

    public function testPurchaseThrowsExceptionForInvalidSlotIndex(): void
    {
        $shop = new Shop([new ShopOffer($this->createDummyItem())]); // 1 seule offre (index 0)
        $wallet = new Wallet(50);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slot index 1, must be between 0 and 0.');

        $shop->purchase(1, $wallet);
    }

    public function testPurchaseThrowsExceptionForNegativeSlotIndex(): void
    {
        $shop = new Shop([new ShopOffer($this->createDummyItem())]);
        $wallet = new Wallet(50);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid slot index -1, must be between 0 and 0.');

        $shop->purchase(-1, $wallet);
    }

    public function testPurchaseThrowsExceptionWhenOfferAlreadyPurchased(): void
    {
        $offer = new ShopOffer($this->createDummyItem());
        $offer->markAsPurchased(); // pré-condition directe

        $shop = new Shop([$offer]);
        $wallet = new Wallet(50);

        try {
            $shop->purchase(0, $wallet);
            $this->fail('Une LogicException aurait dû être levée.');
        } catch (\LogicException $e) {
            $this->assertSame('Offer has already been purchased.', $e->getMessage());
            $this->assertSame(50, $wallet->getBalance()); // Garantit qu'aucun débit n'a eu lieu !
        }
    }

    public function testPurchaseThrowsExceptionWhenBalanceIsInsufficient(): void
    {
        $offer = new ShopOffer($this->createDummyItem('stiletto', Rarity::LEGENDARY)); // Prix: 50
        $shop = new Shop([$offer]);
        $wallet = new Wallet(20); // Solde insuffisant

        try {
            $shop->purchase(0, $wallet);
            $this->fail('Une LogicException aurait dû être levée.');
        } catch (\LogicException $e) {
            $this->assertSame(
                'Cannot afford offer costing 50 gold with current balance of 20.',
                $e->getMessage()
            );
            $this->assertFalse($offer->isPurchased()); // Prouve que l'offre RESTE disponible
            $this->assertSame(20, $wallet->getBalance()); // Prouve que le solde est intact
        }
    }

    public function testGetOffersReturnsAllConfiguredOffers(): void
    {
        $offer1 = new ShopOffer($this->createDummyItem('stiletto'));
        $offer2 = new ShopOffer($this->createDummyItem('shield'));

        $expectedOffers = [$offer1, $offer2];
        $shop = new Shop($expectedOffers);

        $offers = $shop->getOffers();

        $this->assertCount(2, $offers);
        $this->assertSame($expectedOffers, $offers); // assertSame vérifie l'identité stricte des objets et l'ordre
    }
}
