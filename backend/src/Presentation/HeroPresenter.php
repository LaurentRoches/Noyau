<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Model\Hero;

final class HeroPresenter
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     affinity: string,
     *     itemSlots: int,
     *     skill: string|null
     * }
     */
    public static function toArray(Hero $hero): array
    {
        return [
            'id' => $hero->id,
            'name' => $hero->name,
            'affinity' => $hero->affinity,
            'itemSlots' => $hero->itemSlots,
            'skill' => $hero->skill?->value,
        ];
    }
}
