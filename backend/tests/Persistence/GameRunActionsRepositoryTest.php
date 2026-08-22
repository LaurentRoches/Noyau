<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

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
}
