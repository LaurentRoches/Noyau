<?php

declare(strict_types=1);

namespace App\Tests\Domain\Model;

use App\Domain\Model\Hero;
use App\Domain\Model\HeroOffer;
use PHPUnit\Framework\TestCase;

final class HeroOfferTest extends TestCase
{
    private function createHero(string $id, string $affinity = 'shadow'): Hero
    {
        return new Hero(
            id: $id,
            name: $id,
            affinity: $affinity,
            itemSlots: 2,
        );
    }

    public function testHeroOfferExposesItsThreeCandidates(): void
    {
        $candidates = [
            $this->createHero('shadow_bearer'),
            $this->createHero('the_bulwark', 'neutral'),
            $this->createHero('shadow_bastion'),
        ];

        $offer = new HeroOffer($candidates);

        self::assertSame($candidates, $offer->candidates);
    }

    public function testContainsReturnsTrueWhenHeroIdIsAmongCandidates(): void
    {
        $offer = new HeroOffer([
            $this->createHero('shadow_bearer'),
            $this->createHero('the_bulwark', 'neutral'),
            $this->createHero('shadow_bastion'),
        ]);

        self::assertTrue($offer->contains('the_bulwark'));
    }
}
