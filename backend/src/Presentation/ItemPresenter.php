<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Model\Effect;
use App\Domain\Model\Item;

final class ItemPresenter
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     rarity: string,
     *     affinity: string,
     *     size: string,
     *     cooldownTicks: int,
     *     effects: list<array<string, mixed>>
     * }
     */
    public static function toArray(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'rarity' => $item->rarity->value,
            'affinity' => $item->affinity,
            'size' => $item->size->value,
            'cooldownTicks' => $item->cooldownTicks,
            'effects' => array_map(
                static fn (Effect $effect): array => EffectPresenter::toArray($effect),
                $item->effects,
            ),
        ];
    }
}
