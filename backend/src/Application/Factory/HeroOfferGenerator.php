<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Model\Draw\WeightedDraw;
use App\Domain\Model\Hero;
use App\Domain\Model\HeroOffer;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use Random\Randomizer;

final class HeroOfferGenerator
{
    private const int OFFER_SIZE = 3;
    private const float MATCHING_AFFINITY_WEIGHT = 2.0;
    private const float OTHER_AFFINITY_WEIGHT = 1.0;

    public function __construct(
        private readonly JsonHeroRepository $heroRepository,
    ) {
    }

    public function buildInitialOffer(Randomizer $randomizer, string $vestigeAffinity): HeroOffer
    {
        $allHeroes = $this->heroRepository->findAll();

        $matchingAffinity = array_values(array_filter(
            $allHeroes,
            static fn (Hero $hero): bool => $hero->affinity === $vestigeAffinity,
        ));

        $guaranteedHero = $this->drawUniform($matchingAffinity, 1, $randomizer)[0];

        $remainingPool = array_values(array_filter(
            $allHeroes,
            static fn (Hero $hero): bool => $hero->id !== $guaranteedHero->id,
        ));

        $otherHeroes = $this->drawUniform($remainingPool, self::OFFER_SIZE - 1, $randomizer);

        return new HeroOffer([$guaranteedHero, ...$otherHeroes]);
    }

    /**
     * @param list<string> $excludedHeroIds
     */
    public function buildWeightedOffer(Randomizer $randomizer, string $vestigeAffinity, array $excludedHeroIds): HeroOffer
    {
        $allHeroes = $this->heroRepository->findAll();

        $availablePool = array_values(array_filter(
            $allHeroes,
            static fn (Hero $hero): bool => !in_array($hero->id, $excludedHeroIds, true),
        ));

        $weights = array_map(
            static fn (Hero $hero): float => $hero->affinity === $vestigeAffinity
                ? self::MATCHING_AFFINITY_WEIGHT
                : self::OTHER_AFFINITY_WEIGHT,
            $availablePool,
        );

        $randomFloats = array_map(
            static fn (): float => $randomizer->nextFloat(),
            $availablePool,
        );

        $selected = WeightedDraw::pickTop($availablePool, $weights, $randomFloats, self::OFFER_SIZE);

        return new HeroOffer($selected);
    }

    /**
     * @param list<Hero> $pool
     * @return list<Hero>
     */
    private function drawUniform(array $pool, int $count, Randomizer $randomizer): array
    {
        $selectedKeys = $randomizer->pickArrayKeys($pool, $count);

        return array_map(
            static fn (int $key): Hero => $pool[$key],
            $selectedKeys,
        );
    }
}
