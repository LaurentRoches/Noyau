<?php

declare(strict_types=1);

namespace App\Tests\Domain\Player;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Player\AssignedItem;
use App\Domain\Player\Inventory;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function testAddAssignsItemToHero(): void
    {
        $inventory = new Inventory();
        $item = $this->createItem('rusty_dagger');

        $inventory->add($item, 'shadow_bearer');

        $items = $inventory->getItems();
        self::assertCount(1, $items);
        self::assertInstanceOf(AssignedItem::class, $items[0]);
        self::assertSame($item, $items[0]->item);
        self::assertSame('shadow_bearer', $items[0]->heroId);
    }

    private function createItem(string $id): Item
    {
        return new Item(
            id: $id,
            name: 'Test Item',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [],
        );
    }

    public function testRemoveAtReturnsAssignedItemAndRemovesIt(): void
    {
        $inventory = new Inventory();
        $itemA = $this->createItem('rusty_dagger');
        $itemB = $this->createItem('heavy_greatsword');

        $inventory->add($itemA, 'shadow_bearer');
        $inventory->add($itemB, 'neutral_hero');

        $removed = $inventory->removeAt(0);

        self::assertInstanceOf(AssignedItem::class, $removed);
        self::assertSame($itemA, $removed->item);
        self::assertSame('shadow_bearer', $removed->heroId);
        self::assertCount(1, $inventory->getItems());
        self::assertSame($itemB, $inventory->getItems()[0]->item);
    }

    public function testRemoveAtThrowsExceptionWhenIndexInvalid(): void
    {
        $inventory = new Inventory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No item at index 0.');

        $inventory->removeAt(0);
    }

    public function testInsertAtInsertsAssignedItemAtIndex(): void
    {
        $inventory = new Inventory();
        $itemA = $this->createItem('rusty_dagger');
        $itemB = $this->createItem('heavy_greatsword');
        $itemC = $this->createItem('shortsword');

        $inventory->add($itemA, 'shadow_bearer');
        $inventory->add($itemC, 'neutral_hero');

        $inserted = new AssignedItem($itemB, 'shadow_bastion');
        $inventory->insertAt(1, $inserted);

        $items = $inventory->getItems();
        self::assertCount(3, $items);
        self::assertSame($itemA, $items[0]->item);
        self::assertSame($itemB, $items[1]->item);
        self::assertSame('shadow_bastion', $items[1]->heroId);
        self::assertSame($itemC, $items[2]->item);
    }

    public function testInsertAtThrowsExceptionWhenIndexOutOfRange(): void
    {
        $inventory = new Inventory();
        $assignedItem = new AssignedItem($this->createItem('rusty_dagger'), 'shadow_bearer');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot insert at index 1.');

        $inventory->insertAt(1, $assignedItem);
    }

    public function testGetItemIdsByHeroGroupsItemsPerHeroInInventoryOrder(): void
    {
        $inventory = new Inventory();
        $itemA = $this->createItem('rusty_dagger');
        $itemB = $this->createItem('heavy_greatsword');
        $itemC = $this->createItem('shortsword');

        $inventory->add($itemA, 'shadow_bearer');
        $inventory->add($itemB, 'neutral_hero');
        $inventory->add($itemC, 'shadow_bearer');

        $result = $inventory->getItemIdsByHero();

        self::assertSame(
            [
                'shadow_bearer' => ['rusty_dagger', 'shortsword'],
                'neutral_hero' => ['heavy_greatsword'],
            ],
            $result
        );
    }

    public function testGetItemIdsByHeroReturnsEmptyArrayWhenInventoryIsEmpty(): void
    {
        $inventory = new Inventory();

        self::assertSame([], $inventory->getItemIdsByHero());
    }
}
