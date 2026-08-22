<?php

declare(strict_types=1);

namespace App\Persistence;

enum GameRunActionType: string
{
    case OPEN_SHOP = 'OPEN_SHOP';
    case PURCHASE = 'PURCHASE';
    case SWAP = 'SWAP';
    case RESOLVE_ROUND = 'RESOLVE_ROUND';
}
