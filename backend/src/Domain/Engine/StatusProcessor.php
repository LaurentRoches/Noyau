<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\EventType;
use App\Domain\Enum\StatusType;
use App\Domain\Event\CombatEvent;
use App\Domain\Runtime\ActiveStatus;
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
                $events = [...$events, ...$this->pulse($status, $vestige, $context->getCurrentTick())];
            }

            $vestige->removeExpiredStatuses();
        }

        return $events;
    }

    /**
     * @return list<CombatEvent>
     */
    private function pulse(ActiveStatus $status, CombatVestige $vestige, int $currentTick): array
    {
        $status->decrementDuration();

        $primaryEvent = match ($status->getType()) {
            StatusType::POISON => $this->pulsePoison($status, $vestige, $currentTick),
            default => throw new \LogicException(sprintf(
                'Status type "%s" is not supported yet.',
                $status->getType()->value
            )),
        };

        $events = [$primaryEvent];

        if ($status->isExpired()) {
            $events[] = new CombatEvent(
                tick: $currentTick,
                type: EventType::STATUS_EXPIRED,
                payload: [
                    'status' => $status->getType()->value,
                    'target' => $vestige->getId(),
                ]
            );
        }

        return $events;
    }

    private function pulsePoison(ActiveStatus $status, CombatVestige $vestige, int $currentTick): CombatEvent
    {
        $hpBefore = $vestige->getHp();
        $shieldBefore = $vestige->getShield();

        $vestige->takeRawDamage($status->getStacks());

        $hpDamage = $hpBefore - $vestige->getHp();
        $shieldDamage = $shieldBefore - $vestige->getShield();

        return new CombatEvent(
            tick: $currentTick,
            type: EventType::STATUS_DAMAGE_DEALT,
            payload: [
                'status' => $status->getType()->value,
                'amount' => $status->getStacks(),
                'shieldDamage' => $shieldDamage,
                'hpDamage' => $hpDamage,
                'remainingStacks' => $status->getStacks(),
                'remainingTicks' => $status->getRemainingTicks(), // lu après decrementDuration()
                'target' => $vestige->getId(),
            ]
        );
    }
}
