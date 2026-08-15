<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\CombatLog;
use App\Domain\Engine\SimulationContext;
use App\Domain\Model\Hero;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class SimulationContextTest extends TestCase
{
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
            []
        );
    }

    public function testContextInitialStateAndTickAdvancement(): void
    {
        $playerBoard = $this->createBoard();
        $opponentBoard = $this->createBoard();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(12345));

        $context = new SimulationContext($playerBoard, $opponentBoard, $randomizer);

        self::assertSame($playerBoard, $context->getPlayerBoard());
        self::assertSame($opponentBoard, $context->getOpponentBoard());
        self::assertSame($randomizer, $context->getRandomizer());
        self::assertInstanceOf(CombatLog::class, $context->getLog());
        self::assertSame(0, $context->getCurrentTick());

        $context->advanceTick();

        self::assertSame(1, $context->getCurrentTick());
    }

    public function testGetBoardsReturnsBothBoardsInArray(): void
    {
        $playerBoard = $this->createBoard();
        $opponentBoard = $this->createBoard();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));
        $context = new SimulationContext($playerBoard, $opponentBoard, $randomizer);

        $boards = $context->getBoards();

        self::assertCount(2, $boards);
        self::assertSame($playerBoard, $boards[0]);
        self::assertSame($opponentBoard, $boards[1]);
    }

    public function testGetOppositeBoardReturnsTheOtherBoard(): void
    {
        $playerBoard = $this->createBoard();
        $opponentBoard = $this->createBoard();
        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        self::assertSame($opponentBoard, $context->getOppositeBoard($playerBoard));
        self::assertSame($playerBoard, $context->getOppositeBoard($opponentBoard));
    }

    public function testGetOppositeBoardThrowsExceptionForUnknownBoard(): void
    {
        $context = new SimulationContext(
            $this->createBoard(),
            $this->createBoard(),
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $unknownBoard = $this->createBoard();

        $this->expectException(\InvalidArgumentException::class);
        $context->getOppositeBoard($unknownBoard);
    }
}
