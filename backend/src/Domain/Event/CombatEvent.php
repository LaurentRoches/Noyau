<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Enum\EventType;

final readonly class CombatEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $tick,
        public EventType $type,
        public array $payload = [],
    ) {
    }
}
