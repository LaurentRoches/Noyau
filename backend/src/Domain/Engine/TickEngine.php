<?php

declare(strict_types=1);

namespace App\Domain\Engine;

final class TickEngine
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher
    ) {
    }

    /**
     * @return list<PendingAction>
     */
    public function tick(SimulationContext $context): array
    {
        $context->advanceTick();

        // 1. Phase de temps / recharge
        foreach ($context->getBoards() as $board) {
            foreach ($board->getItems() as $item) {
                $item->decrementCooldown();
            }
        }

        // 2. Phase de déclenchement des objets prêts
        $pendingActions = [];

        foreach ($context->getBoards() as $board) {
            foreach ($board->getReadyItems() as $readyItem) {
                // A. Consommation de la charge (reset avant notification)
                $readyItem->resetCooldown();

                // B. Notification et accumulation des PendingAction
                $generatedActions = $this->eventDispatcher->dispatchForItem($board, $readyItem);
                foreach ($generatedActions as $action) {
                    $pendingActions[] = $action;
                }
            }
        }

        return $pendingActions;
    }
}
