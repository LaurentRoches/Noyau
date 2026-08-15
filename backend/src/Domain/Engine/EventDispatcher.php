<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\Trigger;
use App\Domain\Model\Effect;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatItem;

final class EventDispatcher
{
    /**
     * @var array<string, list<array{sourceBoard: CombatBoard, sourceItem: CombatItem, effect: Effect}>>
     */
    private array $listeners = [];

    public function register(
        Trigger $trigger,
        CombatBoard $sourceBoard,
        CombatItem $sourceItem,
        Effect $effect
    ): void {
        $this->listeners[$trigger->value][] = [
            'sourceBoard' => $sourceBoard,
            'sourceItem' => $sourceItem,
            'effect' => $effect,
        ];
    }

    /**
     * @return list<array{sourceBoard: CombatBoard, sourceItem: CombatItem, effect: Effect}>
     */
    public function getListenersFor(Trigger $trigger): array
    {
        return $this->listeners[$trigger->value] ?? [];
    }

    public function registerBoard(CombatBoard $board): void
    {
        foreach ($board->getItems() as $item) {
            foreach ($item->getEffects() as $effect) {
                $this->register($effect->trigger, $board, $item, $effect);
            }
        }
    }

    /**
     * Broadcast global pour un Trigger donné.
     *
     * @return list<PendingAction>
     */
    public function dispatch(Trigger $trigger): array
    {
        return $this->toPendingActions($this->getListenersFor($trigger));
    }

    /**
     * Déclenchement ciblé uniquement pour les effets d'un objet précis.
     *
     * @return list<PendingAction>
     */
    public function dispatchForItem(CombatBoard $sourceBoard, CombatItem $sourceItem): array
    {
        $matchingListeners = [];

        foreach ($this->listeners as $listenersForTrigger) {
            foreach ($listenersForTrigger as $listener) {
                if ($listener['sourceItem'] === $sourceItem && $listener['sourceBoard'] === $sourceBoard) {
                    $matchingListeners[] = $listener;
                }
            }
        }

        return $this->toPendingActions($matchingListeners);
    }

    /**
     * Déplie chaque effet des listeners reçus en une liste d'intentions individuelles (PendingAction).
     *
     * @param list<array{sourceBoard: CombatBoard, sourceItem: CombatItem, effect: Effect}> $listeners
     * @return list<PendingAction>
     */
    private function toPendingActions(array $listeners): array
    {
        $pendingActions = [];

        foreach ($listeners as $listener) {
            foreach ($listener['effect']->actions as $action) {
                $pendingActions[] = new PendingAction(
                    action: $action,
                    sourceItem: $listener['sourceItem'],
                    sourceBoard: $listener['sourceBoard']
                );
            }
        }

        return $pendingActions;
    }
}
