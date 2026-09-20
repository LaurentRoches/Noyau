<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\Side;
use App\Domain\Runtime\CombatBoard;

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

    /**
     * `CombatLog = f(playerBoard, opponentBoard, combatSeed)`.
     *
     * Le combat reçoit une graine **propre à lui** et non plus le `Randomizer`
     * du run (D-22). Ce découplage a trois effets : le contenu d'une boutique
     * ne dépend plus du déroulé du combat précédent, un combat se rejoue
     * isolément — ce qu'exige le moteur embarqué du chantier 1a —, et
     * l'entrée devient sérialisable, un `Randomizer` ne traversant pas stdin.
     *
     * La graine est opaque pour le Domaine : elle est hachée par
     * `RandomStream` avant usage, donc aucune forme n'est exigée ni validée.
     */
    public function run(
        CombatBoard $playerBoard,
        CombatBoard $opponentBoard,
        string $combatSeed
    ): SimulationResult {
        // 1. Setup initial
        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($playerBoard);
        $dispatcher->registerBoard($opponentBoard);

        $tickEngine = new TickEngine($dispatcher);
        $context = new SimulationContext($playerBoard, $opponentBoard, $combatSeed);

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

            // Un statut (Poison/Burn) peut avoir achevé un vestige : ne pas laisser
            // l'enrage s'exécuter sur un combat déjà résolu (pas de "frappe sur
            // cadavre" — même principe que la garde après enrage, appliqué un cran
            // plus tôt).
            if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                break;
            }

            foreach ($this->enrageProcessor->processTick($context) as $enrageEvent) {
                $context->getLog()->addEvent($enrageEvent);
            }

            // L'enrage peut avoir achevé un vestige : ne pas exécuter les
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

        // Le contexte meurt ici : l'attribution des côtés doit donc voyager
        // dans le résultat, sinon l'Application n'a plus aucun moyen de savoir
        // quel côté a reçu son plateau (D-19).
        return new SimulationResult(
            winner: $winner,
            totalTicks: $context->getCurrentTick(),
            log: $context->getLog(),
            boardA: $context->getBoardOnSide(Side::A),
            boardB: $context->getBoardOnSide(Side::B),
        );
    }

    /** @phpstan-impure */
    private function bothBoardsAlive(CombatBoard $playerBoard, CombatBoard $opponentBoard): bool
    {
        return $playerBoard->isAlive() && $opponentBoard->isAlive();
    }
}
