<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Enum\Rarity;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class CombatBoardTest extends TestCase
{
    private function createVestigeDefinition(int $baseHp = 100): Vestige
    {
        return new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: 10,
        );
    }

    private function createHeroDefinition(): Hero
    {
        return new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            itemSlots: 6
        );
    }

    public function testBoardReturnsSameHeroesAndVestigeInstance(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $hero = new CombatHero($this->createHeroDefinition());

        $combatBoard = new CombatBoard($vestige, [$hero], items: []);

        $this->assertSame($vestige, $combatBoard->getVestige());
        $this->assertSame([$hero], $combatBoard->getHeroes());
    }

    public function testGetReadyItemsReturnsOnlyItemsWithZeroCooldown(): void
    {
        $readyItemDef = new Item(
            id: 'quick_dagger',
            name: 'Quick Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 0,
            effects: []
        );
        $readyItem = new CombatItem($readyItemDef);

        $notReadyItemDef = new Item(
            id: 'heavy_hammer',
            name: 'Heavy Hammer',
            rarity: Rarity::RARE,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: []
        );
        $notReadyItem = new CombatItem($notReadyItemDef);

        $vestige = new CombatVestige($this->createVestigeDefinition());
        $hero = new CombatHero($this->createHeroDefinition());
        $combatBoard = new CombatBoard($vestige, [$hero], [$readyItem, $notReadyItem]);

        $readyItems = $combatBoard->getReadyItems();

        $this->assertCount(1, $readyItems);
        $this->assertSame($readyItem, $readyItems[0]);
    }

    public function testIsAliveReturnsTrueWhenVestigeIsAlive(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition(baseHp: 100));
        $hero = new CombatHero($this->createHeroDefinition());
        $combatBoard = new CombatBoard($vestige, [$hero], []);

        $this->assertTrue($combatBoard->isAlive());
    }

    public function testIsAliveReturnsFalseWhenVestigeIsDead(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition(baseHp: 100));
        $vestige->takeDamage(120);

        $hero = new CombatHero($this->createHeroDefinition());
        $combatBoard = new CombatBoard($vestige, [$hero], []);

        $this->assertFalse($combatBoard->isAlive());
    }

    public function testBoardAcceptsUpToThreeHeroes(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $heroes = [
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
        ];

        $combatBoard = new CombatBoard($vestige, $heroes, []);

        $this->assertCount(3, $combatBoard->getHeroes());
    }

    public function testBoardRejectsZeroHeroes(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());

        $this->expectException(\InvalidArgumentException::class);
        new CombatBoard($vestige, [], []);
    }

    public function testBoardRejectsMoreThanThreeHeroes(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $heroes = [
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
        ];

        $this->expectException(\InvalidArgumentException::class);
        new CombatBoard($vestige, $heroes, []);
    }
}
