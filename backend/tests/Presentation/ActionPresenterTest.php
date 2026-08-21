<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Model\Action;
use App\Presentation\ActionPresenter;
use PHPUnit\Framework\TestCase;

final class ActionPresenterTest extends TestCase
{
    public function testItPresentsAMinimalActionToArray(): void
    {
        $action = new Action(type: ActionType::DEAL_DAMAGE);

        $result = ActionPresenter::toArray($action);

        self::assertSame([
            'type' => 'DEAL_DAMAGE',
            'value' => null,
            'target' => null,
            'status' => null,
            'stacks' => null,
            'durationTicks' => null,
        ], $result);
    }

    public function testItPresentsAFullyPopulatedActionToArray(): void
    {
        $action = new Action(
            type: ActionType::APPLY_STATUS,
            value: 5,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 2,
            durationTicks: 30,
        );

        $result = ActionPresenter::toArray($action);

        self::assertSame([
            'type' => 'APPLY_STATUS',
            'value' => 5,
            'target' => 'ENEMY',
            'status' => 'POISON',
            'stacks' => 2,
            'durationTicks' => 30,
        ], $result);
    }
}
