<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\CombatBoardFactory;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;

final class CombatBoardFactoryTest extends TestCase
{
    public function testCreateBoardAssemblesCombatBoardWithHeroAndItems(): void
    {
        // ARRANGE
        $vestigeRepo = new JsonVestigeRepository(__DIR__ . '/../../Fixtures/vestiges.json');
        $heroRepo = new JsonHeroRepository(__DIR__ . '/../../Fixtures/heroes.json');
        $itemRepo = new JsonItemRepository(__DIR__ . '/../../Fixtures/items.json');
        $factory = new CombatBoardFactory($vestigeRepo, $heroRepo, $itemRepo);

        // ACT
        $board = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['rusty_dagger']);

        // ASSERT
        $this->assertCount(1, $board->getHeroes());
        $this->assertSame('shadow_bearer', $board->getHeroes()[0]->getId());
        $this->assertCount(1, $board->getItems());
        $this->assertSame('rusty_dagger', $board->getItems()[0]->getItem()->id);
    }

    public function testCreateBoardThrowsExceptionWhenItemSlotsExceeded(): void
    {
        $vestigeRepo = new JsonVestigeRepository(__DIR__ . '/../../Fixtures/vestiges.json');
        $heroRepo = new JsonHeroRepository(__DIR__ . '/../../Fixtures/heroes.json');
        $itemRepo = new JsonItemRepository(__DIR__ . '/../../Fixtures/items.json');
        $factory = new CombatBoardFactory($vestigeRepo, $heroRepo, $itemRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds total slot budget');

        // shadow_bearer possède 6 slots dans heroes.json
        $tooManyItems = array_fill(0, 7, 'rusty_dagger');
        $factory->createBoard('shadow_vestige', ['shadow_bearer'], $tooManyItems);
    }
}
