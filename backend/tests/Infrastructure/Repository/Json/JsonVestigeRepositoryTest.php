<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Repository\Json;

use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;

final class JsonVestigeRepositoryTest extends TestCase
{
    public function testFindReturnsVestigeWithStartingGold(): void
    {
        $repository = new JsonVestigeRepository(__DIR__ . '/../../../../config/game/vestiges.json');

        $vestige = $repository->find('shadow_vestige');

        self::assertSame(20, $vestige->startingGold);
    }
}
