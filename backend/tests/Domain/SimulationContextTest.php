<?php

declare(strict_types=1);

namespace App\Tests\Domain;

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
        $vestigeDef = new Vestige('shadow_vestige', 'Shadow Vestige', 'shadow', 100, 0);
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

        $this->assertSame($playerBoard, $context->getPlayerBoard());
        $this->assertSame($opponentBoard, $context->getOpponentBoard());
        $this->assertSame($randomizer, $context->getRandomizer());
        $this->assertInstanceOf(CombatLog::class, $context->getLog());
        $this->assertSame(0, $context->getCurrentTick());

        $context->advanceTick();

        $this->assertSame(1, $context->getCurrentTick());
    }

    public function testGetBoardsReturnsBothBoardsInArray(): void
    {
        $playerBoard = $this->createBoard();
        $opponentBoard = $this->createBoard();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));
        $context = new SimulationContext($playerBoard, $opponentBoard, $randomizer);

        $boards = $context->getBoards();

        $this->assertCount(2, $boards);
        $this->assertSame($playerBoard, $boards[0]);
        $this->assertSame($opponentBoard, $boards[1]);
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

        $this->assertSame($opponentBoard, $context->getOppositeBoard($playerBoard));
        $this->assertSame($playerBoard, $context->getOppositeBoard($opponentBoard));
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
