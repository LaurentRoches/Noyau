<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\EventType;
use App\Domain\Event\CombatEvent;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatVestige;

final class EnrageProcessor
{
    public function __construct(
        private readonly int $triggerTick,
        private readonly int $baseDamage = 5,
    ) {
    }

    /**
     * @return list<CombatEvent>
     */
    public function processTick(SimulationContext $context): array
    {
        $currentTick = $context->getCurrentTick();

        if ($currentTick < $this->triggerTick) {
            return [];
        }

        $stage = $currentTick - $this->triggerTick;
        $damage = $this->baseDamage * (2 ** $stage);

        $events = [];
        foreach ($context->getBoards() as $board) {
            $vestige = $board->getVestige();
            $events[] = $this->applyEnrageDamage($vestige, $board, $damage, $context);

            if (!$vestige->isAlive()) {
                // Pas de frappe sur cadavre : si ce Vestige meurt de l'enrage,
                // on ne fait pas subir le même coup au second board ce tick,
                // exactement comme Simulator::run() le fait déjà pour les
                // PendingAction. Rétablit le double-KO structurellement
                // impossible, y compris pour l'enrage.
                break;
            }
        }

        return $events;
    }

    private function applyEnrageDamage(
        CombatVestige $vestige,
        CombatBoard $board,
        int $damage,
        SimulationContext $context
    ): CombatEvent {
        $hpBefore = $vestige->getHp();
        $shieldBefore = $vestige->getShield();

        $vestige->takeDamage($damage);

        return new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::ENRAGE_DAMAGE_DEALT,
            payload: [
                'amount' => $damage,
                'shieldDamage' => $shieldBefore - $vestige->getShield(),
                'hpDamage' => $hpBefore - $vestige->getHp(),
                'target' => $vestige->getId(),
                'targetSide' => $context->getSide($board)->value,
            ]
        );
    }
}
