<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Model\Hero;
use App\Domain\Runtime\CombatHero;
use PHPUnit\Framework\TestCase;

final class CombatHeroTest extends TestCase
{
    public function testTakeDamageReducesHpWhenNoShield(): void
    {
        $heroDefinition = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            itemSlots: 6,
        );

        $combatHero = new CombatHero($heroDefinition);

        $combatHero->takeDamage(15);

        $this->assertSame(85, $combatHero->getHp());
    }

    public function testTakeDamageReducesShield(): void
    {
        $heroDefinition = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            itemSlots: 6,
        );

        $combatHero = new CombatHero($heroDefinition);

        $combatHero->takeDamage(8);

        $this->assertSame(100, $combatHero->getHp());
        $this->assertSame(2, $combatHero->getShield());
    }

    public function testTakeDamageReducesShieldAndHp(): void
    {
        $heroDefinition = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            itemSlots: 6,
        );

        $combatHero = new CombatHero($heroDefinition);

        $combatHero->takeDamage(12);

        $this->assertSame(98, $combatHero->getHp());
        $this->assertSame(0, $combatHero->getShield());
    }

    public function testTakeDamageReducesHpToZero(): void
    {
        $heroDefinition = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            itemSlots: 6,
        );

        $combatHero = new CombatHero($heroDefinition);

        $combatHero->takeDamage(120);

        $this->assertSame(0, $combatHero->getHp());
        $this->assertSame(0, $combatHero->getShield());
    }

    public function testReceiveHealWithoutReachingMaxHp(): void
    {
        $heroDefinition = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            itemSlots: 6,
        );

        $combatHero = new CombatHero($heroDefinition);

        $combatHero->takeDamage(20);
        $combatHero->receiveHeal(15);

        $this->assertSame(95, $combatHero->getHp());
    }

    public function testReceiveHealExceedMaxHp(): void
    {
        $heroDefinition = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            itemSlots: 6,
        );

        $combatHero = new CombatHero($heroDefinition);

        $combatHero->receiveHeal(15);

        $this->assertSame(100, $combatHero->getHp());
    }

    public function testGainShield(): void
    {
        $heroDefinition = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            itemSlots: 6,
        );

        $combatHero = new CombatHero($heroDefinition);

        $combatHero->gainShield(20);

        $this->assertSame(30, $combatHero->getShield());
    }
}
