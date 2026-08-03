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
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class SimulatorTest extends TestCase
{
    private function createHero(string $id, int $hp): CombatHero
    {
        $heroDef = new Hero(
            id: $id,
            name: "Hero {$id}",
            affinity:'shadow',
            baseHp: $hp,
            baseShield: 0,
            itemSlots: 6
        );

        return new CombatHero($heroDef);
    }

    public function testRunExecutesCombatUnitlHeroDefeat(): void
    {
        // Arrange : Joueur avec dague (15 dégâts, CD 1), Ennemi avec 10hp
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
            'dagger',
            'Dagger',
            Rarity::COMMON,
            'shadow',
            1,
            [$effect]
        );

        $playerHero = $this->createHero('player', 100);
        $playerBoard = new CombatBoard($playerHero, [new CombatItem($itemDef)]);

        $opponentHero = $this->createHero('opponent', 10);
        $opponentBoard = new CombatBoard($opponentHero, []);

        $simulator = new Simulator(maxTicks: 100);

        // Act
        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        // Assert
        $this->assertSame($playerHero, $result->winner);
        $this->assertSame(1, $result->totalTicks);
        $this->assertFalse($opponentHero->isAlive());
        $this->assertTrue($playerHero->isAlive());

        // Vérification de la présence de l'évènement dans le log
        $events = $result->log->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(EventType::DAMAGE_DEALT, $events[0]->type);
    }

    public function testRunExecutesSymmetricalCombatAndStopsOnDefeat(): void
    {
        // Arrange
        // Joueur : 100 HP, Dague (15 dmg, CD 1)
        $action = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $effect = new Effect(trigger: Trigger::EVERY_N_TICKS, actions: [$action]);
        $daggerDef = new Item('dagger', 'Dagger', Rarity::COMMON, 'shadow', 1, [$effect]);

        $playerHero = $this->createHero('player', 100);
        $playerBoard = new CombatBoard($playerHero, [new CombatItem($daggerDef)]);

        // Opposant : 20 HP, Dague identique (15 dmg, CD 1)
        $opponentHero = $this->createHero('opponent', 20);
        $opponentBoard = new CombatBoard($opponentHero, [new CombatItem($daggerDef)]);

        $simulator = new Simulator(maxTicks: 100);

        // Act
        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        // Assert
        // Tick 1 : Les deux frappent (Opposant 5 HP, Joueur 85 HP)
        // Tick 2 : Joueur frappe en 1er (Opposant 0 HP), break activé.
        $this->assertSame($playerHero, $result->winner);
        $this->assertSame(2, $result->totalTicks); // Le combat finit au Tick 2
        $this->assertSame(85, $playerHero->getHp());
        $this->assertSame(0, $opponentHero->getHp());
    }

    public function testRunExecutesCombatWithDamageShieldAndHeal(): void
    {
        // Arrange
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $shieldAction = new Action(type: ActionType::GAIN_SHIELD, value: 5, target: Target::SELF);
        $opponentDamageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $healAction = new Action(type: ActionType::HEAL, value: 10, target: Target::SELF);

        $dagger = new Item('dagger', 'Dagger', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$damageAction])]);
        $shield = new Item('shield', 'Shield', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$shieldAction])]);
        $wand = new Item('wand', 'Wand', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$opponentDamageAction])]);
        $potion = new Item('potion', 'Potion', Rarity::COMMON, 'shadow', 1, [new Effect(Trigger::EVERY_N_TICKS, [$healAction])]);

        $playerHero = $this->createHero('player', 50);
        $playerBoard = new CombatBoard($playerHero, [new CombatItem($dagger), new CombatItem($shield)]);

        $opponentHero = $this->createHero('opponent', 30);
        $opponentBoard = new CombatBoard($opponentHero, [new CombatItem($wand), new CombatItem($potion)]);

        $simulator = new Simulator(maxTicks: 100);

        // Act
        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        // Assert
        $this->assertSame($playerHero, $result->winner);
        $this->assertSame(4, $result->totalTicks);
        $this->assertSame(35, $playerHero->getHp());
        $this->assertSame(0, $opponentHero->getHp());

        // Vérification de la variété des événements dans le journal
        $eventTypes = array_map(fn ($e) => $e->type, $result->log->getEvents());
        $this->assertContains(EventType::DAMAGE_DEALT, $eventTypes);
        $this->assertContains(EventType::SHIELD_GAINED, $eventTypes);
        $this->assertContains(EventType::HEAL_RECEIVED, $eventTypes);
    }
}
