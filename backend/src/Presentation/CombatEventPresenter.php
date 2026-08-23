<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Event\CombatEvent;

final class CombatEventPresenter
{
    /**
     * @return array{tick: int, type: string, payload: array<string, mixed>}
     */
    public static function toArray(CombatEvent $event): array
    {
        return [
            'tick' => $event->tick,
            'type' => $event->type->value,
            'payload' => $event->payload,
        ];
    }
}
