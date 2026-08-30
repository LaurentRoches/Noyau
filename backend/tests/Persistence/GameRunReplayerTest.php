<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Application\Factory\GameRunFactory;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Persistence\RunNotFoundException;
use App\Tests\Support\CreatesInMemoryDatabase;
use PHPUnit\Framework\TestCase;

final class GameRunReplayerTest extends TestCase
{
    use CreatesInMemoryDatabase;

    public function testItReplaysARunFromItsActionLog(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 2) . '/config/game';

        // Un même seed/vestigeId produit toujours la même offre initiale
        // (Randomizer déterministe) : on la construit une fois à part pour
        // connaître l'id d'un candidat valide, sans dupliquer la logique de
        // tirage dans le test lui-même.
        $probeGameRun = (new GameRunFactory($configPath))->create(42, 'shadow_vestige');
        $heroId = $probeGameRun->getPendingHeroOffer()->candidates[0]->id;

        $runRepository->create('run-123', 42, 'shadow_vestige');
        $actionsRepository->append('run-123', 1, GameRunActionType::CHOOSE_HERO, ['heroId' => $heroId]);
        $actionsRepository->append('run-123', 2, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);

        $gameRun = $replayer->replay('run-123');

        self::assertNotNull($gameRun->getCurrentShop());
        self::assertTrue($gameRun->getCurrentShop()->getOffers()[0]->isPurchased());
    }

    public function testItThrowsRunNotFoundExceptionForAnUnknownRunId(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 2) . '/config/game';
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath);

        $this->expectException(RunNotFoundException::class);

        $replayer->replay('does-not-exist');
    }
}
