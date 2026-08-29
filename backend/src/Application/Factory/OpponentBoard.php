<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Model\Hero;
use App\Domain\Model\OpponentAssignment;
use App\Domain\Runtime\CombatBoard;

final readonly class OpponentBoard
{
    /**
     * @param list<Hero> $roster
     * @param list<OpponentAssignment> $assignments
     */
    public function __construct(
        public CombatBoard $board,
        public array $roster,
        public array $assignments,
    ) {
    }
}
