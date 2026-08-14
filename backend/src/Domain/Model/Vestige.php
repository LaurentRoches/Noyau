<?php

declare(strict_types=1);

namespace App\Domain\Model;

final readonly class Vestige
{
    public function __construct(
        public string $id,
        public string $name,
        public string $affinity,
        public int $baseHp,
        public int $baseShield,
        public int $startingGold
    ) {
    }
}
