<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;

final class GameRunRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function create(string $id, int $seed, string $vestigeId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO runs (id, seed, vestige_id, created_at) VALUES (:id, :seed, :vestige_id, :created_at)',
        );
        $statement->execute([
            'id' => $id,
            'seed' => $seed,
            'vestige_id' => $vestigeId,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function find(string $id): ?GameRunRecord
    {
        $statement = $this->pdo->prepare('SELECT id, seed, vestige_id FROM runs WHERE id = :id');
        $statement->execute(['id' => $id]);

        /** @var array{id: string, seed: int|string, vestige_id: string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new GameRunRecord($row['id'], (int) $row['seed'], $row['vestige_id']);
    }
}
