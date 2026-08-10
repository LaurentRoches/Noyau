<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

final readonly class CombatBoard
{
    /**
     * @param array<CombatHero> $heroes
     * @param array<CombatItem> $items
     */
    public function __construct(
        private CombatVestige $vestige,
        private array $heroes,
        private array $items = [],
    ) {
        if (count($this->heroes) < 1 || count($this->heroes) > 3) {
            throw new \InvalidArgumentException(sprintf(
                'A CombatBoard must have between 1 and 3 heroes, %d given.',
                count($this->heroes)
            ));
        }
    }

    public function getVestige(): CombatVestige
    {
        return $this->vestige;
    }

    /**
     * @return array<CombatHero>
     */
    public function getHeroes(): array
    {
        return $this->heroes;
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
     * Indique de façon factuelle si le vestige du plateau est encore debout.
     */
    public function isAlive(): bool
    {
        return $this->vestige->isAlive();
    }
}
