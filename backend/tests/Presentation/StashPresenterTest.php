<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Player\Stash;
use App\Presentation\StashPresenter;
use PHPUnit\Framework\TestCase;

final class StashPresenterTest extends TestCase
{
    public function testItPresentsAStashToArray(): void
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
        $stash = new Stash(3);
        $stash->add($item);

        $result = StashPresenter::toArray($stash);

        self::assertSame([
            'items' => [
                [
                    'stashIndex' => 0,
                    'item' => [
                        'id' => 'sword_01',
                        'name' => 'Rusty Sword',
                        'rarity' => 'COMMON',
                        'affinity' => 'physical',
                        'size' => 'ONE_HAND',
                        'cooldownTicks' => 100,
                        'effects' => [],
                    ],
                ],
            ],
            'capacity' => 3,
            'isFull' => false,
        ], $result);
    }
}
