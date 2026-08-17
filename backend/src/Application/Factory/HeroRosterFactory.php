<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Model\Hero;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use Random\Randomizer;

final class HeroRosterFactory
{
    private const int ROSTER_SIZE = 3;

    public function __construct(
        private readonly JsonHeroRepository $heroRepository,
    ) {
    }

    /**
     * @return list<Hero>
     */
    public function createRoster(Randomizer $randomizer, string $requiredAffinity): array
    {
        $allHeroes = $this->heroRepository->findAll();

        $matchingAffinity = array_values(array_filter(
            $allHeroes,
            static fn (Hero $hero): bool => $hero->affinity === $requiredAffinity,
        ));

        $firstHero = $this->drawHeroes($matchingAffinity, 1, $randomizer);

        $remainingPool = array_values(array_filter(
            $allHeroes,
            static fn (Hero $hero): bool => $hero->id !== $firstHero[0]->id,
        ));

        $otherHeroes = $this->drawHeroes($remainingPool, self::ROSTER_SIZE - 1, $randomizer);

        return [...$firstHero, ...$otherHeroes];
    }

    /**
     * @param list<Hero> $pool
     * @return list<Hero>
     */
    private function drawHeroes(array $pool, int $count, Randomizer $randomizer): array
    {
        $selectedKeys = $randomizer->pickArrayKeys($pool, $count);

        return array_map(
            static fn (int $key): Hero => $pool[$key],
            $selectedKeys,
        );
    }
}
