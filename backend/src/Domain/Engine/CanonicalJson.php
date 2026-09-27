<?php

declare(strict_types=1);

namespace App\Domain\Engine;

/**
 * Encodage canonique d'une structure en JSON (chantier 2, commit 8).
 *
 * **Pourquoi une classe à part.** NF-01 exige une chaîne identique octet pour
 * octet entre le serveur et le binaire embarqué. Deux structures ont ce besoin
 * — le `CombatLog` et le snapshot de plateau — et deux implémentations des
 * mêmes règles d'octets, c'est deux occasions de diverger sur un format de
 * parité.
 *
 * **Ce que cette classe fait, et rien d'autre.**
 *
 *  1. Toute table à **clés texte** est triée par `ksort` en `SORT_STRING`, à
 *     chaque niveau. Le tri supprime une classe entière d'erreurs au lieu de la
 *     surveiller : réordonner une structure ne casse plus rien.
 *  2. Les **listes** ne sont jamais triées. L'ordre des objets d'un plateau,
 *     des effets d'un objet et des actions d'un effet est une donnée de jeu —
 *     il décide de qui frappe avant qui.
 *  3. Les **flottants sont refusés**, à toute profondeur. L'écriture JSON d'un
 *     flottant dépend de `serialize_precision`, réglage d'exécution que le
 *     serveur et le binaire `static-php-cli` peuvent ne pas partager : NF-01
 *     tomberait sans qu'aucun calcul ne soit faux.
 *
 * **Ce qu'elle ne fait pas.** Elle n'impose aucun contrat de forme. Le refus
 * des `null` et des tableaux imbriqués appartient à `CombatLogSerializer`,
 * dont les charges utiles sont **plates** par contrat ; le snapshot ne partage
 * pas cette contrainte et serait inexprimable sous elle.
 */
final class CanonicalJson
{
    /**
     * @param array<array-key, mixed> $value
     */
    public static function encode(array $value): string
    {
        // Les options sont écrites ici plutôt que dans une constante : PHPStan
        // ne narrow le type de retour de json_encode() à `string` que s'il voit
        // JSON_THROW_ON_ERROR dans l'appel lui-même.
        return json_encode(
            self::canonicalize($value, ''),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function canonicalize(array $value, string $path): array
    {
        // Relevé AVANT toute reconstruction : `array_is_list()` répond sur la
        // forme des clés, et le tableau reconstruit plus bas les conserve.
        $isList = array_is_list($value);

        $canonical = [];

        foreach ($value as $key => $item) {
            $childPath = $path === '' ? (string) $key : $path . '.' . $key;

            if (\is_array($item)) {
                $canonical[$key] = self::canonicalize($item, $childPath);

                continue;
            }

            if (\is_float($item)) {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot canonicalize a float at "%s". A float\'s JSON representation '
                    . 'depends on serialize_precision, a runtime setting the server and the '
                    . 'static-php-cli binary may not share, so NF-01 would break without any '
                    . 'computation being wrong. Use integer arithmetic.',
                    $childPath,
                ));
            }

            $canonical[$key] = $item;
        }

        // Une liste garde son ordre : c'est une donnée, pas une présentation.
        if (!$isList) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }
}
