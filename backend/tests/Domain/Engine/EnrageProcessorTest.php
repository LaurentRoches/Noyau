<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\EnrageProcessor;
use App\Domain\Engine\SimulationContext;
use App\Domain\Enum\EventType;
use App\Domain\Model\Hero;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class EnrageProcessorTest extends TestCase
{
    private function createBoard(string $id, int $baseHp = 1000, int $baseShield = 0): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: $id,
            name: "Vestige {$id}",
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: $baseShield,
            startingGold: 0,
            startingIncome: 0
        );
        $heroDef = new Hero(
            id: "{$id}_hero",
            name: "Hero {$id}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(new CombatVestige($vestigeDef), [new CombatHero($heroDef)], []);
    }

    private function contextAtTick(int $tick, CombatBoard $playerBoard, CombatBoard $opponentBoard): SimulationContext
    {
        $context = new SimulationContext($playerBoard, $opponentBoard, new Randomizer(new PcgOneseq128XslRr64(1)));

        for ($i = 0; $i < $tick; $i++) {
            $context->advanceTick();
        }

        return $context;
    }

    public function testProcessTickReturnsNoEventsBeforeTriggerTick(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10);
        $context = $this->contextAtTick(9, $this->createBoard('player'), $this->createBoard('opponent'));

        self::assertSame([], $processor->processTick($context));
    }

    public function testProcessTickAppliesDamageToShieldBeforeHpOnBothBoards(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10, baseDamage: 5);
        $playerBoard = $this->createBoard('player', baseShield: 20);
        $opponentBoard = $this->createBoard('opponent', baseShield: 0);
        $context = $this->contextAtTick(10, $playerBoard, $opponentBoard);

        $events = $processor->processTick($context);

        self::assertCount(2, $events);
        self::assertSame(EventType::ENRAGE_DAMAGE_DEALT, $events[0]->type);
        self::assertSame(15, $playerBoard->getVestige()->getShield());
        self::assertSame(1000, $playerBoard->getVestige()->getHp());
        self::assertSame(995, $opponentBoard->getVestige()->getHp());
        self::assertSame([
            'amount' => 5,
            'shieldDamage' => 5,
            'hpDamage' => 0,
            'target' => 'player',
        ], $events[0]->payload);
    }

    public function testProcessTickDamageDoublesEachTickAfterTrigger(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10, baseDamage: 5);
        $context = $this->contextAtTick(13, $this->createBoard('player'), $this->createBoard('opponent'));

        $events = $processor->processTick($context);

        // stage = 13 - 10 = 3 -> 5 * 2^3 = 40
        self::assertSame(40, $events[0]->payload['amount']);
    }

    public function testProcessTickStopsBeforeSecondBoardWhenFirstDies(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10, baseDamage: 100);
        $playerBoard = $this->createBoard('player', baseHp: 50);
        $opponentBoard = $this->createBoard('opponent', baseHp: 50);
        $context = $this->contextAtTick(10, $playerBoard, $opponentBoard);

        $events = $processor->processTick($context);

        self::assertCount(1, $events, 'Le second board ne doit pas être frappé une fois le premier tué.');
        self::assertFalse($playerBoard->getVestige()->isAlive());
        self::assertTrue($opponentBoard->getVestige()->isAlive());
    }
}
