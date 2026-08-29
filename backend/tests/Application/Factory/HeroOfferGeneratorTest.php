<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\HeroOfferGenerator;
use App\Domain\Model\Hero;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class HeroOfferGeneratorTest extends TestCase
{
    private function createGenerator(): HeroOfferGenerator
    {
        $filePath = __DIR__ . '/../../Fixtures/heroes.json';

        return new HeroOfferGenerator(new JsonHeroRepository($filePath));
    }

    public function testBuildInitialOfferGuaranteesFirstCandidateMatchesVestigeAffinity(): void
    {
        $generator = $this->createGenerator();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));

        $offer = $generator->buildInitialOffer($randomizer, 'shadow');

        self::assertCount(3, $offer->candidates);
        self::assertSame('shadow', $offer->candidates[0]->affinity);

        $ids = array_map(static fn (Hero $hero): string => $hero->id, $offer->candidates);
        self::assertSame($ids, array_unique($ids));
    }

    public function testBuildWeightedOfferExcludesAlreadyRecruitedHeroes(): void
    {
        $generator = $this->createGenerator();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));

        $offer = $generator->buildWeightedOffer($randomizer, 'shadow', ['shadow_bearer']);

        self::assertCount(3, $offer->candidates);

        $ids = array_map(static fn (Hero $hero): string => $hero->id, $offer->candidates);
        self::assertSame($ids, array_unique($ids));
        self::assertNotContains('shadow_bearer', $ids);
    }
}
