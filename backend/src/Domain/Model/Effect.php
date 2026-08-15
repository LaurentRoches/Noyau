<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\Trigger;

final readonly class Effect
{
    /**
     * @param   Action[]    $actions
     */
    public function __construct(
        public Trigger $trigger,
        public array $actions,
        public ?int $intervalTicks = null,
    ) {
    }
}
