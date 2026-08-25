<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Model\Vestige;

final class VestigePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Vestige $vestige): array
    {
        return [
            'id' => $vestige->id,
            'name' => $vestige->name,
            'affinity' => $vestige->affinity,
            'baseHp' => $vestige->baseHp,
            'baseShield' => $vestige->baseShield,
            'startingGold' => $vestige->startingGold,
            'startingIncome' => $vestige->startingIncome,
        ];
    }
}
