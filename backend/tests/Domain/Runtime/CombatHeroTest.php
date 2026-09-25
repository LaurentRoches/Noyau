<?php

declare(strict_types=1);

namespace App\Tests\Domain\Runtime;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Model\Hero;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\HeroProfile;
use PHPUnit\Framework\TestCase;

final class CombatHeroTest extends TestCase
{
    private function createHeroDefinition(?HeroSkillType $skill = null): Hero
    {
        return new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            itemSlots: 6,
            skill: $skill,
        );
    }

    public function testGetIdDelegatesToHeroDefinition(): void
    {
        $heroDefinition = $this->createHeroDefinition();
        $combatHero = new CombatHero($heroDefinition);

        self::assertSame('shadow_bearer', $combatHero->getId());
    }

    /**
     * La photographie lit la compétence par ce chemin (D-16).
     *
     * Deux compétences du pool cible — `OPENING` et `AURIC` — agissent
     * **pendant** le combat au lieu d'être résolues à l'assemblage (`02`
     * §2.3.1). Aucune ne l'est encore, mais le champ est dans le format parce
     * que le format est irréversible.
     *
     * **`getProfile()` et non plus `getDefinition()`.** Ce héros de combat ne
     * retient plus la définition de catalogue — nom, affinité, emplacements —
     * mais l'identifiant et la compétence, les deux seules valeurs qu'un combat
     * ou sa photographie consomment. `itemSlots` n'en fait pas partie :
     * `CombatBoardFactory` vérifie le budget **avant** de construire ce héros,
     * jamais après.
     */
    public function testItExposesItsProfile(): void
    {
        $definition = $this->createHeroDefinition(HeroSkillType::RELENTLESS);
        $combatHero = new CombatHero($definition);

        self::assertSame($definition, $combatHero->getProfile());
        self::assertSame(HeroSkillType::RELENTLESS, $combatHero->getProfile()->getSkill());
    }

    /**
     * **Le test qui ouvre l'hydratation.**
     *
     * Un `CombatHero` ne consomme que deux valeurs, et ce sont exactement
     * celles que porte la photographie. Tant que le constructeur réclamait un
     * `Hero`, rejouer un plateau archivé imposait d'en fabriquer un — donc
     * d'inventer un nom, une affinité et un nombre d'emplacements que la
     * photographie ne contient pas, et n'a aucune raison de contenir.
     *
     * Le profil nu est déclaré ici plutôt qu'importé : aucune classe de
     * production ne doit pouvoir le fournir à ce stade. Que ce test compile
     * suffit à prouver que le couplage est rompu.
     */
    public function testItAcceptsAnyProfileAndNotOnlyACatalogueHero(): void
    {
        $bareProfile = new class () implements HeroProfile {
            public function getId(): string
            {
                return 'shadow_bearer';
            }

            public function getSkill(): ?HeroSkillType
            {
                return HeroSkillType::RELENTLESS;
            }
        };

        $definition = $this->createHeroDefinition(HeroSkillType::RELENTLESS);
        self::assertInstanceOf(HeroProfile::class, $definition);

        $fromCatalogue = new CombatHero($definition);
        $fromProfile = new CombatHero($bareProfile);

        self::assertSame($fromCatalogue->getId(), $fromProfile->getId());
        self::assertSame($fromCatalogue->getProfile()->getSkill(), $fromProfile->getProfile()->getSkill());
    }

    /**
     * Une compétence absente le reste, et ne devient pas une valeur par
     * défaut.
     *
     * La photographie omet le champ plutôt que d'écrire `null` (D-19) : il faut
     * donc que l'absence traverse le profil sans être transformée en chemin.
     */
    public function testAnAbsentSkillStaysAbsent(): void
    {
        $combatHero = new CombatHero($this->createHeroDefinition());

        self::assertNull($combatHero->getProfile()->getSkill());
    }
}
