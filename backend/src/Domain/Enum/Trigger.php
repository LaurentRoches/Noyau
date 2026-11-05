<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Ce qui fait agir un objet en combat (D-23, `02` §7.7).
 *
 * **Un vocabulaire fermé, et un format.** Chaque photographie archive le
 * déclencheur de chaque effet, et `BoardHydrator` refuse une valeur qu'il ne
 * connaît pas. Ajouter un cas ne casse rien ; en retirer ou en renommer un rend
 * illisible toute archive qui le porte (`07` §8, point 11). `TriggerTest` fige
 * la liste.
 *
 * Deux cadences — `EVERY_N_TICKS`, et `ON_COMBAT_START`, qui entre en combat
 * rechargé — et trois déclencheurs réactifs. **Aucun n'est encore lu** :
 * `TickEngine` active un objet dès que son cooldown est écoulé, quel que soit
 * son déclencheur. Le moteur les lira au chantier 3, brique 3.
 */
enum Trigger: string
{
    case EVERY_N_TICKS = 'EVERY_N_TICKS';
    case ON_COMBAT_START = 'ON_COMBAT_START';
    case ON_SIBLING_TRIGGERED = 'ON_SIBLING_TRIGGERED';
    case ON_STATUS_APPLIED = 'ON_STATUS_APPLIED';
    case ON_THRESHOLD = 'ON_THRESHOLD';
}
