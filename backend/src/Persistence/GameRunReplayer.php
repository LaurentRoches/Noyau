<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Application\Factory\GameRunFactory;
use App\Application\GameRun;

final class GameRunReplayer
{
    public function __construct(
        private readonly GameRunRepository $runRepository,
        private readonly GameRunActionsRepository $actionsRepository,
        private readonly string $configPath,
    ) {
    }

    public function replay(string $runId): GameRun
    {
        $record = $this->runRepository->find($runId)
            ?? throw new \InvalidArgumentException(sprintf('No run found for id "%s".', $runId));

        $gameRun = (new GameRunFactory($this->configPath))->create($record->seed, $record->vestigeId);

        $applier = new GameRunActionApplier();
        foreach ($this->actionsRepository->findAllForRun($runId) as $action) {
            $applier->apply($gameRun, $action->type, $action->payload);
        }

        return $gameRun;
    }
}
