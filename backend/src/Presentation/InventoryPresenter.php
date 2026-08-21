<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Player\AssignedItem;
use App\Domain\Player\Inventory;

final class InventoryPresenter
{
    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public static function toArray(Inventory $inventory): array
    {
        return [
            'items' => array_map(
                static fn (AssignedItem $assignedItem, int $inventoryIndex): array => [
                    'inventoryIndex' => $inventoryIndex,
                    'item' => ItemPresenter::toArray($assignedItem->item),
                    'heroId' => $assignedItem->heroId,
                ],
                $inventory->getItems(),
                array_keys($inventory->getItems()),
            ),
        ];
    }
}
