<?php

declare(strict_types=1);

namespace App\Tests\Domain;

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
        $vestigeDef = new Vestige("vestige_{$id}", "Vestige {$id}", 'shadow', $hp, 0);
        $heroDef = new Hero($id, "Hero {$id}", 'shadow', 6);

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            new CombatHero($heroDef),
            $items
        );
    }

    public function testRunExecutesCombatUntilHeroDefeat(): void
    {
        $action = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $effect = new Effect(trigger: Trigger::EVERY_N_TICKS, actions: [$action]);
        $itemDef = new Item('dagger', 'Dagger', Rarity::COMMON, 'shadow', 1, [$effect]);

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($itemDef)]);
        $opponentBoard = $this->createBoard('opponent', 10, []);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        $this->assertSame($playerBoard->getHero(), $result->winner);
        $this->assertSame(1, $result->totalTicks);
        $this->assertFalse($opponentBoard->isAlive());
        $this->assertTrue($playerBoard->isAlive());

        $events = $result->log->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(EventType::DAMAGE_DEALT, $events[0]->type);
    }

    public function testRunExecutesSymmetricalCombatAndStopsOnDefeat(): void
    {
        $action = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $effect = new Effect(trigger: Trigger::EVERY_N_TICKS, actions: [$action]);
        $daggerDef = new Item('dagger', 'Dagger', Rarity::COMMON, 'shadow', 1, [$effect]);

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($daggerDef)]);
        $opponentBoard = $this->createBoard('opponent', 20, [new CombatItem($daggerDef)]);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        $this->assertSame($playerBoard->getHero(), $result->winner);
        $this->assertSame(2, $result->totalTicks);
        $this->assertSame(85, $playerBoard->getVestige()->getHp());
        $this->assertSame(0, $opponentBoard->getVestige()->getHp());
    }

    public function testRunExecutesCombatWithDamageShieldAndHeal(): void
    {
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $shieldAction = new Action(type: ActionType::GAIN_SHIELD, value: 5, target: Target::SELF);
        $opponentDamageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $healAction = new Action(type: ActionType::HEAL, value: 10, target: Target::SELF);

        $dagger = new Item('dagger', 'Dagger', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$damageAction])]);
        $shield = new Item('shield', 'Shield', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$shieldAction])]);
        $wand = new Item('wand', 'Wand', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$opponentDamageAction])]);
        $potion = new Item('potion', 'Potion', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$healAction])]);

        $playerBoard = $this->createBoard('player', 50, [new CombatItem($dagger), new CombatItem($shield)]);
        $opponentBoard = $this->createBoard('opponent', 30, [new CombatItem($wand), new CombatItem($potion)]);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        $this->assertSame($playerBoard->getHero(), $result->winner);
        $this->assertSame(4, $result->totalTicks);
        $this->assertSame(35, $playerBoard->getVestige()->getHp());
        $this->assertSame(0, $opponentBoard->getVestige()->getHp());

        $eventTypes = array_map(fn ($e) => $e->type, $result->log->getEvents());
        $this->assertContains(EventType::DAMAGE_DEALT, $eventTypes);
        $this->assertContains(EventType::SHIELD_GAINED, $eventTypes);
        $this->assertContains(EventType::HEAL_RECEIVED, $eventTypes);
    }
}
