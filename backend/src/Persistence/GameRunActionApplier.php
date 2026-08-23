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
            GameRunActionType::PURCHASE => $gameRun->purchaseItem($this->extractInt($payload, 'slotIndex')),
            GameRunActionType::SWAP => $this->applySwap($gameRun, $payload),
            default => throw new \LogicException(sprintf(
                'Action type "%s" is not yet supported.',
                $type->value,
            )),
            GameRunActionType::RESOLVE_ROUND => $gameRun->playRound(),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applySwap(GameRun $gameRun, array $payload): null
    {
        $gameRun->swapWithStash(
            $this->extractInt($payload, 'inventoryIndex'),
            $this->extractInt($payload, 'stashIndex'),
            $this->extractString($payload, 'heroId'),
        );

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractInt(array $payload, string $key): int
    {
        if (!isset($payload[$key]) || !is_int($payload[$key])) {
            throw new \InvalidArgumentException(sprintf(
                'Action requires an integer "%s" payload key.',
                $key,
            ));
        }

        return $payload[$key];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractString(array $payload, string $key): string
    {
        if (!isset($payload[$key]) || !is_string($payload[$key])) {
            throw new \InvalidArgumentException(sprintf(
                'Action requires a string "%s" payload key.',
                $key,
            ));
        }

        return $payload[$key];
    }
}
