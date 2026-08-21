<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Model\Item;
use App\Domain\Player\Stash;

final class StashPresenter
{
    /**
     * @return array{items: list<array<string, mixed>>, capacity: int, isFull: bool}
     */
    public static function toArray(Stash $stash): array
    {
        return [
            'items' => array_map(
                static fn (Item $item, int $stashIndex): array => [
                    'stashIndex' => $stashIndex,
                    'item' => ItemPresenter::toArray($item),
                ],
                $stash->getItems(),
                array_keys($stash->getItems()),
            ),
            'capacity' => $stash->getCapacity(),
            'isFull' => $stash->isFull(),
        ];
    }
}
