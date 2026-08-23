<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ApiResponse;
use App\Http\Request;
use App\Http\Router;
use App\Persistence\RunNotFoundException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testItDispatchesToTheMatchingHandler(): void
    {
        $router = new Router();
        $router->get('/runs/{runId}', function (array $params): ApiResponse {
            return ApiResponse::json(['runId' => $params['runId']]);
        });

        $response = $router->dispatch(Request::fake(method: 'GET', uri: '/runs/run-123'));

        self::assertSame(200, $response->statusCode);
        self::assertSame(['runId' => 'run-123'], $response->body);
    }

    public function testItReturns404ForAnUnmatchedRoute(): void
    {
        $router = new Router();
        $router->get('/runs/{runId}', fn (array $params): ApiResponse => ApiResponse::json([]));

        $response = $router->dispatch(Request::fake(method: 'GET', uri: '/unknown'));

        self::assertSame(404, $response->statusCode);
    }

    public function testItMapsRunNotFoundExceptionTo404(): void
    {
        $router = new Router();
        $router->get('/x', function (array $params): ApiResponse {
            throw new RunNotFoundException('missing-id');
        });

        $response = $router->dispatch(Request::fake(method: 'GET', uri: '/x'));

        self::assertSame(404, $response->statusCode);
    }

    public function testItMapsInvalidArgumentExceptionTo400(): void
    {
        $router = new Router();
        $router->get('/x', function (array $params): ApiResponse {
            throw new \InvalidArgumentException('bad input');
        });

        $response = $router->dispatch(Request::fake(method: 'GET', uri: '/x'));

        self::assertSame(400, $response->statusCode);
    }

    public function testItMapsLogicExceptionTo409(): void
    {
        $router = new Router();
        $router->get('/x', function (array $params): ApiResponse {
            throw new \LogicException('conflict');
        });

        $response = $router->dispatch(Request::fake(method: 'GET', uri: '/x'));

        self::assertSame(409, $response->statusCode);
    }
}
