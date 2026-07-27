<?php

namespace App\Tests\Domain;

use App\Domain\Model\Hero;
use PHPUnit\Framework\TestCase;

final class HeroTest extends TestCase
{
    public function testHeroIsCreatedWithExpectedValues(): void
    {
        $hero = new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity:'shadow',
            baseHp: 100,
            itemSlots: 6,
        );

        self::assertSame('shadow_bearer', $hero->id);
        self::assertSame(100, $hero->baseHp);
        self::assertSame(6, $hero->itemSlots);
    }
}
