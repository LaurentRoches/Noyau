<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\HeroRosterFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Application\GameRun;
use App\Domain\Engine\Simulator;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

trait CreatesRealGameRun
{
    private function createRealGameRun(int $seed, string $vestigeId = 'shadow_vestige'): GameRun
    {
        $configPath = dirname(__DIR__, 2) . '/config/game';

        $vestigeRepository = new JsonVestigeRepository($configPath . '/vestiges.json');
        $heroRepository = new JsonHeroRepository($configPath . '/heroes.json');
        $itemRepository = new JsonItemRepository($configPath . '/items.json');
        $scriptedOpponentRepository = new JsonScriptedOpponentRepository($configPath . '/scripted_opponent.json');

        $combatBoardFactory = new CombatBoardFactory(
            $vestigeRepository,
            $heroRepository,
            $itemRepository,
            new HeroSkillDecorator(),
        );
        $shopFactory = new ShopFactory($itemRepository);
        $opponentFactory = new ScriptedOpponentFactory(
            $combatBoardFactory,
            $itemRepository,
            $heroRepository,
            $scriptedOpponentRepository,
        );
        $heroRosterFactory = new HeroRosterFactory($heroRepository);
        $simulator = new Simulator();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64($seed));

        $vestige = $vestigeRepository->find($vestigeId);

        return new GameRun(
            $vestige,
            $shopFactory,
            $opponentFactory,
            $heroRosterFactory,
            $combatBoardFactory,
            $simulator,
            $randomizer,
        );
    }
}
