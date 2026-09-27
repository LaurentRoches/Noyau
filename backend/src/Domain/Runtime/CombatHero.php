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
 */
final class CombatHero
{
    public function __construct(
        private readonly HeroProfile $profile,
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
}
