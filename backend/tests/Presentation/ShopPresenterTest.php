<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Shop\Shop;
use App\Domain\Shop\ShopOffer;
use App\Presentation\ShopPresenter;
use PHPUnit\Framework\TestCase;

final class ShopPresenterTest extends TestCase
{
    public function testItPresentsAShopWithAnUnpurchasedOfferToArray(): void
    {
        $item = new Item(
            id: 'sword_01',
            name: 'Rusty Sword',
            rarity: Rarity::COMMON,
            affinity: 'physical',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 100,
            effects: [],
        );
        $shop = new Shop([new ShopOffer($item)]);

        $result = ShopPresenter::toArray($shop);

        self::assertSame([
            'offers' => [
                [
                    'slotIndex' => 0,
                    'item' => [
                        'id' => 'sword_01',
                        'name' => 'Rusty Sword',
                        'rarity' => 'COMMON',
                        'affinity' => 'physical',
                        'size' => 'ONE_HAND',
                        'cooldownTicks' => 100,
                        'effects' => [],
                    ],
                    'price' => 10,
                    'purchased' => false,
                ],
            ],
        ], $result);
    }

    public function testItPresentsAShopWithAPurchasedOfferToArray(): void
    {
        $item = new Item(
            id: 'sword_01',
            name: 'Rusty Sword',
            rarity: Rarity::COMMON,
            affinity: 'physical',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 100,
            effects: [],
        );
        $offer = new ShopOffer($item);
        $offer->markAsPurchased();
        $shop = new Shop([$offer]);

        $result = ShopPresenter::toArray($shop);

        self::assertSame(true, $result['offers'][0]['purchased']);
    }
}
