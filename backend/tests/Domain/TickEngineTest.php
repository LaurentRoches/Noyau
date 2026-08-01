<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Engine\SimulationContext;
use App\Domain\Engine\TickEngine;
use App\Domain\Enum\Rarity;
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

    private function createItem(string $id, int $cooldownTicks): CombatItem
    {
        $itemDef = new Item(
            id: $id,
            name: "Test Item {$id}",
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            cooldownTicks: $cooldownTicks,
            effects: []
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

        $engine = new TickEngine();

        $this->assertSame(0, $context->getCurrentTick());

        $engine->tick($context);

        $this->assertSame(1, $context->getCurrentTick());
        $this->assertSame(1, $playerDagger->getCooldown());
        $this->assertSame(0, $playerCloak->getCooldown());
        $this->assertSame(3, $opponentHammer->getCooldown());
    }
}
