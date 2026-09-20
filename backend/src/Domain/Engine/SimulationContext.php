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

    /**
     * Côté attribué à ce plateau (D-19).
     *
     * **Seule définition de l'attribution dans tout le moteur.** Elle est
     * aujourd'hui positionnelle : A est le plateau passé en premier. Le commit
     * qui la rendra canonique — comparaison d'octets des snapshots, départage
     * par identifiant de combat — ne touchera que cette méthode.
     *
     * Les noms `playerBoard` et `opponentBoard` survivent ici à dessein : ils
     * décrivent d'où viennent les plateaux, pas ce que le journal en dit. Ils
     * deviendront faux en PvP, et c'est au commit d'attribution canonique
     * qu'ils devront disparaître, pas avant — aucun code de production ne les
     * lit hors de ce fichier.
     */
    public function getSide(CombatBoard $board): Side
    {
        return match (true) {
            $board === $this->playerBoard => Side::A,
            $board === $this->opponentBoard => Side::B,
            default => throw new \InvalidArgumentException('Provided board is not part of this simulation context.'),
        };
    }

    /**
     * Plateau qui occupe ce côté.
     *
     * **Dérivé de getSide() plutôt que réécrit.** Une seconde table
     * d'attribution, fût-elle triviale, serait une seconde vérité à maintenir
     * — et la première à diverger le jour où l'attribution cesse d'être
     * positionnelle. Le coût de la boucle est de deux comparaisons.
     */
    public function getBoardOnSide(Side $side): CombatBoard
    {
        foreach ($this->getBoards() as $board) {
            if ($this->getSide($board) === $side) {
                return $board;
            }
        }

        throw new \LogicException(sprintf('No board is assigned to side %s.', $side->value));
    }
}
