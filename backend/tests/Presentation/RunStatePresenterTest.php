<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Model\Hero;
use App\Presentation\HeroPresenter;
use App\Presentation\RunStatePresenter;
use App\Presentation\ShopPresenter;
use App\Presentation\VestigePresenter;
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
            ['items' => [], 'capacity' => 6, 'isFull' => false],
            $result['stash'],
        );

        // Roster vide et offre de héros en attente : le contrat de départ du
        // nouveau GameRun, avant tout chooseHero().
        self::assertSame([], $result['roster']);

        $offer = $gameRun->getPendingHeroOffer();
        $expectedOffer = array_map(
            static fn (Hero $hero): array => HeroPresenter::toArray($hero),
            $offer->candidates,
        );
        self::assertSame($expectedOffer, $result['pendingHeroOffer']);
        self::assertCount(3, $result['pendingHeroOffer']);

        // Même logique pour le Vestige : le contenu réel (config/game/vestiges.json)
        // n'est pas dupliqué en dur ici, seule la composition est prouvée.
        self::assertSame(VestigePresenter::toArray($gameRun->getVestige()), $result['vestige']);
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
        $gameRun = $this->createRealGameRunReadyToPlay(seed: 42);

        // La boutique a déjà été ouverte automatiquement par chooseHero().
        $shop = $gameRun->getCurrentShop();

        $result = RunStatePresenter::toArray($gameRun);

        self::assertSame(ShopPresenter::toArray($shop), $result['shop']);
    }
}
