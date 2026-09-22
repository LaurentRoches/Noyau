<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Content;

use App\Domain\Content\ContentVersion;
use App\Infrastructure\Content\ContentCatalogReader;
use PHPUnit\Framework\TestCase;

/**
 * Le lecteur qui donne l'empreinte du contenu présent sur le disque
 * (`04` §2, §6.3).
 *
 * **Ce qu'il est.** Le seul point du code qui sache où vivent les catalogues.
 * `ContentVersion` porte la règle et ne lit aucun fichier ; cette classe lit
 * les fichiers et ne porte aucune règle. La frontière est utile : la règle est
 * irréversible — elle décide du rejet de runs — tandis que l'emplacement des
 * fichiers peut changer librement.
 *
 * **Pourquoi les noms de fichiers ne sont pas écrits ici.** Ils se dérivent de
 * `ContentVersion::CATALOGS`, le Domaine restant l'unique source de vérité sur
 * ce qui compose le contenu. Ajouter un cinquième catalogue à la constante rend
 * son fichier obligatoire sans toucher cette classe.
 *
 * **Pourquoi les pannes sont des `RuntimeException`.** Un catalogue absent,
 * illisible, mal formé ou porteur d'un flottant est une faute de déploiement,
 * pas une faute du client. Le `Router` transforme `LogicException` en 409 et
 * `InvalidArgumentException` en 400 : laisser remonter l'une ou l'autre
 * répondrait au joueur que *sa requête* est en tort. Ce sont des 500, et la
 * hiérarchie d'exception est ce qui le garantit.
 */
