<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Runtime\CombatBoard;

final class SimulationResult
{
    public function __construct(
        public ?CombatBoard $winner,
        public int $totalTicks,
        public CombatLog $log,
    ) {
    }
}
