<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;

final class Schema
{
    /**
     * Version du schéma que ce code sait lire.
     *
     * À incrémenter dans le même commit que toute modification de structure —
     * colonne ajoutée, table créée, contrainte changée. Le chantier 2 en
     * consommera au moins deux de plus : `contentVersion` sur `runs`, puis la
     * table des enregistrements de combat.
     */
    public const int CURRENT_VERSION = 1;

    public static function initialize(PDO $pdo): void
    {
        // Lecture AVANT toute création. `CREATE TABLE IF NOT EXISTS` ne dit pas
        // s'il a créé ou trouvé, et c'est précisément cette distinction qui
        // sépare une base neuve d'une base antérieure au versionnement. Sans
        // elle, une base de développement existante serait estampillée « à
        // jour » et le contrôle ci-dessous ne se déclencherait jamais.
        $isFreshDatabase = !self::tableExists($pdo, 'runs')
            && !self::tableExists($pdo, 'schema_version');

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS runs (
                id TEXT PRIMARY KEY,
                seed INTEGER NOT NULL,
                vestige_id TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS run_actions (
                run_id TEXT NOT NULL,
                sequence INTEGER NOT NULL,
                action_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (run_id, sequence)
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS schema_version (
                version INTEGER NOT NULL
            )
        SQL);

        // Une base antérieure au versionnement reçoit la table, jamais la
        // ligne : c'est cette table vide qui la rend reconnaissable.
        if ($isFreshDatabase) {
            $statement = $pdo->prepare('INSERT INTO schema_version (version) VALUES (:version)');
            $statement->execute(['version' => self::CURRENT_VERSION]);
        }
    }

    /**
     * Refuse une base que ce code ne sait pas lire.
     *
     * Volontairement séparée d'`initialize()`, dont la sémantique de création
     * idempotente est utilisée par tous les tests via `CreatesInMemoryDatabase`.
     * Appelée par le bootstrap HTTP juste après l'initialisation.
     *
     * @throws ObsoleteSchemaException
     */
    public static function assertUpToDate(PDO $pdo): void
    {
        $statement = $pdo->prepare('SELECT version FROM schema_version LIMIT 1');
        $statement->execute();
        $version = $statement->fetchColumn();

        if ($version === false) {
            throw new ObsoleteSchemaException(sprintf(
                'Base de données antérieure au versionnement du schéma : aucune version enregistrée, '
                . 'version attendue %d. La base est jetable jusqu\'à J1 — supprimez le fichier SQLite '
                . 'et relancez.',
                self::CURRENT_VERSION,
            ));
        }

        if ((int) $version !== self::CURRENT_VERSION) {
            throw new ObsoleteSchemaException(sprintf(
                'Schéma de base de données en version %d, version attendue %d. La base est jetable '
                . 'jusqu\'à J1 — supprimez le fichier SQLite et relancez.',
                (int) $version,
                self::CURRENT_VERSION,
            ));
        }
    }

    private static function tableExists(PDO $pdo, string $name): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name",
        );
        $statement->execute(['name' => $name]);

        return $statement->fetchColumn() !== false;
    }
}
