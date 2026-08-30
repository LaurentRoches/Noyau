<?php

declare(strict_types=1);

namespace App\Domain\Model;

final readonly class HeroOffer
{
    private const int REQUIRED_CANDIDATE_COUNT = 3;

    /**
     * @param list<Hero> $candidates
     */
    public function __construct(
        public array $candidates,
    ) {
        if (count($this->candidates) !== self::REQUIRED_CANDIDATE_COUNT) {
            throw new \InvalidArgumentException(sprintf(
                'HeroOffer requires exactly 3 candidates, %d given.',
                count($this->candidates),
            ));
        }

        $ids = array_map(static fn (Hero $hero): string => $hero->id, $this->candidates);
        if (count($ids) !== count(array_unique($ids))) {
            throw new \InvalidArgumentException('HeroOffer cannot contain duplicate heroes.');
        }
    }

    public function contains(string $heroId): bool
    {
        return $this->find($heroId) !== null;
    }

    public function find(string $heroId): ?Hero
    {
        foreach ($this->candidates as $candidate) {
            if ($candidate->id === $heroId) {
                return $candidate;
            }
        }

        return null;
    }
}
