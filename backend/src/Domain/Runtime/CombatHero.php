<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

/**
 * Un héros engagé dans un combat.
 *
 * **Il ne retient qu'un profil.** Deux valeurs suffisent au combat et à sa
 * photographie : l'identifiant, que les `CombatEvent` émettent, et la
 * compétence, pour `OPENING` et `AURIC` (`02` §2.3.1). Le nom, l'affinité et le
 * budget d'emplacements appartiennent au catalogue et à l'assemblage ; les
 * exiger ici rendrait un plateau archivé impossible à rejouer sans les
 * inventer.
 *
 * **Il porte ses objets** (D-28, `04` §5.5). Un objet n'entre dans un plateau
 * qu'en passant par un héros, si bien que l'appartenance ne peut ni se perdre
 * ni laisser un orphelin. `ON_SIBLING_TRIGGERED` la lira pendant le combat
 * (`02` §7.7), et la photographie l'archive sous `heroes[].items[]`. La liste
 * n'a pas de valeur par défaut : un héros sans objet s'écrit `[]`, pour qu'un
 * oubli à l'assemblage ne produise pas en silence un héros désarmé.
 */
final class CombatHero
{
    /**
     * @param list<CombatItem> $items dans l'ordre d'affectation
     */
    public function __construct(
        private readonly HeroProfile $profile,
        private readonly array $items,
    ) {
    }

    public function getId(): string
    {
        return $this->profile->getId();
    }

    /**
     * Le profil de combat du héros.
     *
     * Ce qui est rendu ici n'est plus forcément un `Hero` de catalogue : c'est
     * le contrat minimal que le combat consomme, et qu'un plateau hydraté
     * depuis son archive peut honorer.
     */
    public function getProfile(): HeroProfile
    {
        return $this->profile;
    }

    /**
     * @return list<CombatItem> dans l'ordre d'affectation
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
