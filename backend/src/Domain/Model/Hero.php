<?php 

namespace App\Domain\Model;

final readonly class Hero
{
    public function __construct(
        public string $id,
        public string $name,
        public string $affinity,
        public int $baseHp,
        public int $itemSlots,
    ) {
    }
}
