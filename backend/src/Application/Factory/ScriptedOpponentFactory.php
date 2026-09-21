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

    /**
     * L'adversaire scripté n'a pas de portefeuille : il n'a ni gagné ni
     * dépensé, et zéro est la seule valeur qu'on puisse affirmer.
     *
     * **Ce n'est pas une décision de format.** Son plateau est reconstruit
     * depuis `scripted_opponent.json` à chaque combat, donc cette valeur ne se
     * fige nulle part tant que rien n'archive de plateau. Le jour où
     * l'archivage existe — enregistrement de combat au commit 10, corpus au
     * chantier 12 —, c'est la valeur passée **à ce moment-là** qui se figera :
     * à revoir alors, pas maintenant. `04` §5.4 garantit qu'il n'y aura pas de
     * cas particulier de format à traiter pour autant.
     *
     * Aucune mécanique ne la lit aujourd'hui (`AURIC` reste à créer), donc
     * elle serait invisible : c'est `GameRunTest` qui l'épingle.
     */
    private const int OPPONENT_GOLD_AT_COMBAT_START = 0;

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
            $itemIdsByHero,
            self::OPPONENT_GOLD_AT_COMBAT_START
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
