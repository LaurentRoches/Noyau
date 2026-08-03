<?php

declare(strict_types=1);

namespace App\Tests\Domain;

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
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class TickEngineTest extends TestCase
{
    private function createBoard(array $items): CombatBoard
    {
        $heroDef = new Hero(
            'shadow_bearer',
            "Shadow's Bearer",
            'shadow',
            100,
            0,
            6
        );

        return new CombatBoard(new CombatHero($heroDef), $items);
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

        $this->assertSame(0, $context->getCurrentTick());

        $engine->tick($context);

        $this->assertSame(1, $context->getCurrentTick());
        $this->assertSame(1, $playerDagger->getCooldown());
        $this->assertSame(1, $playerCloak->getCooldown());
        $this->assertSame(3, $opponentHammer->getCooldown());
    }

    public function testTickTriggersReadyItemsAndResetsCooldown(): void
    {
        // Arrange : 1 objet avec cooldown 1 (sera prêt après 1 tick)
        $action = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $effect = new Effect(trigger: Trigger::EVERY_N_TICKS, actions: [$action]);
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

        // Act
        $pendingActions = $engine->tick($context);

        // Assert
        $this->assertCount(1, $pendingActions);
        $this->assertSame($action, $pendingActions[0]->action);
        $this->assertSame($item, $pendingActions[0]->sourceItem);
        $this->assertSame($playerBoard, $pendingActions[0]->sourceBoard);

        // Vérifie la réinitialisation du cooldown
        $this->assertSame(1, $item->getCooldown());
    }
}
