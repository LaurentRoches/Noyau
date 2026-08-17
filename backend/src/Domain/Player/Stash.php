<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Model\Item;

final class Stash
{
    /** @var list<Item> */
    private array $items = [];

    public function __construct(
        private readonly int $capacity,
    ) {
    }

    public function add(Item $item): void
    {
        if ($this->isFull()) {
            throw new \LogicException(sprintf(
                'Cannot add item "%s": inventory is full (capacity: %d).',
                $item->id,
                $this->capacity
            ));
        }

        $this->items[] = $item;
    }

    public function removeAt(int $index): Item
    {
        if (!array_key_exists($index, $this->items)) {
            throw new \InvalidArgumentException(sprintf('No item at index %d.', $index));
        }

        $item = $this->items[$index];
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        return $item;
    }

    public function insertAt(int $index, Item $item): void
    {
        if ($index < 0 || $index > count($this->items)) {
            throw new \InvalidArgumentException(sprintf('Cannot insert at index %d.', $index));
        }

        array_splice($this->items, $index, 0, [$item]);
    }

    public function isFull(): bool
    {
        return count($this->items) >= $this->capacity;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<Item>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return list<string>
     */
    public function getItemIds(): array
    {
        return array_map(static fn (Item $item): string => $item->id, $this->items);
    }
}
