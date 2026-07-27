<?php

namespace App\Domain\Model;

use App\Domain\Enum\Rarity;

final readonly class Item
{
    public function __construct(
        public string $id,
        public string $name,
        public Rarity $rarity,
        public string $affinity,
        public int $cooldownTicks,
        public array $effects,
    ) {
    }
}
