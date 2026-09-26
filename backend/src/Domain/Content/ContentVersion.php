<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Engine\CanonicalJson;

/**
 * Empreinte du contenu de jeu ayant servi à une partie (chantier 2, commit 12).
 *
 * **Le problème.** Une `GameRun` est rejouée depuis son journal d'événements :
 * la base ne stocke pas d'états, elle stocke des décisions, et l'état courant
 * est reconstruit en les appliquant sur le contenu des catalogues. Modifier un
 * objet de `items.json` ne réécrit donc pas les parties passées — il change
 * silencieusement ce qu'elles *deviennent* au rejeu suivant. Une partie sauvée
 * avec une dague à 15 dégâts se rejoue avec la dague à 18 sans que rien ne
 * signale l'écart : les mêmes décisions produisent un autre résultat.
 *
 * **La réponse.** On épingle sur l'enregistrement de partie une empreinte du
 * contenu au moment de sa création. Au rejeu, on la compare à l'empreinte du
 * contenu courant ; si elles diffèrent, la partie est refusée plutôt que
 * rejouée de travers. Un refus franc vaut mieux qu'un état faux.
 *
 * **Pourquoi la règle vit dans le Domaine.** Elle est irréversible — elle
 * décide du rejet d'une partie — et elle est la seule chose à devoir être
 * vérifiable sans système de fichiers. Cette classe ne lit aucun fichier : on
 * lui donne quatre tableaux déjà décodés. Lire `config/game/*.json` et
 * appeler cette méthode est le travail d'Infrastructure.
 *
 * **Pourquoi une enveloppe plutôt qu'une concaténation.** Hacher les quatre
 * catalogues bout à bout ferait dépendre l'empreinte de l'ordre dans lequel
 * l'appelant les fournit, et deux découpages différents du même contenu
 * pourraient se confondre. Les passer comme une seule table dont les clés sont
 * les noms de catalogues règle les deux : le `ksort` de {@see CanonicalJson}
 * fixe l'ordre, et le nom de chaque catalogue fait partie des octets hachés —
 * renommer un catalogue invalide l'empreinte, ce qui est le comportement
 * voulu puisque le lecteur d'Infrastructure ne le trouverait plus.
 *
 * **Pourquoi le jeu exact fait partie de la règle.** Une empreinte calculée
 * sur trois catalogues serait une empreinte parfaitement stable — et
 * parfaitement fausse : elle ne verrait jamais changer le quatrième, et toutes
 * les parties passeraient le contrôle après une modification qui les invalide.
 * Le cas inverse est pire encore à diagnostiquer : un lecteur qui oublie un
 * catalogue produirait une empreinte différente de celle épinglée et rejetterait
 * *toutes* les parties existantes. Le jeu est donc vérifié, pas supposé.
 *
 * Les règles d'octets — tri des clés texte, listes jamais triées, flottants
 * refusés — sont celles de {@see CanonicalJson}, partagées avec le journal de
 * combat et le snapshot de plateau. Un flottant dans un catalogue est refusé
 * ici avec le chemin fautif nommé.
 */
final class ContentVersion
{
    /**
     * Les catalogues qui décident du déroulement d'une partie, triés.
     *
     * Trié parce que la validation compare ce tableau au résultat d'un
     * `sort(SORT_STRING)` sur les clés reçues : l'ordre écrit ici est donc
     * significatif, et c'est aussi celui du message d'erreur.
     *
     * @var list<string>
     */
    public const array CATALOGS = ['heroes', 'items', 'scripted_opponent', 'vestiges'];

    /**
     * @param array<string, mixed> $catalogs les quatre catalogues décodés, indexés par nom
     *
     * @return string l'empreinte sha256 en hexadécimal minuscule (64 caractères)
     *
     * @throws \InvalidArgumentException si le jeu de catalogues n'est pas exactement
     *                                   {@see self::CATALOGS}, ou si un flottant traîne
     *                                   à une profondeur quelconque
     */
    public static function fromCatalogs(array $catalogs): string
    {
        $given = array_keys($catalogs);
        sort($given, SORT_STRING);

        if ($given !== self::CATALOGS) {
            throw new \InvalidArgumentException(sprintf(
                'Content version requires exactly the catalogs [%s], got [%s].',
                implode(', ', self::CATALOGS),
                implode(', ', $given),
            ));
        }

        // L'ordre dans lequel l'appelant a fourni les quatre n'a pas à être
        // corrigé ici : CanonicalJson::encode() trie les clés texte à chaque
        // niveau, celui-ci compris.
        return hash('sha256', CanonicalJson::encode($catalogs));
    }
}
