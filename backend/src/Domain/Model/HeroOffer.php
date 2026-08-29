<?php

declare(strict_types=1);

namespace App\Domain\Model;

final readonly class HeroOffer
{
    /**
     * @param list<Hero> $candidates
     */
    public function __construct(
        public array $candidates,
    ) {
    }
}
