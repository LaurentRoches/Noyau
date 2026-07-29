<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Model\Item;

class CombatItem
{
    private int $currentCooldown;

    public function __construct(
        private readonly Item $item,
    ) {
        $this->currentCooldown = $this->item->cooldownTicks;
    }

    public function getCooldown(): int
    {
        return $this->currentCooldown;
    }

    public function decrementCooldown(int $tick = 1): void
    {
        $this->currentCooldown = max(0, $this->currentCooldown - $tick);
    }

    public function isReady(): bool
    {
        return $this->currentCooldown === 0;
    }

    public function resetCooldown(): void
    {
        $this->currentCooldown = $this->item->cooldownTicks;
    }
}
