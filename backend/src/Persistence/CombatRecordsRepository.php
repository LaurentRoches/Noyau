<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Domain\Snapshot\CombatRecord;
use PDO;

/**
 * L'écriture des archives de combat (`04` §6, D-16).
 *
 * **Écriture seule, tant que personne ne lit.** Le corpus PvP est un
 * chantier 11 et le rejeu octet pour octet est le commit suivant. Une méthode
 * de lecture écrite aujourd'hui serait une forme de retour figée avant qu'on
 * sache ce qu'on en attend — et une méthode publique sans appelant est une
 * méthode qu'il faut quand même tester.
 *
 * **Rien n'est transformé au passage.** Les deux enveloppes sont stockées
 * telles que `CanonicalJson` les écrit. Ce dépôt ne les décode pas, ne les
 * reformate pas, ne les valide pas : le format **est** le sujet du chantier,
 * et c'est lui que le moteur embarqué devra reproduire à l'octet près (NF-01).
 * Une normalisation à l'écriture le casserait sans qu'aucune exception ne soit
 * levée.
 *
 * **Seul celui qui a simulé écrit ici.** `GameRunReplayer` rejoue des issues
 * enregistrées et ne produit aucun combat ; `GameRun::applyRecordedRound()` ne
 * construit aucune archive. Sans cette asymétrie, un simple `GET /runs/{id}`
 * remplacerait les enregistrements d'une run par une reconstitution faite avec
 * le moteur courant.
 */
final class CombatRecordsRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @throws \PDOException si la manche est déjà archivée — la clé composite
     *                       `(run_id, round)` fait de la double écriture une
     *                       erreur franche plutôt qu'un doublon silencieux
     */
    public function save(string $runId, int $round, CombatRecord $record): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO combat_records (run_id, round, board_a, board_b, combat_seed, engine_version, resolution, winner_side, created_at) VALUES (:run_id, :round, :board_a, :board_b, :combat_seed, :engine_version, :resolution, :winner_side, :created_at)',
        );
        $statement->execute([
            'run_id' => $runId,
            'round' => $round,
            'board_a' => $record->boardA->toCanonicalJson(),
            'board_b' => $record->boardB->toCanonicalJson(),
            'combat_seed' => $record->combatSeed,
            'engine_version' => $record->engineVersion,
            'resolution' => $record->resolution->value,
            'winner_side' => $record->winnerSide->value,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
