<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\HeroSkillType;

final readonly class Hero
{
    public function __construct(
        public string $id,
        public string $name,
        public string $affinity,
        public int $itemSlots,
        public ?HeroSkillType $skill = null,
    ) {
    }
}
