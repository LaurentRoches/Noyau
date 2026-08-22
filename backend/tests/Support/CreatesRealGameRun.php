<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Factory\GameRunFactory;
use App\Application\GameRun;

trait CreatesRealGameRun
{
    private function createRealGameRun(int $seed, string $vestigeId = 'shadow_vestige'): GameRun
    {
        $configPath = dirname(__DIR__, 2) . '/config/game';

        return (new GameRunFactory($configPath))->create($seed, $vestigeId);
    }
}
