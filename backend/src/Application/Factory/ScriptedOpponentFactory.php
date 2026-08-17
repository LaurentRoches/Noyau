<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Runtime\CombatBoard;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use Random\Randomizer;

final class ScriptedOpponentFactory
{
    private const string OPPONENT_VESTIGE_ID = 'shadow_vestige';
    private const string OPPONENT_HERO_ID = 'shadow_bearer';
    private const int MAX_SLOTS = 6;

    public function __construct(
        private readonly CombatBoardFactory $combatBoardFactory,
        private readonly JsonItemRepository $itemRepository,
    ) {
    }

    public function createOpponent(int $round, Randomizer $randomizer): CombatBoard
    {
        $slotBudget = min((int) ceil($round / 2), self::MAX_SLOTS);
        $allItems = $this->itemRepository->findAll();

        $shuffledKeys = $randomizer->shuffleArray(array_keys($allItems));

        $itemIds = [];
        $usedSlots = 0;

        foreach ($shuffledKeys as $key) {
            $item = $allItems[$key];
            $cost = $item->size->slotCost();

            if ($usedSlots + $cost > $slotBudget) {
                continue;
            }

            $itemIds[] = $item->id;
            $usedSlots += $cost;
        }

        return $this->combatBoardFactory->createBoard(
            self::OPPONENT_VESTIGE_ID,
            [self::OPPONENT_HERO_ID],
            [self::OPPONENT_HERO_ID => $itemIds]
        );
    }
}
