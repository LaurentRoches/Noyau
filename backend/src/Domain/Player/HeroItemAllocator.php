<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Model\Hero;
use App\Domain\Model\Item;

final class HeroItemAllocator
{
    /**
     * @param list<Hero> $roster
     */
    public function __construct(
        private readonly array $roster,
    ) {
    }

    public function allocate(Item $item, Inventory $inventory): ?string
    {
        foreach ($this->roster as $hero) {
            if ($this->canAssign($item, $hero->id, $inventory)) {
                return $hero->id;
            }
        }

        return null;
    }

    public function canAssign(Item $item, string $heroId, Inventory $inventory): bool
    {
        $hero = $this->findHero($heroId);
        $usedSlots = $this->usedSlotsForHero($heroId, $inventory);

        return $usedSlots + $item->size->slotCost() <= $hero->itemSlots;
    }

    private function usedSlotsForHero(string $heroId, Inventory $inventory): int
    {
        $used = 0;
        foreach ($inventory->getItems() as $assignedItem) {
            if ($assignedItem->heroId === $heroId) {
                $used += $assignedItem->item->size->slotCost();
            }
        }

        return $used;
    }

    private function findHero(string $heroId): Hero
    {
        foreach ($this->roster as $hero) {
            if ($hero->id === $heroId) {
                return $hero;
            }
        }

        throw new \InvalidArgumentException(sprintf('Hero "%s" is not part of this roster.', $heroId));
    }
}
