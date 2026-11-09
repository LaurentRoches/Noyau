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
     *                               Construite par la fabrique, qui connaît
     *                               les identifiants de catalogue. Depuis
     *                               D-28, `CombatBoard` porte aussi
     *                               l'appartenance héros ↔ objet : la recette
     *                               reste de la provenance, pas une donnée de
     *                               rejeu
     */
    public function __construct(
        public CombatBoard $board,
        public array $roster,
        public array $assignments,
        public SnapshotRecipe $recipe,
    ) {
    }
}
