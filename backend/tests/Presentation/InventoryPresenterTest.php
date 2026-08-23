<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Player\Inventory;
use App\Presentation\InventoryPresenter;
use PHPUnit\Framework\TestCase;

final class InventoryPresenterTest extends TestCase
{
    public function testItPresentsAnInventoryToArray(): void
    {
        $item = new Item(
            id: 'sword_01',
            name: 'Rusty Sword',
            rarity: Rarity::COMMON,
            affinity: 'physical',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 100,
            effects: [],
        );
        $inventory = new Inventory();
        $inventory->add($item, 'shadow_bearer');

        $result = InventoryPresenter::toArray($inventory);

        self::assertSame([
            'items' => [
                [
                    'inventoryIndex' => 0,
                    'item' => [
                        'id' => 'sword_01',
                        'name' => 'Rusty Sword',
                        'rarity' => 'COMMON',
                        'affinity' => 'physical',
                        'size' => 'ONE_HAND',
                        'cooldownTicks' => 100,
                        'effects' => [],
                    ],
                    'heroId' => 'shadow_bearer',
                ],
            ],
        ], $result);
    }
}
