<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Runtime\HeroProfile;

/**
 * Le héros tel que le catalogue le décrit.
 *
 * **Pourquoi il implémente un port du runtime.** `HeroProfile` est déclaré dans
 * `Domain\Runtime` parce que c'est le combat qui énonce son besoin ; cette
 * classe s'y conforme par deux accesseurs qui ne font que rendre des champs
 * déjà publics.
 *
 * **`itemSlots` n'entre pas dans le profil.** Le budget d'emplacements est
 * vérifié par `CombatBoardFactory` **avant** que le héros de combat n'existe :
 * c'est une règle d'assemblage, pas une donnée de combat, et elle n'a donc rien
 * à faire dans ce que le combat ou sa photographie consomment.
 */
final readonly class Hero implements HeroProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $affinity,
        public int $itemSlots,
        public ?HeroSkillType $skill = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSkill(): ?HeroSkillType
    {
        return $this->skill;
    }
}
