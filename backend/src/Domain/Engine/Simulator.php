<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Runtime\CombatBoard;
use Random\Randomizer;

final class Simulator
{
    private const int ENRAGE_WINDOW_TICKS = 50;
    private const int ENRAGE_BASE_DAMAGE = 5;

    private ActionProcessor $actionProcessor;
    private StatusProcessor $statusProcessor;
    private EnrageProcessor $enrageProcessor;

    public function __construct(
        private readonly int $maxTicks = 500,
        ?ActionProcessor $actionProcessor = null,
        ?StatusProcessor $statusProcessor = null,
        ?EnrageProcessor $enrageProcessor = null
    ) {
        $this->actionProcessor = $actionProcessor ?? new ActionProcessor();
        $this->statusProcessor = $statusProcessor ?? new StatusProcessor();
        $this->enrageProcessor = $enrageProcessor ?? new EnrageProcessor(
            triggerTick: max(1, $this->maxTicks - self::ENRAGE_WINDOW_TICKS),
            baseDamage: self::ENRAGE_BASE_DAMAGE
        );
    }

    public function run(
        CombatBoard $playerBoard,
        CombatBoard $opponentBoard,
        Randomizer $randomizer
    ): SimulationResult {
        // 1. Setup initial
        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($playerBoard);
        $dispatcher->registerBoard($opponentBoard);

        $tickEngine = new TickEngine($dispatcher);
        $context = new SimulationContext($playerBoard, $opponentBoard, $randomizer);

        // 2. Boucle de combat
        while (
            $context->getCurrentTick() < $this->maxTicks
            && $this->bothBoardsAlive($playerBoard, $opponentBoard)
        ) {
            // TickEngine avance le temps, décrémente les cooldowns, détecte les objets
            // prêts et renvoie leurs intentions SANS les exécuter.
            $pendingActions = $tickEngine->tick($context);

            // Pulsation des statuts déjà actifs, au tick courant qui vient d'être avancé
            // (avant les objets, pour qu'un statut fraîchement appliqué n'attende pas
            // moins d'un tick avant sa première pulsation).
            foreach ($this->statusProcessor->processTick($context) as $statusEvent) {
                $context->getLog()->addEvent($statusEvent);
            }

            foreach ($this->enrageProcessor->processTick($context) as $enrageEvent) {
                $context->getLog()->addEvent($enrageEvent);
            }

            // Un Poison/Burn peut avoir achevé un vestige : ne pas exécuter les
            // PendingAction restantes (pas de "frappe sur cadavre").
            if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                break;
            }

            foreach ($pendingActions as $pendingAction) {
                $event = $this->actionProcessor->process($pendingAction, $context);
                $context->getLog()->addEvent($event);

                if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                    break;
                }
            }
        }

        // 3. Résolution du résultat
        $playerAlive = $playerBoard->isAlive();
        $opponentAlive = $opponentBoard->isAlive();

        $winner = match (true) {
            $playerAlive && !$opponentAlive => $playerBoard,
            !$playerAlive && $opponentAlive => $opponentBoard,
            default => null,
        };

        return new SimulationResult(
            winner: $winner,
            totalTicks: $context->getCurrentTick(),
            log: $context->getLog()
        );
    }

    /** @phpstan-impure */
    private function bothBoardsAlive(CombatBoard $playerBoard, CombatBoard $opponentBoard): bool
    {
        return $playerBoard->isAlive() && $opponentBoard->isAlive();
    }
}
