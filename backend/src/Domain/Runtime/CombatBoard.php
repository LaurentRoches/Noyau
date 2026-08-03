<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

final readonly class CombatBoard
{
    /**
     * @param array<CombatItem> $items
     */
    public function __construct(
        private CombatHero $hero,
        private array $items = [],
    ) {
    }

    public function getHero(): CombatHero
    {
        return $this->hero;
    }

    /**
     * @return array<CombatItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Retourne uniquement les objets dont le cooldown est à zéro et prêts à se déclencher.
     *
     * @return array<CombatItem>
     */
    public function getReadyItems(): array
    {
        return array_values(
            array_filter(
                $this->items,
                static fn (CombatItem $item): bool => $item->isReady()
            )
        );
    }

    /**
     * Indique de façon factuelle si le héros du plateau est encore debout.
     */
    public function hasAliveHero(): bool
    {
        return $this->hero->isAlive();
    }
}
