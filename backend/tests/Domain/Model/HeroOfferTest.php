<?php

declare(strict_types=1);

namespace App\Tests\Domain\Model;

use App\Domain\Model\Hero;
use App\Domain\Model\HeroOffer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeroOfferTest extends TestCase
{
    private static function createHero(string $id, string $affinity = 'shadow'): Hero
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
            self::createHero('shadow_bearer'),
            self::createHero('the_bulwark', 'neutral'),
            self::createHero('shadow_bastion'),
        ];

        $offer = new HeroOffer($candidates);

        self::assertSame($candidates, $offer->candidates);
    }

    public function testContainsReturnsTrueWhenHeroIdIsAmongCandidates(): void
    {
        $offer = new HeroOffer([
            self::createHero('shadow_bearer'),
            self::createHero('the_bulwark', 'neutral'),
            self::createHero('shadow_bastion'),
        ]);

        self::assertTrue($offer->contains('the_bulwark'));
    }

    public function testContainsReturnsFalseWhenHeroIdIsNotAmongCandidates(): void
    {
        $offer = new HeroOffer([
            self::createHero('shadow_bearer'),
            self::createHero('the_bulwark', 'neutral'),
            self::createHero('shadow_bastion'),
        ]);

        self::assertFalse($offer->contains('shadow_venomancer'));
    }

    public function testFindReturnsTheMatchingCandidateWhenPresent(): void
    {
        $shadowBearer = self::createHero('shadow_bearer');
        $offer = new HeroOffer([
            $shadowBearer,
            self::createHero('the_bulwark', 'neutral'),
            self::createHero('shadow_bastion'),
        ]);

        self::assertSame($shadowBearer, $offer->find('shadow_bearer'));
    }

    public function testFindReturnsNullWhenHeroIdIsNotAmongCandidates(): void
    {
        $offer = new HeroOffer([
            self::createHero('shadow_bearer'),
            self::createHero('the_bulwark', 'neutral'),
            self::createHero('shadow_bastion'),
        ]);

        self::assertNull($offer->find('shadow_venomancer'));
    }

    /**
     * @return iterable<string, array{list<Hero>}>
     */
    public static function invalidCandidateCountProvider(): iterable
    {
        yield 'two candidates' => [[
            self::createHero('shadow_bearer'),
            self::createHero('the_bulwark'),
        ]];

        yield 'four candidates' => [[
            self::createHero('shadow_bearer'),
            self::createHero('the_bulwark'),
            self::createHero('shadow_bastion'),
            self::createHero('shadow_venomancer'),
        ]];
    }

    /**
     * @param list<Hero> $candidates
     */
    #[DataProvider('invalidCandidateCountProvider')]
    public function testConstructorThrowsWhenCandidateCountIsNotThree(array $candidates): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'HeroOffer requires exactly 3 candidates, %d given.',
            count($candidates),
        ));

        new HeroOffer($candidates);
    }

    public function testConstructorThrowsWhenCandidatesContainDuplicates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HeroOffer cannot contain duplicate heroes.');

        new HeroOffer([
            self::createHero('shadow_bearer'),
            self::createHero('the_bulwark'),
            self::createHero('shadow_bearer'),
        ]);
    }
}
