<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\HeroRosterFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Application\GameRun;
use App\Domain\Engine\Simulator;
use App\Domain\Model\Hero;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use App\Presentation\HeroPresenter;
use App\Presentation\RunStatePresenter;
use App\Presentation\ShopPresenter;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class RunStatePresenterTest extends TestCase
{
    public function testItPresentsAFreshGameRunToArray(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);

        $result = RunStatePresenter::toArray($gameRun);

        self::assertSame(1, $result['round']);
        self::assertSame(0, $result['victories']);
        self::assertSame(0, $result['defeats']);
        self::assertFalse($result['isOver']);
        self::assertFalse($result['hasWon']);
        self::assertSame(['balance' => 20], $result['wallet']);
        self::assertNull($result['shop']);
        self::assertSame(['items' => []], $result['inventory']);
        self::assertSame(
            ['items' => [], 'capacity' => 3, 'isFull' => false],
            $result['stash'],
        );

        // Le roster réel (contenu dépendant du tirage pondéré) n'est pas
        // hardcodé ici — HeroRosterFactory a déjà son propre test pour ça.
        // On ne prouve que la sérialisation, via une composition indépendante.
        $expectedRoster = array_map(
            static fn (Hero $hero): array => HeroPresenter::toArray($hero),
            $gameRun->getRoster(),
        );
        self::assertSame($expectedRoster, $result['roster']);
    }

    private function createRealGameRun(int $seed): GameRun
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

        $vestige = $vestigeRepository->find('shadow_vestige');

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

    public function testItDistinguishesVictoriesFromDefeats(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);

        $gameRun->recordVictory();
        $gameRun->recordDefeat();
        $gameRun->recordDefeat();

        $result = RunStatePresenter::toArray($gameRun);

        self::assertSame(1, $result['victories']);
        self::assertSame(2, $result['defeats']);
        self::assertSame(4, $result['round']);
        self::assertSame(45, $result['wallet']['balance']); // 20 + (10+5) + 5 + 5
    }

    public function testItPresentsAnOpenShopToArray(): void
    {
        $gameRun = $this->createRealGameRun(seed: 42);

        $shop = $gameRun->openShop();

        $result = RunStatePresenter::toArray($gameRun);

        self::assertSame(ShopPresenter::toArray($shop), $result['shop']);
    }
}
