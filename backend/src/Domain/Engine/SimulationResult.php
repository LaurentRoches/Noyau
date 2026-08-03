<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Runtime\CombatHero;

final class SimulationResult
{
    public function __construct(
        public ?CombatHero $winner,
        public int $totalTicks,
        public CombatLog $log,
    ) {

    }
}
