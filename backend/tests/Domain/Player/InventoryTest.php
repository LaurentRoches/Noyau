<?php

declare(strict_types=1);

namespace App\Tests\Domain\Player;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Player\Inventory;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
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
        $inventory = new Inventory(capacity: 6);

        $inventory->add($this->createItem());

        self::assertCount(1, $inventory->getItems());
        self::assertSame(1, $inventory->count());
    }

    public function testGetItemIdsReturnsItemIdsInOrder(): void
    {
        $inventory = new Inventory(capacity: 6);

        $inventory->add($this->createItem('dagger'));
        $inventory->add($this->createItem('shield'));

        self::assertSame(['dagger', 'shield'], $inventory->getItemIds());
    }

    public function testIsFullReturnsTrueAtCapacity(): void
    {
        $inventory = new Inventory(capacity: 2);

        $inventory->add($this->createItem('a'));
        self::assertFalse($inventory->isFull());

        $inventory->add($this->createItem('b'));
        self::assertTrue($inventory->isFull());
    }

    public function testAddThrowsWhenInventoryIsFull(): void
    {
        $inventory = new Inventory(capacity: 1);
        $inventory->add($this->createItem());

        $this->expectException(\LogicException::class);
        $inventory->add($this->createItem('overflow'));
    }

    public function testRemoveAtReturnsAndRemovesItem(): void
    {
        $inventory = new Inventory(capacity: 6);
        $inventory->add($this->createItem('dagger'));
        $inventory->add($this->createItem('shield'));

        $removed = $inventory->removeAt(0);

        self::assertSame('dagger', $removed->id);
        self::assertSame(['shield'], $inventory->getItemIds());
    }

    public function testRemoveAtThrowsOnInvalidIndex(): void
    {
        $inventory = new Inventory(capacity: 6);

        $this->expectException(\InvalidArgumentException::class);
        $inventory->removeAt(0);
    }

    public function testInsertAtPlacesItemAtGivenIndex(): void
    {
        $inventory = new Inventory(capacity: 6);
        $inventory->add($this->createItem('dagger'));
        $inventory->add($this->createItem('shield'));

        $inventory->insertAt(1, $this->createItem('bow'));

        self::assertSame(['dagger', 'bow', 'shield'], $inventory->getItemIds());
    }
}
