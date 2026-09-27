<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Model\Hero;
use App\Domain\Model\OpponentAssignment;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Snapshot\SnapshotRecipe;

final readonly class OpponentBoard
{
    /**
     * @param list<Hero> $roster
     * @param list<OpponentAssignment> $assignments
     * @param SnapshotRecipe $recipe de quoi le plateau a été assemblé (D-16).
     *                               Construite par la fabrique et non dérivée
     *                               après coup : l'association héros ↔ objet
     *                               est perdue dans la liste plate de
     *                               `CombatBoard`, et seul celui qui a
     *                               assemblé le plateau la connaît
     */
    public function __construct(
        public CombatBoard $board,
        public array $roster,
        public array $assignments,
        public SnapshotRecipe $recipe,
    ) {
    }
}
