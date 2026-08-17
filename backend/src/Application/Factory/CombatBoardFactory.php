<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;

final class CombatBoardFactory
{
    public function __construct(
        private readonly JsonVestigeRepository $vestigeRepository,
        private readonly JsonHeroRepository $heroRepository,
        private readonly JsonItemRepository $itemRepository,
    ) {
    }

    /**
     * @param list<string> $heroIds
     * @param array<string, list<string>> $itemIdsByHero
     */
    public function createBoard(string $vestigeId, array $heroIds, array $itemIdsByHero = []): CombatBoard
    {
        $vestigeDefinition = $this->vestigeRepository->find($vestigeId);
        $combatVestige = new CombatVestige($vestigeDefinition);

        $combatHeroes = [];
        $allItemIds = [];

        foreach ($heroIds as $heroId) {
            $heroDefinition = $this->heroRepository->find($heroId);
            $combatHeroes[] = new CombatHero($heroDefinition);

            $assignedItemIds = $itemIdsByHero[$heroId] ?? [];
            $usedSlots = array_sum(array_map(
                fn (string $itemId): int => $this->itemRepository->find($itemId)->size->slotCost(),
                $assignedItemIds
            ));

            if ($usedSlots > $heroDefinition->itemSlots) {
                throw new \InvalidArgumentException(sprintf(
                    "Hero '%s' cannot equip items totaling %d slots: exceeds budget of %d.",
                    $heroId,
                    $usedSlots,
                    $heroDefinition->itemSlots
                ));
            }

            array_push($allItemIds, ...$assignedItemIds);
        }

        $combatItems = [];
        foreach ($allItemIds as $itemId) {
            $itemDefinition = $this->itemRepository->find($itemId);
            $combatItems[] = new CombatItem($itemDefinition);
        }

        return new CombatBoard($combatVestige, $combatHeroes, $combatItems);
    }
}
