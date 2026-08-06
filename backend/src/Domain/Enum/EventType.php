<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum EventType: string
{
    case DAMAGE_DEALT = 'DAMAGE_DEALT';
    case HEAL_RECEIVED = 'HEAL_RECEIVED';
    case SHIELD_GAINED = 'SHIELD_GAINED';
}
