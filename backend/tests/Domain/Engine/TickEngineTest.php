<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\EventDispatcher;
use App\Domain\Engine\SimulationContext;
use App\Domain\Engine\TickEngine;
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
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class TickEngineTest extends TestCase
{
    private function createBoard(array $items): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            startingGold: 0
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

    private function createItem(string $id, int $cooldownTicks, array $effects = []): CombatItem
    {
        $itemDef = new Item(
            id: $id,
            name: "Test Item {$id}",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: $cooldownTicks,
            effects: $effects
        );

        return new CombatItem($itemDef);
    }

    public function testTickAdvancesTimeAndDecrementsCooldownsAccrossBothBoards(): void
    {
        $playerDagger = $this->createItem('dagger', 2);
        $playerCloak = $this->createItem('cloak', 1);
        $opponentHammer = $this->createItem('hammer', 4);

        $playerBoard = $this->createBoard([$playerDagger, $playerCloak]);
        $opponentBoard = $this->createBoard([$opponentHammer]);

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(42))
        );

        $dispatcher = new EventDispatcher();
        $engine = new TickEngine($dispatcher);

        self::assertSame(0, $context->getCurrentTick());

        $engine->tick($context);

        self::assertSame(1, $context->getCurrentTick());
        self::assertSame(1, $playerDagger->getCooldown());
        self::assertSame(1, $playerCloak->getCooldown());
        self::assertSame(3, $opponentHammer->getCooldown());
    }

    public function testTickTriggersReadyItemsAndResetsCooldown(): void
    {
        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );
        $effect = new Effect(
            trigger: Trigger::EVERY_N_TICKS,
            actions: [$action]
        );
        $item = $this->createItem('dagger', cooldownTicks: 1, effects: [$effect]);

        $playerBoard = $this->createBoard([$item]);
        $opponentBoard = $this->createBoard([]);

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($playerBoard);

        $engine = new TickEngine($dispatcher);

        $pendingActions = $engine->tick($context);

        self::assertCount(1, $pendingActions);
        self::assertSame($action, $pendingActions[0]->action);
        self::assertSame($item, $pendingActions[0]->sourceItem);
        self::assertSame($playerBoard, $pendingActions[0]->sourceBoard);

        self::assertSame(1, $item->getCooldown());
    }
}
