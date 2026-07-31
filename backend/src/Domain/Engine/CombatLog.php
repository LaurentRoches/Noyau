<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Event\CombatEvent;

final class CombatLog
{
    /**
     * @var array<CombatEvent>
     */
    private array $events = [];

    public function addEvent(CombatEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return  array<CombatEvent>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
