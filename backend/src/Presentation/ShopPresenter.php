<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Shop\Shop;
use App\Domain\Shop\ShopOffer;

final class ShopPresenter
{
    /**
     * @return array{offers: list<array<string, mixed>>}
     */
    public static function toArray(Shop $shop): array
    {
        return [
            'offers' => array_map(
                static fn (ShopOffer $offer, int $slotIndex): array => [
                    'slotIndex' => $slotIndex,
                    'item' => ItemPresenter::toArray($offer->getItem()),
                    'price' => $offer->getPrice(),
                    'purchased' => $offer->isPurchased(),
                ],
                $shop->getOffers(),
                array_keys($shop->getOffers()),
            ),
        ];
    }
}
