<?php

declare(strict_types=1);

namespace App\Tests\Domain\Model;

use App\Domain\Model\Hero;
use PHPUnit\Framework\TestCase;

final class HeroTest extends TestCase
{
    public function testHeroIsCreatedWithExpectedValues(): void
    {
        $hero = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            itemSlots: 6,
        );

        self::assertSame('shadow_bearer', $hero->id);
        self::assertSame("Shadow's Bearer", $hero->name);
        self::assertSame('shadow', $hero->affinity);
        self::assertSame(6, $hero->itemSlots);
    }
}
