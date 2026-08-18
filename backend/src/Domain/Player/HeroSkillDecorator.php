<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Enum\ActionType;
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
            HeroSkillType::STALWART => $this->applyStalwart($item),
            HeroSkillType::VITALIC => $this->applyVitalic($item),
        };
    }

    private function applyFrantic(Item $item): Item
    {
        if ($item->size !== ItemSize::ONE_HAND) {
            return $item;
        }

        return $this->withCooldownTicks($item, (int) floor($item->cooldownTicks * 0.8));
    }

    private function applyVirulent(Item $item): Item
    {
        return $this->mapMatchingActions(
            $item,
            fn (Action $action): bool => $action->status === StatusType::POISON,
            fn (Action $action): Action => new Action(
                type: $action->type,
                value: $action->value,
                target: $action->target,
                status: $action->status,
                stacks: ($action->stacks ?? 0) + 1,
                durationTicks: $action->durationTicks,
            ),
        );
    }

    private function applyStalwart(Item $item): Item
    {
        return $this->mapMatchingActions(
            $item,
            fn (Action $action): bool => $action->type === ActionType::GAIN_SHIELD,
            fn (Action $action): Action => new Action(
                type: $action->type,
                value: (int) ceil(($action->value ?? 0) * 1.2),
                target: $action->target,
                status: $action->status,
                stacks: $action->stacks,
                durationTicks: $action->durationTicks,
            ),
        );
    }

    private function applyVitalic(Item $item): Item
    {
        return $this->mapMatchingActions(
            $item,
            fn (Action $action): bool => $action->type === ActionType::HEAL,
            fn (Action $action): Action => new Action(
                type: $action->type,
                value: (int) ceil(($action->value ?? 0) * 1.2),
                target: $action->target,
                status: $action->status,
                stacks: $action->stacks,
                durationTicks: $action->durationTicks,
            ),
        );
    }

    /**
     * @param callable(Action): bool $predicate
     * @param callable(Action): Action $transform
     */
    private function mapMatchingActions(Item $item, callable $predicate, callable $transform): Item
    {
        return $this->withEffects($item, array_map(
            fn (Effect $effect): Effect => new Effect(
                trigger: $effect->trigger,
                actions: array_map(
                    fn (Action $action): Action => $predicate($action) ? $transform($action) : $action,
                    $effect->actions,
                ),
                intervalTicks: $effect->intervalTicks,
            ),
            $item->effects,
        ));
    }

    private function withCooldownTicks(Item $item, int $cooldownTicks): Item
    {
        return new Item(
            id: $item->id,
            name: $item->name,
            rarity: $item->rarity,
            affinity: $item->affinity,
            size: $item->size,
            cooldownTicks: $cooldownTicks,
            effects: $item->effects,
        );
    }

    /**
     * @param Effect[] $effects
     */
    private function withEffects(Item $item, array $effects): Item
    {
        return new Item(
            id: $item->id,
            name: $item->name,
            rarity: $item->rarity,
            affinity: $item->affinity,
            size: $item->size,
            cooldownTicks: $item->cooldownTicks,
            effects: $effects,
        );
    }
}
