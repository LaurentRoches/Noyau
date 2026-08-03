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
}
