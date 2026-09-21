<?php

declare(strict_types=1);

namespace App\Tests\Domain\Snapshot;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\HeroSkillType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use App\Domain\Snapshot\BoardSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * La photographie d'un plateau (D-16, `04` §5.5).
 *
 * **Ce qu'elle contient, et pourquoi.** Les `Item` déjà décorés dans l'ordre
 * du plateau — ils font foi au rejeu —, la définition du Vestige, les héros
 * avec leur compétence, et `goldAtCombatStart`. Les deux dernières lignes ne
 * servent à rien aujourd'hui : elles existent parce que `OPENING` et `AURIC`
 * en auront besoin et que le format est irréversible (`02` §2.3.1).
 *
 * **Un ajout à `04` §5.5.** La table du document dit « la définition du
 * Vestige (`baseHp`, `baseShield`) ». C'est insuffisant : les `CombatEvent`
 * portent des identifiants (`target`, `sourceItemId`), et un rejeu octet pour
 * octet doit les reproduire. Sans l'identifiant du Vestige, deux Vestiges de
 * mêmes PV et bouclier photographieraient à l'identique — un faux miroir,
 * exactement le cas que §3.6 existe pour lever. La photographie porte donc un
 * identifiant partout où le journal en émet un.
 *
 * **Ce qu'elle ne contient pas, et pourquoi.** Le nom et l'affinité du
 * Vestige, son or de départ et son revenu : aucun n'entre dans un calcul de
 * combat, et le client les relit du catalogue. L'objet, lui, est embarqué en
 * entier — c'est lui que §5.5 désigne comme le cœur de la photographie.
 */
