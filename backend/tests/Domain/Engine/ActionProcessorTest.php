<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\ActionProcessor;
use App\Domain\Engine\PendingAction;
use App\Domain\Engine\SimulationContext;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Model\Action;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class ActionProcessorTest extends TestCase
{
    /**
     * @param list<CombatItem> $items
     */
    private function createBoard(string $vestigeId, string $heroId, array $items = []): CombatBoard
    {
        $vestigeDef = new Vestige($vestigeId, "Vestige {$vestigeId}", 'shadow', 100, 0);
        $heroDef = new Hero(
            id: $heroId,
            name: "Hero {$heroId}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            [new CombatHero($heroDef)],
            $items
        );
    }

    private function createItem(): CombatItem
    {
        $itemDef = new Item(
            id: 'shadow_dagger',
            name: 'Shadow Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: []
        );

        return new CombatItem($itemDef);
    }

    private function createSimulationContext(): SimulationContext
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero', [$this->createItem()]);
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        return $context;
    }

    public function testProcessDealsDamageToEnemyVestigeAndReturnsCombatEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        $action = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $sourceItem = $this->createItem();
        $pendingAction = new PendingAction($action, $sourceItem, $playerBoard);

        $processor = new ActionProcessor();

        $event = $processor->process($pendingAction, $context);

        $this->assertSame(85, $opponentBoard->getVestige()->getHp());
        $this->assertSame(1, $event->tick);
        $this->assertSame(EventType::DAMAGE_DEALT, $event->type);
        $this->assertSame([
            'amount' => 15,
            'shieldDamage' => 0,
            'hpDamage' => 15,
            'target' => 'opponent_vestige',
        ], $event->payload);
    }

    public function testProcessGainsShieldOnSelfAndReturnsCombatEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        $action = new Action(type: ActionType::GAIN_SHIELD, value: 20, target: Target::SELF);
        $sourceItem = $this->createItem();
        $pendingAction = new PendingAction($action, $sourceItem, $playerBoard);

        $processor = new ActionProcessor();

        $event = $processor->process($pendingAction, $context);

        $this->assertSame(20, $playerBoard->getVestige()->getShield());
        $this->assertSame(1, $event->tick);
        $this->assertSame(EventType::SHIELD_GAINED, $event->type);
        $this->assertSame([
            'amount' => 20,
            'shieldGained' => 20,
            'target' => 'player_vestige',
        ], $event->payload);
    }

    public function testProcessHealsSelfAndReturnsCombatEventWithCappedHp(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $playerBoard->getVestige()->takeDamage(20);

        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick();

        $action = new Action(type: ActionType::HEAL, value: 30, target: Target::SELF);
        $sourceItem = $this->createItem();
        $pendingAction = new PendingAction($action, $sourceItem, $playerBoard);

        $processor = new ActionProcessor();

        $event = $processor->process($pendingAction, $context);

        $this->assertSame(100, $playerBoard->getVestige()->getHp());
        $this->assertSame(1, $event->tick);
        $this->assertSame(EventType::HEAL_RECEIVED, $event->type);
        $this->assertSame([
            'amount' => 30,
            'hpHealed' => 20,
            'target' => 'player_vestige',
        ], $event->payload);
    }

    public function testProcessApplyStatusAppliesNewStatusToTargetVestigeAndReturnsEvent(): void
    {
        $processor = new ActionProcessor();
        $context = $this->createSimulationContext();

        $action = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 2,
            durationTicks: 30
        );

        $pendingAction = new PendingAction(
            action: $action,
            sourceItem: $context->getPlayerBoard()->getItems()[0],
            sourceBoard: $context->getPlayerBoard()
        );

        $event = $processor->process($pendingAction, $context);
        $opponentVestige = $context->getOpponentBoard()->getVestige();

        $this->assertCount(1, $opponentVestige->getStatuses());
        $this->assertSame(EventType::STATUS_APPLIED, $event->type);
        $this->assertSame([
            'status' => 'POISON',
            'stacksApplied' => 2,
            'durationTicksApplied' => 30,
            'totalStacks' => 2,
            'remainingTicks' => 30,
            'target' => $opponentVestige->getId(),
        ], $event->payload);
    }

    public function testProcessApplyStatusMergesWithExistingStatusAndReturnsUpdatedEvent(): void
    {
        $processor = new ActionProcessor();
        $context = $this->createSimulationContext();
        $opponentVestige = $context->getOpponentBoard()->getVestige();

        $opponentVestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 20));

        $action = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 2,
            durationTicks: 35
        );

        $pendingAction = new PendingAction(
            action: $action,
            sourceItem: $context->getPlayerBoard()->getItems()[0],
            sourceBoard: $context->getPlayerBoard()
        );

        $event = $processor->process($pendingAction, $context);

        $this->assertCount(1, $opponentVestige->getStatuses());
        $this->assertSame(5, $opponentVestige->getStatuses()[0]->getStacks());
        $this->assertSame(35, $opponentVestige->getStatuses()[0]->getRemainingTicks());

        $this->assertSame([
            'status' => 'POISON',
            'stacksApplied' => 2,
            'durationTicksApplied' => 35,
            'totalStacks' => 5,
            'remainingTicks' => 35,
            'target' => $opponentVestige->getId(),
        ], $event->payload);
    }
}
