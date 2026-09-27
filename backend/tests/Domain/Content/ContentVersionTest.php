<?php

declare(strict_types=1);

namespace App\Tests\Domain\Content;

use App\Domain\Content\ContentVersion;
use PHPUnit\Framework\TestCase;

/**
 * L'empreinte du contenu de jeu (D-18, `04` §6.3).
 *
 * **Ce qu'elle décide.** Une run enregistre l'empreinte du catalogue sous
 * lequel elle a été jouée. Si le catalogue change, son journal d'actions ne
 * rejoue plus la même partie — une offre de boutique n'a plus les mêmes prix,
 * un objet acheté n'a plus les mêmes valeurs — et la run est **rejetée**
 * plutôt que rejouée de travers. Avant J1 la base est jetable, c'est la
 * politique D-18.
 *
 * **Pourquoi la règle est pure.** Le calcul est irréversible : il gouverne le
 * rejet de runs, et toute variation de sa définition invalide d'un coup toutes
 * les runs enregistrées. Il est donc épinglé ici sans fichier ni base, à
 * l'écart du lecteur d'Infrastructure qui, lui, peut changer librement.
 *
 * **Pourquoi une enveloppe plutôt qu'une concaténation.** Concaténer quatre
 * JSON canoniques imposerait de fixer leur ordre par convention, qu'il
 * faudrait ensuite se rappeler. L'enveloppe le fait dire par `CanonicalJson` —
 * qui trie déjà les clés texte — et les clés portent les noms de fichiers,
 * donc renommer un catalogue change l'empreinte, ce qui est correct.
 */
