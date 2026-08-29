<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ApiResponse;
use PHPUnit\Framework\TestCase;

final class ApiResponseTest extends TestCase
{
    public function testItBuildsAJsonResponseWithDefaultStatus(): void
    {
        $response = ApiResponse::json(['round' => 1]);

        self::assertSame(200, $response->statusCode);
        self::assertSame(['round' => 1], $response->body);
    }

    public function testItBuildsAnErrorResponse(): void
    {
        $response = ApiResponse::error('Invalid slot index', 400);

        self::assertSame(400, $response->statusCode);
        self::assertSame(['error' => 'Invalid slot index'], $response->body);
    }
}
