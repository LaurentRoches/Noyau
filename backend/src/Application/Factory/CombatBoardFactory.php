<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Enum\ItemSize;
use App\Domain\Model\Item;
use App\Domain\Player\HeroSkillDecorator;
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
        private readonly HeroSkillDecorator $heroSkillDecorator,
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
        $combatItems = [];

        foreach ($heroIds as $heroId) {
            $heroDefinition = $this->heroRepository->find($heroId);
            $combatHeroes[] = new CombatHero($heroDefinition);

            $assignedItemIds = $itemIdsByHero[$heroId] ?? [];
            $itemDefinitions = array_map(
                fn (string $itemId): Item => $this->itemRepository->find($itemId),
                $assignedItemIds,
            );

            $usedSlots = array_sum(array_map(
                fn (Item $item): int => $item->size->slotCost(),
                $itemDefinitions,
            ));

            if ($usedSlots > $heroDefinition->itemSlots) {
                throw new \InvalidArgumentException(sprintf(
                    "Hero '%s' cannot equip items totaling %d slots: exceeds budget of %d.",
                    $heroId,
                    $usedSlots,
                    $heroDefinition->itemSlots
                ));
            }

            $skill = $heroDefinition->skill;
            $shouldApplySkill = match (true) {
                $skill === null => false,
                $skill === HeroSkillType::RELENTLESS => $this->hasFullOneHandLoadout($itemDefinitions, $heroDefinition->itemSlots),
                default => true,
            };

            foreach ($itemDefinitions as $itemDefinition) {
                if ($shouldApplySkill && $skill !== null) {
                    $itemDefinition = $this->heroSkillDecorator->decorate($skill, $itemDefinition);
                }

                $combatItems[] = new CombatItem($itemDefinition);
            }
        }

        return new CombatBoard($combatVestige, $combatHeroes, $combatItems);
    }

    /**
     * @param Item[] $items
     */
    private function hasFullOneHandLoadout(array $items, int $itemSlots): bool
    {
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if ($item->size !== ItemSize::ONE_HAND) {
                return false;
            }
        }

        return count($items) === $itemSlots;
    }
}
