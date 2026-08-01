<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Model\Action;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatItem;

final readonly class PendingAction
{
    public function __construct(
        public Action $action,
        public CombatItem $sourceItem,
        public CombatBoard $sourceBoard,
    ) {
    }
}
