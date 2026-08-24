<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\Model\OpponentAssignment;

final class OpponentInventoryPresenter
{
    /**
     * @param list<OpponentAssignment> $assignments
     * @return array{items: list<array<string, mixed>>}
     */
    public static function toArray(array $assignments): array
    {
        return [
            'items' => array_map(
                static fn (OpponentAssignment $assignment): array => [
                    'item' => ItemPresenter::toArray($assignment->item),
                    'heroId' => $assignment->heroId,
                ],
                $assignments,
            ),
        ];
    }
}
