<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Model\Hero;
use App\Presentation\HeroPresenter;
use App\Presentation\RunStatePresenter;
use App\Presentation\ShopPresenter;
use App\Tests\Support\CreatesRealGameRun;
use PHPUnit\Framework\TestCase;

final class RunStatePresenterTest extends TestCase
{
    use CreatesRealGameRun;

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
