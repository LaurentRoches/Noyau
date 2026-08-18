<?php

declare(strict_types=1);

namespace App\Tests\Domain\Player;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Item;
use App\Domain\Player\HeroSkillDecorator;
use PHPUnit\Framework\TestCase;

final class HeroSkillDecoratorTest extends TestCase
{
    public function testDecorateReducesCooldownForOneHandItemsWithFranticSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $item = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 10,
            effects: []
        );

        $decorated = $decorator->decorate(HeroSkillType::FRANTIC, $item);

        self::assertSame(8, $decorated->cooldownTicks);
    }

    public function testDecorateIgnoresTwoHandItemsWithFranticSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $item = new Item(
            id: 'longsword',
            name: 'Longsword',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::TWO_HAND,
            cooldownTicks: 10,
            effects: []
        );

        $decorated = $decorator->decorate(HeroSkillType::FRANTIC, $item);

        self::assertSame(10, $decorated->cooldownTicks);
    }
}
