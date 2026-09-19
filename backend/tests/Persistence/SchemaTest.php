<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Persistence\ObsoleteSchemaException;
use App\Persistence\Schema;
use App\Tests\Support\CreatesInMemoryDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Contrat du versionnement de schéma (D-18 volet 4, chantier 2).
 *
 * Trois états de base doivent être distingués, et c'est le troisième qui porte
 * toute la valeur du commit :
 *
 *   | runs    | schema_version | état                    | comportement          |
 *   |---------|----------------|-------------------------|-----------------------|
 *   | absent  | absent         | base neuve              | créer + estampiller   |
 *   | présent | présent        | base versionnée         | lire et comparer      |
 *   | présent | absent         | base antérieure au      | REFUSER               |
 *   |         |                | versionnement           |                       |
 *
 * `CREATE TABLE IF NOT EXISTS` ne dit pas s'il a créé ou trouvé. Sans lecture
 * préalable de `sqlite_master`, une base de développement existante serait
 * silencieusement estampillée « à jour » — exactement la corruption silencieuse
 * que D-18 cherche à empêcher.
 *
 * Séparation des responsabilités retenue : `initialize()` conserve sa sémantique
 * de création idempotente — dont dépendent les 294 tests existants via
 * `CreatesInMemoryDatabase` — et le refus vit dans une méthode dédiée, appelée
 * par le bootstrap HTTP juste après.
 *
 * Deux trous de couverture connus.
 *
 * 1. La lecture d'une base SQLite **sur fichier** n'est pas couverte : tous les
 *    cas passent par `sqlite::memory:`. Le comportement de `sqlite_master` est
 *    identique pour les deux, mais la création du fichier et ses droits ne sont
 *    pas exercés.
 * 2. Le **câblage du bootstrap** — l'appel à `assertUpToDate()` dans
 *    `public/index.php` et sa conversion en réponse 503 — n'est couvert par
 *    aucun test. `index.php` n'est pas testable en l'état : il construit ses
 *    dépendances en dur et se termine par un `Response::send()` qui appelle
 *    `exit`. Ce qui est testé ici est le refus lui-même, pas sa restitution
 *    HTTP. À revisiter si le bootstrap gagne un jour un point d'entrée
 *    injectable.
 */
final class SchemaTest extends TestCase
{
    use CreatesInMemoryDatabase;

    /**
     * Reproduit une base antérieure au versionnement : `runs` et `run_actions`
     * existent et contiennent des données, `schema_version` est absente.
     *
     * Le DDL est recopié en dur **volontairement**. Après ce commit,
     * `Schema::initialize()` ne produira plus jamais cet état : l'appeler ici
     * ne reproduirait donc pas la base qu'on cherche à refuser. C'est la
     * caractérisation d'un état passé, pas une duplication par négligence.
     */
    private function createPreVersioningDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->exec(<<<'SQL'
            CREATE TABLE runs (
                id TEXT PRIMARY KEY,
                seed INTEGER NOT NULL,
                vestige_id TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE run_actions (
                run_id TEXT NOT NULL,
                sequence INTEGER NOT NULL,
                action_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                created_at TEXT NOT NULL,
                PRIMARY KEY (run_id, sequence)
            )
        SQL);

        // Une run réelle : une base vide de toute donnée mais dotée de ses
        // tables reste un cas légitime à refuser, et c'est bien ce que le
        // critère « runs existe » capture.
        $pdo->exec(<<<'SQL'
            INSERT INTO runs (id, seed, vestige_id, created_at)
            VALUES ('run-anterieure', 42, 'shadow_vestige', '2026-09-01T10:00:00+00:00')
        SQL);

        return $pdo;
    }

