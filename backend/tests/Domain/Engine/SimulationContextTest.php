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

    private function createBoard(): CombatBoard
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
            id: 'shadow_bearer',
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
