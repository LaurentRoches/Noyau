<?php

declare(strict_types=1);

namespace App\Tests\Domain\Player;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Player\Stash;
use PHPUnit\Framework\TestCase;

final class StashTest extends TestCase
{
    private function createItem(string $id = 'dagger'): Item
    {
        return new Item(
            id: $id,
            name: "Item {$id}",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 4,
            effects: []
        );
    }

    public function testAddStoresItem(): void
    {
        $Stash = new Stash(capacity: 6);

        $Stash->add($this->createItem());

        self::assertCount(1, $Stash->getItems());
        self::assertSame(1, $Stash->count());
    }

    public function testGetItemIdsReturnsItemIdsInOrder(): void
    {
        $Stash = new Stash(capacity: 6);

        $Stash->add($this->createItem('dagger'));
        $Stash->add($this->createItem('shield'));

        self::assertSame(['dagger', 'shield'], $Stash->getItemIds());
    }

    public function testIsFullReturnsTrueAtCapacity(): void
    {
        $Stash = new Stash(capacity: 2);

        $Stash->add($this->createItem('a'));
        self::assertFalse($Stash->isFull());

        $Stash->add($this->createItem('b'));
        self::assertTrue($Stash->isFull());
    }

    public function testAddThrowsWhenStashIsFull(): void
    {
        $Stash = new Stash(capacity: 1);
        $Stash->add($this->createItem());

        $this->expectException(\LogicException::class);
        $Stash->add($this->createItem('overflow'));
    }

    public function testRemoveAtReturnsAndRemovesItem(): void
    {
        $Stash = new Stash(capacity: 6);
        $Stash->add($this->createItem('dagger'));
        $Stash->add($this->createItem('shield'));

        $removed = $Stash->removeAt(0);

        self::assertSame('dagger', $removed->id);
        self::assertSame(['shield'], $Stash->getItemIds());
    }

    public function testRemoveAtThrowsOnInvalidIndex(): void
    {
        $Stash = new Stash(capacity: 6);

        $this->expectException(\InvalidArgumentException::class);
        $Stash->removeAt(0);
    }

    public function testInsertAtPlacesItemAtGivenIndex(): void
    {
        $Stash = new Stash(capacity: 6);
        $Stash->add($this->createItem('dagger'));
        $Stash->add($this->createItem('shield'));

        $Stash->insertAt(1, $this->createItem('bow'));

        self::assertSame(['dagger', 'bow', 'shield'], $Stash->getItemIds());
    }

    public function testItExposesItsCapacity(): void
    {
        $Stash = new Stash(capacity: 3);
        self::assertSame(3, $Stash->getCapacity());
    }
}
