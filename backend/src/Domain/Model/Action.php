<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActionType;

final readonly class Action
{
    public function __construct(
        public ActionType $type,
        public ?int $value = null,
        public ?string $status = null,
        public ?int $stacks = null,
        public ?int $durationTicks = null,
    ) {
    }
}
