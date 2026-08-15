<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Application\Factory\CombatBoardFactory;
use App\Domain\Engine\Simulator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

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
        $factory = new CombatBoardFactory($vestigeRepo, $heroRepo, $itemRepo);
        $boardA = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_dagger']);
        $boardB = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_dagger']);

        // 3. Instanciation du Randomizer avec seed
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(123456));

        // 4. Lancement de la simulation
        $simulator = new Simulator();
        $result = $simulator->run($boardA, $boardB, $randomizer);

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

        $factory = new CombatBoardFactory($vestigeRepo, $heroRepo, $itemRepo);
        $boardA = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['venomous_vial']);
        $boardB = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_dagger']);

        $randomizer = new Randomizer(new PcgOneseq128XslRr64(123456));

        $simulator = new Simulator();
        $result = $simulator->run($boardA, $boardB, $randomizer);

        $eventTypes = array_map(
            static fn ($event) => $event->type->value,
            $result->log->getEvents()
        );

        self::assertGreaterThan(0, $result->totalTicks);
        self::assertContains('STATUS_APPLIED', $eventTypes);
        self::assertContains('STATUS_DAMAGE_DEALT', $eventTypes);
    }
}
