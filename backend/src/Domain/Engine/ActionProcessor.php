<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
use App\Domain\Enum\Target;
use App\Domain\Event\CombatEvent;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;

final class ActionProcessor
{
    public function process(PendingAction $pendingAction, SimulationContext $context): CombatEvent
    {
        $targetBoard = $this->resolveTargetBoard(
            $pendingAction->sourceBoard,
            $pendingAction->action->target,
            $context
        );
        $targetHero = $targetBoard->getHero();

        return match ($pendingAction->action->type) {
            ActionType::DEAL_DAMAGE => $this->processDealDamage(
                $pendingAction->action->value,
                $targetHero,
                $context->getCurrentTick()
            ),
            default => throw new \LogicException(sprintf(
                'Action type "%s" is not supported yet.',
                $pendingAction->action->type->value
            )),
        };
    }

    private function resolveTargetBoard(
        CombatBoard $sourceBoard,
        Target $target,
        SimulationContext $context
    ): CombatBoard {
        return match ($target) {
            Target::ENEMY => $context->getOppositeBoard($sourceBoard),
            Target::SELF => $sourceBoard,
            default => throw new \LogicException(sprintf(
                'Target "%s" is not supported yet.',
                $target->value
            )),
        };
    }

    private function processDealDamage(
        int $damageValue,
        CombatHero $targetHero,
        int $currentTick
    ): CombatEvent {
        $hpBefore = $targetHero->getHp();
        $shieldBefore = $targetHero->getShield();

        $targetHero->takeDamage($damageValue);

        $hpDamage = $hpBefore - $targetHero->getHp();
        $shieldDamage = $shieldBefore - $targetHero->getShield();

        return new CombatEvent(
            tick: $currentTick,
            type: EventType::DAMAGE_DEALT,
            payload: [
                'amount' => $damageValue,
                'shieldDamage' => $shieldDamage,
                'hpDamage' => $hpDamage,
                'target' => $targetHero->getId(),
            ]
        );
    }
}
