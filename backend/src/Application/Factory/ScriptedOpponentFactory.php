<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Model\Hero;
use App\Domain\Model\OpponentAssignment;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;

final class ScriptedOpponentFactory
{
    private const string OPPONENT_VESTIGE_ID = 'shadow_vestige';

    public function __construct(
        private readonly CombatBoardFactory $combatBoardFactory,
        private readonly JsonItemRepository $itemRepository,
        private readonly JsonHeroRepository $heroRepository,
        private readonly JsonScriptedOpponentRepository $scriptedOpponentRepository,
    ) {
    }

    public function createOpponent(int $round): OpponentBoard
    {
        $scriptedItemsByHero = $this->scriptedOpponentRepository->findAll();
        $heroIds = array_keys($scriptedItemsByHero);

        $heroBudgets = [];
        foreach ($heroIds as $heroId) {
            $heroBudgets[$heroId] = $this->heroRepository->find($heroId)->itemSlots;
        }
        $totalBudget = array_sum($heroBudgets);
        $slotBudget = min((int) ceil($round / 2), $totalBudget);

        $itemIdsByHero = array_fill_keys($heroIds, []);
        $usedSlotsByHero = array_fill_keys($heroIds, 0);
        $totalUsedSlots = 0;

        foreach ($heroIds as $heroId) {
            foreach ($scriptedItemsByHero[$heroId] as $itemId) {
                $cost = $this->itemRepository->find($itemId)->size->slotCost();

                if ($totalUsedSlots + $cost > $slotBudget) {
                    continue 2;
                }
                if ($usedSlotsByHero[$heroId] + $cost > $heroBudgets[$heroId]) {
                    continue 2;
                }

                $itemIdsByHero[$heroId][] = $itemId;
                $usedSlotsByHero[$heroId] += $cost;
                $totalUsedSlots += $cost;
            }
        }

        $board = $this->combatBoardFactory->createBoard(
            self::OPPONENT_VESTIGE_ID,
            $heroIds,
            $itemIdsByHero
        );

        $roster = array_map(
            fn (string $heroId): Hero => $this->heroRepository->find($heroId),
            $heroIds
        );

        $assignments = [];
        foreach ($itemIdsByHero as $heroId => $itemIds) {
            foreach ($itemIds as $itemId) {
                $assignments[] = new OpponentAssignment($this->itemRepository->find($itemId), $heroId);
            }
        }

        return new OpponentBoard($board, $roster, $assignments);
    }
}
