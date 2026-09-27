<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Application\CombatSeed;
use App\Application\Factory\CombatBoardFactory;
use App\Domain\Engine\Simulator;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;

final class SimulationE2ETest extends TestCase
{
    public function testCompleteSimulationFromProductionJsonFiles(): void
    {
        // 1. Instanciation des repositories avec les fichiers JSON de production
        $configDir = __DIR__ . '/../../config/game';
        $vestigeRepo = new JsonVestigeRepository($configDir . '/vestiges.json');
        $heroRepo = new JsonHeroRepository($configDir . '/heroes.json');
        $itemRepo = new JsonItemRepository($configDir . '/items.json');

        // 2. Assemblage des plateaux via la Factory
        $factory = new CombatBoardFactory(
            $vestigeRepo,
            $heroRepo,
            $itemRepo,
            new HeroSkillDecorator(),
        );
        $boardA = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_bearer' => ['shadow_dagger']], 0);
        $boardB = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_bearer' => ['shadow_dagger']], 0);

        // 3. Graine de combat, calculee comme en production (D-22)
        $combatSeed = CombatSeed::forRound(runSeed: 123456, round: 1);

        // 4. Lancement de la simulation
        $simulator = new Simulator();
        $result = $simulator->run($boardA, $boardB, $combatSeed);

        // 5. Assertions
        self::assertGreaterThan(0, $result->totalTicks);
        self::assertNotEmpty($result->log->getEvents());
    }

    public function testSimulationAppliesAndPulsesStatusEffectsFromProductionJsonFiles(): void
    {
        $configDir = __DIR__ . '/../../config/game';
        $vestigeRepo = new JsonVestigeRepository($configDir . '/vestiges.json');
        $heroRepo = new JsonHeroRepository($configDir . '/heroes.json');
        $itemRepo = new JsonItemRepository($configDir . '/items.json');

        $factory = new CombatBoardFactory(
            $vestigeRepo,
            $heroRepo,
            $itemRepo,
            new HeroSkillDecorator(),
        );
        $boardA = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_bearer' => ['venomous_vial']], 0);
        $boardB = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_bearer' => ['shadow_dagger']], 0);

        $combatSeed = CombatSeed::forRound(runSeed: 123456, round: 1);

        $simulator = new Simulator();
        $result = $simulator->run($boardA, $boardB, $combatSeed);

        $eventTypes = array_map(
            static fn ($event) => $event->type->value,
            $result->log->getEvents()
        );

        self::assertGreaterThan(0, $result->totalTicks);
        self::assertContains('STATUS_APPLIED', $eventTypes);
        self::assertContains('STATUS_DAMAGE_DEALT', $eventTypes);
    }

    public function testHeroSkillDecorationSurvivesAllRealHeroesAgainstProductionItems(): void
    {
        $configDir = __DIR__ . '/../../config/game';
        $heroRepo = new JsonHeroRepository($configDir . '/heroes.json');
        $factory = new CombatBoardFactory(
            new JsonVestigeRepository($configDir . '/vestiges.json'),
            $heroRepo,
            new JsonItemRepository($configDir . '/items.json'),
            new HeroSkillDecorator(),
        );

        foreach ($heroRepo->findAll() as $hero) {
            $itemIds = $hero->itemSlots >= 2 ? ['scimitar', 'scimitar'] : ['scimitar'];

            $board = $factory->createBoard('shadow_vestige', [$hero->id], [$hero->id => $itemIds], 0);

            self::assertCount(count($itemIds), $board->getItems());
        }
    }
}
