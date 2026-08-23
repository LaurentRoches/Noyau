<?php

declare(strict_types=1);

namespace App\Tests\Http\Controller;

use App\Http\Controller\RunController;
use App\Http\Request;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Persistence\RunNotFoundException;
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

    public function testItShowsAnExistingRun(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 3) . '/config/game';
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);
        $controller = new RunController($runRepository, $actionsRepository, $replayer);

        $createResponse = $controller->create([]);
        $runId = $createResponse->body['run_id'];

        $response = $controller->show(['runId' => $runId]);

        self::assertSame(200, $response->statusCode);
        self::assertSame($createResponse->body['state'], $response->body['state']);
    }

    public function testItThrowsForAnUnknownRunOnShow(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 3) . '/config/game';
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);
        $controller = new RunController($runRepository, $actionsRepository, $replayer);

        $this->expectException(RunNotFoundException::class);

        $controller->show(['runId' => 'does-not-exist']);
    }

    public function testItBuysAnItemFromTheShop(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 3) . '/config/game';
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);
        $controller = new RunController($runRepository, $actionsRepository, $replayer);

        $createResponse = $controller->create([]);
        $runId = $createResponse->body['run_id'];

        $request = Request::fake(rawBody: json_encode(['slotIndex' => 0]));
        $response = $controller->buyItem(['runId' => $runId], $request);

        self::assertSame(200, $response->statusCode);
        self::assertTrue($response->body['state']['shop']['offers'][0]['purchased']);

        $actions = $actionsRepository->findAllForRun($runId);
        self::assertCount(2, $actions);
        self::assertSame(GameRunActionType::PURCHASE, $actions[1]->type);
    }

    public function testItDoesNotPersistAFailedPurchase(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 3) . '/config/game';
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);
        $controller = new RunController($runRepository, $actionsRepository, $replayer);

        $createResponse = $controller->create([]);
        $runId = $createResponse->body['run_id'];

        $request = Request::fake(rawBody: json_encode(['slotIndex' => 99]));

        try {
            $controller->buyItem(['runId' => $runId], $request);
            self::fail('Expected an exception for an invalid slot index.');
        } catch (\InvalidArgumentException) {
            // attendu — Shop::purchase() rejette un index hors bornes
        }

        // Le journal ne doit contenir QUE l'OPEN_SHOP initial — l'achat raté
        // n'a rien laissé derrière lui, sinon tout futur replay() serait cassé.
        $actions = $actionsRepository->findAllForRun($runId);
        self::assertCount(1, $actions);
    }
}
