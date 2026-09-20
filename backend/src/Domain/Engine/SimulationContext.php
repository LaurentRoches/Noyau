<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\RandomStream;
use App\Domain\Enum\Side;
use App\Domain\Runtime\CombatBoard;
use Random\Randomizer;

final class SimulationContext
{
    private int $currentTick = 0;

    /**
     * Flux mémoïsés, indexés par la valeur de `RandomStream`.
     *
     * @var array<string, Randomizer>
     */
    private array $randomizers = [];

    public function __construct(
        private readonly CombatBoard $playerBoard,
        private readonly CombatBoard $opponentBoard,
        private readonly string $combatSeed,
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

    /**
     * Rend le flux demandé, dérivé une seule fois par combat.
     *
     * **La mémoïsation est une exigence de correction, pas une optimisation.**
     * `RandomStream::randomizerFor()` rend une instance neuve à chaque appel,
     * repartant du premier tirage : sans mémoïsation, l'ordre d'initiative
     * tiré au tick 1 et celui tiré au tick 2 seraient identiques, et le tirage
     * serait figé pour tout le combat. Le combat resterait parfaitement
     * déterministe et le test de parité d'EX-J0-01 passerait — le défaut
     * n'apparaîtrait qu'en jouant.
     */
    public function getRandomizer(RandomStream $stream): Randomizer
    {
        return $this->randomizers[$stream->value] ??= $stream->randomizerFor($this->combatSeed);
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

    public function getSide(CombatBoard $board): Side
    {
        return match (true) {
            $board === $this->playerBoard => Side::PLAYER,
            $board === $this->opponentBoard => Side::OPPONENT,
            default => throw new \InvalidArgumentException('Provided board is not part of this simulation context.'),
        };
    }
}
