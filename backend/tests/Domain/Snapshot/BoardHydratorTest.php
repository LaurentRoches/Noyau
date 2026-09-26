<?php

declare(strict_types=1);

namespace App\Tests\Domain\Snapshot;

use App\Domain\Engine\CanonicalJson;
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
use App\Domain\Snapshot\BoardHydrator;
use App\Domain\Snapshot\BoardRecord;
use App\Domain\Snapshot\BoardSnapshot;
use App\Domain\Snapshot\SnapshotRecipe;
use App\Domain\Snapshot\UnreadableBoardRecordException;
use PHPUnit\Framework\TestCase;

/**
 * Le chemin de retour : une enveloppe archivée redevient un plateau jouable
 * (D-16, `04` §5.5).
 *
 * **Il ne reçoit qu'une chaîne.** Pas de tableau déjà décodé, pas de chemin de
 * configuration, pas de dépôt. L'isolement est structurel et non déclaratif :
 * une signature qui n'accepte qu'un `string` rend impossible, et non
 * seulement déconseillée, la relecture d'un catalogue au rejeu. C'est la
 * première des deux bornes de D-16 — un fantôme archivé avant un rééquilibrage
 * rejoue avec ses chiffres d'origine — et aucun test ne peut la garantir si le
 * type d'entrée laisse la porte ouverte.
 *
 * **Ce qu'il ne refait pas.** `HeroSkillDecorator` n'est pas réappliqué : la
 * photographie porte les objets **déjà décorés**, et les redécorer les
 * décorerait deux fois. Le budget d'emplacements n'est pas revérifié :
 * `CombatBoardFactory` l'a contrôlé avant que le plateau n'existe, et les
 * objets archivés sont ceux qui ont réellement combattu.
 *
 * **Deux versions, deux rôles** (`04` §5.3). `formatVersion` dit comment lire
 * l'enveloppe : une valeur inconnue est un refus, parce qu'il n'y a rien à
 * tenter. `engineVersion` dit sous quelles règles le journal a été produit —
 * c'est la question du droit de **resimuler** (`04` §6), pas celle du droit de
 * reconstruire. L'hydrateur ne la lit pas, et un test l'épingle : sans lui, la
 * prochaine lecture de ce fichier « corrigera » l'oubli et l'hydrateur se
 * mettra à refuser des plateaux qu'il sait parfaitement rebâtir.
 *
 * **Ni recette ni version de contenu.** Toutes deux voyagent dans l'enveloppe
 * et relèvent de la provenance. La recette n'est même pas dérivable du plateau
 * — l'association héros ↔ objet est perdue dans la liste plate de
 * `CombatBoard` — ce qui la rend impossible à contrôler ici ; elle est donc
 * ignorée franchement plutôt que crue à moitié.
 */
