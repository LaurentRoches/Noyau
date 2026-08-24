<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Model\OpponentAssignment;
use App\Presentation\OpponentInventoryPresenter;
use PHPUnit\Framework\TestCase;

final class OpponentInventoryPresenterTest extends TestCase
{
    private function createItem(string $id): Item
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

    public function testItPresentsEmptyAssignmentListToArray(): void
    {
        $result = OpponentInventoryPresenter::toArray([]);

        self::assertSame(['items' => []], $result);
    }

    public function testItPresentsAssignmentsToArray(): void
    {
        $assignment = new OpponentAssignment($this->createItem('shadow_dagger'), 'shadow_hero_1');

        $result = OpponentInventoryPresenter::toArray([$assignment]);

        self::assertSame([
            'items' => [
                [
                    'item' => [
                        'id' => 'shadow_dagger',
                        'name' => 'Item shadow_dagger',
                        'rarity' => 'COMMON',
                        'affinity' => 'shadow',
                        'size' => 'ONE_HAND',
                        'cooldownTicks' => 4,
                        'effects' => [],
                    ],
                    'heroId' => 'shadow_hero_1',
                ],
            ],
        ], $result);
    }
}
