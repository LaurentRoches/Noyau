<?php

declare(strict_types=1);

namespace App\Tests\Domain\Player;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Player\HeroItemAllocator;
use App\Domain\Player\Inventory;
use PHPUnit\Framework\TestCase;

final class HeroItemAllocatorTest extends TestCase
{
    private function createItem(string $id, ItemSize $size): Item
    {
        return new Item(
            id: $id,
            name: 'Test Item',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: $size,
            cooldownTicks: 1,
            effects: [],
        );
    }

    private function createHero(string $id, int $itemSlots): Hero
    {
        return new Hero(
            id: $id,
            name: 'Test Hero',
            affinity: 'neutral',
            itemSlots: $itemSlots
        );
    }

    public function testAllocateReturnsFirstHeroWithEnoughRoom(): void
    {
        $roster = [
            $this->createHero('shadow_bearer', 2),
            $this->createHero('neutral_hero', 2),
        ];
        $allocator = new HeroItemAllocator($roster);
        $inventory = new Inventory();

        $heroId = $allocator->allocate($this->createItem('rusty_dagger', ItemSize::ONE_HAND), $inventory);

        self::assertSame('shadow_bearer', $heroId);
    }

    public function testAllocateSkipsFullHeroAndReturnsNextOneWithRoom(): void
    {
        $roster = [
            $this->createHero('shadow_bearer', 1),
            $this->createHero('neutral_hero', 2),
        ];
        $allocator = new HeroItemAllocator($roster);
        $inventory = new Inventory();
        $inventory->add($this->createItem('rusty_dagger', ItemSize::ONE_HAND), 'shadow_bearer');

        $heroId = $allocator->allocate($this->createItem('shortsword', ItemSize::ONE_HAND), $inventory);

        self::assertSame('neutral_hero', $heroId);
    }

    public function testAllocateReturnsNullWhenNoHeroHasRoom(): void
    {
        $roster = [$this->createHero('shadow_bearer', 1)];
        $allocator = new HeroItemAllocator($roster);
        $inventory = new Inventory();
        $inventory->add($this->createItem('rusty_dagger', ItemSize::ONE_HAND), 'shadow_bearer');

        $heroId = $allocator->allocate($this->createItem('shortsword', ItemSize::ONE_HAND), $inventory);

        self::assertNull($heroId);
    }

    public function testCanAssignThrowsExceptionWhenHeroNotInRoster(): void
    {
        $roster = [$this->createHero('shadow_bearer', 2)];
        $allocator = new HeroItemAllocator($roster);
        $inventory = new Inventory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hero "unknown_hero" is not part of this roster.');

        $allocator->canAssign($this->createItem('rusty_dagger', ItemSize::ONE_HAND), 'unknown_hero', $inventory);
    }
}
