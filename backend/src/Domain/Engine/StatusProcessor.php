<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\EventType;
use App\Domain\Enum\StatusType;
use App\Domain\Event\CombatEvent;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatVestige;

final class StatusProcessor
{
    /**
     * @return list<CombatEvent>
     */
    public function processTick(SimulationContext $context): array
    {
        $events = [];

        foreach ($context->getBoards() as $board) {
            $vestige = $board->getVestige();

            foreach ($vestige->getStatuses() as $status) {
                $events = [...$events, ...$this->pulse($status, $vestige, $board, $context)];
            }

            $vestige->removeExpiredStatuses();
        }

        return $events;
    }

    /**
     * @return list<CombatEvent>
     */
    private function pulse(
        ActiveStatus $status,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): array {
        $status->decrementDuration();

        $primaryEvent = match ($status->getType()) {
            StatusType::POISON => $this->pulsePoison($status, $vestige, $board, $context),
            StatusType::BURN => $this->pulseBurn($status, $vestige, $board, $context),
            StatusType::REGEN => $this->pulseRegen($status, $vestige, $board, $context),
            StatusType::WARD => $this->pulseWard($status, $vestige, $board, $context),
        };

        $events = [$primaryEvent];

        if ($status->isExpired()) {
            $events[] = new CombatEvent(
                tick: $context->getCurrentTick(),
                type: EventType::STATUS_EXPIRED,
                payload: [
                    'status' => $status->getType()->value,
                    'target' => $vestige->getId(),
                    'targetSide' => $context->getSide($board)->value,
                ]
            );
        }

        return $events;
    }

    private function pulseWard(
        ActiveStatus $status,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $shieldBefore = $vestige->getShield();

        $vestige->gainShield($status->getStacks());

        $shieldGained = $vestige->getShield() - $shieldBefore;

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_SHIELD_GAINED,
            payload: [
                'status' => $status->getType()->value,
                'amount' => $status->getStacks(),
                'shieldGained' => $shieldGained,
                'remainingStacks' => $status->getStacks(),
                'remainingTicks' => $status->getRemainingTicks(),
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }

    private function pulseRegen(
        ActiveStatus $status,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $vestige->getHp();

        $vestige->receiveHeal($status->getStacks());

        $hpHealed = $vestige->getHp() - $hpBefore;

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_HEAL_RECEIVED,
            payload: [
                'status' => $status->getType()->value,
                'amount' => $status->getStacks(),
                'hpHealed' => $hpHealed,
                'remainingStacks' => $status->getStacks(),
                'remainingTicks' => $status->getRemainingTicks(),
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }

    private function pulseBurn(
        ActiveStatus $status,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $vestige->getHp();
        $shieldBefore = $vestige->getShield();

        $vestige->takeDamage($status->getStacks());

        $hpDamage = $hpBefore - $vestige->getHp();
        $shieldDamage = $shieldBefore - $vestige->getShield();

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_DAMAGE_DEALT,
            payload: [
                'status' => $status->getType()->value,
                'amount' => $status->getStacks(),
                'shieldDamage' => $shieldDamage,
                'hpDamage' => $hpDamage,
                'remainingStacks' => $status->getStacks(),
                'remainingTicks' => $status->getRemainingTicks(),
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }

    private function pulsePoison(
        ActiveStatus $status,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $vestige->getHp();
        $shieldBefore = $vestige->getShield();

        $vestige->takeRawDamage($status->getStacks());

        $hpDamage = $hpBefore - $vestige->getHp();
        $shieldDamage = $shieldBefore - $vestige->getShield();

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_DAMAGE_DEALT,
            payload: [
                'status' => $status->getType()->value,
                'amount' => $status->getStacks(),
                'shieldDamage' => $shieldDamage,
                'hpDamage' => $hpDamage,
                'remainingStacks' => $status->getStacks(),
                'remainingTicks' => $status->getRemainingTicks(), // lu après decrementDuration()
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }
}
