<?php

declare(strict_types=1);

namespace App\Tests\Presentation;

use App\Domain\Model\Vestige;
use App\Presentation\VestigePresenter;
use PHPUnit\Framework\TestCase;

final class VestigePresenterTest extends TestCase
{
    public function testToArrayReturnsAllVestigeFields(): void
    {
        $vestige = new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
            startingGold: 20,
            startingIncome: 5,
        );

        $result = VestigePresenter::toArray($vestige);

        self::assertSame([
            'id' => 'shadow_vestige',
            'name' => 'Shadow Vestige',
            'affinity' => 'shadow',
            'baseHp' => 100,
            'baseShield' => 10,
            'startingGold' => 20,
            'startingIncome' => 5,
        ], $result);
    }
}
