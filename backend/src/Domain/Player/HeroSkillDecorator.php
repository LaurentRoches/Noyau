<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Enum\ItemSize;
use App\Domain\Model\Item;

final class HeroSkillDecorator
{
    public function decorate(HeroSkillType $skill, Item $item): Item
    {
        return match ($skill) {
            HeroSkillType::FRANTIC => $this->applyFrantic($item),
        };
    }

    private function applyFrantic(Item $item): Item
    {
        if ($item->size !== ItemSize::ONE_HAND) {
            return $item;
        }

        return new Item(
            id: $item->id,
            name: $item->name,
            rarity: $item->rarity,
            affinity: $item->affinity,
            size: $item->size,
            cooldownTicks: (int) floor($item->cooldownTicks * 0.8),
            effects: $item->effects,
        );
    }
}
