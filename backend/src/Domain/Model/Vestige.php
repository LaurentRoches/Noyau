<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Runtime\VestigeProfile;

/**
 * Le Vestige tel que le catalogue le décrit.
 *
 * **Pourquoi il implémente un port du runtime.** `VestigeProfile` est déclaré
 * dans `Domain\Runtime` parce que c'est le combat qui énonce son besoin ; cette
 * classe s'y conforme par trois accesseurs qui ne font que rendre des champs
 * déjà publics. Aucun état n'est ajouté, aucune règle n'est déplacée : la
 * dépendance pointe du catalogue vers le contrat, jamais l'inverse, et c'est ce
 * qui permettra à un plateau archivé d'être rejoué sans entrée de catalogue.
 *
 * **Les champs publics restent publics.** Les soixante-six sites de
 * construction existants et la lecture directe `$vestige->baseHp` continuent de
 * fonctionner : ce commit ajoute un chemin, il n'en ferme aucun.
 */
final readonly class Vestige implements VestigeProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $affinity,
        public int $baseHp,
        public int $baseShield,
        public int $startingGold,
        public int $startingIncome
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
