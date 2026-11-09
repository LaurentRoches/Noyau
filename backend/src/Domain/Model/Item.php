<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;

final readonly class Item
{
    /**
     * Un objet ne porte qu'un déclencheur (D-23, `02` §7.7) : tous ses effets
     * partagent le même. La règle vit ici pour que chaque chemin d'entrée la
     * subisse — chargement du catalogue, relecture d'une archive, décoration
     * par une compétence. Elle compte les déclencheurs distincts : plusieurs
     * effets au même déclencheur restent permis, et un objet sans effet aussi.
     *
     * @param Effect[] $effects
     */
    public function __construct(
        public string $id,
        public string $name,
        public Rarity $rarity,
        public string $affinity,
        public ItemSize $size,
        public int $cooldownTicks,
        public array $effects,
    ) {
        $triggers = [];
        foreach ($effects as $effect) {
            $triggers[$effect->trigger->value] = true;
        }

        if (\count($triggers) > 1) {
            throw new \InvalidArgumentException(sprintf(
                'Item "%s" carries more than one trigger (%s); all its effects must share one.',
                $id,
                implode(', ', array_keys($triggers)),
            ));
        }
    }
}
