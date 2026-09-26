<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

use App\Domain\Runtime\VestigeProfile;

/**
 * Le profil d'un Vestige tel qu'une archive le porte (D-16, `04` §5.5).
 *
 * **C'est tout ce que la photographie contient**, et c'est exactement ce que
 * `VestigeProfile` réclame. Cette classe est la raison d'être de l'inversion du
 * commit précédent : sans elle, rejouer un plateau imposait de fabriquer un
 * `Vestige` de catalogue complet à partir de trois nombres — donc d'inventer un
 * nom, une affinité, un or de départ et un revenu que l'archive n'a jamais vus.
 *
 * **Une classe nommée plutôt qu'anonyme.** Elle est l'une des deux seules
 * autres implémentations des ports de `Domain\Runtime` ; la nommer rend
 * l'inversion lisible dans l'arborescence, et le rejeu de référence du commit
 * suivant peut s'y référer.
 */
final readonly class HydratedVestigeProfile implements VestigeProfile
{
    public function __construct(
        private string $id,
        private int $baseHp,
        private int $baseShield,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getBaseHp(): int
    {
        return $this->baseHp;
    }

    public function getBaseShield(): int
    {
        return $this->baseShield;
    }
}
