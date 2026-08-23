<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
use App\Domain\Enum\Target;
use App\Domain\Event\CombatEvent;
use App\Domain\Model\Action;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatVestige;

final class ActionProcessor
{
    public function process(PendingAction $pendingAction, SimulationContext $context): CombatEvent
    {
        $targetBoard = $this->resolveTargetBoard(
            $pendingAction->sourceBoard,
            $pendingAction->action->target,
            $context,
            $pendingAction->action->type
        );
        $targetVestige = $targetBoard->getVestige();

        return match ($pendingAction->action->type) {
            ActionType::DEAL_DAMAGE => $this->processDealDamage(
                $pendingAction->action->value ?? 0,
                $targetVestige,
                $targetBoard,
                $pendingAction,
                $context
            ),
            ActionType::GAIN_SHIELD => $this->processGainShield(
                $pendingAction->action->value ?? 0,
                $targetVestige,
                $targetBoard,
                $pendingAction,
                $context
            ),
            ActionType::HEAL => $this->processHeal(
                $pendingAction->action->value ?? 0,
                $targetVestige,
                $targetBoard,
                $pendingAction,
                $context
            ),
            ActionType::APPLY_STATUS => $this->processApplyStatus(
                $pendingAction->action,
                $targetVestige,
                $targetBoard,
                $pendingAction,
                $context
            ),
            default => throw new \LogicException(sprintf(
                'Action type "%s" is not supported yet.',
                $pendingAction->action->type->value
            )),
        };
    }

    private function resolveTargetBoard(
        CombatBoard $sourceBoard,
        ?Target $target,
        SimulationContext $context,
        ActionType $actionType
    ): CombatBoard {
        $resolvedTarget = $target ?? match ($actionType) {
            ActionType::GAIN_SHIELD, ActionType::HEAL => Target::SELF,
            default => Target::ENEMY,
        };

        return match ($resolvedTarget) {
            Target::ENEMY => $context->getOppositeBoard($sourceBoard),
            Target::SELF => $sourceBoard,
            default => throw new \LogicException(sprintf(
                'Target "%s" is not supported yet.',
                $resolvedTarget->value
            )),
        };
    }

    /**
     * @return array{targetSide: string, sourceSide: string, sourceItemId: string}
     */
    private function sideAndSourceFields(
        CombatBoard $targetBoard,
        PendingAction $pendingAction,
        SimulationContext $context
    ): array {
        return [
            'targetSide' => $context->getSide($targetBoard)->value,
            'sourceSide' => $context->getSide($pendingAction->sourceBoard)->value,
            'sourceItemId' => $pendingAction->sourceItem->getItem()->id,
        ];
    }

    private function processDealDamage(
        int $damageValue,
        CombatVestige $targetVestige,
        CombatBoard $targetBoard,
        PendingAction $pendingAction,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $targetVestige->getHp();
        $shieldBefore = $targetVestige->getShield();

        $targetVestige->takeDamage($damageValue);

        $hpDamage = $hpBefore - $targetVestige->getHp();
        $shieldDamage = $shieldBefore - $targetVestige->getShield();

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::DAMAGE_DEALT,
            payload: [
                'amount' => $damageValue,
                'shieldDamage' => $shieldDamage,
                'hpDamage' => $hpDamage,
                'target' => $targetVestige->getId(),
                ...$this->sideAndSourceFields($targetBoard, $pendingAction, $context),
            ]
        );
    }

    private function processGainShield(
        int $shieldValue,
        CombatVestige $targetVestige,
        CombatBoard $targetBoard,
        PendingAction $pendingAction,
        SimulationContext $context
    ): CombatEvent {
        $shieldBefore = $targetVestige->getShield();

        $targetVestige->gainShield($shieldValue);

        $shieldGained = $targetVestige->getShield() - $shieldBefore;

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::SHIELD_GAINED,
            payload: [
                'amount' => $shieldValue,
                'shieldGained' => $shieldGained,
                'target' => $targetVestige->getId(),
                ...$this->sideAndSourceFields($targetBoard, $pendingAction, $context),
            ]
        );
    }

    private function processHeal(
        int $healValue,
        CombatVestige $targetVestige,
        CombatBoard $targetBoard,
        PendingAction $pendingAction,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $targetVestige->getHp();

        $targetVestige->receiveHeal($healValue);

        $hpHealed = $targetVestige->getHp() - $hpBefore;

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::HEAL_RECEIVED,
            payload: [
                'amount' => $healValue,
                'hpHealed' => $hpHealed,
                'target' => $targetVestige->getId(),
                ...$this->sideAndSourceFields($targetBoard, $pendingAction, $context),
            ]
        );
    }

    private function processApplyStatus(
        Action $action,
        CombatVestige $targetVestige,
        CombatBoard $targetBoard,
        PendingAction $pendingAction,
        SimulationContext $context
    ): CombatEvent {
        if ($action->status === null || $action->stacks === null || $action->durationTicks === null) {
            throw new \LogicException('APPLY_STATUS action requires status, stacks, and durationTicks to be defined.');
        }

        $targetVestige->applyStatus(new ActiveStatus(
            type: $action->status,
            stacks: $action->stacks,
            durationTicks: $action->durationTicks
        ));

        $resultStatus = $targetVestige->getStatus($action->status);
        assert($resultStatus !== null);

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_APPLIED,
            payload: [
                'status' => $action->status->value,
                'stacksApplied' => $action->stacks,
                'durationTicksApplied' => $action->durationTicks,
                'totalStacks' => $resultStatus->getStacks(),
                'remainingTicks' => $resultStatus->getRemainingTicks(),
                'target' => $targetVestige->getId(),
                ...$this->sideAndSourceFields($targetBoard, $pendingAction, $context),
            ]
        );
    }
}
