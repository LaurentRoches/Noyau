<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Enum\EventType;
use App\Domain\Event\CombatEvent;
use PHPUnit\Framework\TestCase;

final class CombatEventTest extends TestCase
{
    public function testCombatEventHoldsConstructorData(): void
    {
        $tick = 3;
        $type = EventType::DAMAGE_DEALT;
        $payload = [
            'amount' => 25,
            'target' => 'enemy_hero'
        ];

        $event = new CombatEvent(
            tick: $tick,
            type: $type,
            payload: $payload
        );

        $this->assertSame(3, $event->tick);
        $this->assertSame(EventType::DAMAGE_DEALT, $event->type);
        $this->assertSame(['amount' => 25, 'target' => 'enemy_hero'], $event->payload);
    }
}
