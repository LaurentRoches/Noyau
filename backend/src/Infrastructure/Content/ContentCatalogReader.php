<?php

declare(strict_types=1);

namespace App\Infrastructure\Content;

use App\Domain\Content\ContentVersion;

/**
 * Lit les catalogues de jeu sur le disque et en donne l'empreinte
 * (chantier 2, commit 12 ; `04` §2, §6.3).
 *
 * **Le partage des rôles.** `ContentVersion` porte la règle et ne touche aucun
 * fichier ; cette classe touche les fichiers et ne porte aucune règle. La
 * frontière n'est pas décorative : la règle est irréversible — la moindre
 * variation invalide d'un coup toutes les runs enregistrées — alors que
 * l'emplacement des fichiers, lui, peut bouger sans conséquence.
 *
 * **Les noms de fichiers viennent du Domaine.** Chaque entrée de
 * {@see ContentVersion::CATALOGS} suffixée de `.json`. Le répertoire est la
 * seule chose que cette classe décide. Ajouter un cinquième catalogue à la
 * constante rend son fichier obligatoire sans qu'une ligne change ici, et sans
 * qu'on puisse oublier de le hacher.
 *
 * **Pourquoi lire, et pas `glob()`.** Un `glob('*.json')` serait plus court et
 * ferait dépendre l'empreinte de tout fichier déposé dans le répertoire — une
 * sauvegarde d'éditeur, un catalogue en préparation. Des runs seraient rejetées
 * pour des raisons invisibles depuis le code.
 *
 * **Pourquoi tout échec est une `RuntimeException`.** Le `Router` rend
 * `InvalidArgumentException` en 400 et `LogicException` en 409. Un catalogue
 * absent, illisible, mal formé ou porteur d'un flottant n'est ni l'un ni
 * l'autre : c'est le serveur qui est mal déployé, et le joueur n'y peut rien.
 * L'`InvalidArgumentException` que peut lever le Domaine sur un flottant est
 * donc **rhabillée** ici, en conservant la cause et le chemin fautif. C'est la
 * contrainte « fail-fast sur configuration incomplète » de `04` §2, exprimée
 * par la hiérarchie d'exceptions plutôt que par un commentaire.
 *
 * **Pourquoi l'empreinte est mémorisée.** Le même lecteur sert à
 * `RunController` — qui épingle l'empreinte sur une run créée — et à
 * `GameRunReplayer` — qui la compare au rejeu. Si le contenu pouvait changer
 * entre les deux appels d'une même requête, une run pourrait être écrite sous
 * une empreinte et refusée sous une autre dans la seconde qui suit. Le gel rend
 * la requête cohérente avec elle-même.
 *
 * **Double lecture assumée.** `GameRunReplayer` recharge ces mêmes fichiers
 * pour reconstruire l'état d'une run. Mutualiser les deux chargements
 * déplacerait le travail de toutes les fabriques dans un commit qui porte déjà
 * une migration de schéma irréversible. Quatre fichiers d'une vingtaine de
 * kilo-octets lus deux fois par requête : le coût est mesuré et accepté, le
 * nettoyage est reporté.
 */
final class ContentCatalogReader
{
    private ?string $version = null;

    public function __construct(private readonly string $configPath)
    {
    }

    /**
     * @return string l'empreinte du contenu présent sur le disque, gelée au premier appel
     *
     * @throws \RuntimeException si un catalogue manque, ne se lit pas, n'est pas
     *                           du JSON valide, ne décode pas en tableau, ou si
     *                           le Domaine refuse de l'empreindre
     */
    public function version(): string
    {
        return $this->version ??= $this->computeVersion();
    }

    private function computeVersion(): string
    {
        $catalogs = [];

        foreach (ContentVersion::CATALOGS as $catalog) {
            $catalogs[$catalog] = $this->readCatalog($catalog);
        }

        try {
            return ContentVersion::fromCatalogs($catalogs);
        } catch (\InvalidArgumentException $e) {
            // Seule la clause « flottant » de CanonicalJson est atteignable :
            // les clés viennent d'être construites depuis CATALOGS, donc le jeu
            // est exact par construction. On rhabille quand même sans
            // discriminer — une règle de Domaine qui gagnerait un motif de refus
            // ne doit pas se mettre à sortir d'ici en 400 sans qu'on le décide.
            throw new \RuntimeException(sprintf(
                'The game catalogs in "%s" cannot be fingerprinted: %s',
                $this->configPath,
                $e->getMessage(),
            ), 0, $e);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readCatalog(string $catalog): array
    {
        $path = $this->configPath . '/' . $catalog . '.json';

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Game catalog "%s" is missing.', $path));
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new \RuntimeException(sprintf('Game catalog "%s" cannot be read.', $path));
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf(
                'Game catalog "%s" is not valid JSON: %s',
                $path,
                $e->getMessage(),
            ), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf(
                'Game catalog "%s" must decode to an array, got %s.',
                $path,
                get_debug_type($decoded),
            ));
        }

        return $decoded;
    }
}