final class BoardHydratorTest extends TestCase
{
    private function vestigeDefinition(): Vestige
    {
        return new Vestige(
            id: 'shadow_vestige',
            name: 'Shadow Vestige',
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 10,
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
     * facultatifs d'`Action`. Identique à celui de `BoardSnapshotTest`, pour
     * que la mesure des 2 129 octets reste la même des deux côtés.
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

    private function simpleBoard(): CombatBoard
    {
        return $this->board([$this->hero('shadow_bearer')], [$this->dagger()]);
    }

    private function simpleRecipe(): SnapshotRecipe
    {
        return new SnapshotRecipe(
            vestigeId: 'shadow_vestige',
            heroIds: ['shadow_bearer'],
            itemIdsByHero: ['shadow_bearer' => ['rusty_dagger']],
        );
    }

    private function simpleEnvelope(?string $contentVersion = 'a1b2c3'): string
    {
        return BoardRecord::fromBoard(
            $this->simpleBoard(),
            $this->simpleRecipe(),
            contentVersion: $contentVersion,
        )->toCanonicalJson();
    }

    private function maximalBoard(): CombatBoard
    {
        $items = [];
        for ($i = 1; $i <= 6; $i++) {
            $items[] = $this->armor($i);
        }

        return $this->board([
            $this->hero('shadow_bearer', HeroSkillType::RELENTLESS),
            $this->hero('shadow_duelist', HeroSkillType::SUNDERING),
            $this->hero('neutral_ironblade', HeroSkillType::RELENTLESS),
        ], $items, gold: 250);
    }

    private function maximalEnvelope(): string
    {
        return BoardRecord::fromBoard(
            $this->maximalBoard(),
            new SnapshotRecipe(
                vestigeId: 'shadow_vestige',
                heroIds: ['shadow_bearer', 'shadow_duelist', 'neutral_ironblade'],
                itemIdsByHero: [
                    'shadow_bearer' => ['shadow_armor_1', 'shadow_armor_2'],
                    'shadow_duelist' => ['shadow_armor_3', 'shadow_armor_4'],
                    'neutral_ironblade' => ['shadow_armor_5', 'shadow_armor_6'],
                ],
            ),
            contentVersion: 'a1b2c3',
        )->toCanonicalJson();
    }

    /**
     * Abîme une enveloppe valide au lieu d'en écrire une à la main.
     *
     * Une enveloppe écrite à la main dans un test finirait par diverger de
     * celle que la production écrit, sans que rien ne le signale. Partir de la
     * vraie et n'en changer qu'un champ garantit que le reste est exactement ce
     * que `BoardRecord` produit.
     *
     * @param \Closure(array<string, mixed>): array<string, mixed> $mutation
     */
    private function mutate(string $json, \Closure $mutation): string
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return CanonicalJson::encode($mutation($data));
    }

    private function photographOf(CombatBoard $board): string
    {
        return BoardSnapshot::fromBoard($board)->toCanonicalJson();
    }

    /**
     * **Le test qui porte tout le commit.**
     *
     * Un plateau photographié, archivé, relu et rephotographié doit rendre la
     * même chaîne, à l'octet près. C'est la propriété qui rend le rejeu
     * possible, et elle est vérifiée sur la photographie plutôt que sur la
     * structure : c'est la chaîne qui voyage, c'est elle qu'on archive, et
     * c'est elle que le moteur embarqué devra reproduire (NF-01).
     */
    public function testItRebuildsABoardWhosePhotographIsByteIdenticalToTheOriginal(): void
    {
        $board = $this->simpleBoard();

        $hydrated = BoardHydrator::fromCanonicalJson($this->simpleEnvelope());

        self::assertSame($this->photographOf($board), $this->photographOf($hydrated));
    }

    /**
     * Le maximum structurel, celui que `BoardSnapshotTest` épingle à 2 129
     * octets : 3 héros, 6 objets légendaires à deux actions.
     *
     * Un plateau minimal ne verrait pas une troncature — une liste coupée au
     * premier élément, un effet perdu, une action facultative avalée. Celui-ci
     * la verrait.
     */
    public function testItRebuildsTheStructuralMaximumByteForByte(): void
    {
        $photograph = $this->photographOf($this->maximalBoard());
        self::assertSame(2129, strlen($photograph), 'Précondition : le plateau de référence n\'a pas bougé.');

        $hydrated = BoardHydrator::fromCanonicalJson($this->maximalEnvelope());

        self::assertSame($photograph, $this->photographOf($hydrated));
    }

    /**
     * Un champ absent le reste après l'aller-retour.
     *
     * `04` §5.3 fait de l'absence le mécanisme de migration du format : un
     * hydrateur qui retraduirait l'absence en `0`, en chaîne vide ou en `null`
     * fermerait cette porte au premier rejeu, et la photographie de retour ne
     * serait plus celle de départ.
     */
    public function testItKeepsAbsentFieldsAbsentThroughTheRoundTrip(): void
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

        $envelope = BoardRecord::fromBoard(
            $this->board([$this->hero('shadow_bearer')], [$bareItem], gold: 0),
            new SnapshotRecipe(
                vestigeId: 'shadow_vestige',
                heroIds: ['shadow_bearer'],
                itemIdsByHero: ['shadow_bearer' => ['inert']],
            ),
            contentVersion: 'a1b2c3',
        )->toCanonicalJson();

        $photograph = $this->photographOf(BoardHydrator::fromCanonicalJson($envelope));

        self::assertStringContainsString('"actions":[{"type":"DEAL_DAMAGE"}]', $photograph);
        self::assertStringNotContainsString('"skill"', $photograph);
        self::assertStringNotContainsString('null', $photograph);
    }

    /**
     * Le plateau rejoué démarre dans l'état d'un plateau assemblé.
     *
     * La photographie ne porte aucun état de runtime — ni PV courants, ni
     * cooldowns entamés — et c'est délibéré : elle photographie le plateau au
     * lancement. L'état de départ se déduit donc entièrement des valeurs de
     * base, exactement comme lors d'un assemblage. Si ce test rougit, c'est que
     * quelqu'un a commencé à archiver du runtime.
     */
    public function testItRebuildsABoardInTheSameStartingStateAsAnAssembledOne(): void
    {
        $hydrated = BoardHydrator::fromCanonicalJson($this->maximalEnvelope());

        self::assertSame(100, $hydrated->getVestige()->getHp());
        self::assertSame(10, $hydrated->getVestige()->getShield());
        self::assertSame(250, $hydrated->getGoldAtCombatStart());
        self::assertCount(3, $hydrated->getHeroes());
        self::assertCount(6, $hydrated->getItems());
        self::assertSame(18, $hydrated->getItems()[0]->getCooldown());
    }

