<?php

declare(strict_types=1);

namespace App\Domain\Engine;

final class TickEngine
{
    public function tick(SimulationContext $context): void
    {
        $context->advanceTick();

        foreach ($context->getBoards() as $board) {
            foreach ($board->getItems() as $item) {
                $item->decrementCooldown();
            }
        }
    }
}
