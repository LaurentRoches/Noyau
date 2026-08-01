<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Engine\EventDispatcher;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    private function createBoard(): CombatBoard
    {
        $heroDef = new Hero('shadow_bearer', "Shadow's Bearer", 'shadow', 100, 0, 6);
        return new CombatBoard(new CombatHero($heroDef), []);
    }

    private function createItem(Effect $effect): CombatItem
    {
        $itemDef = new Item(
            id: 'shadow_dagger',
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

        $this->assertEmpty($dispatcher->getListenersFor(Trigger::ON_ATTACK));

        $dispatcher->register(Trigger::ON_ATTACK, $board, $item, $effect);

        $listeners = $dispatcher->getListenersFor(Trigger::ON_ATTACK);

        $this->assertCount(1, $listeners);
        $this->assertSame($board, $listeners[0]['sourceBoard']);
        $this->assertSame($item, $listeners[0]['sourceItem']);
        $this->assertSame($effect, $listeners[0]['effect']);
    }

    public function testRegisterBoardRegistersAllEffectsFromAllItems(): void
    {
        $attackEffect = new Effect(
            trigger: Trigger::ON_ATTACK,
            actions: [new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY)]
        );

        $defendEffect = new Effect(
            trigger: Trigger::ON_ATTACK, // Même trigger sur un autre objet
            actions: [new Action(type: ActionType::GAIN_SHIELD, value: 5, target: Target::SELF)]
        );

        $item1 = $this->createItem($attackEffect);
        $item2 = $this->createItem($defendEffect);

        $board = new CombatBoard(
            $this->createBoard()->getHero(),
            [$item1, $item2]
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($board);

        $listeners = $dispatcher->getListenersFor(Trigger::ON_ATTACK);

        $this->assertCount(2, $listeners);
        $this->assertSame($item1, $listeners[0]['sourceItem']);
        $this->assertSame($item2, $listeners[1]['sourceItem']);
    }

    public function testDispatchReturnsPendingActionsForTrigger(): void
    {
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $shieldAction = new Action(type: ActionType::GAIN_SHIELD, value: 5, target: Target::SELF);

        // Un seul effet contenant 2 actions
        $comboEffect = new Effect(
            trigger: Trigger::ON_ATTACK,
            actions: [$damageAction, $shieldAction]
        );

        $board = $this->createBoard();
        $item = $this->createItem($comboEffect);

        $dispatcher = new EventDispatcher();
        $dispatcher->register(Trigger::ON_ATTACK, $board, $item, $comboEffect);

        $pendingActions = $dispatcher->dispatch(Trigger::ON_ATTACK);

        // Vérification : 2 PendingActions générées pour 1 seul listener
        $this->assertCount(2, $pendingActions);

        $this->assertSame($damageAction, $pendingActions[0]->action);
        $this->assertSame($item, $pendingActions[0]->sourceItem);
        $this->assertSame($board, $pendingActions[0]->sourceBoard);

        $this->assertSame($shieldAction, $pendingActions[1]->action);
        $this->assertSame($item, $pendingActions[1]->sourceItem);
        $this->assertSame($board, $pendingActions[1]->sourceBoard);
    }
}