    /**
     * L'ordre des objets est une donnée de jeu, pas une présentation.
     *
     * `CanonicalJson` trie les clés et jamais les listes, précisément parce que
     * l'ordre du plateau décide de qui frappe avant qui à l'intérieur d'un
     * camp. Un hydrateur qui reconstruirait par identifiant, ou qui rangerait
     * au passage, casserait le rejeu sans qu'aucune exception ne soit levée.
     */
    public function testItKeepsItemsInBoardOrder(): void
    {
        $hydrated = BoardHydrator::fromCanonicalJson($this->maximalEnvelope());

        self::assertSame(
            [
                'shadow_armor_1',
                'shadow_armor_2',
                'shadow_armor_3',
                'shadow_armor_4',
                'shadow_armor_5',
                'shadow_armor_6',
            ],
            array_map(
                static fn (CombatItem $item): string => $item->getItem()->id,
                $hydrated->getItems()
            )
        );
    }

    /**
     * La recette est de la provenance, la photographie est la vérité.
     *
     * L'enveloppe porte les deux. Une recette qui ment — ici trois héros
     * annoncés pour un seul photographié — ne doit changer ni le nombre de
     * héros rebâtis, ni la photographie de retour. `BoardRecord::fromBoard()`
     * contrôle déjà la cohérence à l'écriture ; ce test dit ce qui arrive quand
     * une ligne plus ancienne que ce contrôle remonte du corpus.
     */
    public function testItReadsThePhotographAndNotTheRecipe(): void
    {
        $lying = $this->mutate($this->simpleEnvelope(), static function (array $data): array {
            $data['recipe'] = [
                'vestigeId' => 'un_autre_vestige',
                'heroIds' => ['a', 'b', 'c'],
                'itemIdsByHero' => ['a' => ['x', 'y']],
            ];

            return $data;
        });

        $hydrated = BoardHydrator::fromCanonicalJson($lying);

        self::assertCount(1, $hydrated->getHeroes());
        self::assertSame($this->photographOf($this->simpleBoard()), $this->photographOf($hydrated));
    }

    /**
     * La version de contenu n'est pas regardée, et c'est tout l'intérêt.
     *
     * D-16 : un fantôme archivé avant un rééquilibrage rejoue avec **ses**
     * chiffres. Refuser une archive dont la version de contenu a changé, ou
     * pire, relire le catalogue courant pour la « corriger », viderait le
     * chantier de son objet.
     */
    public function testItIgnoresTheContentVersion(): void
    {
        $obsolete = $this->simpleEnvelope('contenu-obsolete');
        $absent = $this->simpleEnvelope(null);
        self::assertNotSame($obsolete, $absent, 'Précondition : les deux enveloppes diffèrent bien.');

        self::assertSame(
            $this->photographOf(BoardHydrator::fromCanonicalJson($obsolete)),
            $this->photographOf(BoardHydrator::fromCanonicalJson($absent))
        );
    }

    /**
     * La version de moteur n'est pas regardée non plus, et celle-ci mérite une
     * explication.
     *
     * `04` §6 : l'`engineVersion` décide si le `CombatLog` d'un combat archivé
     * peut être **resimulé**. C'est une question d'exécution, tranchée par
     * l'appelant — qui la lit dans la colonne `engine_version` de
     * `combat_records`, sans décoder l'enveloppe. Reconstruire un plateau est
     * une question de **format**, et le format a son propre champ.
     *
     * Ce test existe parce que la confusion est facile — je l'ai faite en
     * cadrant ce commit. Sans lui, la prochaine lecture de ce fichier
     * « corrigera » l'oubli, et l'hydrateur refusera des plateaux qu'il sait
     * parfaitement rebâtir.
     */
    public function testItIgnoresTheEngineVersion(): void
    {
        $fromAFutureEngine = $this->mutate($this->simpleEnvelope(), static function (array $data): array {
            $data['engineVersion'] = 999;

            return $data;
        });

        self::assertSame(
            $this->photographOf($this->simpleBoard()),
            $this->photographOf(BoardHydrator::fromCanonicalJson($fromAFutureEngine))
        );
    }

