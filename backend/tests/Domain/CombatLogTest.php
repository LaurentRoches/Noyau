<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Engine\CombatLog;
use App\Domain\Enum\EventType;
use App\Domain\Event\CombatEvent;
use PHPUnit\Framework\TestCase;

final class CombatLogTest extends TestCase
{
    public function testCombatLogIsEmptyInitially(): void
    {
        $log = new CombatLog();

        $this->assertCount(0, $log->getEvents());
        $this->assertSame(0, $log->count());
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

        $this->assertCount(1, $log->getEvents());
        $this->assertSame(1, $log->count());
        $this->assertSame($event, $log->getEvents()[0]);
    }
}
