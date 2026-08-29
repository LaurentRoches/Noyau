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

    /**
     * @return list<GameRunActionRecord>
     */
    public function findAllForRun(string $runId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT sequence, action_type, payload FROM run_actions WHERE run_id = :run_id ORDER BY sequence ASC',
        );
        $statement->execute(['run_id' => $runId]);

        /** @var list<array{sequence: int|string, action_type: string, payload: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $row): GameRunActionRecord => new GameRunActionRecord(
                (int) $row['sequence'],
                GameRunActionType::from($row['action_type']),
                json_decode($row['payload'], true),
            ),
            $rows,
        );
    }

    public function countForRun(string $runId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM run_actions WHERE run_id = :run_id');
        $statement->execute(['run_id' => $runId]);

        return (int) $statement->fetchColumn();
    }
}
