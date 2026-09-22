<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Persistence\GameRunRecord;
use App\Persistence\GameRunRepository;
use App\Tests\Support\CreatesInMemoryDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class GameRunRepositoryTest extends TestCase
{
    use CreatesInMemoryDatabase;

    /**
     * Une empreinte de forme réaliste — 64 caractères hexadécimaux — mais
     * arbitraire : le repository ne la calcule pas et n'a pas à savoir d'où
     * elle vient. C'est `ContentCatalogReader` qui la produit, et lui seul est
     * testé pour ça.
     */
    private const string CONTENT_VERSION = '530ec56495ca87e701ef8ebef34ff3e41b6a6b7785ecd195b9fec1f069562e89';

    private PDO $pdo;

    private GameRunRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = $this->createInMemoryDatabase();
        $this->repository = new GameRunRepository($this->pdo);
    }

    public function testItCreatesAndFindsARun(): void
    {
        $this->repository->create('run-123', 42, 'shadow_vestige', self::CONTENT_VERSION);

        $record = $this->repository->find('run-123');

        self::assertEquals(
            new GameRunRecord('run-123', 42, 'shadow_vestige', self::CONTENT_VERSION),
            $record,
        );
    }

    /**
     * L'empreinte est écrite sur la ligne, pas seulement portée par l'objet.
     *
     * L'assertion passe par un `SELECT` direct plutôt que par `find()` : tant
     * que la colonne n'existe pas, SQLite lève, ce qui donne un rouge sans
     * ambiguïté. Le tour par `find()` de l'assertion précédente ne le donnerait
     * pas — PHP ignore silencieusement un argument surnuméraire, donc le
     * quatrième argument passé à un constructeur qui n'en prend que trois se
     * perd sans bruit, des deux côtés de la comparaison.
     */
    public function testItStoresTheContentVersionOnTheRow(): void
    {
        $this->repository->create('run-123', 42, 'shadow_vestige', self::CONTENT_VERSION);

        $statement = $this->pdo->prepare('SELECT content_version FROM runs WHERE id = :id');
        $statement->execute(['id' => 'run-123']);

        self::assertSame(self::CONTENT_VERSION, $statement->fetchColumn());
    }

    public function testItReturnsNullForAnUnknownRun(): void
    {
        self::assertNull($this->repository->find('does-not-exist'));
    }
}
