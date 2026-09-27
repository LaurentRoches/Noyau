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
     * colonne ajoutée, table créée, contrainte changée.
     *
     * **Version 2, commit 12 :** colonne `content_version` sur `runs`.
     * **Version 3, commit 13c :** table `combat_records`.
     *
     * Les deux ne se traitent pas de la même façon sur une base ancienne, et
     * c'est une propriété de SQLite, pas un choix : `CREATE TABLE IF NOT
     * EXISTS` **crée** une table absente, alors qu'il ne peut pas ajouter une
     * colonne absente. Une base en version 2 repart donc avec la table — et
     * reste refusée sur son numéro, donc personne n'y écrira jamais.
     */
    public const int CURRENT_VERSION = 3;

    public static function initialize(PDO $pdo): void
    {
        // Lecture AVANT toute création. `CREATE TABLE IF NOT EXISTS` ne dit pas
        // s'il a créé ou trouvé, et c'est précisément cette distinction qui
        // sépare une base neuve d'une base antérieure au versionnement. Sans
        // elle, une base de développement existante serait estampillée « à
        // jour » et le contrôle ci-dessous ne se déclencherait jamais.
        $isFreshDatabase = !self::tableExists($pdo, 'runs')
            && !self::tableExists($pdo, 'schema_version');

        // `content_version` est NOT NULL sans valeur par défaut, et aucun
        // ALTER TABLE ne vient l'ajouter aux bases existantes — les deux
        // décisions tiennent ensemble. Une colonne nullable, ou remplie après
        // coup par une migration, donnerait des runs dont l'empreinte est
        // inventée : elles passeraient le contrôle du rejeu sans que personne
        // ne sache sous quel catalogue elles ont réellement commencé. Une base
        // en version 1 est donc refusée par assertUpToDate(), pas rattrapée
        // (D-18, jetable avant J1).
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS runs (
                id TEXT PRIMARY KEY,
                seed INTEGER NOT NULL,
                vestige_id TEXT NOT NULL,
                content_version TEXT NOT NULL,
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

        // Les deux plateaux sont stockés tels que `CanonicalJson` les écrit,
        // dans deux colonnes TEXT. Les éclater en colonnes SQL rendrait le
        // format réinventable à la relecture et lierait le schéma aux
        // évolutions futures d'un snapshot — or c'est exactement ce format-là
        // que le moteur embarqué devra reproduire à l'octet près (NF-01).
        //
        // `engine_version` est redondante avec les deux enveloppes, qui la
        // portent déjà : elle est ici pour que le chantier 11 puisse filtrer un
        // bassin d'appariement sans décoder cinq mille JSON.
        //
        // La clé composite transforme une double écriture en erreur franche
        // plutôt qu'en doublon silencieux. Ce n'est pas théorique : `04` §7
        // relève qu'aucune garde anti-double-soumission n'existe sur
        // `resolveRound`.
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS combat_records (
                run_id TEXT NOT NULL,
                round INTEGER NOT NULL,
                board_a TEXT NOT NULL,
                board_b TEXT NOT NULL,
                combat_seed TEXT NOT NULL,
                engine_version INTEGER NOT NULL,
                resolution TEXT NOT NULL,
                winner_side TEXT NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (run_id, round)
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
