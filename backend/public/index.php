<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Http\ApiResponse;
use App\Http\Controller\RunController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Persistence\ObsoleteSchemaException;
use App\Persistence\Schema;
use PDO;

$databasePath = dirname(__DIR__) . '/database/database.sqlite';
$configPath = dirname(__DIR__) . '/config/game';

$pdo = new PDO('sqlite:' . $databasePath);
Schema::initialize($pdo);

// 503 et non 409 : le service ne refuse pas *cette requête*, il refuse de
// servir. Le contrôle vit hors du Router, qui n'enveloppe que l'appel du
// handler — une exception levée ici ne serait jamais mappée par lui.
// ObsoleteSchemaException étend RuntimeException pour cette raison : même
// levée plus tard, elle ne serait pas transformée en 409.
try {
    Schema::assertUpToDate($pdo);
} catch (ObsoleteSchemaException $e) {
    Response::send(ApiResponse::error($e->getMessage(), 503));
}

$runRepository = new GameRunRepository($pdo);
$actionsRepository = new GameRunActionsRepository($pdo);
$replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);
$controller = new RunController($runRepository, $actionsRepository, $replayer);

$router = new Router();
$router->post('/runs', fn (array $params, Request $request): ApiResponse => $controller->create($params));
$router->get('/runs/{runId}', fn (array $params, Request $request): ApiResponse => $controller->show($params));
$router->post('/runs/{runId}/hero/choose', fn (array $params, Request $request): ApiResponse => $controller->chooseHero($params, $request));
$router->post('/runs/{runId}/shop/buy', fn (array $params, Request $request): ApiResponse => $controller->buyItem($params, $request));
$router->post('/runs/{runId}/inventory/swap', fn (array $params, Request $request): ApiResponse => $controller->swapItem($params, $request));
$router->post('/runs/{runId}/round/resolve', fn (array $params, Request $request): ApiResponse => $controller->resolveRound($params, $request));

Response::send($router->dispatch(Request::fromGlobals()));