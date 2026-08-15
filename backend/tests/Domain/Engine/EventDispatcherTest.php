<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\EventDispatcher;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    private function createBoard(array $items = []): CombatBoard
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
            itemSlots: 6
        );

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            [new CombatHero($heroDef)],
            $items
        );
    }

    private function createItem(Effect $effect, string $id = 'shadow_dagger'): CombatItem
    {
        $itemDef = new Item(
            id: $id,
            name: 'Shadow Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: [$effect]
        );

        return new CombatItem($itemDef);
    }

    public function testRegisterSingleListener(): void
    {
        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );

        $effect = new Effect(
            trigger: Trigger::ON_ATTACK,
            actions: [$action]
        );

        $board = $this->createBoard();
        $item = $this->createItem($effect);

        $dispatcher = new EventDispatcher();

        self::assertEmpty($dispatcher->getListenersFor(Trigger::ON_ATTACK));

        $dispatcher->register(Trigger::ON_ATTACK, $board, $item, $effect);

        $listeners = $dispatcher->getListenersFor(Trigger::ON_ATTACK);

        self::assertCount(1, $listeners);
        self::assertSame($board, $listeners[0]['sourceBoard']);
        self::assertSame($item, $listeners[0]['sourceItem']);
        self::assertSame($effect, $listeners[0]['effect']);
    }

    public function testRegisterBoardRegistersAllEffectsFromAllItems(): void
    {
        $attackEffect = new Effect(
            trigger: Trigger::ON_ATTACK,
            actions: [new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY)]
        );

        $defendEffect = new Effect(
            trigger: Trigger::ON_ATTACK,
            actions: [new Action(type: ActionType::GAIN_SHIELD, value: 5, target: Target::SELF)]
        );

        $item1 = $this->createItem($attackEffect);
        $item2 = $this->createItem($defendEffect);

        $board = $this->createBoard([$item1, $item2]);

        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($board);

        $listeners = $dispatcher->getListenersFor(Trigger::ON_ATTACK);

        self::assertCount(2, $listeners);
        self::assertSame($item1, $listeners[0]['sourceItem']);
        self::assertSame($item2, $listeners[1]['sourceItem']);
    }

    public function testDispatchReturnsPendingActionsForTrigger(): void
    {
        $damageAction = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );
        $shieldAction = new Action(
            type: ActionType::GAIN_SHIELD,
            value: 5,
            target: Target::SELF
        );

        $comboEffect = new Effect(
            trigger: Trigger::ON_ATTACK,
            actions: [$damageAction, $shieldAction]
        );

        $board = $this->createBoard();
        $item = $this->createItem($comboEffect);

        $dispatcher = new EventDispatcher();
        $dispatcher->register(Trigger::ON_ATTACK, $board, $item, $comboEffect);

        $pendingActions = $dispatcher->dispatch(Trigger::ON_ATTACK);

        self::assertCount(2, $pendingActions);

        self::assertSame($damageAction, $pendingActions[0]->action);
        self::assertSame($item, $pendingActions[0]->sourceItem);
        self::assertSame($board, $pendingActions[0]->sourceBoard);

        self::assertSame($shieldAction, $pendingActions[1]->action);
        self::assertSame($item, $pendingActions[1]->sourceItem);
        self::assertSame($board, $pendingActions[1]->sourceBoard);
    }

    public function testDispatchForItemOnlyReturnsActionsFromSpecifiedItem(): void
    {
        $actionItemA = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 10,
            target: Target::ENEMY
        );
        $effectA = new Effect(
            trigger: Trigger::EVERY_N_TICKS,
            actions: [$actionItemA]
        );
        $itemA = $this->createItem($effectA, id: 'dagger_a');

        $actionItemB = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 99,
            target: Target::ENEMY
        );
        $effectB = new Effect(
            trigger: Trigger::EVERY_N_TICKS,
            actions: [$actionItemB]
        );
        $itemB = $this->createItem($effectB, id: 'dagger_b');

        $board = $this->createBoard([$itemA, $itemB]);

        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($board);

        $pendingActions = $dispatcher->dispatchForItem($board, $itemA);

        self::assertCount(1, $pendingActions);
        self::assertSame($actionItemA, $pendingActions[0]->action);
        self::assertSame($itemA, $pendingActions[0]->sourceItem);
        self::assertSame($board, $pendingActions[0]->sourceBoard);
    }
}
