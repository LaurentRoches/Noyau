<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum EventType: string {
    case DAMAGE_DEALT = 'damage_dealt';
    case HEAL_RECEIVED = 'heal_received';
    case SHIELD_GAINED = 'shield_gained';
}
