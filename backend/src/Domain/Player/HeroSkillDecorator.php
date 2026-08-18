<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\StatusType;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Item;

final class HeroSkillDecorator
{
    public function decorate(HeroSkillType $skill, Item $item): Item
    {
        return match ($skill) {
            HeroSkillType::FRANTIC => $this->applyFrantic($item),
            HeroSkillType::VIRULENT => $this->applyVirulent($item),
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

    private function applyVirulent(Item $item): Item
    {
        return new Item(
            id: $item->id,
            name: $item->name,
            rarity: $item->rarity,
            affinity: $item->affinity,
            size: $item->size,
            cooldownTicks: $item->cooldownTicks,
            effects: array_map(
                fn (Effect $effect): Effect => new Effect(
                    trigger: $effect->trigger,
                    actions: array_map(
                        fn (Action $action): Action => $action->status === StatusType::POISON
                            ? new Action(
                                type: $action->type,
                                value: $action->value,
                                target: $action->target,
                                status: $action->status,
                                stacks: ($action->stacks ?? 0) + 1,
                                durationTicks: $action->durationTicks,
                            )
                            : $action,
                        $effect->actions,
                    ),
                    intervalTicks: $effect->intervalTicks,
                ),
                $item->effects,
            ),
        );
    }
}
