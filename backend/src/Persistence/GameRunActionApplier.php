<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Application\GameRun;
use App\Application\RoundOutcome;

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
            GameRunActionType::CHOOSE_HERO => $gameRun->chooseHero($this->extractString($payload, 'heroId')),
            GameRunActionType::RESOLVE_ROUND => $this->applyRecordedRound($gameRun, $payload),
        };
    }

    /**
     * Le rejeu **applique** l'issue enregistrée, il ne resimule pas
     * (D-18 volet 1, `07` E-11).
     *
     * @param array<string, mixed> $payload
     */
    private function applyRecordedRound(GameRun $gameRun, array $payload): null
    {
        $gameRun->applyRecordedRound($this->extractOutcome($payload));

        return null;
    }

    /**
     * **`LogicException` et non `InvalidArgumentException`**, contrairement aux
     * deux extracteurs ci-dessous.
     *
     * Ceux-là valident une charge utile venue du **client**, d'où le 400. La
     * charge utile de `RESOLVE_ROUND` est écrite par le serveur : son absence
     * ne dit pas que la requête est malformée, elle dit que le **journal** est
     * antérieur à l'enregistrement de l'issue. C'est un conflit d'état, que le
     * `Router` rend en 409.
     *
     * Pourquoi refuser plutôt que retomber sur `playRound()`. Le repli
     * resimulerait le combat avec le moteur courant, ce qui est exactement le
     * défaut que ce commit ferme. La base est jetable avant J1 (D-18) : le run
     * est perdu, et c'est le prix annoncé.
     *
     * @param array<string, mixed> $payload
     */
    private function extractOutcome(array $payload): RoundOutcome
    {
        $recorded = $payload['outcome'] ?? null;
        $outcome = is_string($recorded) ? RoundOutcome::tryFrom($recorded) : null;

        if ($outcome === null) {
            throw new \LogicException(sprintf(
                'Cannot replay a RESOLVE_ROUND action without a recorded outcome. Expected an '
                . '"outcome" payload key, one of [%s], got %s. A run journalled before the '
                . 'outcome was recorded cannot be replayed: doing so would resimulate its past '
                . 'combats with the current engine, and a round that was won could come back '
                . 'lost (D-18).',
                implode(', ', array_map(
                    static fn (RoundOutcome $case): string => $case->value,
                    RoundOutcome::cases(),
                )),
                $recorded === null ? 'nothing' : var_export($recorded, true),
            ));
        }

        return $outcome;
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
