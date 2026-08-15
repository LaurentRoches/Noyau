<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Shop\Shop;
use App\Domain\Shop\ShopOffer;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use Random\Randomizer;

final class ShopFactory
{
    private const int OFFERS_PER_VISIT = 4;
    private const int MAX_LEGENDARY_OFFERS = 1;

    public function __construct(
        private readonly JsonItemRepository $itemRepository,
    ) {
    }

    public function createShop(Randomizer $randomizer): Shop
    {
        $allItems = $this->itemRepository->findAll();

        $nonLegendaryItems = array_values(array_filter(
            $allItems,
            static fn (Item $item): bool => $item->rarity !== Rarity::LEGENDARY,
        ));

        $guaranteedItems = $this->drawItems(
            $nonLegendaryItems,
            self::OFFERS_PER_VISIT - self::MAX_LEGENDARY_OFFERS,
            $randomizer,
        );

        $guaranteedIds = array_map(static fn (Item $item): string => $item->id, $guaranteedItems);
        $remainingPool = array_values(array_filter(
            $allItems,
            static fn (Item $item): bool => !in_array($item->id, $guaranteedIds, true),
        ));

        $lastSlotItems = $this->drawItems($remainingPool, self::MAX_LEGENDARY_OFFERS, $randomizer);

        $offers = array_map(
            static fn (Item $item): ShopOffer => new ShopOffer($item),
            [...$guaranteedItems, ...$lastSlotItems],
        );

        return new Shop($offers);
    }

    /**
     * @param list<Item> $pool
     * @return list<Item>
     */
    private function drawItems(array $pool, int $count, Randomizer $randomizer): array
    {
        $selectedKeys = $randomizer->pickArrayKeys($pool, $count);

        return array_map(
            static fn (int $key): Item => $pool[$key],
            $selectedKeys,
        );
    }
}
