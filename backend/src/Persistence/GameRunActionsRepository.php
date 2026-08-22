<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;

final class GameRunActionsRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function append(string $runId, int $sequence, GameRunActionType $type, array $payload): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO run_actions (run_id, sequence, action_type, payload, created_at)
             VALUES (:run_id, :sequence, :action_type, :payload, :created_at)',
        );
        $statement->execute([
            'run_id' => $runId,
            'sequence' => $sequence,
            'action_type' => $type->value,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
