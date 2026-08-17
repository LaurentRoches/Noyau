<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Model\Item;

final readonly class AssignedItem
{
    public function __construct(
        public Item $item,
        public string $heroId,
    ) {
    }
}
