<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;

final class ScriptedOpponentFactoryTest extends TestCase
{
    private function createFactory(): ScriptedOpponentFactory
    {
        $configPath = __DIR__ . '/../../../config/game';

        $combatBoardFactory = new CombatBoardFactory(
            new JsonVestigeRepository($configPath . '/vestiges.json'),
            new JsonHeroRepository($configPath . '/heroes.json'),
            new JsonItemRepository($configPath . '/items.json'),
            new HeroSkillDecorator(),
        );

        return new ScriptedOpponentFactory(
            $combatBoardFactory,
            new JsonItemRepository($configPath . '/items.json'),
            new JsonHeroRepository($configPath . '/heroes.json'),
            new JsonScriptedOpponentRepository($configPath . '/scripted_opponent.json'),
        );
    }

    public function testCreateOpponentAtRoundOneRevealsOnlyFirstScriptedItem(): void
    {
        $factory = $this->createFactory();

        $result = $factory->createOpponent(round: 1);

        self::assertCount(1, $result->board->getItems());
    }

    public function testCreateOpponentIsDeterministicAcrossCalls(): void
    {
        $factory = $this->createFactory();

        $resultA = $factory->createOpponent(round: 5);
        $resultB = $factory->createOpponent(round: 5);

        $itemIdsA = array_map(static fn ($item) => $item->getItem()->id, $resultA->board->getItems());
        $itemIdsB = array_map(static fn ($item) => $item->getItem()->id, $resultB->board->getItems());

        self::assertSame($itemIdsA, $itemIdsB);
    }

    public function testCreateOpponentExposesRosterAndAssignments(): void
    {
        $factory = $this->createFactory();

        $result = $factory->createOpponent(round: 1);

        self::assertNotEmpty($result->roster);
        self::assertCount(1, $result->assignments);
        self::assertSame(
            $result->board->getItems()[0]->getItem()->id,
            $result->assignments[0]->item->id,
        );
    }
}
