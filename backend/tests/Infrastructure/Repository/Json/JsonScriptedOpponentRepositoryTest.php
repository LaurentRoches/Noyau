<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Repository\Json;

use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use PHPUnit\Framework\TestCase;

final class JsonScriptedOpponentRepositoryTest extends TestCase
{
    public function testFindAllReturnsItemIdsGroupedByHeroInFileOrder(): void
    {
        $filePath = __DIR__ . '/../../../Fixtures/scripted_opponent.json';
        $repository = new JsonScriptedOpponentRepository($filePath);

        $result = $repository->findAll();

        self::assertSame(
            [
                'shadow_bearer' => ['rusty_dagger', 'rusty_dagger'],
                'shadow_venomancer' => ['heavy_greatsword'],
            ],
            $result
        );
    }

    public function testFindAllThrowsExceptionWhenFileNotFound(): void
    {
        $repository = new JsonScriptedOpponentRepository('invalid/path/scripted_opponent.json');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $repository->findAll();
    }
}
