<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Enum\HeroSkillType;

/**
 * Ce qu'un combat lit d'un héros — et rien de plus (D-16, `04` §5.5).
 *
 * **Le port appartient au consommateur**, pour la même raison que
 * `VestigeProfile` : c'est le combat qui énonce son besoin, `Hero` qui s'y
 * conforme.
 *
 * **Deux accesseurs.** L'identifiant, que les `CombatEvent` émettent et qu'un
 * rejeu octet pour octet doit reproduire ; la compétence, pour `OPENING` et
 * `AURIC` — les deux seules du pool cible qui agissent **pendant** le combat
 * au lieu d'être résolues à l'assemblage (`02` §2.3.1). Aucune ne l'est
 * encore, mais le champ est dans le format parce que le format est
 * irréversible.
 *
 * **`itemSlots` n'en fait pas partie.** `CombatBoardFactory` vérifie le budget
 * d'emplacements **avant** de construire le héros de combat, jamais après :
 * une photographie n'a donc pas à le porter, et un rejeu n'a pas à le
 * revalider — les objets qu'elle contient sont ceux qui ont réellement
 * combattu, déjà décorés et déjà comptés.
 *
 * **L'absence reste une absence.** `getSkill()` rend `null` quand le héros n'a
 * pas de compétence, et `BoardSnapshot` omet alors le champ plutôt que
 * d'écrire `"skill":null` (D-19, `04` §5.3).
 */
interface HeroProfile
{
    public function getId(): string;

    public function getSkill(): ?HeroSkillType;
}
