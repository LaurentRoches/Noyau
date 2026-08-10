<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class CombatVestigeTest extends TestCase
{
    private function createVestigeDefinition(int $baseHp = 100, int $baseShield = 10): Vestige
    {
        return new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: $baseShield
        );
    }

    public function testTakeDamageReducesHpWhenNoShield(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition(baseHp: 100, baseShield: 0);
        $combatVestige = new CombatVestige($vestigeDefinition);

        $combatVestige->takeDamage(15);

        $this->assertSame(85, $combatVestige->getHp());
    }

    public function testGetIdDelegatesToVestigeDefinition(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition(baseHp: 100, baseShield: 0);
        $combatVestige = new CombatVestige($vestigeDefinition);

        $this->assertSame('shadow_vestige', $combatVestige->getId());
    }

    public function testTakeDamageReducesShield(): void
    {
        $combatVestige = new CombatVestige($this->createVestigeDefinition());

        $combatVestige->takeDamage(8);

        $this->assertSame(100, $combatVestige->getHp());
        $this->assertSame(2, $combatVestige->getShield());
    }

    public function testTakeDamageReducesShieldAndHp(): void
    {
        $combatVestige = new CombatVestige($this->createVestigeDefinition());

        $combatVestige->takeDamage(12);

        $this->assertSame(98, $combatVestige->getHp());
        $this->assertSame(0, $combatVestige->getShield());
    }

    public function testTakeDamageReducesHpToZero(): void
    {
        $combatVestige = new CombatVestige($this->createVestigeDefinition());

        $combatVestige->takeDamage(120);

        $this->assertSame(0, $combatVestige->getHp());
        $this->assertSame(0, $combatVestige->getShield());
    }

    public function testReceiveHealWithoutReachingMaxHp(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition(baseHp: 100, baseShield: 0);
        $combatVestige = new CombatVestige($vestigeDefinition);

        $combatVestige->takeDamage(20);
        $combatVestige->receiveHeal(15);

        $this->assertSame(95, $combatVestige->getHp());
    }

    public function testReceiveHealExceedMaxHp(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition(baseHp: 100, baseShield: 0);
        $combatVestige = new CombatVestige($vestigeDefinition);

        $combatVestige->receiveHeal(15);

        $this->assertSame(100, $combatVestige->getHp());
    }

    public function testGainShield(): void
    {
        $combatVestige = new CombatVestige($this->createVestigeDefinition(baseShield: 10));

        $combatVestige->gainShield(20);

        $this->assertSame(30, $combatVestige->getShield());
    }

    public function testVestigeIsAlive(): void
    {
        $combatVestige = new CombatVestige($this->createVestigeDefinition());

        $this->assertTrue($combatVestige->isAlive());
    }

    public function testVestigeIsDead(): void
    {
        $combatVestige = new CombatVestige($this->createVestigeDefinition());

        $combatVestige->takeDamage(120);

        $this->assertFalse($combatVestige->isAlive());
    }
}
