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
     * @return  list<PendingAction>
     */
    public function dispatch(Trigger $trigger): array
    {
        $pendingActions = [];

        foreach ($this->getListenersFor($trigger) as $listener) {
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
