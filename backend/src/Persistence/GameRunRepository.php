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

    /**
     * @param string $contentVersion empreinte des catalogues au moment de la création
     *                               (`04` §6.3). Le repository ne la calcule pas et ne
     *                               la vérifie pas : il l'écrit telle qu'on la lui
     *                               donne. La produire est le travail de
     *                               `ContentCatalogReader`, la comparer celui du rejeu.
     */
    public function create(string $id, int $seed, string $vestigeId, string $contentVersion): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO runs (id, seed, vestige_id, content_version, created_at) VALUES (:id, :seed, :vestige_id, :content_version, :created_at)',
        );
        $statement->execute([
            'id' => $id,
            'seed' => $seed,
            'vestige_id' => $vestigeId,
            'content_version' => $contentVersion,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function find(string $id): ?GameRunRecord
    {
        $statement = $this->pdo->prepare('SELECT id, seed, vestige_id, content_version FROM runs WHERE id = :id');
        $statement->execute(['id' => $id]);

        /** @var array{id: string, seed: int|string, vestige_id: string, content_version: string}|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new GameRunRecord($row['id'], (int) $row['seed'], $row['vestige_id'], $row['content_version']);
    }
}
