<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\CombatLog;
use App\Domain\Enum\EventType;
use App\Domain\Event\CombatEvent;
use PHPUnit\Framework\TestCase;

final class CombatLogTest extends TestCase
{
    public function testCombatLogIsEmptyInitially(): void
    {
        $log = new CombatLog();

        self::assertCount(0, $log->getEvents());
        self::assertSame(0, $log->count());
    }

    public function testAddAndRetrieveEvents(): void
    {
        $log = new CombatLog();

        $event = new CombatEvent(
            tick: 1,
            type: EventType::DAMAGE_DEALT,
            payload: ['amount' => 15, 'target' => 'shadow_bearer']
        );

        $log->addEvent($event);

        self::assertCount(1, $log->getEvents());
        self::assertSame(1, $log->count());
        self::assertSame($event, $log->getEvents()[0]);
    }
}
