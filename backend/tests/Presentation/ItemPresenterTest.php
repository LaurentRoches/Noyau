<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
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
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 100,
            effects: [],
        );

        $result = ItemPresenter::toArray($item);

        self::assertSame([
            'id' => 'sword_01',
            'name' => 'Rusty Sword',
            'rarity' => 'COMMON',
            'affinity' => 'neutral',
            'size' => 'ONE_HAND',
            'cooldownTicks' => 100,
            'effects' => [],
        ], $result);
    }

    public function testItPresentsAnItemWithEffectsToArray(): void
    {
        $item = new Item(
            id: 'sword_02',
            name: 'Flaming Sword',
            rarity: Rarity::RARE,
            affinity: 'fire',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 80,
            effects: [
                new Effect(
                    trigger: Trigger::ON_ATTACK,
                    actions: [
                        new Action(
                            type: ActionType::APPLY_STATUS,
                            target: Target::ENEMY,
                            status: StatusType::BURN,
                            stacks: 1
                        ),
                    ],
                ),
            ],
        );

        $result = ItemPresenter::toArray($item);

        self::assertSame([
            'id' => 'sword_02',
            'name' => 'Flaming Sword',
            'rarity' => 'RARE',
            'affinity' => 'fire',
            'size' => 'ONE_HAND',
            'cooldownTicks' => 80,
            'effects' => [
                [
                    'trigger' => 'ON_ATTACK',
                    'actions' => [
                        [
                            'type' => 'APPLY_STATUS',
                            'value' => null,
                            'target' => 'ENEMY',
                            'status' => 'BURN',
                            'stacks' => 1,
                            'durationTicks' => null,
                        ],
                    ],
                    'intervalTicks' => null,
                ],
            ],
        ], $result);
    }
}
