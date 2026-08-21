<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Presentation\ItemPresenter;
use PHPUnit\Framework\TestCase;

final class ItemPresenterTest extends TestCase
{
    public function testItPresentsAnItemWithoutEffectsToArray(): void
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

        $result = ItemPresenter::toArray($item);

        self::assertSame([
            'id' => 'sword_01',
            'name' => 'Rusty Sword',
            'rarity' => 'COMMON',
            'affinity' => 'physical',
            'size' => 'ONE_HAND',
            'cooldownTicks' => 100,
            'effects' => [],
        ], $result);
    }
}
