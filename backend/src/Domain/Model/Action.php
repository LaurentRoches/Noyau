<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;

final readonly class Action
{
    public function __construct(
        public ActionType $type,
        public ?int $value = null,
        public ?Target $target = null,
        public ?StatusType $status = null,
        public ?int $stacks = null,
        public ?int $durationTicks = null,
    ) {
    }
}
