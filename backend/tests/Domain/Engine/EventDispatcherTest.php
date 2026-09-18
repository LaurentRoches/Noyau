<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\EventDispatcher;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\ItemSize;
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

    /**
     * @param list<Effect> $effects
     */
    private function createItem(array $effects, string $id = 'shadow_dagger'): CombatItem
    {
        $itemDef = new Item(
            id: $id,
            name: 'Shadow Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 4,
            effects: $effects
        );

        return new CombatItem($itemDef);
    }

    public function testRegisterBoardExposesEveryEffectOfAnItem(): void
    {
        // registerBoard() boucle sur les effets de chaque objet. Les deux
        // effets portent des triggers différents, et les deux ressortent :
        // dispatchForItem() ne filtre pas sur le trigger.
        $attackAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $tickAction = new Action(type: ActionType::GAIN_SHIELD, value: 5, target: Target::SELF);

        $item = $this->createItem([
            new Effect(trigger: Trigger::ON_ATTACK, actions: [$attackAction]),
            new Effect(trigger: Trigger::EVERY_N_TICKS, actions: [$tickAction]),
        ]);
        $board = $this->createBoard([$item]);

        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($board);

        $pendingActions = $dispatcher->dispatchForItem($board, $item);

        self::assertCount(2, $pendingActions);
        self::assertSame($attackAction, $pendingActions[0]->action);
        self::assertSame($tickAction, $pendingActions[1]->action);
    }

    public function testDispatchForItemUnfoldsEveryActionOfAnEffect(): void
    {
        // Un effet à plusieurs actions se déplie en autant de PendingAction,
        // chacune conservant son objet et son plateau d'origine.
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $shieldAction = new Action(type: ActionType::GAIN_SHIELD, value: 5, target: Target::SELF);

        $item = $this->createItem([
            new Effect(trigger: Trigger::ON_ATTACK, actions: [$damageAction, $shieldAction]),
        ]);
        $board = $this->createBoard([$item]);

        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($board);

        $pendingActions = $dispatcher->dispatchForItem($board, $item);

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
        $itemA = $this->createItem([$effectA], id: 'dagger_a');

        $actionItemB = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 99,
            target: Target::ENEMY
        );
        $effectB = new Effect(
            trigger: Trigger::EVERY_N_TICKS,
            actions: [$actionItemB]
        );
        $itemB = $this->createItem([$effectB], id: 'dagger_b');

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
