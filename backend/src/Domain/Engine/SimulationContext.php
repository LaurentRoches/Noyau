<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\RandomStream;
use App\Domain\Enum\Side;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Snapshot\BoardSnapshot;
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

    private readonly CombatBoard $boardOnSideA;
    private readonly CombatBoard $boardOnSideB;

    /**
     * **Attribution canonique des côtés, calculée une fois ici** (D-19,
     * `04` §3.6). A est le plateau dont la photographie canonique est la plus
     * petite en octets.
     *
     * *Une fois* n'est pas une optimisation : `Simulator::groupActionsBySide()`
     * appelle `getSide()` une fois par action en attente, à chaque tick. Une
     * attribution recalculée resterait juste parce que `BoardSnapshot` ne lit
     * que des objets immuables — mais elle ferait dépendre une propriété de
     * correction d'un détail d'implémentation d'une autre classe.
     *
     * **« Plus petite » n'a aucun sens de jeu.** La comparaison est lexicale,
     * donc un or de 10 passe avant un or de 9. Sans importance : §3.6 n'a
     * besoin que d'un ordre total et déterministe, pas d'un ordre signifiant.
     *
     * **À égalité stricte, l'ordre des arguments tranche.** La clause de §3.6
     * — « départage par un identifiant de combat » — est inapplicable :
     * l'identifiant de combat est une valeur unique partagée par les deux
     * plateaux, pas une valeur par plateau, et aucune fonction de
     * (photoA, photoB, combatId) ne peut ordonner deux photographies égales.
     * Ce repli n'est pas anodin — en miroir, le journal est identique dans les
     * deux sens mais désigne « A » comme vainqueur, donc l'ordre décide quel
     * joueur gagne. Le commit PvP devra faire venir cet ordre d'une donnée
     * enregistrée avant la simulation. `07` anomalie E-14.
     *
     * **Les noms `playerBoard` et `opponentBoard` sont désormais faux** et
     * aucun code de production ne les lit hors de ce fichier. Leur renommage
     * est reporté au commit suivant : c'est un changement purement cosmétique,
     * et l'empiler sur un commit qui déplace le sens de A et de B rendrait le
     * diff illisible.
     */
    public function __construct(
        private readonly CombatBoard $playerBoard,
        private readonly CombatBoard $opponentBoard,
        private readonly string $combatSeed,
        private readonly CombatLog $log = new CombatLog(),
    ) {
        $firstPhotograph = BoardSnapshot::fromBoard($playerBoard)->toCanonicalJson();
        $secondPhotograph = BoardSnapshot::fromBoard($opponentBoard)->toCanonicalJson();

        [$this->boardOnSideA, $this->boardOnSideB] = strcmp($firstPhotograph, $secondPhotograph) <= 0
            ? [$playerBoard, $opponentBoard]
            : [$opponentBoard, $playerBoard];
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
     * Les deux plateaux, **dans l'ordre canonique A puis B**.
     *
     * **Ce n'est pas cosmétique.** `StatusProcessor`, `EnrageProcessor` et
     * `TickEngine` bouclent tous trois là-dessus, et les deux premiers écrivent
     * un événement par plateau : l'ordre de cette liste est donc l'ordre des
     * événements au journal. Tant qu'elle rendait l'ordre des arguments,
     * `run($a, $b)` et `run($b, $a)` produisaient deux journaux différents
     * octet pour octet — NF-01 tombait, et D-19 manquait le but même qu'il se
     * donne : rendre le combat indépendant de la façon dont l'appelant a rangé
     * ses plateaux.
     *
     * @return array{CombatBoard, CombatBoard}
     */
    public function getBoards(): array
    {
        return [
            $this->boardOnSideA,
            $this->boardOnSideB,
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
        if ($board === $this->boardOnSideA) {
            return $this->boardOnSideB;
        }

        if ($board === $this->boardOnSideB) {
            return $this->boardOnSideA;
        }

        throw new \InvalidArgumentException('Provided board is not part of this simulation context.');
    }

    /**
     * Côté attribué à ce plateau (D-19).
     *
     * **Seule définition de l'attribution dans tout le moteur**, avec
     * `getBoardOnSide()` : les deux lisent la même table, figée au
     * constructeur. Comparaison par identité d'objet, jamais par identifiant —
     * un combat miroir oppose deux plateaux au même identifiant de Vestige, et
     * c'est exactement le cas que les libellés neutres existent pour lever.
     */
    public function getSide(CombatBoard $board): Side
    {
        return match (true) {
            $board === $this->boardOnSideA => Side::A,
            $board === $this->boardOnSideB => Side::B,
            default => throw new \InvalidArgumentException('Provided board is not part of this simulation context.'),
        };
    }

    /**
     * Plateau qui occupe ce côté.
     */
    public function getBoardOnSide(Side $side): CombatBoard
    {
        return match ($side) {
            Side::A => $this->boardOnSideA,
            Side::B => $this->boardOnSideB,
        };
    }
}
