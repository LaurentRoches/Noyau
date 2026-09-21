<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\ActionProcessor;
use App\Domain\Engine\PendingAction;
use App\Domain\Engine\SimulationContext;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
use App\Domain\Enum\ItemSize;
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

final class ActionProcessorTest extends TestCase
{
    private const string COMBAT_SEED = '2a1fc9b42d6f7deabb34ec8d303950e95a203eb05bfec19c42e1eb7ac1fca71a';

    /**
     * @param list<CombatItem> $items
     */
    private function createBoard(string $vestigeId, string $heroId, array $items = []): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: $vestigeId,
            name: "Vestige {$vestigeId}",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            startingGold: 0,
            startingIncome: 0
        );
        $heroDef = new Hero(
            id: $heroId,
            name: "Hero {$heroId}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            [new CombatHero($heroDef)],
            $items,
            goldAtCombatStart: 0
        );
    }

    private function createItem(): CombatItem
    {
        $itemDef = new Item(
            id: 'shadow_dagger',
            name: 'Shadow Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
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
            self::COMBAT_SEED
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
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );
        $sourceItem = $this->createItem();
        $pendingAction = new PendingAction($action, $sourceItem, $playerBoard);

        $processor = new ActionProcessor();

        $event = $processor->process($pendingAction, $context);

        self::assertSame(85, $opponentBoard->getVestige()->getHp());
        self::assertSame(1, $event->tick);
        self::assertSame(EventType::DAMAGE_DEALT, $event->type);
        self::assertSame([
            'amount' => 15,
            'shieldDamage' => 0,
            'hpDamage' => 15,
            'target' => 'opponent_vestige',
            // Le côté n'est plus « A, c'est le joueur » : il est attribué par
            // comparaison des photographies (D-19). Ce test porte sur le fait
            // que la charge utile transporte le côté que le contexte a
            // attribué, pas sur la lettre — celle-ci est épinglée par
            // SimulationContextTest, seul endroit qui doive la connaître.
            'targetSide' => $context->getSide($opponentBoard)->value,
            'sourceSide' => $context->getSide($playerBoard)->value,
            'sourceItemId' => 'shadow_dagger',
        ], $event->payload);
    }

    public function testProcessGainsShieldOnSelfAndReturnsCombatEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $action = new Action(
            type: ActionType::GAIN_SHIELD,
            value: 20,
            target: Target::SELF
        );
        $sourceItem = $this->createItem();
        $pendingAction = new PendingAction($action, $sourceItem, $playerBoard);

        $processor = new ActionProcessor();

        $event = $processor->process($pendingAction, $context);

        self::assertSame(20, $playerBoard->getVestige()->getShield());
        self::assertSame(1, $event->tick);
        self::assertSame(EventType::SHIELD_GAINED, $event->type);
        self::assertSame([
            'amount' => 20,
            'shieldGained' => 20,
            'target' => 'player_vestige',
            'targetSide' => $context->getSide($playerBoard)->value,
            'sourceSide' => $context->getSide($playerBoard)->value,
            'sourceItemId' => 'shadow_dagger',
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
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $action = new Action(
            type: ActionType::HEAL,
            value: 30,
            target: Target::SELF
        );
        $sourceItem = $this->createItem();
        $pendingAction = new PendingAction($action, $sourceItem, $playerBoard);

        $processor = new ActionProcessor();

        $event = $processor->process($pendingAction, $context);

        self::assertSame(100, $playerBoard->getVestige()->getHp());
        self::assertSame(1, $event->tick);
        self::assertSame(EventType::HEAL_RECEIVED, $event->type);
        self::assertSame([
            'amount' => 30,
            'hpHealed' => 20,
            'poisonCleansed' => 0,
            'burnCleansed' => 0,
            'target' => 'player_vestige',
            'targetSide' => $context->getSide($playerBoard)->value,
            'sourceSide' => $context->getSide($playerBoard)->value,
            'sourceItemId' => 'shadow_dagger',
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
        $opponentBoard = $context->getOpponentBoard();
        $opponentVestige = $opponentBoard->getVestige();

        self::assertCount(1, $opponentVestige->getStatusInstances(StatusType::POISON));
        self::assertSame(EventType::STATUS_APPLIED, $event->type);
        self::assertSame([
            'status' => 'POISON',
            'stacksApplied' => 2,
            'durationTicksApplied' => 30,
            'totalStacks' => 2,
            'remainingTicks' => 30,
            'target' => $opponentVestige->getId(),
            'targetSide' => $context->getSide($opponentBoard)->value,
            'sourceSide' => $context->getSide($context->getPlayerBoard())->value,
            'sourceItemId' => 'shadow_dagger',
        ], $event->payload);
    }

    public function testProcessApplyStatusAddsASecondInstanceAndReturnsAggregatedEvent(): void
    {
        $processor = new ActionProcessor();
        $context = $this->createSimulationContext();
        $opponentBoard = $context->getOpponentBoard();
        $opponentVestige = $opponentBoard->getVestige();

        $opponentVestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 20, sourceId: 'nightfang'));

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

        // Deux instances indépendantes, aucune fusion (D-20). La charge utile
        // de l'événement est pourtant inchangée : somme 3 + 2 = 5, maximum
        // max(20, 35) = 35, exactement ce que produisait mergeWith().
        $instances = $opponentVestige->getStatusInstances(StatusType::POISON);
        self::assertCount(2, $instances);
        self::assertSame('nightfang', $instances[0]->getSourceId());
        self::assertSame(3, $instances[0]->getStacks());
        self::assertSame(20, $instances[0]->getRemainingTicks());
        self::assertSame('shadow_dagger', $instances[1]->getSourceId());
        self::assertSame(2, $instances[1]->getStacks());
        self::assertSame(35, $instances[1]->getRemainingTicks());

        self::assertSame([
            'status' => 'POISON',
            'stacksApplied' => 2,
            'durationTicksApplied' => 35,
            'totalStacks' => 5,
            'remainingTicks' => 35,
            'target' => $opponentVestige->getId(),
            'targetSide' => $context->getSide($opponentBoard)->value,
            'sourceSide' => $context->getSide($context->getPlayerBoard())->value,
            'sourceItemId' => 'shadow_dagger',
        ], $event->payload);
    }

    public function testProcessHealCleansesOneStackOfEachHostileStatus(): void
    {
        $processor = new ActionProcessor();
        $context = $this->createSimulationContext();
        $playerVestige = $context->getPlayerBoard()->getVestige();

        $playerVestige->takeRawDamage(20);
        $playerVestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 30, sourceId: 'venomous_vial'));
        $playerVestige->applyStatus(new ActiveStatus(StatusType::BURN, stacks: 4, durationTicks: 20, sourceId: 'firesteel'));
        $playerVestige->applyStatus(new ActiveStatus(StatusType::WARD, stacks: 2, durationTicks: 30, sourceId: 'shadow_armor'));

        $pendingAction = new PendingAction(
            action: new Action(type: ActionType::HEAL, value: 10, target: Target::SELF),
            sourceItem: $context->getPlayerBoard()->getItems()[0],
            sourceBoard: $context->getPlayerBoard()
        );

        $event = $processor->process($pendingAction, $context);

        self::assertSame(2, $playerVestige->getAggregatedStatus(StatusType::POISON)->stacks);
        self::assertSame(3, $playerVestige->getAggregatedStatus(StatusType::BURN)->stacks);
        self::assertSame(2, $playerVestige->getAggregatedStatus(StatusType::WARD)->stacks);

        self::assertSame(1, $event->payload['poisonCleansed']);
        self::assertSame(1, $event->payload['burnCleansed']);
        self::assertSame(10, $event->payload['hpHealed']);
    }

    public function testProcessHealCleansesEvenAtFullHealth(): void
    {
        // D-21, règle 2 : le nettoyage porte sur le soin TENTÉ. Sans cela, un
        // Vestige à pleine vie ne pourrait jamais se nettoyer, receiveHeal()
        // plafonnant à baseHp.
        $processor = new ActionProcessor();
        $context = $this->createSimulationContext();
        $playerVestige = $context->getPlayerBoard()->getVestige();

        $playerVestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 30, sourceId: 'venomous_vial'));

        $pendingAction = new PendingAction(
            action: new Action(type: ActionType::HEAL, value: 25, target: Target::SELF),
            sourceItem: $context->getPlayerBoard()->getItems()[0],
            sourceBoard: $context->getPlayerBoard()
        );

        $event = $processor->process($pendingAction, $context);

        self::assertSame(100, $playerVestige->getHp());
        self::assertSame(0, $event->payload['hpHealed']);
        self::assertSame(1, $event->payload['poisonCleansed']);
        self::assertSame(2, $playerVestige->getAggregatedStatus(StatusType::POISON)->stacks);
    }

    public function testProcessHealCleansesEvenWithAZeroValue(): void
    {
        // Lecture littérale de D-21 : « un déclenchement de l'action HEAL »
        // retire un stack. L'action s'est déclenchée, donc le nettoyage a lieu,
        // quelle que soit la valeur. Aucun objet du catalogue n'a de HEAL à 0 ;
        // ce test fige la lecture plutôt qu'un cas de jeu réel.
        $processor = new ActionProcessor();
        $context = $this->createSimulationContext();
        $playerVestige = $context->getPlayerBoard()->getVestige();

        $playerVestige->applyStatus(new ActiveStatus(StatusType::BURN, stacks: 2, durationTicks: 20, sourceId: 'firesteel'));

        $pendingAction = new PendingAction(
            action: new Action(type: ActionType::HEAL, value: 0, target: Target::SELF),
            sourceItem: $context->getPlayerBoard()->getItems()[0],
            sourceBoard: $context->getPlayerBoard()
        );

        $event = $processor->process($pendingAction, $context);

        self::assertSame(0, $event->payload['hpHealed']);
        self::assertSame(1, $event->payload['burnCleansed']);
        self::assertSame(1, $playerVestige->getAggregatedStatus(StatusType::BURN)->stacks);
    }
}
