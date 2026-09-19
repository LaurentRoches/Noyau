<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Event\CombatEvent;

/**
 * Forme canonique du `CombatLog` (D-19, chantier 2).
 *
 * Séparé de `CombatEventPresenter` à dessein : celui-ci sert le contrat de
 * l'API et doit pouvoir évoluer librement, celui-ci sert la comparaison octet
 * pour octet d'EX-J0-01 et ne doit pas bouger. Deux consommateurs, deux
 * contrats, deux classes — une seule aurait lié l'un à l'autre.
 */
final class CombatLogSerializer
{
    public const int FORMAT_VERSION = 1;

    public static function serialize(CombatLog $log): string
    {
        $envelope = [
            'formatVersion' => self::FORMAT_VERSION,
            'events' => array_map(
                static fn (CombatEvent $event): array => self::serializeEvent($event),
                $log->getEvents(),
            ),
        ];

        // L'enveloppe est triée comme n'importe quelle autre table à clés
        // texte. Une règle sans exception vaut mieux qu'une règle assortie
        // d'une liste de cas particuliers à retenir — quitte à ce que
        // `events` précède `formatVersion` à la lecture.
        ksort($envelope, SORT_STRING);

        // Les options sont écrites ici plutôt que dans une constante : PHPStan
        // ne narrow le type de retour de json_encode() à `string` que s'il
        // voit JSON_THROW_ON_ERROR dans l'appel lui-même.
        return json_encode(
            $envelope,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @return array{payload: object, tick: int, type: string}
     */
    private static function serializeEvent(CombatEvent $event): array
    {
        $payload = $event->payload;

        foreach ($payload as $key => $value) {
            if (!\is_int($value) && !\is_string($value) && !\is_bool($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'CombatEvent payload key "%s" holds a value of type %s. '
                    . 'Only int, string and bool are serializable: a float\'s JSON '
                    . 'representation depends on serialize_precision and would break '
                    . 'server/embedded-engine parity, and a nested value would break '
                    . 'the flat payload contract.',
                    $key,
                    get_debug_type($value),
                ));
            }
        }

        ksort($payload, SORT_STRING);

        $serialized = [
            'tick' => $event->tick,
            'type' => $event->type->value,
            // Cast explicite : en PHP un tableau vide s'encode `[]` et non
            // `{}`, si bien qu'une charge utile serait tantôt un objet, tantôt
            // un tableau — et `STATUS_EXPIRED` est justement émis sans charge
            // utile. JSON_FORCE_OBJECT n'est pas la réponse : il
            // transformerait aussi la liste d'événements en objet indexé.
            'payload' => (object) $payload,
        ];

        ksort($serialized, SORT_STRING);

        return $serialized;
    }
}
