<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\Simulator;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
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

final class SimulatorTest extends TestCase
{
    private function createBoard(string $id, int $hp, array $items = []): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: "vestige_{$id}",
            name: "Vestige {$id}",
            affinity: 'shadow',
            baseHp: $hp,
            baseShield: 0,
            startingGold: 0
        );
        $heroDef = new Hero(
            id: $id,
            name: "Hero {$id}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            [new CombatHero($heroDef)],
            $items
        );
    }

    public function testRunExecutesCombatUntilHeroDefeat(): void
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
        $itemDef = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: [$effect]
        );

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($itemDef)]);
        $opponentBoard = $this->createBoard('opponent', 10, []);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        $this->assertSame($playerBoard, $result->winner);
        $this->assertSame(1, $result->totalTicks);
        $this->assertFalse($opponentBoard->isAlive());
        $this->assertTrue($playerBoard->isAlive());

        $events = $result->log->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(EventType::DAMAGE_DEALT, $events[0]->type);
    }

    public function testRunExecutesSymmetricalCombatAndStopsOnDefeat(): void
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
        $daggerDef = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: [$effect]
        );

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($daggerDef)]);
        $opponentBoard = $this->createBoard('opponent', 20, [new CombatItem($daggerDef)]);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        $this->assertSame($playerBoard, $result->winner);
        $this->assertSame(2, $result->totalTicks);
        $this->assertSame(85, $playerBoard->getVestige()->getHp());
        $this->assertSame(0, $opponentBoard->getVestige()->getHp());
    }

    public function testRunExecutesCombatWithDamageShieldAndHeal(): void
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
        $opponentDamageAction = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 10,
            target: Target::ENEMY
        );
        $healAction = new Action(
            type: ActionType::HEAL,
            value: 10,
            target: Target::SELF
        );

        $dagger = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$damageAction])]
        );
        $shield = new Item(
            id: 'shield',
            name: 'Shield',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$shieldAction])]
        );
        $wand = new Item(
            id: 'wand',
            name: 'Wand',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$opponentDamageAction])]
        );
        $potion = new Item(
            id: 'potion',
            name: 'Potion',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$healAction])]
        );

        $playerBoard = $this->createBoard('player', 50, [new CombatItem($dagger), new CombatItem($shield)]);
        $opponentBoard = $this->createBoard('opponent', 30, [new CombatItem($wand), new CombatItem($potion)]);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        $this->assertSame($playerBoard, $result->winner);
        $this->assertSame(4, $result->totalTicks);
        $this->assertSame(35, $playerBoard->getVestige()->getHp());
        $this->assertSame(0, $opponentBoard->getVestige()->getHp());

        $eventTypes = array_map(fn ($e) => $e->type, $result->log->getEvents());
        $this->assertContains(EventType::DAMAGE_DEALT, $eventTypes);
        $this->assertContains(EventType::SHIELD_GAINED, $eventTypes);
        $this->assertContains(EventType::HEAL_RECEIVED, $eventTypes);
    }
}
