<?php

declare(strict_types=1);

namespace App\Persistence;

final readonly class GameRunActionRecord
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $sequence,
        public GameRunActionType $type,
        public array $payload,
    ) {
    }
}
