<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\HeroOfferGenerator;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Application\GameRun;
use App\Application\RoundOutcome;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Side;
use App\Domain\Model\Vestige;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class GameRunTest extends TestCase
{
    /**
     * Construit un GameRun brut, tel qu'il sort du constructeur : roster vide,
     * offre initiale en attente. Utilisé uniquement par le test qui inspecte
     * précisément cet état de départ.
     */
    private function createRawGameRun(int $startingGold = 20): GameRun
    {
        $vestige = new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            startingGold: $startingGold,
            startingIncome: 5
        );

        $configPath = __DIR__ . '/../../config/game';
        $itemRepository = new JsonItemRepository($configPath . '/items.json');
        $heroRepository = new JsonHeroRepository($configPath . '/heroes.json');

        $combatBoardFactory = new CombatBoardFactory(
            new JsonVestigeRepository($configPath . '/vestiges.json'),
            $heroRepository,
            $itemRepository,
            new HeroSkillDecorator(),
        );

        $opponentFactory = new ScriptedOpponentFactory(
            $combatBoardFactory,
            $itemRepository,
            $heroRepository,
            new JsonScriptedOpponentRepository($configPath . '/scripted_opponent.json'),
        );

        return new GameRun(
            $vestige,
            new ShopFactory($itemRepository),
            $opponentFactory,
            new HeroOfferGenerator($heroRepository),
            $combatBoardFactory,
            new Simulator(maxTicks: 200),
            new Randomizer(new PcgOneseq128XslRr64(1)),
            1,
        );
    }

    /**
     * Construit un GameRun prêt à jouer : l'offre initiale est immédiatement
     * consommée en choisissant son premier candidat (le héros d'affinité
     * garantie). C'est l'état de départ attendu par la quasi-totalité des
     * tests existants, qui portent sur le déroulé du jeu après ce choix.
     */
    private function createGameRun(int $startingGold = 20): GameRun
    {
        $gameRun = $this->createRawGameRun($startingGold);

        $offer = $gameRun->getPendingHeroOffer();
        $gameRun->chooseHero($offer->candidates[0]->id);

        return $gameRun;
    }

    public function testRosterIsEmptyAndInitialHeroOfferIsPendingAtConstruction(): void
    {
        $gameRun = $this->createRawGameRun();

        self::assertSame([], $gameRun->getRoster());

        $offer = $gameRun->getPendingHeroOffer();
        self::assertNotNull($offer);
        self::assertCount(3, $offer->candidates);
        self::assertSame('shadow', $offer->candidates[0]->affinity);
    }

    public function testInitializesWalletWithVestigeStartingGold(): void
    {
        $gameRun = $this->createGameRun();

        self::assertSame(20, $gameRun->getWallet()->getBalance());
    }

    public function testRecordVictoryCreditsWalletWithRewardAndIncome(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();

        self::assertSame(35, $gameRun->getWallet()->getBalance());
    }

    public function testRecordDefeatCreditsWalletWithIncomeOnly(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordDefeat();

        self::assertSame(25, $gameRun->getWallet()->getBalance());
    }

    public function testVictoryAndDefeatCountersCanBeRead(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();
        $gameRun->recordVictory();
        $gameRun->recordDefeat();

        self::assertSame(2, $gameRun->getVictories());
        self::assertSame(1, $gameRun->getDefeats());
    }

    public function testRunIsOverAfterTenVictories(): void
    {
        $gameRun = $this->createGameRun();

        for ($i = 0; $i < 10; $i++) {
            $gameRun->recordVictory();
        }

        self::assertTrue($gameRun->isOver());
        self::assertTrue($gameRun->hasWon());
    }

    public function testRunIsOverAfterThreeDefeats(): void
    {
        $gameRun = $this->createGameRun();

        for ($i = 0; $i < 3; $i++) {
            $gameRun->recordDefeat();
        }

        self::assertTrue($gameRun->isOver());
        self::assertFalse($gameRun->hasWon());
    }

    public function testRunIsNotOverBeforeThresholds(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();
        $gameRun->recordDefeat();

        self::assertFalse($gameRun->isOver());
    }

    public function testCurrentRoundStartsAtOne(): void
    {
        $gameRun = $this->createGameRun();

        self::assertSame(1, $gameRun->getCurrentRound());
    }

    public function testCurrentRoundIncrementsAfterVictory(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordVictory();

        self::assertSame(2, $gameRun->getCurrentRound());
    }

    public function testCurrentRoundIncrementsAfterDefeat(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordDefeat();

        self::assertSame(2, $gameRun->getCurrentRound());
    }

    public function testOpenShopGeneratesShopWithFourOffers(): void
    {
        $gameRun = $this->createGameRun();

        $shop = $gameRun->openShop();

        self::assertCount(4, $shop->getOffers());
        self::assertSame($shop, $gameRun->getCurrentShop());
    }

    public function testPurchaseItemAddsToInventoryAndDebitsWallet(): void
    {
        $gameRun = $this->createGameRun();
        $gameRun->openShop();
        $balanceBefore = $gameRun->getWallet()->getBalance();

        $offer = $gameRun->getCurrentShop()->getOffers()[0];
        $item = $gameRun->purchaseItem(0);

        self::assertSame($offer->getItem(), $item);
        self::assertCount(1, $gameRun->getInventory()->getItems());
        self::assertSame($balanceBefore - $offer->getPrice(), $gameRun->getWallet()->getBalance());
    }

    public function testPurchaseItemThrowsWhenNoShopIsOpen(): void
    {
        // État brut : offre en attente, roster vide, aucune boutique ouverte.
        // Avec le nouveau contrat, chooseHero() ouvre toujours la boutique
        // dans le flux normal — cet état n'est donc atteignable qu'avant tout
        // choix de héros, pas après (l'ancien état "héros choisi mais pas de
        // boutique" n'existe plus).
        $gameRun = $this->createRawGameRun();

        $this->expectException(\LogicException::class);
        $gameRun->purchaseItem(0);
    }

    public function testPlayRoundBuildsBoardsRunsSimulationAndAdvancesRound(): void
    {
        $gameRun = $this->createGameRun();

        $result = $gameRun->playRound();

        self::assertInstanceOf(SimulationResult::class, $result);
        self::assertSame(2, $gameRun->getCurrentRound());
    }

    public function testPlayRoundRecordsWhichSideThePlayerBoardWasAssigned(): void
    {
        $gameRun = $this->createGameRun();

        self::assertNull(
            $gameRun->getLastPlayerSide(),
            'Aucun combat joué : aucun côté attribué.'
        );

        $gameRun->playRound();

        // L'attribution est désormais canonique (D-19) : A est le plateau à
        // la plus petite photographie, et ce n'est plus celui du joueur. Le
        // jour annoncé par la version précédente de ce commentaire est arrivé,
        // et c'est bien cette ligne seule qui a bougé — aucune du frontend.
        //
        // B est une CARACTÉRISATION, pas une règle : elle dépend du héros tiré
        // par l'offre initiale sur la graine 1 et du contenu de
        // scripted_opponent.json. Un rééquilibrage du catalogue peut la faire
        // basculer, et ce test doit alors être relu, pas rafistolé.
        self::assertSame(Side::B, $gameRun->getLastPlayerSide());
    }

    /**
     * L'or d'entrée de combat arrive sur le plateau, et il vient du
     * portefeuille — pas de la définition du Vestige.
     *
     * Le solde est volontairement décalé de `startingGold` avant le combat :
     * sans ce décalage, le test passerait aussi bien si la fabrique lisait
     * `$vestige->startingGold`. `creditIncome()` ajoute le revenu sans toucher
     * au compteur de manche ni aux compteurs de victoires et de défaites.
     */
    public function testPlayRoundGivesEachBoardItsGoldAtCombatStart(): void
    {
        $gameRun = $this->createGameRun(startingGold: 20);

        $gameRun->creditIncome();
        $balanceAtCombatStart = $gameRun->getWallet()->getBalance();
        self::assertSame(25, $balanceAtCombatStart, 'Précondition : le solde ne vaut plus startingGold.');

        $result = $gameRun->playRound();

        $playerSide = $gameRun->getLastPlayerSide();
        self::assertNotNull($playerSide);

        $playerBoard = $playerSide === Side::A ? $result->boardA : $result->boardB;
        $opponentBoard = $playerSide === Side::A ? $result->boardB : $result->boardA;

        self::assertSame($balanceAtCombatStart, $playerBoard->getGoldAtCombatStart());

        // L'adversaire scripté n'a pas de portefeuille : zéro est la seule
        // valeur qu'on puisse affirmer. Elle est épinglée ici parce qu'aucune
        // mécanique ne la lit — `AURIC` reste à créer —, donc rien d'autre ne
        // la rendrait visible si elle changeait par accident.
        self::assertSame(0, $opponentBoard->getGoldAtCombatStart());
    }

    public function testPlayRoundThrowsWhenRunIsAlreadyOver(): void
    {
        $gameRun = $this->createGameRun();

        for ($i = 0; $i < 10; $i++) {
            $gameRun->recordVictory();
        }

        self::assertTrue($gameRun->isOver());

        $this->expectException(\LogicException::class);
        $gameRun->playRound();
    }

    public function testPlayRoundOpensANewShopWhenRunIsNotOver(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->playRound();

        self::assertNotNull($gameRun->getCurrentShop());
    }

    public function testPlayRoundDoesNotOpenANewShopWhenThisRoundEndsTheRun(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->openShop();

        // Deux défaites déjà enregistrées ; sans le moindre objet en inventaire,
        // ce round sera nécessairement une défaite aussi (aucun dégât possible
        // côté joueur face à un adversaire scripté équipé) — la 3e défaite
        // termine le run.
        $gameRun->recordDefeat();
        $gameRun->recordDefeat();

        $gameRun->playRound();

        self::assertTrue($gameRun->isOver());
        self::assertNull($gameRun->getCurrentShop());
    }

    public function testSwapWithStashExchangesItemsBetweenBoardAndStash(): void
    {
        $gameRun = $this->createGameRun(startingGold: 1000);

        // Avec un seul héros dans le roster (contre 3 auparavant), la capacité
        // totale d'inventaire dépend directement de ses propres emplacements.
        // On calcule la cible dynamiquement plutôt que de recopier un nombre
        // en dur qui ne vaudrait plus rien selon le héros tiré par l'offre.
        $heroSlots = $gameRun->getRoster()[0]->itemSlots;
        $targetPurchases = $heroSlots + 1; // le +1e objet déborde nécessairement dans le stash

        $purchased = 0;
        $attempts = 0;
        while ($purchased < $targetPurchases && $attempts < 30) {
            $shop = $gameRun->openShop();
            $attempts++;

            $oneHandIndex = null;
            foreach ($shop->getOffers() as $index => $offer) {
                if ($offer->getItem()->size === ItemSize::ONE_HAND) {
                    $oneHandIndex = $index;
                    break;
                }
            }

            if ($oneHandIndex === null) {
                continue;
            }

            $gameRun->purchaseItem($oneHandIndex);
            $purchased++;
        }

        self::assertSame($targetPurchases, $purchased, sprintf(
            'Expected to purchase %d ONE_HAND items within 30 shop attempts.',
            $targetPurchases,
        ));
        self::assertNotEmpty($gameRun->getStash()->getItems(), 'Expected stash to contain at least one item after purchases.');

        $inventoryIndex = 0;
        $stashIndex = 0;
        $heroId = $gameRun->getInventory()->getItems()[$inventoryIndex]->heroId;

        $boardAssignedItemBefore = $gameRun->getInventory()->getItems()[$inventoryIndex];
        $stashItemBefore = $gameRun->getStash()->getItems()[$stashIndex];

        $gameRun->swapWithStash($inventoryIndex, $stashIndex, $heroId);

        self::assertSame($stashItemBefore, $gameRun->getInventory()->getItems()[$inventoryIndex]->item);
        self::assertSame($heroId, $gameRun->getInventory()->getItems()[$inventoryIndex]->heroId);
        self::assertSame($boardAssignedItemBefore->item, $gameRun->getStash()->getItems()[$stashIndex]);
    }

    public function testSwapWithStashRestoresInventoryWhenHeroIsNotInRoster(): void
    {
        $gameRun = $this->createGameRun(startingGold: 1000);

        // Même amorçage que le test de swap nominal : on remplit les
        // emplacements du héros puis on déborde d'un objet dans le stash.
        // Sans objet dans le stash, swapWithStash() échoue à la lecture,
        // avant toute mutation, et le trou visé ici n'est jamais atteint.
        $heroSlots = $gameRun->getRoster()[0]->itemSlots;
        $targetPurchases = $heroSlots + 1;

        $purchased = 0;
        $attempts = 0;
        while ($purchased < $targetPurchases && $attempts < 30) {
            $shop = $gameRun->openShop();
            $attempts++;

            $oneHandIndex = null;
            foreach ($shop->getOffers() as $index => $offer) {
                if ($offer->getItem()->size === ItemSize::ONE_HAND) {
                    $oneHandIndex = $index;
                    break;
                }
            }

            if ($oneHandIndex === null) {
                continue;
            }

            $gameRun->purchaseItem($oneHandIndex);
            $purchased++;
        }

        self::assertSame($targetPurchases, $purchased, sprintf(
            'Expected to purchase %d ONE_HAND items within 30 shop attempts.',
            $targetPurchases,
        ));
        self::assertNotEmpty($gameRun->getStash()->getItems(), 'Expected stash to contain at least one item after purchases.');

        $inventoryBefore = $gameRun->getInventory()->getItems();
        $stashBefore = $gameRun->getStash()->getItems();

        // Comportement ATTENDU : un heroId absent du roster fait LEVER
        // HeroItemAllocator::canAssign() via findHero(), il ne retourne pas
        // false. Le retrait temporaire de l'inventaire doit donc être
        // restauré avant que l'exception ne remonte, sinon l'objet équipé
        // est purement et simplement détruit.
        try {
            $gameRun->swapWithStash(0, 0, 'unknown_hero');
            self::fail('Une InvalidArgumentException aurait dû être levée.');
        } catch (\InvalidArgumentException) {
            self::assertSame($inventoryBefore, $gameRun->getInventory()->getItems());
            self::assertSame($stashBefore, $gameRun->getStash()->getItems());
        }
    }

    public function testGetLastCombatResultReturnsNullBeforeAnyRoundIsPlayed(): void
    {
        $gameRun = $this->createGameRun();

        self::assertNull($gameRun->getLastCombatResult());
    }

    public function testGetLastCombatResultReturnsTheResultOfTheMostRecentRound(): void
    {
        $gameRun = $this->createGameRun();

        $result = $gameRun->playRound();

        self::assertSame($result, $gameRun->getLastCombatResult());
    }

    public function testPlayRoundClearsTheShopWhenThisRoundEndsTheRun(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordDefeat();
        $gameRun->recordDefeat();

        $gameRun->playRound();

        self::assertTrue($gameRun->isOver());
        self::assertNull($gameRun->getCurrentShop());
    }

    public function testGetVestigeReturnsTheInjectedVestige(): void
    {
        $gameRun = $this->createGameRun();

        $vestige = $gameRun->getVestige();

        self::assertSame('shadow_vestige', $vestige->id);
        self::assertSame(100, $vestige->baseHp);
        self::assertSame(10, $vestige->baseShield);
    }

    private function equipHeroWithAffordableOneHandItems(GameRun $gameRun, int $maxSlots): void
    {
        $shop = $gameRun->getCurrentShop() ?? $gameRun->openShop();
        $bought = 0;

        while ($bought < $maxSlots) {
            $affordableOneHandIndex = null;

            foreach ($shop->getOffers() as $index => $offer) {
                if (
                    $offer->getItem()->size === ItemSize::ONE_HAND
                    && $gameRun->getWallet()->canAfford($offer->getPrice())
                ) {
                    $affordableOneHandIndex = $index;
                    break;
                }
            }

            if ($affordableOneHandIndex === null) {
                break; // aucun objet ONE_HAND abordable dans cette offre : on s'arrête là.
            }

            $gameRun->purchaseItem($affordableOneHandIndex);
            $bought++;

            if ($bought < $maxSlots) {
                $shop = $gameRun->openShop();
            }
        }
    }

    public function testPlayRoundRefusesImmediatelyWhenHeroOfferIsPendingWithoutRunningCombat(): void
    {
        $gameRun = $this->createGameRun();

        $heroSlots = $gameRun->getRoster()[0]->itemSlots;
        $this->equipHeroWithAffordableOneHandItems($gameRun, $heroSlots);

        $gameRun->playRound(); // round 1
        if (!$gameRun->isOver() && $gameRun->getCurrentShop() !== null) {
            $this->equipHeroWithAffordableOneHandItems($gameRun, $heroSlots);
        }
        $gameRun->playRound(); // round 2 -> pendingHeroOffer positionnée si non terminé

        self::assertFalse($gameRun->isOver(), 'Précondition : au moins une victoire attendue sur les 2 premiers rounds.');
        self::assertNotNull($gameRun->getPendingHeroOffer());

        $roundBefore = $gameRun->getCurrentRound();
        $gamesPlayedBefore = $gameRun->getVictories() + $gameRun->getDefeats();
        $lastCombatResultBefore = $gameRun->getLastCombatResult();

        // Comportement ATTENDU (E-06) : playRound() doit refuser AVANT
        // d'exécuter le moindre combat quand une offre de héros est en
        // attente, symétriquement à purchaseItem() et openShop(). Aucune
        // mutation d'état ne doit avoir lieu : ni round avancé, ni
        // victoire/défaite comptabilisée, ni résultat de combat écrasé.
        try {
            $gameRun->playRound();
            self::fail('Une LogicException aurait dû être levée avant tout combat.');
        } catch (\LogicException $e) {
            self::assertSame($roundBefore, $gameRun->getCurrentRound());
            self::assertSame($gamesPlayedBefore, $gameRun->getVictories() + $gameRun->getDefeats());
            self::assertSame($lastCombatResultBefore, $gameRun->getLastCombatResult());
        }
    }

    // === Issue enregistrée — D-18 volet 1, E-11 ============================
    //
    // Le journal ne stocke pas le résultat des combats, seulement l'action
    // RESOLVE_ROUND : à chaque rejeu, playRound() resimule tous les combats
    // passés avec le moteur COURANT. Le chantier 2 ayant changé le moteur
    // (D-14, D-22), une manche gagnée peut devenir perdue — le compteur de
    // victoires diverge, les offres de héros des manches 3 et 5 apparaissent
    // ou disparaissent, et un CHOOSE_HERO journalisé peut lever au rejeu.
    //
    // applyRecordedRound() est le chemin du rejeu : il fait avancer la manche
    // exactement comme playRound(), mais SANS moteur. C'est ce qui rend le
    // journal indépendant du moteur.

    public function testApplyRecordedVictoryAdvancesTheRunWithoutSimulatingACombat(): void
    {
        $gameRun = $this->createGameRun(startingGold: 20);

        $gameRun->applyRecordedRound(RoundOutcome::VICTORY);

        self::assertSame(1, $gameRun->getVictories());
        self::assertSame(0, $gameRun->getDefeats());
        self::assertSame(2, $gameRun->getCurrentRound());
        self::assertSame(35, $gameRun->getWallet()->getBalance(), '20 de départ + 10 de récompense + 5 de revenu.');

        // La preuve qu'aucun combat n'a tourné. Un résultat de simulation
        // présent ici signifierait que le rejeu a resimulé — exactement ce que
        // E-11 décrit.
        self::assertNull($gameRun->getLastCombatResult());
        self::assertNull($gameRun->getLastPlayerSide());
    }

    public function testApplyRecordedDefeatAdvancesTheRunWithoutSimulatingACombat(): void
    {
        $gameRun = $this->createGameRun(startingGold: 20);

        $gameRun->applyRecordedRound(RoundOutcome::DEFEAT);

        self::assertSame(0, $gameRun->getVictories());
        self::assertSame(1, $gameRun->getDefeats());
        self::assertSame(2, $gameRun->getCurrentRound());
        self::assertSame(25, $gameRun->getWallet()->getBalance(), 'Le revenu seul, pas de récompense.');
        self::assertNull($gameRun->getLastCombatResult());
    }

    /**
     * La transition d'état est la MÊME que celle de `playRound()`, et c'est
     * tout l'enjeu : si les deux divergent, un run rejoué n'aboutit pas au
     * même état que le run joué, et le journal ne vaut plus rien.
     */
    public function testApplyRecordedRoundOpensTheNextShop(): void
    {
        $gameRun = $this->createGameRun();
        $shopBefore = $gameRun->getCurrentShop();

        $gameRun->applyRecordedRound(RoundOutcome::VICTORY);

        self::assertNotNull($gameRun->getCurrentShop());
        self::assertNotSame($shopBefore, $gameRun->getCurrentShop());
    }

    public function testApplyRecordedRoundPositionsTheHeroOfferOnOfferRounds(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->applyRecordedRound(RoundOutcome::VICTORY); // -> manche 2
        self::assertNull($gameRun->getPendingHeroOffer(), 'La manche 2 n\'est pas une manche d\'offre.');

        $gameRun->applyRecordedRound(RoundOutcome::VICTORY); // -> manche 3

        self::assertSame(3, $gameRun->getCurrentRound());
        self::assertNotNull($gameRun->getPendingHeroOffer());
        self::assertNull($gameRun->getCurrentShop(), 'La boutique reste fermée tant que l\'offre est en attente.');
    }

    public function testApplyRecordedRoundClearsTheShopWhenTheRoundEndsTheRun(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->recordDefeat();
        $gameRun->recordDefeat();

        $gameRun->applyRecordedRound(RoundOutcome::DEFEAT);

        self::assertTrue($gameRun->isOver());
        self::assertNull($gameRun->getCurrentShop());
    }

    /**
     * Les deux gardes de `playRound()` valent aussi pour le rejeu.
     *
     * Elles ne sont pas décoratives ici : un journal qui contiendrait une
     * manche de trop, ou une manche avant un choix de héros, est un journal
     * corrompu, et le rejeu doit s'arrêter plutôt que produire un état que le
     * jeu n'aurait jamais pu atteindre.
     */
    public function testApplyRecordedRoundRefusesWhenTheRunIsAlreadyOver(): void
    {
        $gameRun = $this->createGameRun();

        for ($i = 0; $i < 10; $i++) {
            $gameRun->recordVictory();
        }

        $this->expectException(\LogicException::class);

        $gameRun->applyRecordedRound(RoundOutcome::VICTORY);
    }

    public function testApplyRecordedRoundRefusesWhenAHeroOfferIsPending(): void
    {
        $gameRun = $this->createRawGameRun();

        self::assertNotNull($gameRun->getPendingHeroOffer(), 'Précondition : offre initiale en attente.');

        $this->expectException(\LogicException::class);

        $gameRun->applyRecordedRound(RoundOutcome::VICTORY);
    }

    public function testGetLastRoundOutcomeIsNullBeforeAnyRoundIsPlayed(): void
    {
        $gameRun = $this->createGameRun();

        self::assertNull($gameRun->getLastRoundOutcome());
    }

    /**
     * C'est `playRound()` qui produit l'issue à journaliser.
     *
     * Le contrôleur ne la recalcule pas à partir du `SimulationResult` : il la
     * lit ici. Une seconde dérivation du vainqueur, côté Http, serait un
     * second endroit où l'issue peut se tromper.
     *
     * `DEFEAT` est une **caractérisation** : sur la graine 1, avec un roster
     * d'un héros et un inventaire vide, le joueur n'inflige aucun dégât face à
     * un adversaire scripté équipé dès la manche 1. Un rééquilibrage du
     * catalogue peut la faire basculer, et ce test doit alors être relu.
     */
    public function testPlayRoundExposesTheOutcomeItJustProduced(): void
    {
        $gameRun = $this->createGameRun();

        $gameRun->playRound();

        self::assertSame(RoundOutcome::DEFEAT, $gameRun->getLastRoundOutcome());
    }
}
