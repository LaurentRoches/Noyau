<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\GameRunFactory;
use PHPUnit\Framework\TestCase;

final class GameRunFactoryTest extends TestCase
{
    public function testItCreatesAGameRunForAKnownVestige(): void
    {
        $factory = new GameRunFactory(dirname(__DIR__, 3) . '/config/game');

        $gameRun = $factory->create(seed: 42, vestigeId: 'shadow_vestige');

        self::assertSame(1, $gameRun->getCurrentRound());
        self::assertSame(20, $gameRun->getWallet()->getBalance());
        self::assertNotEmpty($gameRun->getRoster());
    }
}
