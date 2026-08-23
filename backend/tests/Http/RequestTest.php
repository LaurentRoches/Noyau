<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testItExposesMethodAndUri(): void
    {
        $request = Request::fake(method: 'POST', uri: '/runs/run-123/shop/buy');

        self::assertSame('POST', $request->method());
        self::assertSame('/runs/run-123/shop/buy', $request->uri());
    }

    public function testItDecodesAJsonBody(): void
    {
        $request = Request::fake(rawBody: '{"slotIndex": 2}');

        self::assertSame(['slotIndex' => 2], $request->json());
    }

    public function testItReturnsNullForAnEmptyBody(): void
    {
        $request = Request::fake();

        self::assertNull($request->json());
    }
}
