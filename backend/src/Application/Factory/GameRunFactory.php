<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Application\GameRun;
use App\Domain\Engine\Simulator;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class GameRunFactory
{
    public function __construct(
        private readonly string $configPath,
    ) {
    }

    public function create(int $seed, string $vestigeId): GameRun
    {
        $vestigeRepository = new JsonVestigeRepository($this->configPath . '/vestiges.json');
        $heroRepository = new JsonHeroRepository($this->configPath . '/heroes.json');
        $itemRepository = new JsonItemRepository($this->configPath . '/items.json');
        $scriptedOpponentRepository = new JsonScriptedOpponentRepository($this->configPath . '/scripted_opponent.json');

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
        $heroOfferGenerator = new HeroOfferGenerator($heroRepository);
        $simulator = new Simulator();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64($seed));

        $vestige = $vestigeRepository->find($vestigeId);

        return new GameRun(
            $vestige,
            $shopFactory,
            $opponentFactory,
            $heroOfferGenerator,
            $combatBoardFactory,
            $simulator,
            $randomizer,
        );
    }
}
