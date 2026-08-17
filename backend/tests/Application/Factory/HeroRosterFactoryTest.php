<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\HeroRosterFactory;
use App\Domain\Model\Hero;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class HeroRosterFactoryTest extends TestCase
{
    private function createFactory(): HeroRosterFactory
    {
        $filePath = __DIR__ . '/../../Fixtures/heroes.json';

        return new HeroRosterFactory(new JsonHeroRepository($filePath));
    }

    public function testCreateRosterReturnsThreeDistinctHeroes(): void
    {
        $factory = $this->createFactory();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));

        $roster = $factory->createRoster($randomizer, 'shadow');

        self::assertCount(3, $roster);
        self::assertContainsOnlyInstancesOf(Hero::class, $roster);

        $ids = array_map(static fn (Hero $hero): string => $hero->id, $roster);
        self::assertSame($ids, array_unique($ids));
    }

    public function testCreateRosterFirstHeroMatchesRequiredAffinity(): void
    {
        $factory = $this->createFactory();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));

        $roster = $factory->createRoster($randomizer, 'shadow');

        self::assertSame('shadow', $roster[0]->affinity);
    }

    public function testCreateRosterIsDeterministicForSameSeed(): void
    {
        $factory = $this->createFactory();

        $rosterA = $factory->createRoster(new Randomizer(new PcgOneseq128XslRr64(42)), 'shadow');
        $rosterB = $factory->createRoster(new Randomizer(new PcgOneseq128XslRr64(42)), 'shadow');

        $idsA = array_map(static fn (Hero $hero): string => $hero->id, $rosterA);
        $idsB = array_map(static fn (Hero $hero): string => $hero->id, $rosterB);

        self::assertSame($idsA, $idsB);
    }
}
