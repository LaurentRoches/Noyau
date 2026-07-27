<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum ActionType: string
{
    case DEAL_DAMAGE = 'DEAL_DAMAGE';
    case APPLY_STATUS = 'APPLY_STATUS';
    case GAIN_SHIELD = 'GAIN_SHIELD';
    case HEAL = 'HEAL';
    case GAIN_GOLD = 'GAIN_GOLD';
    case GAIN_MANA = 'GAIN_MANA';

    // Prévu pour la mécanique différée de conversion d'affinité (V2/V3, non implémenté)
    case SET_AFFINITY = 'SET_AFFINITY';
}
