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
            HeroSkillType::STALWART => $this->applyActionValueBonus($item, ActionType::GAIN_SHIELD, 1.2),
            HeroSkillType::VITALIC => $this->applyActionValueBonus($item, ActionType::HEAL, 1.2),
            HeroSkillType::SAVAGE => $this->applyActionValueBonus($item, ActionType::DEAL_DAMAGE, 1.2),
            HeroSkillType::VIRULENT => $this->applyStatusStackBonus($item, StatusType::POISON),
            HeroSkillType::SEARING => $this->applyStatusStackBonus($item, StatusType::BURN),
            HeroSkillType::WARDEN => $this->applyStatusStackBonus($item, StatusType::WARD),
            HeroSkillType::RESURGENT => $this->applyStatusStackBonus($item, StatusType::REGEN),
            HeroSkillType::SUNDERING => $this->applySundering($item),
            HeroSkillType::RELENTLESS => $this->applyRelentless($item),
        };
    }

    private function applyFrantic(Item $item): Item
    {
        if ($item->size !== ItemSize::ONE_HAND) {
            return $item;
        }

        return $this->withCooldownTicks($item, (int) floor($item->cooldownTicks * 0.8));
    }

    private function applySundering(Item $item): Item
    {
        if ($item->size !== ItemSize::TWO_HAND) {
            return $item;
        }

        $withDamageBonus = $this->applyActionValueBonus($item, ActionType::DEAL_DAMAGE, 1.35);

        return $this->withCooldownTicks(
            $withDamageBonus,
            (int) floor($withDamageBonus->cooldownTicks * 1.10),
        );
    }

    private function applyRelentless(Item $item): Item
    {
        $withDamageBonus = $this->applyActionValueBonus($item, ActionType::DEAL_DAMAGE, 1.10);

        return $this->withCooldownTicks(
            $withDamageBonus,
            (int) floor($withDamageBonus->cooldownTicks * 0.90),
        );
    }

    private function applyActionValueBonus(Item $item, ActionType $actionType, float $multiplier): Item
    {
        return $this->mapMatchingActions(
            $item,
            fn (Action $action): bool => $action->type === $actionType,
            fn (Action $action): Action => new Action(
                type: $action->type,
                value: (int) ceil(($action->value ?? 0) * $multiplier),
                target: $action->target,
                status: $action->status,
                stacks: $action->stacks,
                durationTicks: $action->durationTicks,
            ),
        );
    }

    private function applyStatusStackBonus(Item $item, StatusType $status): Item
    {
        return $this->mapMatchingActions(
            $item,
            fn (Action $action): bool => $action->status === $status,
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
