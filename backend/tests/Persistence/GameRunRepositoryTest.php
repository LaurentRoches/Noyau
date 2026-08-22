<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Persistence\GameRunRecord;
use App\Persistence\GameRunRepository;
use App\Persistence\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

final class GameRunRepositoryTest extends TestCase
{
    private GameRunRepository $repository;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        Schema::initialize($pdo);
        $this->repository = new GameRunRepository($pdo);
    }

    public function testItCreatesAndFindsARun(): void
    {
        $this->repository->create('run-123', 42, 'shadow_vestige');

        $record = $this->repository->find('run-123');

        self::assertEquals(new GameRunRecord('run-123', 42, 'shadow_vestige'), $record);
    }

    public function testItReturnsNullForAnUnknownRun(): void
    {
        self::assertNull($this->repository->find('does-not-exist'));
    }
}
