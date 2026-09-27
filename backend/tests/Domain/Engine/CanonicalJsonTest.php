<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\CanonicalJson;
use PHPUnit\Framework\TestCase;

/**
 * Encodage canonique partagé (chantier 2, commit 8).
 *
 * Extrait de `CombatLogSerializer`, qui l'appliquait à des charges utiles
 * **plates**. Le snapshot, lui, est imbriqué de part en part — objets, effets,
 * actions — et les deux ont besoin des mêmes octets pour la même raison :
 * NF-01 exige une chaîne identique entre le serveur et le binaire embarqué.
 *
 * **Ce qui est partagé, et ce qui ne l'est pas.** Le tri et les options JSON
 * sont communs. Le refus des `null` et des tableaux imbriqués ne l'est pas :
 * il appartient au contrat de **platitude** des charges utiles d'événement, que
 * le snapshot ne partage pas. Une extraction qui aurait tout emporté aurait
 * rendu le snapshot inexprimable.
 */
final class CanonicalJsonTest extends TestCase
{
    // --- Tri --------------------------------------------------------------

    public function testItSortsStringKeysAtEveryLevel(): void
    {
        self::assertSame(
            '{"a":{"x":{"m":1,"z":2},"y":3},"b":4}',
            CanonicalJson::encode([
                'b' => 4,
                'a' => ['y' => 3, 'x' => ['z' => 2, 'm' => 1]],
            ]),
        );
    }

    /**
     * La règle qui distingue cet encodeur d'un tri naïf.
     *
     * L'ordre des objets sur un plateau, des effets dans un objet et des
     * actions dans un effet est une **donnée de jeu** : il décide de qui frappe
     * avant qui. Trier ces listes changerait le combat, pas sa représentation.
     */
    public function testItNeverSortsLists(): void
    {
        self::assertSame(
            '[{"id":"zeta"},{"id":"alpha"}]',
            CanonicalJson::encode([['id' => 'zeta'], ['id' => 'alpha']]),
        );
    }

    public function testItSortsMapsNestedInsideLists(): void
    {
        self::assertSame(
            '{"items":[{"cooldownTicks":20,"id":"dagger"}]}',
            CanonicalJson::encode(['items' => [['id' => 'dagger', 'cooldownTicks' => 20]]]),
        );
    }

    public function testKeyOrderAtInsertionDoesNotChangeTheOutput(): void
    {
        self::assertSame(
            CanonicalJson::encode(['b' => 1, 'a' => ['d' => 2, 'c' => 3]]),
            CanonicalJson::encode(['a' => ['c' => 3, 'd' => 2], 'b' => 1]),
        );
    }

    // --- Types ------------------------------------------------------------

    /**
     * Même motif qu'en D-19, et il vaut à toute profondeur.
     *
     * L'écriture JSON d'un flottant dépend de `serialize_precision`, réglage
     * d'exécution que le serveur et le binaire `static-php-cli` peuvent ne pas
     * partager. NF-01 tomberait sans qu'aucun calcul ne soit faux.
     */
    public function testItRejectsAFloatAndNamesItsPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Le chemin, pas seulement la clé : dans une structure à quatre niveaux,
        // « value » seul n'aide personne.
        $this->expectExceptionMessage('items.0.effects.0.actions.0.value');

        CanonicalJson::encode([
            'items' => [['effects' => [['actions' => [['value' => 12.5]]]]]],
        ]);
    }

    /**
     * `null` est admis ici, contrairement à `CombatLogSerializer`.
     *
     * Son refus là-bas relève du contrat de platitude des charges utiles, pas
     * de la canonicité des octets. Le snapshot omet ses champs nuls plutôt que
     * de les écrire, mais c'est **sa** règle à lui : l'encodeur ne l'impose pas.
     */
    public function testItAcceptsNullAndBooleans(): void
    {
        self::assertSame(
            '{"absent":null,"flag":true}',
            CanonicalJson::encode(['flag' => true, 'absent' => null]),
        );
    }

    // --- Options d'encodage -----------------------------------------------

    public function testItEscapesNeitherSlashesNorUnicode(): void
    {
        self::assertSame(
            '{"name":"Panacée","path":"a/b"}',
            CanonicalJson::encode(['path' => 'a/b', 'name' => 'Panacée']),
        );
    }

    public function testItWritesNoWhitespace(): void
    {
        $encoded = CanonicalJson::encode(['a' => 1, 'b' => ['c' => 2]]);

        self::assertSame('{"a":1,"b":{"c":2}}', $encoded);
        self::assertStringNotContainsString(' ', $encoded);
        self::assertStringNotContainsString("\n", $encoded);
    }

    // --- Invariants -------------------------------------------------------

    public function testItLeavesItsInputUntouched(): void
    {
        $input = ['b' => 1, 'a' => ['d' => 2, 'c' => 3]];
        $before = $input;

        CanonicalJson::encode($input);

        self::assertSame($before, $input);
    }

    public function testItIsDeterministicAcrossCalls(): void
    {
        $value = ['items' => [['id' => 'dagger', 'cooldownTicks' => 20]]];

        self::assertSame(CanonicalJson::encode($value), CanonicalJson::encode($value));
    }

    /**
     * Un tableau vide s'encode `[]`, pas `{}`.
     *
     * L'ambiguïté est réelle en PHP et `CombatLogSerializer` la résout par un
     * cast explicite en objet, parce qu'une charge utile vide doit rester un
     * objet. C'est son besoin, pas celui de l'encodeur : un plateau sans objet
     * porte bien une **liste** vide.
     */
    public function testAnEmptyArrayEncodesAsAList(): void
    {
        self::assertSame('{"items":[]}', CanonicalJson::encode(['items' => []]));
    }
}
