<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class ScriptedOpponentFactoryTest extends TestCase
{
    private function createFactory(): ScriptedOpponentFactory
    {
        $configPath = __DIR__ . '/../../../config/game';

        $combatBoardFactory = new CombatBoardFactory(
            new JsonVestigeRepository($configPath . '/vestiges.json'),
            new JsonHeroRepository($configPath . '/heroes.json'),
            new JsonItemRepository($configPath . '/items.json'),
        );

        return new ScriptedOpponentFactory(
            $combatBoardFactory,
            new JsonItemRepository($configPath . '/items.json'),
        );
    }

    public function testCreateOpponentAtRoundOneEquipsOneItem(): void
    {
        $factory = $this->createFactory();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));

        $board = $factory->createOpponent(round: 1, randomizer: $randomizer);

        self::assertCount(1, $board->getItems());
    }

    public function testCreateOpponentCapsItemCountAtSix(): void
    {
        $factory = $this->createFactory();
        $randomizer = new Randomizer(new PcgOneseq128XslRr64(1));

        $board = $factory->createOpponent(round: 12, randomizer: $randomizer);

        self::assertCount(6, $board->getItems());
    }

    public function testCreateOpponentIsDeterministicForSameSeed(): void
    {
        $factory = $this->createFactory();

        $boardA = $factory->createOpponent(round: 3, randomizer: new Randomizer(new PcgOneseq128XslRr64(42)));
        $boardB = $factory->createOpponent(round: 3, randomizer: new Randomizer(new PcgOneseq128XslRr64(42)));

        $itemIdsA = array_map(static fn ($item) => $item->getItem()->id, $boardA->getItems());
        $itemIdsB = array_map(static fn ($item) => $item->getItem()->id, $boardB->getItems());

        self::assertSame($itemIdsA, $itemIdsB);
    }
}
