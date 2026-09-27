<?php

declare(strict_types=1);

namespace App\Application;

/**
 * Calcul de la graine de combat (D-22, chantier 2).
 *
 * Le calcul appartient à l'Application, la dérivation des flux au Domaine.
 * C'est ce découpage qui permet au moteur embarqué de résoudre un combat sans
 * jamais connaître la seed du run : la CLI du chantier 1a reçoit
 * `{ snapshotA, snapshotB, combatSeed }` et rien d'autre.
 *
 * **Format irréversible** (`07` §8, point 3) : l'étiquette, l'algorithme de
 * hachage, l'encodage décimal ASCII des entiers et le séparateur `|` en font
 * tous partie.
 *
 * Le chantier 8 introduira deux combats par manche, PvE puis PvP. L'étiquette
 * devra alors les distinguer, et **c'est un changement de format à décider à
 * ce moment-là**, pas à découvrir au chantier 11.
 */
final class CombatSeed
{
    /**
     * Digest SHA-256 en hexadécimal, 64 caractères.
     *
     * La forme hexadécimale n'est pas cosmétique : la graine est stockée dans
     * chaque snapshot, donc elle traverse JSON, et le format canonique de
     * D-19 n'admet que `int`, `string` et `bool`. Une chaîne binaire brute
     * n'y survivrait pas.
     *
     * Le séparateur est explicite parce que la concaténation de deux entiers
     * est ambiguë sans lui : `12` suivi de `3` et `1` suivi de `23`
     * donneraient la même chaîne, donc le même combat pour deux manches de
     * deux runs différentes.
     */
    public static function forRound(int $runSeed, int $round): string
    {
        return hash('sha256', sprintf('combat|%d|%d', $runSeed, $round));
    }
}