final class ContentVersionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function catalogs(): array
    {
        return [
            'heroes' => [['id' => 'h1', 'name' => 'Hero One', 'itemSlots' => 2]],
            'items' => [['id' => 'i1', 'name' => 'Item One', 'cooldownTicks' => 20]],
            'scripted_opponent' => [['heroId' => 'h1', 'itemIds' => ['i1']]],
            'vestiges' => [['id' => 'v1', 'baseHp' => 100, 'baseShield' => 10]],
        ];
    }

    /**
     * La règle, épinglée sur une valeur mesurée.
     *
     * Ce n'est pas un test tautologique : il fige **que** l'empreinte est le
     * SHA-256 de l'enveloppe canonique, et non d'une concaténation, d'un
     * `serialize()` ou d'un `json_encode()` brut. Trois candidats qui
     * rendraient un nombre tout aussi plausible et un corpus incompatible.
     *
     * Si ce chiffre bouge, **toutes les runs enregistrées deviennent
     * irrejouables** : c'est un signal, pas un test à mettre à jour.
     */
    public function testItIsTheSha256OfTheCanonicalEnvelope(): void
    {
        self::assertSame(
            'fa5d804aff6db8de4cd885774c269ccb48c0635add0bfb971678eae35f7d39b5',
            ContentVersion::fromCatalogs($this->catalogs())
        );
    }

    /**
     * Un reformatage ne change rien — c'est la raison d'être de la
     * canonicalisation.
     *
     * Réindenter un catalogue, réordonner les clés d'un objet ou ajouter une
     * espace avant un deux-points sont des non-événements. `items.json` porte
     * d'ailleurs aujourd'hui deux `"actions" : [` avec cette espace : sans
     * canonicalisation, le jour où quelqu'un la retire, toutes les runs en
     * cours seraient rejetées pour rien.
     */
    public function testReformattingACatalogDoesNotChangeIt(): void
    {
        $reformatted = [
            'vestiges' => [['baseShield' => 10, 'baseHp' => 100, 'id' => 'v1']],
            'items' => [['cooldownTicks' => 20, 'name' => 'Item One', 'id' => 'i1']],
            'heroes' => [['itemSlots' => 2, 'id' => 'h1', 'name' => 'Hero One']],
            'scripted_opponent' => [['itemIds' => ['i1'], 'heroId' => 'h1']],
        ];

        self::assertSame(
            ContentVersion::fromCatalogs($this->catalogs()),
            ContentVersion::fromCatalogs($reformatted)
        );
    }

    /**
     * L'ordre des entrées d'un catalogue **est** du contenu.
     *
     * `CanonicalJson` ne trie jamais les listes, et c'est exactement ce qu'il
     * faut ici : l'ordre des objets décide des offres de boutique, donc du
     * déroulé d'une run. Permuter deux objets doit invalider les runs en
     * cours, au même titre qu'en changer les valeurs.
     */
    public function testReorderingTheEntriesOfACatalogChangesIt(): void
    {
        $first = $this->catalogs();
        $first['items'] = [
            ['id' => 'i0', 'name' => 'Item Zero', 'cooldownTicks' => 10],
            ['id' => 'i1', 'name' => 'Item One', 'cooldownTicks' => 20],
        ];

        $swapped = $this->catalogs();
        $swapped['items'] = [
            ['id' => 'i1', 'name' => 'Item One', 'cooldownTicks' => 20],
            ['id' => 'i0', 'name' => 'Item Zero', 'cooldownTicks' => 10],
        ];

        self::assertNotSame(
            ContentVersion::fromCatalogs($first),
            ContentVersion::fromCatalogs($swapped)
        );
    }

    /**
     * L'ordre dans lequel l'appelant fournit les quatre catalogues, lui, n'est
     * pas du contenu — d'où l'enveloppe à clés plutôt qu'une concaténation.
     */
    public function testTheOrderTheFourCatalogsAreGivenInDoesNotMatter(): void
    {
        $catalogs = $this->catalogs();
        $shuffled = [
            'vestiges' => $catalogs['vestiges'],
            'scripted_opponent' => $catalogs['scripted_opponent'],
            'items' => $catalogs['items'],
            'heroes' => $catalogs['heroes'],
        ];

        self::assertSame(
            ContentVersion::fromCatalogs($catalogs),
            ContentVersion::fromCatalogs($shuffled)
        );
    }

    /**
     * Une empreinte calculée sur trois catalogues serait silencieusement
     * fausse, et rejetterait **toutes** les runs à la première lecture.
     *
     * Le jeu de catalogues fait partie de la règle : en ajouter un cinquième
     * changera l'empreinte de tout le monde, ce qui est correct — un nouveau
     * catalogue est du nouveau contenu.
     */
    public function testItRejectsAnIncompleteSetOfCatalogs(): void
    {
        $catalogs = $this->catalogs();
        unset($catalogs['vestiges']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Content version requires exactly the catalogs '
            . '[heroes, items, scripted_opponent, vestiges], got [heroes, items, scripted_opponent].'
        );

        ContentVersion::fromCatalogs($catalogs);
    }

    public function testItRejectsAnUnknownCatalog(): void
    {
        $catalogs = $this->catalogs();
        $catalogs['affinities'] = [['id' => 'shadow']];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Content version requires exactly the catalogs '
            . '[heroes, items, scripted_opponent, vestiges], '
            . 'got [affinities, heroes, items, scripted_opponent, vestiges].'
        );

        ContentVersion::fromCatalogs($catalogs);
    }

    /**
     * Un flottant rend l'empreinte **incalculable**, pas seulement instable.
     *
     * `CanonicalJson` les refuse parce que leur écriture JSON dépend de
     * `serialize_precision` (D-19). La conséquence est plus large ici que dans
     * un journal de combat : sans empreinte, aucune run ne peut être créée ni
     * relue. Ce test dit que l'échec est explicite et nomme le chemin fautif,
     * plutôt que de laisser chercher dans quatre fichiers.
     */
    public function testAFloatAnywhereInACatalogIsRefused(): void
    {
        $catalogs = $this->catalogs();
        $catalogs['items'][0]['cooldownTicks'] = 20.5;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('items.0.cooldownTicks');

        ContentVersion::fromCatalogs($catalogs);
    }
}
