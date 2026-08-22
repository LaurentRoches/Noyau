<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Application\GameRun;

final class GameRunActionApplier
{
    /**
     * @param array<string, mixed> $payload
     */
    public function apply(GameRun $gameRun, GameRunActionType $type, array $payload): mixed
    {
        return match ($type) {
            GameRunActionType::OPEN_SHOP => $gameRun->openShop(),
            default => throw new \LogicException(sprintf(
                'Action type "%s" is not yet supported.',
                $type->value,
            )),
        };
    }
}
