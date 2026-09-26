<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Comment un combat s'est terminé (D-15, `02` §7.5).
 *
 * **Le vainqueur seul ne suffit pas.** Une victoire par KO et une victoire
 * arrachée au départage d'une double mort sont deux issues différentes pour
 * le joueur, et `SimulationResult::$winner` ne les distingue pas. Sans ce
 * champ, l'interface ne peut annoncer que « gagné » ou « perdu », et une
 * défaite au départage passe pour un bug.
 */
enum Resolution: string
{
    /** Un seul plateau est tombé. Aucun départage n'a eu lieu. */
    case KNOCKOUT = 'KNOCKOUT';

    /** Les deux plateaux sont tombés dans la même phase, et ont été départagés. */
    case SIMULTANEOUS_RESOLVED = 'SIMULTANEOUS_RESOLVED';

    /** `maxTicks` atteint, les deux plateaux vivants, départagés. */
    case TIMEOUT_RESOLVED = 'TIMEOUT_RESOLVED';
}
