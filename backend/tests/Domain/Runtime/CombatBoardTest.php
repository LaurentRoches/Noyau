<?php

declare(strict_types=1);

namespace App\Tests\Domain\Runtime;

use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class CombatBoardTest extends TestCase
{
    private function createVestigeDefinition(int $baseHp = 100): Vestige
    {
        return new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: 10,
            startingGold: 0,
            startingIncome: 0
        );
    }

    private function createHeroDefinition(): Hero
    {
        return new Hero(
            id: 'shadow_bearer',
            name: "Shadow's Bearer",
            affinity: 'shadow',
            itemSlots: 6
        );
    }

    public function testBoardReturnsSameHeroesAndVestigeInstance(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $hero = new CombatHero($this->createHeroDefinition());

        $combatBoard = new CombatBoard($vestige, [$hero], items: [], goldAtCombatStart: 0);

        self::assertSame($vestige, $combatBoard->getVestige());
        self::assertSame([$hero], $combatBoard->getHeroes());
    }

    public function testGetReadyItemsReturnsOnlyItemsWithZeroCooldown(): void
    {
        $readyItemDef = new Item(
            id: 'quick_dagger',
            name: 'Quick Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 0,
            effects: []
        );
        $readyItem = new CombatItem($readyItemDef);

        $notReadyItemDef = new Item(
            id: 'heavy_hammer',
            name: 'Heavy Hammer',
            rarity: Rarity::RARE,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 4,
            effects: []
        );
        $notReadyItem = new CombatItem($notReadyItemDef);

        $vestige = new CombatVestige($this->createVestigeDefinition());
        $hero = new CombatHero($this->createHeroDefinition());
        $combatBoard = new CombatBoard($vestige, [$hero], [$readyItem, $notReadyItem], goldAtCombatStart: 0);

        $readyItems = $combatBoard->getReadyItems();

        self::assertCount(1, $readyItems);
        self::assertSame($readyItem, $readyItems[0]);
    }

    public function testIsAliveReturnsTrueWhenVestigeIsAlive(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition(baseHp: 100));
        $hero = new CombatHero($this->createHeroDefinition());
        $combatBoard = new CombatBoard($vestige, [$hero], [], goldAtCombatStart: 0);

        self::assertTrue($combatBoard->isAlive());
    }

    public function testIsAliveReturnsFalseWhenVestigeIsDead(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition(baseHp: 100));
        $vestige->takeDamage(120);

        $hero = new CombatHero($this->createHeroDefinition());
        $combatBoard = new CombatBoard($vestige, [$hero], [], goldAtCombatStart: 0);

        self::assertFalse($combatBoard->isAlive());
    }

    public function testBoardAcceptsUpToThreeHeroes(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $heroes = [
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
        ];

        $combatBoard = new CombatBoard($vestige, $heroes, [], goldAtCombatStart: 0);

        self::assertCount(3, $combatBoard->getHeroes());
    }

    public function testBoardRejectsZeroHeroes(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());

        $this->expectException(\InvalidArgumentException::class);
        new CombatBoard($vestige, [], [], goldAtCombatStart: 0);
    }

    public function testBoardRejectsMoreThanThreeHeroes(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $heroes = [
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
            new CombatHero($this->createHeroDefinition()),
        ];

        $this->expectException(\InvalidArgumentException::class);
        new CombatBoard($vestige, $heroes, [], goldAtCombatStart: 0);
    }

    /**
     * L'or d'entrée de combat est un état de plateau (D-16, `04` §5.5).
     *
     * **Pourquoi sur le plateau et pas à côté.** `02` §2.3.1 classe `AURIC`
     * parmi les deux seules compétences du pool cible qui n'agissent pas par
     * décoration : elle a besoin de lire l'or **pendant** le combat. Une
     * compétence qui lit l'or au tick N le lit sur le plateau — le sérialiseur
     * de snapshot n'est pas dans la boucle de combat. Passer l'or à la
     * photographie en second argument laisserait de surcroît au producteur du
     * snapshot la liberté d'en passer un autre que celui du combat.
     */
    public function testBoardCarriesTheGoldBalanceItWasBuiltWith(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $hero = new CombatHero($this->createHeroDefinition());

        $combatBoard = new CombatBoard($vestige, [$hero], [], goldAtCombatStart: 40);

        self::assertSame(40, $combatBoard->getGoldAtCombatStart());
    }

    /**
     * Fail-fast plutôt que valeur par défaut silencieuse.
     *
     * `Wallet` refuse déjà un solde initial négatif et `spend()` ne peut pas
     * passer sous zéro : un or négatif ici ne peut venir que d'un défaut de
     * câblage. Le laisser entrer le figerait dans une photographie que
     * personne ne pourra plus rejouer — le format est irréversible, pas la
     * valeur. C'est la leçon de l'écart 7 de `02` §10 (`baseShield`), prise
     * cette fois avant et non après.
     */
    public function testBoardRejectsANegativeGoldBalance(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $hero = new CombatHero($this->createHeroDefinition());

        $this->expectException(\InvalidArgumentException::class);
        new CombatBoard($vestige, [$hero], [], goldAtCombatStart: -1);
    }
}
