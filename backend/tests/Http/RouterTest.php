<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ApiResponse;
use App\Http\Request;
use App\Http\Router;
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
}