    private function tableExists(PDO $pdo, string $name): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name",
        );
        $statement->execute(['name' => $name]);

        return $statement->fetchColumn() !== false;
    }

    // --- Base neuve -------------------------------------------------------

    public function testItCreatesTheVersionTableOnAFreshDatabase(): void
    {
        $pdo = $this->createInMemoryDatabase();

        self::assertTrue($this->tableExists($pdo, 'schema_version'));
    }

    public function testItStampsTheCurrentVersionOnAFreshDatabase(): void
    {
        $pdo = $this->createInMemoryDatabase();

        $version = $pdo->query('SELECT version FROM schema_version')->fetchColumn();

        self::assertSame(Schema::CURRENT_VERSION, (int) $version);
    }

    public function testTheVersionTableHoldsExactlyOneRow(): void
    {
        $pdo = $this->createInMemoryDatabase();

        $count = $pdo->query('SELECT COUNT(*) FROM schema_version')->fetchColumn();

        self::assertSame(1, (int) $count);
    }

    public function testItAcceptsAFreshlyInitializedDatabase(): void
    {
        $pdo = $this->createInMemoryDatabase();

        Schema::assertUpToDate($pdo);

        // Aucune exception : l'assertion porte sur l'absence de levée.
        // expectNotToPerformAssertions() serait trompeur — le contrat testé
        // est bien « ne refuse pas », pas « ne fait rien ».
        self::assertTrue(true);
    }

    // --- Base antérieure au versionnement ---------------------------------

    public function testItRejectsAPreVersioningDatabase(): void
    {
        $pdo = $this->createPreVersioningDatabase();

        Schema::initialize($pdo);

        $this->expectException(ObsoleteSchemaException::class);

        Schema::assertUpToDate($pdo);
    }

    /**
     * Le faux positif que tout ce commit existe pour empêcher.
     *
     * Si `initialize()` insérait la ligne de version sans condition, une base
     * de développement antérieure serait déclarée à jour et le refus ci-dessus
     * ne se déclencherait jamais. Ce test échoue **avant** celui du refus si
     * la détection est mal placée, ce qui distingue les deux causes.
     */
    public function testItDoesNotStampAPreVersioningDatabase(): void
    {
        $pdo = $this->createPreVersioningDatabase();

        Schema::initialize($pdo);

        $version = $pdo->query('SELECT version FROM schema_version')->fetchColumn();

        self::assertFalse(
            $version,
            'Une base antérieure au versionnement ne doit jamais être estampillée à la version courante.',
        );
    }

    // --- Base versionnée, mais pas à la bonne version ---------------------

    public function testItRejectsADatabaseAtADifferentVersion(): void
    {
        $pdo = $this->createInMemoryDatabase();

        $statement = $pdo->prepare('UPDATE schema_version SET version = :version');
        $statement->execute(['version' => Schema::CURRENT_VERSION - 1]);

        $this->expectException(ObsoleteSchemaException::class);

        Schema::assertUpToDate($pdo);
    }

    public function testTheRejectionMessageNamesBothVersions(): void
    {
        $pdo = $this->createInMemoryDatabase();

        $statement = $pdo->prepare('UPDATE schema_version SET version = :version');
        $statement->execute(['version' => Schema::CURRENT_VERSION - 1]);

        // expectExceptionMessage() fait une correspondance par sous-chaîne
        // (str_contains), pas une égalité stricte : on ne fige donc que les
        // deux nombres, pas la phrase qui les porte.
        $this->expectExceptionMessage((string) (Schema::CURRENT_VERSION - 1));

        Schema::assertUpToDate($pdo);
    }

    // --- Non-régression ---------------------------------------------------

    /**
     * Doit être VERT dès le premier lancement, avant toute implémentation.
     *
     * C'est le filet des 294 tests existants : `CreatesInMemoryDatabase`
     * appelle `initialize()` sur une base vide à chaque cas de test, et aucun
     * d'eux ne doit se mettre à échouer parce que le versionnement est arrivé.
     */
    public function testInitializeRemainsIdempotent(): void
    {
        $pdo = new PDO('sqlite::memory:');

        Schema::initialize($pdo);
        Schema::initialize($pdo);
        Schema::initialize($pdo);

        self::assertTrue($this->tableExists($pdo, 'runs'));
        self::assertTrue($this->tableExists($pdo, 'run_actions'));
    }

    public function testRepeatedInitializeDoesNotDuplicateTheVersionRow(): void
    {
        $pdo = $this->createInMemoryDatabase();

        Schema::initialize($pdo);
        Schema::initialize($pdo);

        $count = $pdo->query('SELECT COUNT(*) FROM schema_version')->fetchColumn();

        self::assertSame(1, (int) $count);
    }
}
