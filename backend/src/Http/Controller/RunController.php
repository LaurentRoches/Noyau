<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\ApiResponse;
use App\Http\Request;
use App\Persistence\GameRunActionApplier;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Presentation\CombatEventPresenter;
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

    /**
     * @param array<string, string> $params
     */
    public function buyItem(array $params, Request $request): ApiResponse
    {
        $runId = $params['runId'];
        $payload = $request->json() ?? [];

        $gameRun = $this->replayer->replay($runId);

        (new GameRunActionApplier())->apply($gameRun, GameRunActionType::PURCHASE, $payload);

        $sequence = $this->actionsRepository->countForRun($runId) + 1;
        $this->actionsRepository->append($runId, $sequence, GameRunActionType::PURCHASE, $payload);

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function swapItem(array $params, Request $request): ApiResponse
    {
        $runId = $params['runId'];
        $payload = $request->json() ?? [];

        $gameRun = $this->replayer->replay($runId);

        (new GameRunActionApplier())->apply($gameRun, GameRunActionType::SWAP, $payload);

        $sequence = $this->actionsRepository->countForRun($runId) + 1;
        $this->actionsRepository->append($runId, $sequence, GameRunActionType::SWAP, $payload);

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function resolveRound(array $params, Request $request): ApiResponse
    {
        $runId = $params['runId'];

        $gameRun = $this->replayer->replay($runId);

        (new GameRunActionApplier())->apply($gameRun, GameRunActionType::RESOLVE_ROUND, []);

        $sequence = $this->actionsRepository->countForRun($runId) + 1;
        $this->actionsRepository->append($runId, $sequence, GameRunActionType::RESOLVE_ROUND, []);

        $combatResult = $gameRun->getLastCombatResult();

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
            'combatLog' => $combatResult !== null
                ? array_map(
                    static fn (\App\Domain\Event\CombatEvent $event): array => CombatEventPresenter::toArray($event),
                    $combatResult->log->getEvents(),
                )
                : [],
        ]);
    }
}
