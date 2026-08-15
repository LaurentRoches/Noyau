<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use App\Domain\Model\Item;

final class Shop
{
    /**
     * @param list<ShopOffer> $offers
     */
    public function __construct(
        private readonly array $offers,
    ) {
    }

    public function purchase(int $slotIndex, Wallet $wallet): Item
    {
        // 1. Validation de l'index
        if (!array_key_exists($slotIndex, $this->offers)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid slot index %d, must be between 0 and %d.',
                $slotIndex,
                count($this->offers) - 1
            ));
        }

        $offer = $this->offers[$slotIndex];

        // 2. Validation de l'état de l'offre
        if ($offer->isPurchased()) {
            throw new \LogicException('Offer has already been purchased.');
        }

        // 3. Validation de la solvabilité
        if (!$wallet->canAfford($offer->getPrice())) {
            throw new \LogicException(sprintf(
                'Cannot afford offer costing %d gold with current balance of %d.',
                $offer->getPrice(),
                $wallet->getBalance()
            ));
        }

        // 4. Mutations atomiques
        $wallet->spend($offer->getPrice());
        $offer->markAsPurchased();

        return $offer->getItem();
    }

    /**
     * @return list<ShopOffer>
     */
    public function getOffers(): array
    {
        return $this->offers;
    }
}
