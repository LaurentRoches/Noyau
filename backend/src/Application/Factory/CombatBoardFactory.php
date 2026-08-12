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
     * @param list<string> $itemIds
     */
    public function createBoard(string $vestigeId, array $heroIds, array $itemIds = []): CombatBoard
    {
        $vestigeDefinition = $this->vestigeRepository->find($vestigeId);
        $combatVestige = new CombatVestige($vestigeDefinition);

        $combatHeroes = [];
        $totalItemSlots = 0;

        foreach ($heroIds as $heroId) {
            $heroDefinition = $this->heroRepository->find($heroId);
            $combatHeroes[] = new CombatHero($heroDefinition);
            $totalItemSlots += $heroDefinition->itemSlots;
        }

        if (count($itemIds) > $totalItemSlots) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot equip %d items: exceeds total slot budget (%d) across %d hero(es)',
                count($itemIds),
                $totalItemSlots,
                count($heroIds)
            ));
        }

        $combatItems = [];
        foreach ($itemIds as $itemId) {
            $itemDefinition = $this->itemRepository->find($itemId);
            $combatItems[] = new CombatItem($itemDefinition);
        }

        return new CombatBoard($combatVestige, $combatHeroes, $combatItems);
    }
}
