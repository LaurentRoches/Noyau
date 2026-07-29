<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Model\Hero;
use App\Domain\Runtime\CombatHero;
use PHPUnit\Framework\TestCase;

final class CombatHeroTest extends TestCase
{
    private function createHeroDefinition(int $baseHp = 100, int $baseShield = 10): Hero
    {
        return new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: $baseShield,
            itemSlots: 6,
        );
    }

    public function testTakeDamageReducesHpWhenNoShield(): void
    {
        $heroDefinition = $this->createHeroDefinition(baseHp: 100, baseShield: 0);
        $combatHero = new CombatHero($heroDefinition);

        $combatHero->takeDamage(15);

        $this->assertSame(85, $combatHero->getHp());
    }

    public function testTakeDamageReducesShield(): void
    {
        $combatHero = new CombatHero($this->createHeroDefinition());

        $combatHero->takeDamage(8);

        $this->assertSame(100, $combatHero->getHp());
        $this->assertSame(2, $combatHero->getShield());
    }

    public function testTakeDamageReducesShieldAndHp(): void
    {
        $combatHero = new CombatHero($this->createHeroDefinition());

        $combatHero->takeDamage(12);

        $this->assertSame(98, $combatHero->getHp());
        $this->assertSame(0, $combatHero->getShield());
    }

    public function testTakeDamageReducesHpToZero(): void
    {
        $combatHero = new CombatHero($this->createHeroDefinition());

        $combatHero->takeDamage(120);

        $this->assertSame(0, $combatHero->getHp());
        $this->assertSame(0, $combatHero->getShield());
    }

    public function testReceiveHealWithoutReachingMaxHp(): void
    {
        $heroDefinition = $this->createHeroDefinition(baseHp: 100, baseShield: 0);
        $combatHero = new CombatHero($heroDefinition);

        $combatHero->takeDamage(20);
        $combatHero->receiveHeal(15);

        $this->assertSame(95, $combatHero->getHp());
    }

    public function testReceiveHealExceedMaxHp(): void
    {
        $heroDefinition = $this->createHeroDefinition(baseHp: 100, baseShield: 0);
        $combatHero = new CombatHero($heroDefinition);

        $combatHero->receiveHeal(15);

        $this->assertSame(100, $combatHero->getHp());
    }

    public function testGainShield(): void
    {
        $combatHero = new CombatHero($this->createHeroDefinition(baseShield: 10));

        $combatHero->gainShield(20);

        $this->assertSame(30, $combatHero->getShield());
    }

    public function testHeroIsAlive(): void
    {
        $combatHero = new CombatHero($this->createHeroDefinition());

        $this->assertTrue($combatHero->isAlive());
    }

    public function testHeroIsDead(): void
    {
        $combatHero = new CombatHero($this->createHeroDefinition());

        $combatHero->takeDamage(120);

        $this->assertFalse($combatHero->isAlive());
    }
}
