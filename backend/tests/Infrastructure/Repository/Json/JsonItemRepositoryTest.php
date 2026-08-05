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

        $this->assertInstanceOf(Item::class, $item);
        $this->assertSame('rusty_dagger', $item->id);
        $this->assertSame('Rusty Dagger', $item->name);
        $this->assertSame(Rarity::COMMON, $item->rarity);
        $this->assertSame('neutral', $item->affinity);
        $this->assertSame(2, $item->cooldownTicks);

        $this->assertCount(1, $item->effects);
        $effect = $item->effects[0];
        $this->assertSame(Trigger::EVERY_N_TICKS, $effect->trigger);

        $this->assertCount(1, $effect->actions);
        $action = $effect->actions[0];
        $this->assertSame(ActionType::DEAL_DAMAGE, $action->type);
        $this->assertSame(Target::ENEMY, $action->target);
        $this->assertSame(6, $action->value);
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
}
