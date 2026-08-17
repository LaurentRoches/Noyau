<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use App\Domain\Model\Item;

final class ShopOffer
{
    private readonly int $price;
    private bool $isPurchased = false;

    public function __construct(
        private readonly Item $item,
    ) {
        $this->price = (int) round($this->item->rarity->basePrice() * $this->item->size->priceMultiplier());
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function isPurchased(): bool
    {
        return $this->isPurchased;
    }

    public function markAsPurchased(): void
    {
        if ($this->isPurchased) {
            throw new \LogicException('Offer has already been purchased.');
        }

        $this->isPurchased = true;
    }
}
