<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum StatusType: string
{
    case POISON = 'POISON';
    case BURN = 'BURN';
    case REGEN = 'REGEN';
    case WARD = 'WARD';
}
