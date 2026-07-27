<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Trigger: string
{
    case ON_ATTACK = 'ON_ATTACK';
    case EVERY_N_TICKS = 'EVERY_N_TICKS';
    case ON_KILL = 'ON_KILL';
    case ON_DEATH = 'ON_DEATH';
}
