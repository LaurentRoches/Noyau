<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

/**
 * Vue agrégée d'un type de statut sur un Vestige (D-20).
 *
 * L'état interne est une liste d'instances indépendantes qui ne fusionnent
 * jamais. Les CombatEvent, eux, exposent un couple de valeurs par type et par
 * tick : la somme des stacks vivants et le maximum des durées restantes.
 *
 * Les deux valeurs sont calculées sur le même état de la liste, en un seul
 * appel : les lire séparément exposerait à un écart si la liste bouge entre
 * les deux lectures, ce qui arrive au tick entre le décrément et la purge.
 */
final class AggregatedStatus
{
    public function __construct(
        public readonly int $stacks,
        public readonly int $remainingTicks,
    ) {
    }
}
