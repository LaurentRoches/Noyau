<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Repository\Json;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Item;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use PHPUnit\Framework\TestCase;

final class JsonItemRepositoryTest extends TestCase
{
    public function testFindReturnsItemWhenExists(): void
    {
        $filePath = __DIR__ . '/../../../Fixtures/items.json';
        $repository = new JsonItemRepository($filePath);

        $item = $repository->find('rusty_dagger');

        self::assertInstanceOf(Item::class, $item);
        self::assertSame('rusty_dagger', $item->id);
        self::assertSame('Rusty Dagger', $item->name);
        self::assertSame(Rarity::COMMON, $item->rarity);
        self::assertSame('neutral', $item->affinity);
        self::assertSame(2, $item->cooldownTicks);

        self::assertCount(1, $item->effects);
        $effect = $item->effects[0];
        self::assertSame(Trigger::EVERY_N_TICKS, $effect->trigger);

        self::assertCount(1, $effect->actions);
        $action = $effect->actions[0];
        self::assertSame(ActionType::DEAL_DAMAGE, $action->type);
        self::assertSame(Target::ENEMY, $action->target);
        self::assertSame(6, $action->value);
    }

    public function testFindThrowsExceptionWhenItemNotFound(): void
    {
        $filePath = __DIR__ . '/../../../Fixtures/items.json';
        $repository = new JsonItemRepository($filePath);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Item with ID 'unknown_item' not found");

        $repository->find('unknown_item');
    }

    public function testFindThrowsExceptionWhenFileNotFound(): void
    {
        $repository = new JsonItemRepository('invalid/path/items.json');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $repository->find('rusty_dagger');
    }

    public function testFindAllReturnsAllItems(): void
    {
        $filePath = __DIR__ . '/../../../Fixtures/items.json';
        $repository = new JsonItemRepository($filePath);

        $items = $repository->findAll();

        self::assertNotEmpty($items);
        self::assertContainsOnlyInstancesOf(Item::class, $items);
    }
}
