<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\Side;
use App\Domain\Runtime\CombatBoard;

final class SimulationResult
{
    public function __construct(
        public ?CombatBoard $winner,
        public int $totalTicks,
        public CombatLog $log,
        public CombatBoard $boardA,
        public CombatBoard $boardB,
    ) {
    }

    /**
     * Côté attribué à ce plateau dans ce combat.
     *
     * **Pourquoi l'attribution voyage dans le résultat.** Le `SimulationContext`
     * qui la calcule disparaît à la sortie de `Simulator::run()`. Sans elle
     * ici, l'Application n'aurait aucun moyen de savoir quel côté a reçu son
     * plateau, et la couche Http devrait écrire « A » en dur — exact tant que
     * l'attribution est positionnelle, faux ensuite, et sans le moindre test
     * pour le signaler.
     *
     * **Comparaison par identité d'objet, jamais par identifiant de Vestige.**
     * Un combat miroir oppose deux plateaux au même identifiant : c'est
     * exactement le cas que les libellés neutres existent pour lever, et une
     * table indexée sur cet identifiant confondrait les deux côtés.
     */
    public function sideOf(CombatBoard $board): Side
    {
        return match (true) {
            $board === $this->boardA => Side::A,
            $board === $this->boardB => Side::B,
            default => throw new \InvalidArgumentException(
                'This board did not take part in this combat.'
            ),
        };
    }
}
