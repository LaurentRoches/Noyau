<?php

declare(strict_types=1);

namespace App\Domain\Model;

final readonly class OpponentAssignment
{
    public function __construct(
        public Item $item,
        public string $heroId,
    ) {
    }
}
