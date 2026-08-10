<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Runtime\CombatBoard;
use Random\Randomizer;

final class Simulator
{
    private ActionProcessor $actionProcessor;

    public function __construct(
        private readonly int $maxTicks = 500,
        ?ActionProcessor $actionProcessor = null
    ) {
        $this->actionProcessor = $actionProcessor ?? new ActionProcessor();
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
            $pendingActions = $tickEngine->tick($context);

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
            $playerAlive && !$opponentAlive => $playerBoard->getHero(),
            !$playerAlive && $opponentAlive => $opponentBoard->getHero(),
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
