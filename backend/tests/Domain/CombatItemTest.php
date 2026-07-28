<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Runtime\CombatItem;
use PHPUnit\Framework\TestCase;

final class CombatItemTest extends TestCase
{
    public function testSimpleDecrementCooldown(): void
    {
        $itemDefinition = new Item(
            id: 'shadow_dagger',
            name: "Shadow's Dagger",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: []
        );

        $combatItem = new CombatItem($itemDefinition);

        $combatItem->decrementCooldown();

        $this->assertSame(3, $combatItem->getCooldown());
    }
    
    public function testDecrementCooldownUnderZero(): void
    {
        $itemDefinition = new Item(
            id: 'shadow_dagger',
            name: "Shadow's Dagger",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: []
        );

        $combatItem = new CombatItem($itemDefinition);

        $combatItem->decrementCooldown(3);

        $this->assertSame(0, $combatItem->getCooldown());
    }
    
    public function testResetCooldown(): void
    {
        $itemDefinition = new Item(
            id: 'shadow_dagger',
            name: "Shadow's Dagger",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: []
        );

        $combatItem = new CombatItem($itemDefinition);

        $combatItem->decrementCooldown(3);
        $combatItem->resetCooldown();

        $this->assertSame(4, $combatItem->getCooldown());
    }
    
    public function testItemIsReadyToUse(): void
    {
        $itemDefinition = new Item(
            id: 'shadow_dagger',
            name: "Shadow's Dagger",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: []
        );

        $combatItem = new CombatItem($itemDefinition);

        $combatItem->decrementCooldown(1);

        $this->assertSame(true, $combatItem->isReady());
    }
    
    public function testItemIsNotReadyToUse(): void
    {
        $itemDefinition = new Item(
            id: 'shadow_dagger',
            name: "Shadow's Dagger",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: []
        );

        $combatItem = new CombatItem($itemDefinition);

        $combatItem->decrementCooldown(1);

        $this->assertSame(false, $combatItem->isReady());
    }
}
