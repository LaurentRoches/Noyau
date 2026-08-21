<?php

declare(strict_types=1);

namespace Tests\Presentation;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Model\Hero;
use App\Presentation\HeroPresenter;
use PHPUnit\Framework\TestCase;

final class HeroPresenterTest extends TestCase
{
    public function testItPresentsAHeroWithoutSkillToArray(): void
    {
        $hero = new Hero(
            id: 'shadow_bearer',
            name: 'Shadow Bearer',
            affinity: 'shadow',
            itemSlots: 2,
        );

        $result = HeroPresenter::toArray($hero);

        self::assertSame([
            'id' => 'shadow_bearer',
            'name' => 'Shadow Bearer',
            'affinity' => 'shadow',
            'itemSlots' => 2,
            'skill' => null,
        ], $result);
    }

    public function testItPresentsAHeroWithSkillToArray(): void
    {
        $hero = new Hero(
            id: 'shadow_venomancer',
            name: 'Shadow Venomancer',
            affinity: 'shadow',
            itemSlots: 2,
            skill: HeroSkillType::VIRULENT,
        );

        $result = HeroPresenter::toArray($hero);

        self::assertSame([
            'id' => 'shadow_venomancer',
            'name' => 'Shadow Venomancer',
            'affinity' => 'shadow',
            'itemSlots' => 2,
            'skill' => 'VIRULENT',
        ], $result);
    }
}