final class ContentCatalogReaderTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/corebound-catalogs-' . bin2hex(random_bytes(8));

        mkdir($this->configPath);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->configPath)) {
            return;
        }

        // scandir() et non glob() : glob() traite l'antislash comme un
        // échappement, et sys_get_temp_dir() en rend plein sous Windows.
        foreach (scandir($this->configPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            unlink($this->configPath . '/' . $entry);
        }

        rmdir($this->configPath);
    }

    /**
     * Le même corpus que `ContentVersionTest`, à dessein : les deux suites
     * épinglent alors la **même** empreinte, l'une depuis des tableaux, l'autre
     * depuis des fichiers. Si elles divergent, le lecteur ment.
     *
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

    private function write(string $catalog, string $contents): void
    {
        file_put_contents($this->configPath . '/' . $catalog . '.json', $contents);
    }

    private function writeAllCatalogs(): void
    {
        foreach ($this->catalogs() as $catalog => $entries) {
            $this->write($catalog, json_encode($entries, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Le contrat, en une ligne : l'empreinte du Domaine appliquée à ce qui est
     * sur le disque.
     *
     * L'assertion se fait contre `ContentVersion::fromCatalogs()` et non contre
     * un SHA-256 littéral. Le littéral est épinglé une fois, dans
     * `ContentVersionTest` ; le redoubler ici donnerait deux endroits à corriger
     * et un jour deux valeurs différentes. Ce que ce test ajoute, c'est le
     * **câblage** : les bons fichiers, décodés, sous les bonnes clés.
     */
    public function testItIsTheFingerprintOfTheFourCatalogsOnDisk(): void
    {
        $this->writeAllCatalogs();

        self::assertSame(
            ContentVersion::fromCatalogs($this->catalogs()),
            (new ContentCatalogReader($this->configPath))->version()
        );
    }

    /**
     * Réindenter un fichier de configuration ne doit rejeter aucune run.
     *
     * C'est le test qui interdit de hacher les octets bruts — une implémentation
     * plausible, plus simple, et qui invaliderait toutes les runs en cours au
     * premier passage d'un formateur JSON. Les quatre fichiers écrits ici
     * portent les mêmes données que `catalogs()` avec des clés permutées, des
     * indentations différentes et des espaces avant les deux-points, comme
     * `items.json` en contient aujourd'hui.
     */
    public function testReformattingAFileOnDiskDoesNotChangeIt(): void
    {
        $this->write('heroes', "[\n  { \"itemSlots\" : 2, \"name\": \"Hero One\", \"id\": \"h1\" }\n]");
        $this->write('items', '[{"cooldownTicks":20,"name":"Item One","id":"i1"}]');
        $this->write('scripted_opponent', "[\n    { \"itemIds\": [\"i1\"], \"heroId\": \"h1\" }\n]");
        $this->write('vestiges', '[ { "baseShield" : 10 , "baseHp" : 100 , "id" : "v1" } ]');

        self::assertSame(
            ContentVersion::fromCatalogs($this->catalogs()),
            (new ContentCatalogReader($this->configPath))->version()
        );
    }

    /**
     * L'empreinte est figée à la première lecture, pour toute la vie de
     * l'instance.
     *
     * Ce n'est pas qu'une optimisation. `RunController` et `GameRunReplayer`
     * partagent le même lecteur : si le contenu pouvait changer entre le calcul
     * qui épingle et le contrôle qui compare, une run pourrait être créée sous
     * une empreinte et rejetée par la suivante dans la même requête. On observe
     * le gel en supprimant un fichier après le premier appel.
     */
    public function testTheVersionIsFrozenAtFirstRead(): void
    {
        $this->writeAllCatalogs();

        $reader = new ContentCatalogReader($this->configPath);
        $first = $reader->version();

        unlink($this->configPath . '/items.json');

        self::assertSame($first, $reader->version());
    }

    /**
     * Un catalogue absent arrête tout, en le nommant.
     *
     * Sans ce contrôle, `ContentVersion::fromCatalogs()` recevrait trois clés
     * et lèverait une `InvalidArgumentException` — que le `Router` rendrait en
     * **400**. Un fichier manquant sur le serveur n'est pas une requête
     * malformée.
     */
    public function testAMissingCatalogIsRefused(): void
    {
        $this->writeAllCatalogs();
        unlink($this->configPath . '/vestiges.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('vestiges.json" is missing.');

        (new ContentCatalogReader($this->configPath))->version();
    }

    public function testACatalogThatIsNotValidJsonIsRefused(): void
    {
        $this->writeAllCatalogs();
        $this->write('items', '[{"id": "i1",');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('items.json" is not valid JSON');

        (new ContentCatalogReader($this->configPath))->version();
    }

    /**
     * Un JSON valide n'est pas forcément un catalogue.
     *
     * `json_decode('"Hero One"')` rend une chaîne, parfaitement valide et
     * parfaitement inutilisable. Sans ce contrôle, l'erreur remonterait sous
     * une forme obscure depuis `CanonicalJson`, ou pas du tout.
     */
    public function testACatalogWhoseRootIsNotAnArrayIsRefused(): void
    {
        $this->writeAllCatalogs();
        $this->write('heroes', '"Hero One"');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('heroes.json" must decode to an array, got string.');

        (new ContentCatalogReader($this->configPath))->version();
    }

    /**
     * Un flottant dans un catalogue est une faute de configuration, pas une
     * faute de requête.
     *
     * `CanonicalJson` le refuse par une `InvalidArgumentException` (D-19), que
     * le `Router` rendrait en **400**. Le lecteur la rhabille en
     * `RuntimeException` en conservant le chemin fautif, qui est la seule
     * information utile pour la corriger. C'est la conséquence directe du choix
     * de couche : tout ce qui sort d'ici est une panne de déploiement.
     */
    public function testAFloatInACatalogIsRefusedAsAConfigurationFault(): void
    {
        $this->writeAllCatalogs();
        $this->write('items', '[{"id":"i1","name":"Item One","cooldownTicks":20.5}]');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('items.0.cooldownTicks');

        (new ContentCatalogReader($this->configPath))->version();
    }

    /**
     * Le lecteur lit les catalogues nommés par le Domaine, pas le contenu du
     * répertoire.
     *
     * Un `glob('*.json')` serait plus court et ferait dépendre l'empreinte de
     * tout fichier déposé à côté — un `affinities.json` de travail, une
     * sauvegarde d'éditeur — donc rejetterait des runs pour des raisons
     * invisibles.
     */
    public function testAnUnrelatedFileInTheDirectoryIsIgnored(): void
    {
        $this->writeAllCatalogs();
        $this->write('affinities', '[{"id":"shadow"}]');

        self::assertSame(
            ContentVersion::fromCatalogs($this->catalogs()),
            (new ContentCatalogReader($this->configPath))->version()
        );
    }
}
