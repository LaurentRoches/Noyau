<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\HeroOfferGenerator;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Enum\Side;
use App\Domain\Model\Hero;
use App\Domain\Model\HeroOffer;
use App\Domain\Model\Item;
use App\Domain\Model\OpponentAssignment;
use App\Domain\Model\Vestige;
use App\Domain\Player\AssignedItem;
use App\Domain\Player\HeroItemAllocator;
use App\Domain\Player\Inventory;
use App\Domain\Player\Stash;
use App\Domain\Shop\Shop;
use App\Domain\Shop\Wallet;
use App\Domain\Snapshot\BoardRecord;
use App\Domain\Snapshot\CombatRecord;
use App\Domain\Snapshot\SnapshotRecipe;
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
    private ?RoundOutcome $lastRoundOutcome = null;
    private ?CombatRecord $lastCombatRecord = null;
    private ?Side $lastPlayerSide = null;
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
        private readonly int $seed,
        private readonly string $contentVersion,
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
        $this->assertCanPlayRound();

        // La recette est construite AVANT le plateau, et c'est elle qui
        // fournit les arguments de la fabrique. Elle ne peut donc pas décrire
        // autre chose que ce qui a été assemblé — et `BoardRecord` refuse de
        // toute façon une recette dont les comptes ne correspondent pas au
        // plateau, ce qui ferait lever ici, à chaque manche.
        $recipe = new SnapshotRecipe(
            $this->vestige->id,
            $this->heroIds(),
            $this->inventory->getItemIdsByHero(),
        );

        // L'or embarqué est le solde AU LANCEMENT du combat : la récompense de
        // victoire et le revenu sont crédités après, par recordVictory() et
        // recordDefeat(). Lu ici plutôt que reconstruit ailleurs, c'est la
        // seule lecture qui ne puisse pas se désynchroniser du combat qu'elle
        // décrit (D-16, `04` §5.5).
        $playerBoard = $this->combatBoardFactory->createBoard(
            $recipe->vestigeId,
            $recipe->heroIds,
            $recipe->itemIdsByHero,
            $this->wallet->getBalance()
        );

        $opponent = $this->opponentFactory->createOpponent($this->currentRound);
        $combatSeed = CombatSeed::forRound($this->seed, $this->currentRound);
        $result = $this->simulator->run($playerBoard, $opponent->board, $combatSeed);

        $this->lastCombatResult = $result;
        // Côté que le moteur a donné à NOTRE plateau (D-19). Lu au résultat,
        // jamais supposé ici : l'attribution est canonique depuis le commit 9,
        // et cette ligne a continué de dire vrai sans être touchée.
        $playerSide = $result->sideOf($playerBoard);
        $this->lastPlayerSide = $playerSide;
        $this->lastOpponentRoster = $opponent->roster;
        $this->lastOpponentAssignments = $opponent->assignments;

        // L'archive du combat, rangée par côté et non « joueur d'abord » : elle
        // ne connaît aucun spectateur, c'est ce qui la rend rejouable par les
        // deux joueurs d'un futur PvP (D-19).
        $playerRecord = BoardRecord::fromBoard($playerBoard, $recipe, $this->contentVersion);
        $opponentRecord = BoardRecord::fromBoard($opponent->board, $opponent->recipe, $this->contentVersion);

        $this->lastCombatRecord = new CombatRecord(
            boardA: $playerSide === Side::A ? $playerRecord : $opponentRecord,
            boardB: $playerSide === Side::A ? $opponentRecord : $playerRecord,
            combatSeed: $combatSeed,
            resolution: $result->resolution,
            winnerSide: $result->sideOf($result->winner),
        );

        $this->concludeRound(
            $result->winner === $playerBoard ? RoundOutcome::VICTORY : RoundOutcome::DEFEAT,
        );

        return $result;
    }

    /**
     * Le chemin du **rejeu** : faire avancer une manche depuis son issue
     * enregistrée, sans moteur (D-18 volet 1, `07` E-11).
     *
     * **Réservé à `GameRunReplayer`.** `06` §8 : « le chemin *appliquer une
     * issue enregistrée* est réservé au rejeu ; le handler HTTP simule toujours
     * lui-même ». Si `RunController::resolveRound()` empruntait cette méthode,
     * l'issue viendrait d'ailleurs que du moteur — et la seule autre source
     * possible serait la requête, c'est-à-dire le joueur.
     *
     * Les deux gardes de `playRound()` s'appliquent telles quelles. Un journal
     * qui contiendrait une manche de trop, ou une manche avant un choix de
     * héros, est un journal corrompu : le rejeu doit s'arrêter plutôt que
     * produire un état que le jeu n'aurait jamais pu atteindre.
     */
    public function applyRecordedRound(RoundOutcome $outcome): void
    {
        $this->assertCanPlayRound();

        $this->concludeRound($outcome);
    }

    /**
     * Issue de la manche la plus récente, quelle que soit la façon dont elle a
     * été produite — simulée ou rejouée. `null` tant qu'aucune n'a été jouée.
     *
     * C'est ce que `RunController` journalise. Le contrôleur ne redérive pas le
     * vainqueur depuis le `SimulationResult` : une seconde dérivation serait un
     * second endroit où elle peut se tromper.
     */
    public function getLastRoundOutcome(): ?RoundOutcome
    {
        return $this->lastRoundOutcome;
    }

    /**
     * L'archive du dernier combat **réellement simulé**, ou `null`.
     *
     * `applyRecordedRound()` ne l'écrit jamais : un rejeu ne produit aucun
     * combat. Sans cette asymétrie, rejouer une run réécrirait ses archives
     * avec le moteur courant — c'est-à-dire remplacerait l'enregistrement par
     * une reconstitution, ce que tout le chantier existe pour empêcher.
     */
    public function getLastCombatRecord(): ?CombatRecord
    {
        return $this->lastCombatRecord;
    }

    private function assertCanPlayRound(): void
    {
        if ($this->isOver()) {
            throw new \LogicException('Cannot play a round: this run is already over.');
        }

        if ($this->pendingHeroOffer !== null) {
            throw new \LogicException('Cannot play a round: a hero offer is currently pending. Call chooseHero() first.');
        }
    }

    /**
     * La transition de fin de manche, **partagée** par la simulation et le
     * rejeu.
     *
     * C'est le seul endroit où une manche avance. Deux copies de cette
     * transition, et un run rejoué n'aboutirait plus au même état que le run
     * joué : le journal cesserait de valoir quelque chose sans qu'aucun test
     * unitaire ne le signale.
     *
     * Le `match` est exhaustif et sans branche par défaut : une troisième issue
     * ne compilera pas tant que son effet sur la run n'aura pas été tranché ici.
     */
    private function concludeRound(RoundOutcome $outcome): void
    {
        $this->lastRoundOutcome = $outcome;

        match ($outcome) {
            RoundOutcome::VICTORY => $this->recordVictory(),
            RoundOutcome::DEFEAT => $this->recordDefeat(),
        };

        if ($this->isOver()) {
            $this->currentShop = null;

            return;
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
    }

    public function getLastCombatResult(): ?SimulationResult
    {
        return $this->lastCombatResult;
    }

    /**
     * Côté occupé par le plateau du joueur au dernier combat résolu.
     *
     * `null` tant qu'aucune manche n'a été jouée : avant un combat, aucun côté
     * n'a été attribué, et une valeur par défaut serait une invention.
     */
    public function getLastPlayerSide(): ?Side
    {
        return $this->lastPlayerSide;
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

        $canAssign = false;
        $this->inventory->removeAt($inventoryIndex);

        try {
            $canAssign = $this->heroItemAllocator()->canAssign($stashItem, $heroId, $this->inventory);
        } finally {
            if (!$canAssign) {
                $this->inventory->insertAt($inventoryIndex, $assignedItem);
            }
        }

        if (!$canAssign) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot assign item "%s" to hero "%s": exceeds item slot budget.',
                $stashItem->id,
                $heroId,
            ));
        }

        $this->stash->removeAt($stashIndex);
        $this->inventory->insertAt($inventoryIndex, new AssignedItem($stashItem, $heroId));
        $this->stash->insertAt($stashIndex, $assignedItem->item);
    }
}
