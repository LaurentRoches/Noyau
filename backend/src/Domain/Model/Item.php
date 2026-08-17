<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;

final readonly class Item
{
    /**
     * @param Effect[] $effects
     */
    public function __construct(
        public string $id,
        public string $name,
        public Rarity $rarity,
        public string $affinity,
        public ItemSize $size,
        public int $cooldownTicks,
        public array $effects,
    ) {
    }
}
