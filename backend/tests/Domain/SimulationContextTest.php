<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Engine\CombatLog;
use App\Domain\Engine\SimulationContext;
use App\Domain\Model\Hero;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class SimulationContextTest extends TestCase
{
    private function createBoard(): CombatBoard
    {
        $heroDef = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            itemSlots: 6,
        );

        return new CombatBoard(new CombatHero($heroDef), []);
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
}
