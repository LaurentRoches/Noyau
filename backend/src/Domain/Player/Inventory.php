<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Model\Item;

final class Inventory
{
    /** @var list<AssignedItem> */
    private array $items = [];

    public function add(Item $item, string $heroId): void
    {
        $this->items[] = new AssignedItem($item, $heroId);
    }

    /**
     * @return list<AssignedItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function removeAt(int $index): AssignedItem
    {
        if (!array_key_exists($index, $this->items)) {
            throw new \InvalidArgumentException(sprintf('No item at index %d.', $index));
        }

        $assignedItem = $this->items[$index];
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        return $assignedItem;
    }

    public function insertAt(int $index, AssignedItem $assignedItem): void
    {
        if ($index < 0 || $index > count($this->items)) {
            throw new \InvalidArgumentException(sprintf('Cannot insert at index %d.', $index));
        }

        array_splice($this->items, $index, 0, [$assignedItem]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getItemIdsByHero(): array
    {
        $result = [];

        foreach ($this->items as $assignedItem) {
            $result[$assignedItem->heroId][] = $assignedItem->item->id;
        }

        return $result;
    }
}
