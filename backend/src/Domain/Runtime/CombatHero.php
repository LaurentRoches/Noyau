<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Model\Hero;

final class CombatHero
{
    public function __construct(
        private readonly Hero $definition,
    ) {
    }

    public function getId(): string
    {
        return $this->definition->id;
    }

    public function getDefinition(): Hero
    {
        return $this->definition;
    }
}
