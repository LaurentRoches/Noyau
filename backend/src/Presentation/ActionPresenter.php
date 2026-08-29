<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Model\Action;

final class ActionPresenter
{
    /**
     * @return array{
     *     type: string,
     *     value: int|null,
     *     target: string|null,
     *     status: string|null,
     *     stacks: int|null,
     *     durationTicks: int|null
     * }
     */
    public static function toArray(Action $action): array
    {
        return [
            'type' => $action->type->value,
            'value' => $action->value,
            'target' => $action->target?->value,
            'status' => $action->status?->value,
            'stacks' => $action->stacks,
            'durationTicks' => $action->durationTicks,
        ];
    }
}
