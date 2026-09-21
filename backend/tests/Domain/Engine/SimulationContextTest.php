<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\CombatLog;
use App\Domain\Engine\SimulationContext;
use App\Domain\Enum\RandomStream;
use App\Domain\Enum\Side;
use App\Domain\Model\Hero;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class SimulationContextTest extends TestCase
{
    private const string SEED = '2a1fc9b42d6f7deabb34ec8d303950e95a203eb05bfec19c42e1eb7ac1fca71a';

    private function createBoard(string $heroId = 'shadow_bearer'): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            startingGold: 0,
            startingIncome: 0
        );
        $heroDef = new Hero(
            id: $heroId,
            name: "Shadow's Bearer",
            affinity: 'shadow',
            itemSlots: 6,
        );

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            [new CombatHero($heroDef)],
            [],
            goldAtCombatStart: 0
        );
    }

    private function createContext(
        ?CombatBoard $playerBoard = null,
        ?CombatBoard $opponentBoard = null,
    ): SimulationContext {
        return new SimulationContext(
            $playerBoard ?? $this->createBoard(),
            $opponentBoard ?? $this->createBoard(),
            self::SEED,
        );
    }

    public function testContextInitialStateAndTickAdvancement(): void
    {
        $playerBoard = $this->createBoard();
        $opponentBoard = $this->createBoard();

        $context = new SimulationContext($playerBoard, $opponentBoard, self::SEED);

        self::assertSame($playerBoard, $context->getPlayerBoard());
        self::assertSame($opponentBoard, $context->getOpponentBoard());
        self::assertInstanceOf(CombatLog::class, $context->getLog());
        self::assertSame(0, $context->getCurrentTick());

        $context->advanceTick();

        self::assertSame(1, $context->getCurrentTick());
    }

    public function testGetBoardsReturnsBothBoardsInArray(): void
    {
        $playerBoard = $this->createBoard();
        $opponentBoard = $this->createBoard();
        $context = new SimulationContext($playerBoard, $opponentBoard, self::SEED);

        $boards = $context->getBoards();

        self::assertCount(2, $boards);
        self::assertSame($playerBoard, $boards[0]);
        self::assertSame($opponentBoard, $boards[1]);
    }

    public function testGetOppositeBoardReturnsTheOtherBoard(): void
    {
        $playerBoard = $this->createBoard();
        $opponentBoard = $this->createBoard();
        $context = new SimulationContext($playerBoard, $opponentBoard, self::SEED);

        self::assertSame($opponentBoard, $context->getOppositeBoard($playerBoard));
        self::assertSame($playerBoard, $context->getOppositeBoard($opponentBoard));
    }

    public function testGetOppositeBoardThrowsExceptionForUnknownBoard(): void
    {
        $context = $this->createContext();

        $this->expectException(\InvalidArgumentException::class);
        $context->getOppositeBoard($this->createBoard());
    }

    public function testGetSideReturnsPlayerForPlayerBoard(): void
    {
        $playerBoard = $this->createBoard();
        $context = new SimulationContext($playerBoard, $this->createBoard(), self::SEED);

        self::assertSame(Side::A, $context->getSide($playerBoard));
    }

    public function testGetSideReturnsOpponentForOpponentBoard(): void
    {
        $opponentBoard = $this->createBoard();
        $context = new SimulationContext($this->createBoard(), $opponentBoard, self::SEED);

        self::assertSame(Side::B, $context->getSide($opponentBoard));
    }

    public function testGetSideThrowsExceptionForUnknownBoard(): void
    {
        $context = $this->createContext();

        $this->expectException(\InvalidArgumentException::class);
        $context->getSide($this->createBoard());
    }

    // === Attribution canonique des côtés — D-19, `04` §3.6 =================

    /**
     * **Le cœur de D-19.** Le côté d'un plateau ne dépend plus de la place
     * qu'il occupe dans l'appel.
     *
     * Pourquoi c'est nécessaire : le tirage d'initiative de D-14 désigne « A ».
     * Tant que l'appelant décide qui est A, inverser les deux plateaux avec la
     * même graine inverse l'initiative et peut changer le vainqueur — le
     * défaut même que D-14 corrige, réintroduit par une autre porte. En PvP,
     * où le serveur simule une seule fois pour deux joueurs, il n'existe de
     * surcroît aucun « premier » plateau légitime.
     *
     * La règle : **A est le plateau dont la photographie canonique est la plus
     * petite en octets.** « Plus petite » n'a aucun sens de jeu — la
     * comparaison est lexicale, donc un or de 10 passe avant un or de 9. Sans
     * importance : §3.6 n'a besoin que d'un ordre total et déterministe, pas
     * d'un ordre signifiant.
     *
     * Ici `alpha_hero` trie avant `beta_hero`, et la clé `heroes` précède
     * `vestige` dans l'ordre canonique.
     */
    public function testTheSmallerPhotographTakesSideAWhicheverArgumentItCameIn(): void
    {
        $alpha = $this->createBoard('alpha_hero');
        $beta = $this->createBoard('beta_hero');

        $alphaFirst = new SimulationContext($alpha, $beta, self::SEED);
        $betaFirst = new SimulationContext($beta, $alpha, self::SEED);

        self::assertSame(Side::A, $alphaFirst->getSide($alpha));
        self::assertSame(Side::B, $alphaFirst->getSide($beta));

        // Les mêmes plateaux, passés dans l'autre sens : même attribution.
        self::assertSame(Side::A, $betaFirst->getSide($alpha));
        self::assertSame(Side::B, $betaFirst->getSide($beta));
    }

    /**
     * Le cas que la règle de `04` §3.6 ne sait pas trancher.
     *
     * §3.6 annonce un départage « par un identifiant de combat enregistré avec
     * les données d'entrée ». **Cette clause est inapplicable** : l'identifiant
     * de combat est une valeur unique, partagée par les deux plateaux, pas une
     * valeur par plateau. Aucune fonction de (photoA, photoB, combatId) ne peut
     * ordonner deux photographies égales. §3.6 avait d'ailleurs déjà écarté les
     * identifiants par plateau au paragraphe précédent, l'adversaire scripté
     * n'en ayant pas.
     *
     * L'ordre des arguments tranche donc, faute de mieux. **Ce n'est pas
     * anodin** : en miroir, le journal est identique dans les deux sens mais il
     * désigne « A » comme vainqueur — donc l'ordre décide quel joueur gagne. La
     * contrainte qui en découle appartient au commit PvP : cet ordre devra
     * venir d'une donnée enregistrée avant la simulation, jamais d'un rangement
     * local. Anomalie E-14.
     */
    public function testByteIdenticalPhotographsAreSeparatedByArgumentOrder(): void
    {
        $first = $this->createBoard();
        $second = $this->createBoard();

        $context = new SimulationContext($first, $second, self::SEED);

        self::assertSame(Side::A, $context->getSide($first));
        self::assertSame(Side::B, $context->getSide($second));
    }

    /**
     * L'attribution est figée à la construction, pas recalculée à chaque appel.
     *
     * `Simulator::groupActionsBySide()` appelle `getSide()` une fois par action
     * en attente, à chaque tick. Une attribution recalculée sur l'état courant
     * basculerait au premier point de dégât, et le journal deviendrait
     * incohérent avec lui-même — un plateau nommé A au tick 3 et B au tick 4.
     *
     * `BoardSnapshot` ne lit déjà que des objets immuables, ce qui rend le
     * défaut impossible ; ce test le vérifie au niveau où il se verrait.
     */
    public function testTheAssignmentDoesNotMoveWhenTheBoardsTakeDamage(): void
    {
        $alpha = $this->createBoard('alpha_hero');
        $beta = $this->createBoard('beta_hero');
        $context = new SimulationContext($alpha, $beta, self::SEED);

        self::assertSame(Side::A, $context->getSide($alpha));

        $alpha->getVestige()->takeDamage(60);
        $beta->getVestige()->takeDamage(5);

        self::assertSame(Side::A, $context->getSide($alpha));
        self::assertSame(Side::B, $context->getSide($beta));
        self::assertSame($alpha, $context->getBoardOnSide(Side::A));
    }

    // === Flux aléatoires (D-22) ===========================================

    /**
     * Le test central de ce commit.
     *
     * Le contexte doit rendre **la même instance** à chaque appel pour un flux
     * donné. Une redérivation rendrait un `Randomizer` neuf, repartant du
     * premier tirage : l'ordre d'initiative serait figé d'un tick à l'autre.
     * Le combat resterait déterministe, le test de parité d'EX-J0-01 passerait,
     * et le défaut ne se verrait qu'en jouant. Voir
     * `RandomStreamTest::testEachDerivationReturnsAFreshStreamRestartingFromTheBeginning`,
     * qui fige le comportement que cette mémoïsation corrige.
     */
    public function testItMemoizesEachStream(): void
    {
        $context = $this->createContext();

        self::assertSame(
            $context->getRandomizer(RandomStream::ORDER),
            $context->getRandomizer(RandomStream::ORDER),
        );
    }

    /**
     * Le symptôme observable de la mémoïsation : le flux avance d'un appel à
     * l'autre au lieu de se réinitialiser.
     *
     * Vingt tirages plutôt qu'un seul : sur un intervalle étroit, deux suites
     * distinctes peuvent commencer par la même valeur, et le test passerait
     * par hasard sur une implémentation fautive.
     */
    public function testAMemoizedStreamAdvancesBetweenCalls(): void
    {
        $context = $this->createContext();

        $first = [];
        $second = [];
        for ($i = 0; $i < 20; $i++) {
            $first[] = $context->getRandomizer(RandomStream::ORDER)->getInt(0, 999);
        }
        for ($i = 0; $i < 20; $i++) {
            $second[] = $context->getRandomizer(RandomStream::ORDER)->getInt(0, 999);
        }

        self::assertNotSame($first, $second);
    }

    public function testTheTwoStreamsAreDistinctInstances(): void
    {
        $context = $this->createContext();

        self::assertNotSame(
            $context->getRandomizer(RandomStream::ORDER),
            $context->getRandomizer(RandomStream::EFFECTS),
        );
    }

    /**
     * Consommer un flux ne doit pas déplacer l'autre. C'est la propriété qui
     * rend l'ajout du critique au chantier 4 sans effet sur les ordres
     * d'initiative déjà produits.
     */
    public function testConsumingOneStreamDoesNotAdvanceTheOther(): void
    {
        $consumed = $this->createContext();
        for ($i = 0; $i < 50; $i++) {
            $consumed->getRandomizer(RandomStream::ORDER)->getInt(0, 999);
        }

        $untouched = $this->createContext();

        self::assertSame(
            $untouched->getRandomizer(RandomStream::EFFECTS)->getInt(0, 999),
            $consumed->getRandomizer(RandomStream::EFFECTS)->getInt(0, 999),
        );
    }

    public function testTwoContextsWithTheSameSeedProduceTheSameStream(): void
    {
        $a = $this->createContext();
        $b = $this->createContext();

        $drawA = [];
        $drawB = [];
        for ($i = 0; $i < 20; $i++) {
            $drawA[] = $a->getRandomizer(RandomStream::ORDER)->getInt(0, 999);
            $drawB[] = $b->getRandomizer(RandomStream::ORDER)->getInt(0, 999);
        }

        self::assertSame($drawA, $drawB);
    }
}
