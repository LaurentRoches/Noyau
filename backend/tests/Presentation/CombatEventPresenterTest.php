<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Enum\EventType;
use App\Domain\Event\CombatEvent;
use App\Presentation\CombatEventPresenter;
use PHPUnit\Framework\TestCase;

final class CombatEventPresenterTest extends TestCase
{
    public function testItPresentsAnEventWithEmptyPayloadToArray(): void
    {
        $event = new CombatEvent(
            tick: 12,
            type: EventType::STATUS_EXPIRED,
        );

        $result = CombatEventPresenter::toArray($event);

        self::assertSame([
            'tick' => 12,
            'type' => 'STATUS_EXPIRED',
            'payload' => [],
        ], $result);
    }

    public function testItPresentsAnEventWithPayloadToArray(): void
    {
        $event = new CombatEvent(
            tick: 40,
            type: EventType::DAMAGE_DEALT,
            payload: [
                'amount' => 15,
                'shieldDamage' => 0,
                'hpDamage' => 15,
                'target' => 'opponent_vestige',
                'targetSide' => 'OPPONENT',
                'sourceSide' => 'PLAYER',
                'sourceItemId' => 'shadow_dagger',
            ],
        );

        $result = CombatEventPresenter::toArray($event);

        self::assertSame([
            'tick' => 40,
            'type' => 'DAMAGE_DEALT',
            'payload' => [
                'amount' => 15,
                'shieldDamage' => 0,
                'hpDamage' => 15,
                'target' => 'opponent_vestige',
                'targetSide' => 'OPPONENT',
                'sourceSide' => 'PLAYER',
                'sourceItemId' => 'shadow_dagger',
            ],
        ], $result);
    }
}
