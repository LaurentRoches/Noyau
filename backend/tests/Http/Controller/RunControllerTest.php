<?php

declare(strict_types=1);

namespace App\Tests\Http\Controller;

use App\Http\Controller\RunController;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Tests\Support\CreatesInMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class RunControllerTest extends TestCase
{
    use CreatesInMemoryDatabase;

    public function testItCreatesARunWithAnOpenShop(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 3) . '/config/game';
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);
        $controller = new RunController($runRepository, $actionsRepository, $replayer);

        $response = $controller->create([]);

        self::assertSame(201, $response->statusCode);
        self::assertIsString($response->body['run_id']);
        self::assertNotSame('', $response->body['run_id']);

        $state = $response->body['state'];
        self::assertSame(1, $state['round']);
        self::assertSame(20, $state['wallet']['balance']);
        self::assertNotNull($state['shop']);
        self::assertCount(4, $state['shop']['offers']);

        // Effets de bord réellement persistés, pas juste ce qui est retourné
        $record = $runRepository->find($response->body['run_id']);
        self::assertNotNull($record);
        self::assertSame('shadow_vestige', $record->vestigeId);

        $actions = $actionsRepository->findAllForRun($response->body['run_id']);
        self::assertCount(1, $actions);
        self::assertSame(GameRunActionType::OPEN_SHOP, $actions[0]->type);
    }
}
