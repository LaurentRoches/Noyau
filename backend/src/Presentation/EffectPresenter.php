<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Model\Effect;

final class EffectPresenter
{
    /**
     * @return array{
     *     trigger: string,
     *     actions: list<array<string, mixed>>,
     *     intervalTicks: int|null
     * }
     */
    public static function toArray(Effect $effect): array
    {
        return [
            'trigger' => $effect->trigger->value,
            'actions' => array_map(
                static fn (\App\Domain\Model\Action $action): array => ActionPresenter::toArray($action),
                $effect->actions,
            ),
            'intervalTicks' => $effect->intervalTicks,
        ];
    }
}