final class BoardSnapshotTest extends TestCase
{
    private function vestigeDefinition(int $baseHp = 100, int $baseShield = 10): Vestige
    {
        return new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: $baseShield,
            startingGold: 20,
            startingIncome: 5
        );
    }

    private function hero(string $id, ?HeroSkillType $skill = null): CombatHero
    {
        return new CombatHero(new Hero(
            id: $id,
            name: 'Hero ' . $id,
            affinity: 'shadow',
            itemSlots: 2,
            skill: $skill
        ));
    }

    /**
     * Objet simple : une action, tous champs facultatifs renseignés sauf ceux
     * du statut.
     */
    private function dagger(): CombatItem
    {
        return new CombatItem(new Item(
            id: 'rusty_dagger',
            name: 'Rusty Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 4,
            effects: [new Effect(Trigger::ON_ATTACK, [
                new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY),
            ])]
        ));
    }

    /**
     * Objet riche : deux actions, dont une qui renseigne les six champs
     * facultatifs d'`Action`.
     */
    private function armor(int $index): CombatItem
    {
        return new CombatItem(new Item(
            id: 'shadow_armor_' . $index,
            name: 'Shadow armor ' . $index,
            rarity: Rarity::LEGENDARY,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 18,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [
                new Action(type: ActionType::GAIN_SHIELD, value: 17, target: Target::SELF),
                new Action(
                    type: ActionType::APPLY_STATUS,
                    target: Target::SELF,
                    status: StatusType::WARD,
                    stacks: 1,
                    durationTicks: 30
                ),
            ])]
        ));
    }

    /**
     * @param list<CombatHero> $heroes
     * @param list<CombatItem> $items
     */
    private function board(array $heroes, array $items, int $gold = 40): CombatBoard
    {
        return new CombatBoard(
            new CombatVestige($this->vestigeDefinition()),
            $heroes,
            $items,
            goldAtCombatStart: $gold
        );
    }

    /**
     * La forme complète, épinglée octet pour octet.
     *
     * L'assertion porte sur la chaîne canonique et non sur le tableau : c'est
     * elle qui voyage, elle qu'on archive, et elle que le moteur embarqué doit
     * reproduire à l'octet près (NF-01). L'ordre des clés qu'on lit ici est
     * celui que `CanonicalJson` impose, pas celui dans lequel le code les
     * écrit — réordonner la construction ne doit rien casser.
     */
    public function testItPhotographsAMinimalBoardExactly(): void
    {
        $board = $this->board([$this->hero('shadow_bearer')], [$this->dagger()], gold: 40);

        self::assertSame(
            '{"goldAtCombatStart":40,"heroes":[{"id":"shadow_bearer"}],"items":[{"affinity":"neutral",'
            . '"cooldownTicks":4,"effects":[{"actions":[{"target":"ENEMY","type":"DEAL_DAMAGE","value":15}],'
            . '"trigger":"ON_ATTACK"}],"id":"rusty_dagger","name":"Rusty Dagger","rarity":"COMMON",'
            . '"size":"ONE_HAND"}],"vestige":{"baseHp":100,"baseShield":10,"id":"shadow_vestige"}}',
            BoardSnapshot::fromBoard($board)->toCanonicalJson()
        );
    }

    /**
     * Un champ absent est absent, pas `null`.
     *
     * D-19 n'autorise que `int`, `string` et `bool` : un `null` JSON n'est pas
     * dans la liste. Et `04` §5.3 désigne explicitement l'absence comme le
     * mécanisme de migration — « donner une valeur par défaut aux champs
     * absents ». Encoder `"value":null` fermerait cette porte tout en
     * alourdissant chaque photographie.
     */
    public function testItOmitsAbsentFieldsRatherThanEncodingNull(): void
    {
        $bareItem = new CombatItem(new Item(
            id: 'inert',
            name: 'Inert',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::ON_ATTACK, [new Action(type: ActionType::DEAL_DAMAGE)])]
        ));

        $json = BoardSnapshot::fromBoard(
            $this->board([$this->hero('shadow_bearer')], [$bareItem])
        )->toCanonicalJson();

        self::assertStringContainsString('"actions":[{"type":"DEAL_DAMAGE"}]', $json);
        self::assertStringNotContainsString('"skill"', $json);
        self::assertStringNotContainsString('null', $json);
    }

    public function testItCarriesTheHeroSkillWhenThereIsOne(): void
    {
        $json = BoardSnapshot::fromBoard($this->board(
            [$this->hero('shadow_bearer', HeroSkillType::RELENTLESS)],
            []
        ))->toCanonicalJson();

        self::assertStringContainsString('"heroes":[{"id":"shadow_bearer","skill":"RELENTLESS"}]', $json);
    }

    /**
     * L'ordre des objets est une donnée de jeu, pas une présentation.
     *
     * `CanonicalJson` trie les clés et jamais les listes, précisément pour ça :
     * l'ordre du plateau décide de qui frappe avant qui à l'intérieur d'un
     * camp. Deux plateaux aux mêmes objets rangés autrement sont deux plateaux
     * différents, et leurs photographies doivent le dire.
     */
    public function testItKeepsItemsInBoardOrder(): void
    {
        $heroes = [$this->hero('shadow_bearer')];

        $first = BoardSnapshot::fromBoard(
            $this->board($heroes, [$this->dagger(), $this->armor(1)])
        )->toCanonicalJson();

        $swapped = BoardSnapshot::fromBoard(
            $this->board($heroes, [$this->armor(1), $this->dagger()])
        )->toCanonicalJson();

        self::assertNotSame($first, $swapped);
    }

    /**
     * **Le test qui protège l'attribution canonique des côtés.**
     *
     * `SimulationContext::getSide()` comparera des photographies, et
     * `Simulator::groupActionsBySide()` l'appelle une fois par action en
     * attente, à chaque tick. Une photographie bâtie sur `getHp()` — l'état
     * **courant** — changerait de valeur au premier point de dégât, et
     * l'attribution des côtés basculerait en plein combat : le journal
     * deviendrait incohérent avec lui-même.
     *
     * La photographie ne lit donc que des objets immuables : `Vestige`, `Hero`,
     * `Item`. Jamais un objet de runtime.
     */
    public function testItPhotographsTheVestigeDefinitionAndNotItsCurrentState(): void
    {
        $board = $this->board([$this->hero('shadow_bearer')], [$this->dagger()]);

        $before = BoardSnapshot::fromBoard($board)->toCanonicalJson();

        $board->getVestige()->takeDamage(45);
        self::assertSame(65, $board->getVestige()->getHp(), 'Précondition : le plateau a bien changé d\'état.');

        self::assertSame($before, BoardSnapshot::fromBoard($board)->toCanonicalJson());
    }

    /**
     * Le cas miroir, que l'attribution canonique devra départager.
     *
     * Deux plateaux construits séparément mais équivalents produisent la même
     * chaîne, à l'octet près. C'est la propriété qui rend la comparaison de
     * §3.6 possible — et c'est aussi elle qui rend son critère insuffisant
     * dans ce cas précis, ce que le commit d'attribution traitera.
     */
    public function testTwoSeparatelyBuiltEquivalentBoardsProduceByteIdenticalPhotographs(): void
    {
        $a = $this->board([$this->hero('shadow_bearer', HeroSkillType::RELENTLESS)], [$this->armor(1)], gold: 75);
        $b = $this->board([$this->hero('shadow_bearer', HeroSkillType::RELENTLESS)], [$this->armor(1)], gold: 75);

        self::assertNotSame($a, $b, 'Précondition : deux plateaux distincts, pas la même instance.');
        self::assertSame(
            BoardSnapshot::fromBoard($a)->toCanonicalJson(),
            BoardSnapshot::fromBoard($b)->toCanonicalJson()
        );
    }

    /**
     * La réserve de dimensionnement de `04` §5.3, refermée par une mesure.
     *
     * Le document posait « ~5 Ko l'unité, ~25 Mo embarqués — négligeable »
     * quand le snapshot était supposé n'être qu'une recette, puis notait que
     * D-16 en faisait une photographie « pour jusqu'à six objets », sans
     * l'avoir mesurée.
     *
     * **Six est le bon chiffre** : `02` §2.1 fixe `itemSlots` à 2, contrainte
     * individuelle, donc 3 héros × 2 emplacements. Le stash n'entre pas dans
     * le compte — `CombatBoardFactory::createBoard()` ne reçoit que
     * `Inventory::getItemIdsByHero()`, jamais son contenu.
     *
     * Le plateau photographié ici est ce maximum, garni des objets les plus
     * volumineux possibles : légendaires, deux actions chacun, dont une qui
     * renseigne les six champs facultatifs d'`Action`.
     *
     * Mesuré : **2 129 octets**, soit 10,2 Mo pour 5 000 photographies toutes
     * au maximum — un corpus qui n'existera jamais, les manches basses étant
     * les plus nombreuses. Une manche 1 pèse 349 octets. L'estimation
     * d'origine était donc conservatrice d'un facteur 2,4 sur le pire cas.
     *
     * **Pourquoi une valeur exacte plutôt qu'un plafond.** Le format est
     * irréversible : une variation du chiffre signale un changement de format,
     * jamais un ajustement. Même doctrine que le bouclier de référence de
     * `SimulatorTest`, et même conséquence — si ce test rougit, c'est le
     * format qu'il faut regarder, pas le test.
     */
    public function testAReferencePhotographAtTheStructuralMaximumIsPinnedAtItsMeasuredSize(): void
    {
        $heroes = [
            $this->hero('shadow_bearer', HeroSkillType::RELENTLESS),
            $this->hero('shadow_duelist', HeroSkillType::SUNDERING),
            $this->hero('neutral_ironblade', HeroSkillType::RELENTLESS),
        ];

        $items = [];
        for ($i = 1; $i <= 6; $i++) {
            $items[] = $this->armor($i);
        }

        $json = BoardSnapshot::fromBoard($this->board($heroes, $items, gold: 250))->toCanonicalJson();

        self::assertSame(2129, strlen($json));
    }
}
