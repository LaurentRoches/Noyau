<?php

declare(strict_types=1);

namespace App\Persistence;

final readonly class GameRunRecord
{
    public function __construct(
        public string $id,
        public int $seed,
        public string $vestigeId,
    ) {
    }
}
