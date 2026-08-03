<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Engine\ActionProcessor;
use App\Domain\Engine\PendingAction;
use App\Domain\Engine\SimulationContext;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\Target;
use App\Domain\Model\Action;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class ActionProcessorTest extends TestCase
{
    private function createBoard(string $heroId): CombatBoard
    {
        $heroDef = new Hero(
            id: $heroId,
            name: "Hero {$heroId}",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            itemSlots: 6
        );

        return new CombatBoard(new CombatHero($heroDef), []);
    }

    private function createItem(): CombatItem
    {
        $itemDef = new Item(
            id: 'shadow_dagger',
            name:'Shadow Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 4,
            effects: []
        );

        return new CombatItem($itemDef);
    }

    public function testProcessDealsDamageToEnemyHeroAndReturnsCombatEvent(): void
    {
        // Arrange
        $playerBoard = $this->createBoard('player_hero');
        $opponentBoard = $this->createBoard('opponent_hero');

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );
        $context->advanceTick(); // Tick 1

        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );
        $sourceItem = $this->createItem();
        $pendingAction = new PendingAction(
            $action,
            $sourceItem,
            $playerBoard
        );

        $processor = new ActionProcessor();

        // Act
        $event = $processor->process($pendingAction, $context);

        // Assert : Effet sur l'état
        $this->assertSame(85, $opponentBoard->getHero()->getHp());

        // Assert : Evénemment produit avec delta honnête
        $this->assertSame(1, $event->tick);
        $this->assertSame(EventType::DAMAGE_DEALT, $event->type);
        $this->assertSame([
            'amount' => 15,
            'shieldDamage' => 0,
            'hpDamage' => 15,
            'target' => 'opponent_hero',
        ], $event->payload);
    }
}
