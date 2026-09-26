<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum EventType: string
{
    case DAMAGE_DEALT = 'DAMAGE_DEALT';
    case HEAL_RECEIVED = 'HEAL_RECEIVED';
    case SHIELD_GAINED = 'SHIELD_GAINED';
    case STATUS_APPLIED = 'STATUS_APPLIED';
    case STATUS_DAMAGE_DEALT = 'STATUS_DAMAGE_DEALT';
    case STATUS_HEAL_RECEIVED = 'STATUS_HEAL_RECEIVED';
    case STATUS_SHIELD_GAINED = 'STATUS_SHIELD_GAINED';
    case STATUS_EXPIRED = 'STATUS_EXPIRED';
    case ENRAGE_DAMAGE_DEALT = 'ENRAGE_DAMAGE_DEALT';

    /**
     * Départage de fin de combat (D-15).
     *
     * Émis une fois au plus, et seulement quand aucun KO n'a tranché. Il
     * existe pour que le rejeu montre **pourquoi** ce vainqueur a été retenu :
     * sans lui, une défaite au départage est indiscernable d'une défaite par
     * KO, et le joueur conclut au bug.
     */
    case RESOLUTION_TIEBREAK = 'RESOLUTION_TIEBREAK';
}
