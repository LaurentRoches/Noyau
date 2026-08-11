<?php

declare(strict_types=1);

namespace App\Tests\Domain\Runtime;

use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Runtime\CombatItem;
use PHPUnit\Framework\TestCase;

final class CombatItemTest extends TestCase
{
    private function createItemDefinition(int $cooldownTicks = 4): Item
    {
        return new Item(
            id: 'shadow_dagger',
            name: "Shadow's Dagger",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: $cooldownTicks,
            effects: []
        );
    }

    public function testSimpleDecrementCooldown(): void
    {
        $combatItem = new CombatItem($this->createItemDefinition(cooldownTicks: 4));

        $combatItem->decrementCooldown();

        $this->assertSame(3, $combatItem->getCooldown());
    }

    public function testDecrementCooldownUnderZero(): void
    {
        $combatItem = new CombatItem($this->createItemDefinition(cooldownTicks: 1));

        $combatItem->decrementCooldown(3);

        $this->assertSame(0, $combatItem->getCooldown());
    }

    public function testResetCooldown(): void
    {
        $combatItem = new CombatItem($this->createItemDefinition(cooldownTicks: 4));

        $combatItem->decrementCooldown(3);
        $combatItem->resetCooldown();

        $this->assertSame(4, $combatItem->getCooldown());
    }

    public function testItemIsReadyToUse(): void
    {
        $combatItem = new CombatItem($this->createItemDefinition(cooldownTicks: 1));

        $combatItem->decrementCooldown(1);

        $this->assertTrue($combatItem->isReady());
    }

    public function testItemIsNotReadyToUse(): void
    {
        $combatItem = new CombatItem($this->createItemDefinition(cooldownTicks: 4));

        $combatItem->decrementCooldown(1);

        $this->assertFalse($combatItem->isReady());
    }

    public function testGetItemReturnsItemDefinition(): void
    {
        $itemDefinition = $this->createItemDefinition();
        $combatItem = new CombatItem($itemDefinition);

        $this->assertSame($itemDefinition, $combatItem->getItem());
    }
}
