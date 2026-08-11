<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\SimulationContext;
use App\Domain\Engine\StatusProcessor;
use App\Domain\Enum\EventType;
use App\Domain\Enum\StatusType;
use App\Domain\Model\Hero;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class StatusProcessorTest extends TestCase
{
    private function createBoard(string $vestigeId, string $heroId): CombatBoard
    {
        $vestigeDef = new Vestige($vestigeId, "Vestige {$vestigeId}", 'shadow', 100, 20);
        $heroDef = new Hero($heroId, "Hero {$heroId}", 'shadow', 6);

        return new CombatBoard(new CombatVestige($vestigeDef), [new CombatHero($heroDef)], []);
    }

    public function testProcessTickAppliesPoisonDamageBypassingShieldAndReturnsEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 20)
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        $processor = new StatusProcessor();
        $events = $processor->processTick($context);

        $this->assertSame(97, $playerBoard->getVestige()->getHp());
        $this->assertSame(20, $playerBoard->getVestige()->getShield());

        $this->assertCount(1, $events);
        $this->assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        $this->assertSame(1, $events[0]->tick);
        $this->assertSame([
            'status' => 'POISON',
            'amount' => 3,
            'shieldDamage' => 0,
            'hpDamage' => 3,
            'remainingStacks' => 3,
            'remainingTicks' => 19,
            'target' => 'player_vestige',
        ], $events[0]->payload);
    }

    public function testProcessTickDecrementsRemainingTicksOnEachCall(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 5)
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        $processor = new StatusProcessor();

        $eventsTick1 = $processor->processTick($context);
        $this->assertSame(4, $eventsTick1[0]->payload['remainingTicks']);

        $context->advanceTick();
        $eventsTick2 = $processor->processTick($context);
        $this->assertSame(3, $eventsTick2[0]->payload['remainingTicks']);
    }

    public function testProcessTickExpiresStatusAndPurgesItFromVestige(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 1)
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        $processor = new StatusProcessor();
        $events = $processor->processTick($context);

        $this->assertCount(2, $events);

        $this->assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        $this->assertSame(0, $events[0]->payload['remainingTicks']);

        $this->assertSame(EventType::STATUS_EXPIRED, $events[1]->type);
        $this->assertSame([
            'status' => 'POISON',
            'target' => 'player_vestige',
        ], $events[1]->payload);

        $this->assertCount(0, $playerBoard->getVestige()->getStatuses());
    }

    public function testProcessTickAppliesBurnDamageThroughShieldAndReturnsEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        // Vestige a 20 de bouclier (défini dans createBoard)
        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::BURN, stacks: 5, durationTicks: 20)
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        $processor = new StatusProcessor();
        $events = $processor->processTick($context);

        // 5 dégâts de Burn, absorbés entièrement par les 20 de bouclier
        $this->assertSame(100, $playerBoard->getVestige()->getHp());
        $this->assertSame(15, $playerBoard->getVestige()->getShield());

        $this->assertCount(1, $events);
        $this->assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        $this->assertSame([
            'status' => 'BURN',
            'amount' => 5,
            'shieldDamage' => 5,
            'hpDamage' => 0,
            'remainingStacks' => 5,
            'remainingTicks' => 19,
            'target' => 'player_vestige',
        ], $events[0]->payload);
    }
}
