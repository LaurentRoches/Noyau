<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\HeroOfferGenerator;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Model\Hero;
use App\Domain\Model\HeroOffer;
use App\Domain\Model\Item;
use App\Domain\Model\OpponentAssignment;
use App\Domain\Model\Vestige;
use App\Domain\Player\HeroItemAllocator;
use App\Domain\Player\Inventory;
use App\Domain\Player\Stash;
use App\Domain\Shop\Shop;
use App\Domain\Shop\Wallet;
use Random\Randomizer;

final class GameRun
{
    private const int VICTORIES_TO_WIN = 10;
    private const int DEFEATS_TO_LOSE = 3;
    private const int VICTORY_REWARD = 10;
    private const int STASH_CAPACITY = 6;
    /** @var list<int> */
    private const array HERO_OFFER_ROUNDS = [3, 5];

    private Wallet $wallet;
    private Inventory $inventory;
    private Stash $stash;
    /** @var list<Hero> */
    private array $roster = [];
    private readonly HeroOfferGenerator $heroOfferGenerator;
    private ?HeroOffer $pendingHeroOffer;
    private readonly int $income;
    private int $victories = 0;
    private int $defeats = 0;
    private int $currentRound = 1;
    private ?Shop $currentShop = null;
    private ?SimulationResult $lastCombatResult = null;
    /** @var list<Hero>|null */
    private ?array $lastOpponentRoster = null;
    /** @var list<OpponentAssignment>|null */
    private ?array $lastOpponentAssignments = null;

    public function __construct(
        private readonly Vestige $vestige,
        private readonly ShopFactory $shopFactory,
        private readonly ScriptedOpponentFactory $opponentFactory,
        HeroOfferGenerator $heroOfferGenerator,
        private readonly CombatBoardFactory $combatBoardFactory,
        private readonly Simulator $simulator,
        private readonly Randomizer $randomizer,
    ) {
        $this->wallet = new Wallet($vestige->startingGold);
        $this->heroOfferGenerator = $heroOfferGenerator;
        $this->pendingHeroOffer = $this->heroOfferGenerator->buildInitialOffer($this->randomizer, $vestige->affinity);
        $this->inventory = new Inventory();
        $this->stash = new Stash(self::STASH_CAPACITY);
        $this->income = $vestige->startingIncome;
    }

    /**
     * @return list<Hero>
     */
    public function getRoster(): array
    {
        return $this->roster;
    }

    public function getPendingHeroOffer(): ?HeroOffer
    {
        return $this->pendingHeroOffer;
    }

    public function chooseHero(string $heroId): void
    {
        if ($this->isOver()) {
            throw new \LogicException('Cannot choose a hero: this run is already over.');
        }

        if ($this->pendingHeroOffer === null) {
            throw new \LogicException('Cannot choose a hero: no hero offer is currently pending.');
        }

        $chosenHero = $this->pendingHeroOffer->find($heroId)
            ?? throw new \InvalidArgumentException(sprintf(
                'Hero "%s" is not part of the current offer.',
                $heroId,
            ));

        $this->roster[] = $chosenHero;
        $this->pendingHeroOffer = null;

        if ($this->currentShop === null) {
            $this->openShop();
        }
    }

    public function getVestige(): Vestige
    {
        return $this->vestige;
    }

    public function getInventory(): Inventory
    {
        return $this->inventory;
    }

    public function getStash(): Stash
    {
        return $this->stash;
    }

    public function purchaseItem(int $slotIndex): Item
    {
        if ($this->pendingHeroOffer !== null) {
            throw new \LogicException('Cannot purchase: a hero offer is currently pending. Call chooseHero() first.');
        }

        if ($this->currentShop === null) {
            throw new \LogicException('Cannot purchase: no shop is currently open. Call openShop() first.');
        }

        $item = $this->currentShop->purchase($slotIndex, $this->wallet);

        $heroId = $this->heroItemAllocator()->allocate($item, $this->inventory);
        if ($heroId !== null) {
            $this->inventory->add($item, $heroId);
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

    public function getVictories(): int
    {
        return $this->victories;
    }

    public function getDefeats(): int
    {
        return $this->defeats;
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
        if ($this->pendingHeroOffer !== null) {
            throw new \LogicException('Cannot open shop: a hero offer is currently pending. Call chooseHero() first.');
        }

        $this->currentShop = $this->shopFactory->createShop($this->randomizer);

        return $this->currentShop;
    }

    public function getCurrentShop(): ?Shop
    {
        return $this->currentShop;
    }

    /**
     * @return list<string>
     */
    private function heroIds(): array
    {
        return array_map(static fn (Hero $hero): string => $hero->id, $this->roster);
    }

    private function heroItemAllocator(): HeroItemAllocator
    {
        return new HeroItemAllocator($this->roster);
    }

    public function playRound(): SimulationResult
    {
        if ($this->isOver()) {
            throw new \LogicException('Cannot play a round: this run is already over.');
        }

        $playerBoard = $this->combatBoardFactory->createBoard(
            $this->vestige->id,
            $this->heroIds(),
            $this->inventory->getItemIdsByHero()
        );

        $opponent = $this->opponentFactory->createOpponent($this->currentRound);
        $result = $this->simulator->run($playerBoard, $opponent->board, $this->randomizer);

        $this->lastCombatResult = $result;
        $this->lastOpponentRoster = $opponent->roster;
        $this->lastOpponentAssignments = $opponent->assignments;

        if ($result->winner === $playerBoard) {
            $this->recordVictory();
        } else {
            $this->recordDefeat();
        }

        if ($this->isOver()) {
            $this->currentShop = null;

            return $result;
        }

        if (in_array($this->currentRound, self::HERO_OFFER_ROUNDS, true)) {
            $this->currentShop = null;
            $this->pendingHeroOffer = $this->heroOfferGenerator->buildWeightedOffer(
                $this->randomizer,
                $this->vestige->affinity,
                $this->heroIds(),
            );
        } else {
            $this->openShop();
        }

        return $result;
    }

    public function getLastCombatResult(): ?SimulationResult
    {
        return $this->lastCombatResult;
    }

    /**
     * @return list<Hero>|null
     */
    public function getLastOpponentRoster(): ?array
    {
        return $this->lastOpponentRoster;
    }

    /**
     * @return list<OpponentAssignment>|null
     */
    public function getLastOpponentAssignments(): ?array
    {
        return $this->lastOpponentAssignments;
    }

    public function swapWithStash(int $inventoryIndex, int $stashIndex, string $heroId): void
    {
        $assignedItem = $this->inventory->getItems()[$inventoryIndex]
            ?? throw new \InvalidArgumentException(sprintf('No item at inventory index %d.', $inventoryIndex));
        $stashItem = $this->stash->getItems()[$stashIndex]
            ?? throw new \InvalidArgumentException(sprintf('No item at stash index %d.', $stashIndex));

        $this->inventory->removeAt($inventoryIndex);

        if (!$this->heroItemAllocator()->canAssign($stashItem, $heroId, $this->inventory)) {
            $this->inventory->insertAt($inventoryIndex, $assignedItem);

            throw new \InvalidArgumentException(sprintf(
                'Cannot assign item "%s" to hero "%s": exceeds item slot budget.',
                $stashItem->id,
                $heroId,
            ));
        }

        $this->stash->removeAt($stashIndex);
        $this->inventory->insertAt($inventoryIndex, new \App\Domain\Player\AssignedItem($stashItem, $heroId));
        $this->stash->insertAt($stashIndex, $assignedItem->item);
    }
}
