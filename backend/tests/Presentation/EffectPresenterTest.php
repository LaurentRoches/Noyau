<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Presentation\EffectPresenter;
use PHPUnit\Framework\TestCase;

final class EffectPresenterTest extends TestCase
{
    public function testItPresentsAnEffectWithoutActionsToArray(): void
    {
        $effect = new Effect(
            trigger: Trigger::ON_ATTACK,
            actions: [],
        );

        $result = EffectPresenter::toArray($effect);

        self::assertSame([
            'trigger' => 'ON_ATTACK',
            'actions' => [],
            'intervalTicks' => null,
        ], $result);
    }

    public function testItPresentsAnEffectWithActionsAndIntervalTicksToArray(): void
    {
        $effect = new Effect(
            trigger: Trigger::EVERY_N_TICKS,
            actions: [
                new Action(type: ActionType::DEAL_DAMAGE, value: 12, target: Target::ENEMY),
            ],
            intervalTicks: 90,
        );

        $result = EffectPresenter::toArray($effect);

        self::assertSame([
            'trigger' => 'EVERY_N_TICKS',
            'actions' => [
                [
                    'type' => 'DEAL_DAMAGE',
                    'value' => 12,
                    'target' => 'ENEMY',
                    'status' => null,
                    'stacks' => null,
                    'durationTicks' => null,
                ],
            ],
            'intervalTicks' => 90,
        ], $result);
    }
}
