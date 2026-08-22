<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Tests\Support\CreatesInMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class GameRunReplayerTest extends TestCase
{
    use CreatesInMemoryDatabase;

    public function testItReplaysARunFromItsActionLog(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);

        $runRepository->create('run-123', 42, 'shadow_vestige');
        $actionsRepository->append('run-123', 1, GameRunActionType::OPEN_SHOP, []);
        $actionsRepository->append('run-123', 2, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        $configPath = dirname(__DIR__, 2) . '/config/game';
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);

        $gameRun = $replayer->replay('run-123');

        self::assertNotNull($gameRun->getCurrentShop());
        self::assertTrue($gameRun->getCurrentShop()->getOffers()[0]->isPurchased());
    }
}
