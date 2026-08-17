<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Repository\Json;

use App\Domain\Model\Hero;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use PHPUnit\Framework\TestCase;

final class JsonHeroRepositoryTest extends TestCase
{
    private JsonHeroRepository $repository;

    protected function setUp(): void
    {
        $filePath = __DIR__ . '/../../../Fixtures/heroes.json';
        $this->repository = new JsonHeroRepository($filePath);
    }

    public function testFindReturnsHeroWhenExists(): void
    {
        $hero = $this->repository->find('shadow_bearer');

        self::assertInstanceOf(Hero::class, $hero);
        self::assertSame('shadow_bearer', $hero->id);
        self::assertSame("Shadow's bearer", $hero->name);
        self::assertSame('shadow', $hero->affinity);
        self::assertSame(6, $hero->itemSlots);
    }

    public function testFindThrowsExceptionWhenHeroNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Hero with ID 'unknown_hero' not found");

        $this->repository->find('unknown_hero');
    }

    public function testFindThrowsExceptionWhenFileNotFound(): void
    {
        $repository = new JsonHeroRepository('invalid/path/heroes.json');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $repository->find('shadow_bearer');
    }

    public function testFindAllReturnsAllHeroes(): void
    {
        $heroes = $this->repository->findAll();

        self::assertNotEmpty($heroes);
        self::assertContainsOnlyInstancesOf(Hero::class, $heroes);
    }
}
