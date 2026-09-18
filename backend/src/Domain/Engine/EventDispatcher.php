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

    public function registerBoard(CombatBoard $board): void
    {
        foreach ($board->getItems() as $item) {
            foreach ($item->getEffects() as $effect) {
                $this->register($effect->trigger, $board, $item, $effect);
            }
        }
    }

    /**
     * Seul point de déclenchement du moteur : les effets d'un objet précis,
     * appelé par TickEngine quand le cooldown de cet objet atteint zéro.
     *
     * Le parcours ignore les clés de `$listeners`, donc le `Trigger` déclaré
     * n'a aucun effet sur la cadence. Dette consignée en `04` §3.4, traitée au
     * chantier 3 et non ici.
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

    private function register(
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
