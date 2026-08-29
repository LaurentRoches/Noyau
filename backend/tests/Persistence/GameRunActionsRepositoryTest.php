<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Persistence\GameRunActionRecord;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Tests\Support\CreatesInMemoryDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class GameRunActionsRepositoryTest extends TestCase
{
    use CreatesInMemoryDatabase;

    public function testItAppendsAnActionToTheLog(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $repository = new GameRunActionsRepository($pdo);

        $repository->append('run-123', 1, GameRunActionType::PURCHASE, ['slotIndex' => 2]);

        $statement = $pdo->prepare(
            'SELECT run_id, sequence, action_type, payload FROM run_actions WHERE run_id = :run_id',
        );
        $statement->execute(['run_id' => 'run-123']);
        /** @var array{run_id: string, sequence: int|string, action_type: string, payload: string} $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertSame('run-123', $row['run_id']);
        self::assertSame(1, (int) $row['sequence']);
        self::assertSame('PURCHASE', $row['action_type']);
        self::assertSame(['slotIndex' => 2], json_decode($row['payload'], true));
    }

    public function testItFindsAllActionsForARunInSequenceOrder(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $repository = new GameRunActionsRepository($pdo);

        $repository->append('run-123', 1, GameRunActionType::OPEN_SHOP, []);
        $repository->append('run-123', 2, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        $records = $repository->findAllForRun('run-123');

        self::assertEquals([
            new GameRunActionRecord(1, GameRunActionType::OPEN_SHOP, []),
            new GameRunActionRecord(2, GameRunActionType::PURCHASE, ['slotIndex' => 0]),
        ], $records);
    }

    public function testItOrdersActionsBySequenceRegardlessOfInsertionOrder(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $repository = new GameRunActionsRepository($pdo);

        $repository->append('run-123', 2, GameRunActionType::PURCHASE, ['slotIndex' => 0]);
        $repository->append('run-123', 1, GameRunActionType::OPEN_SHOP, []);

        $records = $repository->findAllForRun('run-123');

        self::assertSame(1, $records[0]->sequence);
        self::assertSame(2, $records[1]->sequence);
    }

    public function testItCountsActionsForARun(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $repository = new GameRunActionsRepository($pdo);

        self::assertSame(0, $repository->countForRun('run-123'));

        $repository->append('run-123', 1, GameRunActionType::OPEN_SHOP, []);
        $repository->append('run-123', 2, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        self::assertSame(2, $repository->countForRun('run-123'));
    }
}
