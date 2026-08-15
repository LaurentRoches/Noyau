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
    private const int MAX_ITEMS = 6;

    public function __construct(
        private readonly CombatBoardFactory $combatBoardFactory,
        private readonly JsonItemRepository $itemRepository,
    ) {
    }

    public function createOpponent(int $round, Randomizer $randomizer): CombatBoard
    {
        $itemCount = min((int) ceil($round / 2), self::MAX_ITEMS);
        $allItems = $this->itemRepository->findAll();

        $selectedKeys = $randomizer->pickArrayKeys($allItems, $itemCount);
        $itemIds = array_map(
            static fn (int $key): string => $allItems[$key]->id,
            $selectedKeys
        );

        return $this->combatBoardFactory->createBoard(
            self::OPPONENT_VESTIGE_ID,
            [self::OPPONENT_HERO_ID],
            $itemIds
        );
    }
}
