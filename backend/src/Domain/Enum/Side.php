<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Side: string
{
    case PLAYER = 'PLAYER';
    case OPPONENT = 'OPPONENT';
}