    /**
     * Le garde-fou du banc d'essai lui-même.
     *
     * Les tests de refus partent d'une enveloppe réelle qu'ils abîment d'un
     * champ. Encore faut-il que le décodage suivi du ré-encodage canonique
     * rende la chaîne de départ — sinon ces tests porteraient sur une enveloppe
     * que la production n'écrit pas, et leur verdict ne vaudrait rien.
     */
    public function testTheMutationHelperReproducesAnUntouchedEnvelopeExactly(): void
    {
        $envelope = $this->maximalEnvelope();

        self::assertSame($envelope, $this->mutate($envelope, static fn (array $data): array => $data));
    }

    /**
     * Une version de format inconnue est un refus, pas une tentative.
     *
     * `BoardRecord::FORMAT_VERSION` dit comment lire l'enveloppe. Une valeur
     * qu'on ne connaît pas signifie que la disposition des champs a changé :
     * deviner reviendrait à produire un plateau plausible et faux, ce qui est
     * la seule issue dont on ne se relève pas sur un format de parité.
     */
    public function testItRefusesAnUnknownFormatVersion(): void
    {
        $envelope = $this->mutate($this->simpleEnvelope(), static function (array $data): array {
            $data['formatVersion'] = 2;

            return $data;
        });

        $this->expectException(UnreadableBoardRecordException::class);
        $this->expectExceptionMessage('format version 2');

        BoardHydrator::fromCanonicalJson($envelope);
    }

    /**
     * Un JSON invalide sort en `UnreadableBoardRecordException`, pas en
     * `JsonException`.
     *
     * L'appelant du chantier 11 écartera une archive illisible de son bassin
     * d'appariement : il lui faut un seul type à attraper, quelle que soit la
     * façon dont la ligne est abîmée.
     */
    public function testItRefusesMalformedJson(): void
    {
        $this->expectException(UnreadableBoardRecordException::class);

        BoardHydrator::fromCanonicalJson('{"board":');
    }

    public function testItRefusesAnEnvelopeMissingItsBoard(): void
    {
        $envelope = $this->mutate($this->simpleEnvelope(), static function (array $data): array {
            unset($data['board']);

            return $data;
        });

        $this->expectException(UnreadableBoardRecordException::class);
        $this->expectExceptionMessage('board');

        BoardHydrator::fromCanonicalJson($envelope);
    }

    /**
     * Un champ du bon nom mais du mauvais type est refusé nommément.
     *
     * Sans ce contrôle, `"100"` traverserait le décodage et exploserait en
     * `TypeError` au constructeur — une exception qui ne dit pas quelle archive
     * est en cause ni où. Le message porte le chemin du champ, parce que c'est
     * la seule chose qui rende une ligne de corpus réparable.
     */
    public function testItRefusesAFieldOfTheWrongType(): void
    {
        $envelope = $this->mutate($this->simpleEnvelope(), static function (array $data): array {
            $data['board']['vestige']['baseHp'] = '100';

            return $data;
        });

        $this->expectException(UnreadableBoardRecordException::class);
        $this->expectExceptionMessage('board.vestige.baseHp');

        BoardHydrator::fromCanonicalJson($envelope);
    }

    /**
     * Une enveloppe bien formée peut décrire un plateau injouable.
     *
     * `CombatBoard` refuse déjà moins d'un héros ou plus de trois, et un or
     * négatif. Ces règles ne sont pas réécrites ici : elles sont attrapées et
     * rhabillées, pour que l'appelant n'ait toujours qu'un seul type à
     * connaître.
     */
    public function testItRefusesAnEnvelopeThatDoesNotDescribeAPlayableBoard(): void
    {
        $envelope = $this->mutate($this->simpleEnvelope(), static function (array $data): array {
            $data['board']['heroes'] = [];

            return $data;
        });

        $this->expectException(UnreadableBoardRecordException::class);
        $this->expectExceptionMessage('between 1 and 3 heroes');

        BoardHydrator::fromCanonicalJson($envelope);
    }

    /**
     * Une valeur qu'aucun cas d'énumération ne connaît est refusée.
     *
     * C'est le cas qui arrivera pour de bon : une rareté, un déclencheur ou un
     * type d'action retirés du jeu après qu'un corpus les a enregistrés.
     * `tryFrom` rend `null` là où `from` lèverait un `ValueError` — le refus
     * est donc explicite, et il nomme le champ.
     */
    public function testItRefusesAValueNoEnumKnows(): void
    {
        $envelope = $this->mutate($this->simpleEnvelope(), static function (array $data): array {
            $data['board']['items'][0]['rarity'] = 'MYTHIC';

            return $data;
        });

        $this->expectException(UnreadableBoardRecordException::class);
        $this->expectExceptionMessage('board.items.0.rarity');

        BoardHydrator::fromCanonicalJson($envelope);
    }
}
