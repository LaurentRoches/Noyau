<?php

declare(strict_types=1);

namespace App\Tests\Domain\Runtime;

use App\Domain\Model\Hero;
use App\Domain\Runtime\CombatHero;
use PHPUnit\Framework\TestCase;

final class CombatHeroTest extends TestCase
{
    private function createHeroDefinition(): Hero
    {
        return new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            itemSlots: 6,
        );
    }

    public function testGetIdDelegatesToHeroDefinition(): void
    {
        $heroDefinition = $this->createHeroDefinition();
        $combatHero = new CombatHero($heroDefinition);

        $this->assertSame('shadow_bearer', $combatHero->getId());
    }
}
