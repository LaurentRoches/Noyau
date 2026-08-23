<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\ApiResponse;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Presentation\RunStatePresenter;

final class RunController
{
    private const string VESTIGE_ID = 'shadow_vestige';

    public function __construct(
        private readonly GameRunRepository $runRepository,
        private readonly GameRunActionsRepository $actionsRepository,
        private readonly GameRunReplayer $replayer,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function create(array $params): ApiResponse
    {
        $runId = bin2hex(random_bytes(16));
        $seed = random_int(0, PHP_INT_MAX);

        $this->runRepository->create($runId, $seed, self::VESTIGE_ID);
        $this->actionsRepository->append($runId, 1, GameRunActionType::OPEN_SHOP, []);

        $gameRun = $this->replayer->replay($runId);

        return ApiResponse::json([
            'run_id' => $runId,
            'state' => RunStatePresenter::toArray($gameRun),
        ], 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): ApiResponse
    {
        $gameRun = $this->replayer->replay($params['runId']);

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
        ]);
    }
}
