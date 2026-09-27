<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Runtime\HeroProfile;

/**
 * Le profil d'un héros tel qu'une archive le porte (D-16, `04` §5.5).
 *
 * **Deux valeurs, et l'absence reste une absence.** La photographie omet le
 * champ `skill` quand le héros n'a pas de compétence (D-19, `04` §5.3) ; le
 * défaut à `null` traduit cette absence sans la transformer en chemin. Un
 * défaut différent — une compétence « neutre », une valeur sentinelle — ferait
 * diverger la photographie de retour de celle de départ, et le rejeu octet pour
 * octet tomberait sans qu'aucune exception ne soit levée.
 *
 * **`itemSlots` n'y est pas** : le budget d'emplacements est une règle
 * d'assemblage, vérifiée par `CombatBoardFactory` avant que le héros de combat
 * n'existe. Les objets d'une archive sont ceux qui ont réellement combattu ; il
 * n'y a rien à revalider.
 */
final readonly class HydratedHeroProfile implements HeroProfile
{
    public function __construct(
        private string $id,
        private ?HeroSkillType $skill = null,
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
