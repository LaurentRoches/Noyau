<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Runtime\CombatBoard;
use Random\Randomizer;

final class SimulationContext
{
    private int $currentTick = 0;

    public function __construct(
        private readonly CombatBoard $playerBoard,
        private readonly CombatBoard $opponentBoard,
        private readonly Randomizer $randomizer,
        private readonly CombatLog $log = new CombatLog(),
    ) {
    }

    public function getPlayerBoard(): CombatBoard
    {
        return $this->playerBoard;
    }

    public function getOpponentBoard(): CombatBoard
    {
        return $this->opponentBoard;
    }

    /**
     * @return  array{CombatBoard, CombatBoard}
     */
    public function getBoards(): array
    {
        return [
            $this->playerBoard,
            $this->opponentBoard,
        ];
    }

    public function getRandomizer(): Randomizer
    {
        return $this->randomizer;
    }

    public function getLog(): CombatLog
    {
        return $this->log;
    }

    public function getCurrentTick(): int
    {
        return $this->currentTick;
    }

    public function advanceTick(): void
    {
        $this->currentTick++;
    }

    public function getOppositeBoard(CombatBoard $board): CombatBoard
    {
        if ($board === $this->playerBoard) {
            return $this->opponentBoard;
        }

        if ($board === $this->opponentBoard) {
            return $this->playerBoard;
        }

        throw new \InvalidArgumentException('Provided board is not part of this simulation context.');
    }
}
