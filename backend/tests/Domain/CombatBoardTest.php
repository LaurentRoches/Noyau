<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Enum\Rarity;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use PHPUnit\Framework\TestCase;

final class CombatBoardTest extends TestCase
{
    private function createHeroDefinition(int $baseHp = 100): Hero
    {
        return new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: 10,
            itemSlots: 6
        );
    }

    public function testBoardReturnsSameHeroInstance(): void
    {
        $heroDefinition = $this->createHeroDefinition();
        $combatHero = new CombatHero($heroDefinition);

        $combatBoard = new CombatBoard($combatHero, items: []);

        $this->assertSame($combatHero, $combatBoard->getHero());
    }

    public function testGetReadyItemsReturnsOnlyItemsWithZeroCooldown(): void
    {
        // 1. Un objet prêt (cooldown 0)
        $readyItemDef = new Item(
            id: 'quick_dagger',
            name: 'Quick Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 0,
            effects: []
        );
        $readyItem = new CombatItem($readyItemDef);

        // 2. Un objet en recharge (cooldown 4)
        $notReadyItemDef = new Item(
            id: 'heavy_hammer',
            name: 'Heavy Hammer',
            rarity: Rarity::RARE,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: []
        );
        $notReadyItem = new CombatItem($notReadyItemDef);

        $hero = new CombatHero($this->createHeroDefinition());
        $combatBoard = new CombatBoard($hero, [$readyItem, $notReadyItem]);

        $readyItems = $combatBoard->getReadyItems();

        $this->assertCount(1, $readyItems);
        $this->assertSame($readyItem, $readyItems[0]);
    }

    public function testHasAliveHeroReturnsTrueWhenHeroIsAlive(): void
    {
        $hero = new CombatHero($this->createHeroDefinition(baseHp: 100));
        $combatBoard = new CombatBoard($hero, []);

        $this->assertTrue($combatBoard->hasAliveHero());
    }

    public function testHasAliveHeroReturnsFalseWhenHeroIsDead(): void
    {
        $hero = new CombatHero($this->createHeroDefinition(baseHp: 100));
        $hero->takeDamage(120);

        $combatBoard = new CombatBoard($hero, []);

        $this->assertFalse($combatBoard->hasAliveHero());
    }
}
