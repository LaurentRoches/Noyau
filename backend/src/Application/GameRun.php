<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Player\Inventory;
use App\Domain\Shop\Shop;
use App\Domain\Shop\Wallet;
use Random\Randomizer;

final class GameRun
{
    private const int VICTORIES_TO_WIN = 10;
    private const int DEFEATS_TO_LOSE = 3;
    private const int VICTORY_REWARD = 10;
    private const int INVENTORY_CAPACITY = 6;
    private const int STASH_CAPACITY = 3;
    private const string PLAYER_HERO_ID = 'shadow_bearer';

    private Wallet $wallet;
    private Inventory $inventory;
    private Inventory $stash;
    private readonly int $income;
    private int $victories = 0;
    private int $defeats = 0;
    private int $currentRound = 1;
    private ?Shop $currentShop = null;

    public function __construct(
        private readonly Vestige $vestige,
        private readonly ShopFactory $shopFactory,
        private readonly ScriptedOpponentFactory $opponentFactory,
        private readonly CombatBoardFactory $combatBoardFactory,
        private readonly Simulator $simulator,
        private readonly Randomizer $randomizer,
    ) {
        $this->wallet = new Wallet($vestige->startingGold);
        $this->inventory = new Inventory(self::INVENTORY_CAPACITY);
        $this->stash = new Inventory(self::STASH_CAPACITY);
        $this->income = $vestige->startingIncome;
    }

    public function getInventory(): Inventory
    {
        return $this->inventory;
    }

    public function getStash(): Inventory
    {
        return $this->stash;
    }

    public function purchaseItem(int $slotIndex): Item
    {
        if ($this->currentShop === null) {
            throw new \LogicException('Cannot purchase: no shop is currently open. Call openShop() first.');
        }

        $item = $this->currentShop->purchase($slotIndex, $this->wallet);
        if (!$this->inventory->isFull()) {
            $this->inventory->add($item);
        } else {
            $this->stash->add($item);
        }

        return $item;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function getCurrentRound(): int
    {
        return $this->currentRound;
    }

    public function recordVictory(): void
    {
        $this->victories++;
        $this->wallet->credit(self::VICTORY_REWARD);
        $this->creditIncome();
        $this->currentRound++;
    }

    public function recordDefeat(): void
    {
        $this->defeats++;
        $this->creditIncome();
        $this->currentRound++;
    }

    public function creditIncome(): void
    {
        if ($this->income > 0) {
            $this->wallet->credit($this->income);
        }
    }

    public function isOver(): bool
    {
        return $this->hasWon() || $this->defeats >= self::DEFEATS_TO_LOSE;
    }

    public function hasWon(): bool
    {
        return $this->victories >= self::VICTORIES_TO_WIN;
    }

    public function openShop(): Shop
    {
        $this->currentShop = $this->shopFactory->createShop($this->randomizer);

        return $this->currentShop;
    }

    public function getCurrentShop(): ?Shop
    {
        return $this->currentShop;
    }

    public function playRound(): SimulationResult
    {
        if ($this->isOver()) {
            throw new \LogicException('Cannot play a round: this run is already over.');
        }

        $playerBoard = $this->combatBoardFactory->createBoard(
            $this->vestige->id,
            [self::PLAYER_HERO_ID],
            $this->inventory->getItemIds()
        );

        $opponentBoard = $this->opponentFactory->createOpponent($this->currentRound, $this->randomizer);
        $result = $this->simulator->run($playerBoard, $opponentBoard, $this->randomizer);

        if ($result->winner === $playerBoard) {
            $this->recordVictory();
        } else {
            $this->recordDefeat();
        }

        return $result;
    }

    public function swapWithStash(int $inventoryIndex, int $stashIndex): void
    {
        $inventoryItem = $this->inventory->getItems()[$inventoryIndex]
            ?? throw new \InvalidArgumentException(sprintf('No item at inventory index %d.', $inventoryIndex));
        $stashItem = $this->stash->getItems()[$stashIndex]
            ?? throw new \InvalidArgumentException(sprintf('No item at stash index %d.', $stashIndex));

        $this->inventory->removeAt($inventoryIndex);
        $this->stash->removeAt($stashIndex);

        $this->inventory->insertAt($inventoryIndex, $stashItem);
        $this->stash->insertAt($stashIndex, $inventoryItem);
    }
}
