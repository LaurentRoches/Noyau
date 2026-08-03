<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Target: string
{
    case SELF = 'SELF';
    case ENEMY = 'ENEMY';
    case ALL_ENEMIES = 'ALL_ENEMIES';
    case ALL_ALLIES = 'ALL_ALLIES';
}
