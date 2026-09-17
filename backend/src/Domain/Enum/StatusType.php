<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum StatusType: string
{
    case POISON = 'POISON';
    case BURN = 'BURN';
    case REGEN = 'REGEN';
    case WARD = 'WARD';

    /**
     * Seuls les statuts hostiles sont nettoyés par le soin (D-21).
     *
     * Le `match` est volontairement sans branche par défaut : l'ajout d'un
     * statut ne compilera pas tant que son camp n'aura pas été tranché ici.
     * C'est ce qui interdit la liste dispersée dans le moteur que `07` §6
     * proscrit explicitement.
     */
    public function isHostile(): bool
    {
        return match ($this) {
            self::POISON, self::BURN => true,
            self::REGEN, self::WARD => false,
        };
    }
}
