<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\EventType;
use App\Domain\Enum\StatusType;
use App\Domain\Event\CombatEvent;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\AggregatedStatus;
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

            foreach ($vestige->getStatusTypes() as $type) {
                $events = [...$events, ...$this->pulse($type, $vestige, $board, $context)];
            }

            $vestige->removeExpiredStatuses();
        }

        return $events;
    }

    /**
     * Séquence D-20 : chaque instance décrémente son compteur, l'effet
     * s'applique pour la somme des stacks de toutes les instances vivantes du
     * type, puis les instances à zéro tick sont retirées (par l'appelant).
     *
     * Un seul événement d'effet par type et par tick, charge utile inchangée.
     * Un seul STATUS_EXPIRED, et seulement quand le type n'a plus aucune
     * instance vivante : l'événement signifie « ce statut a cessé », pas
     * « une application a expiré », qui n'est pas exposée en V1.
     *
     * @return list<CombatEvent>
     */
    private function pulse(
        StatusType $type,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): array {
        $instances = $vestige->getStatusInstances($type);

        foreach ($instances as $instance) {
            $instance->decrementDuration();
        }

        $aggregated = $vestige->getAggregatedStatus($type);

        $primaryEvent = match ($type) {
            StatusType::POISON => $this->pulsePoison($type, $aggregated, $vestige, $board, $context),
            StatusType::BURN => $this->pulseBurn($type, $aggregated, $vestige, $board, $context),
            StatusType::REGEN => $this->pulseRegen($type, $aggregated, $vestige, $board, $context),
            StatusType::WARD => $this->pulseWard($type, $aggregated, $vestige, $board, $context),
        };

        $events = [$primaryEvent];

        if ($this->allInstancesExpired($instances)) {
            $events[] = new CombatEvent(
                tick: $context->getCurrentTick(),
                type: EventType::STATUS_EXPIRED,
                payload: [
                    'status' => $type->value,
                    'target' => $vestige->getId(),
                    'targetSide' => $context->getSide($board)->value,
                ]
            );
        }

        return $events;
    }

    /**
     * @param list<ActiveStatus> $instances
     */
    private function allInstancesExpired(array $instances): bool
    {
        foreach ($instances as $instance) {
            if (!$instance->isExpired()) {
                return false;
            }
        }

        return true;
    }

    private function pulseWard(
        StatusType $type,
        AggregatedStatus $aggregated,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $shieldBefore = $vestige->getShield();

        $vestige->gainShield($aggregated->stacks);

        $shieldGained = $vestige->getShield() - $shieldBefore;

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_SHIELD_GAINED,
            payload: [
                'status' => $type->value,
                'amount' => $aggregated->stacks,
                'shieldGained' => $shieldGained,
                'remainingStacks' => $aggregated->stacks,
                'remainingTicks' => $aggregated->remainingTicks,
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }

    private function pulseRegen(
        StatusType $type,
        AggregatedStatus $aggregated,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $vestige->getHp();

        $vestige->receiveHeal($aggregated->stacks);

        $hpHealed = $vestige->getHp() - $hpBefore;

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_HEAL_RECEIVED,
            payload: [
                'status' => $type->value,
                'amount' => $aggregated->stacks,
                'hpHealed' => $hpHealed,
                'remainingStacks' => $aggregated->stacks,
                'remainingTicks' => $aggregated->remainingTicks,
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }

    private function pulseBurn(
        StatusType $type,
        AggregatedStatus $aggregated,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $vestige->getHp();
        $shieldBefore = $vestige->getShield();

        $vestige->takeDamage($aggregated->stacks);

        $hpDamage = $hpBefore - $vestige->getHp();
        $shieldDamage = $shieldBefore - $vestige->getShield();

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_DAMAGE_DEALT,
            payload: [
                'status' => $type->value,
                'amount' => $aggregated->stacks,
                'shieldDamage' => $shieldDamage,
                'hpDamage' => $hpDamage,
                'remainingStacks' => $aggregated->stacks,
                'remainingTicks' => $aggregated->remainingTicks,
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }

    private function pulsePoison(
        StatusType $type,
        AggregatedStatus $aggregated,
        CombatVestige $vestige,
        CombatBoard $board,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $vestige->getHp();
        $shieldBefore = $vestige->getShield();

        $vestige->takeRawDamage($aggregated->stacks);

        $hpDamage = $hpBefore - $vestige->getHp();
        $shieldDamage = $shieldBefore - $vestige->getShield();

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::STATUS_DAMAGE_DEALT,
            payload: [
                'status' => $type->value,
                'amount' => $aggregated->stacks,
                'shieldDamage' => $shieldDamage,
                'hpDamage' => $hpDamage,
                'remainingStacks' => $aggregated->stacks,
                'remainingTicks' => $aggregated->remainingTicks, // lu après decrementDuration()
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }
}
