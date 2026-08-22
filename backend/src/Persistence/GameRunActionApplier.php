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
            GameRunActionType::PURCHASE => $gameRun->purchaseItem($this->extractSlotIndex($payload)),
            default => throw new \LogicException(sprintf(
                'Action type "%s" is not yet supported.',
                $type->value,
            )),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractSlotIndex(array $payload): int
    {
        if (!isset($payload['slotIndex']) || !is_int($payload['slotIndex'])) {
            throw new \InvalidArgumentException('PURCHASE action requires an integer "slotIndex" payload key.');
        }

        return $payload['slotIndex'];
    }
}
