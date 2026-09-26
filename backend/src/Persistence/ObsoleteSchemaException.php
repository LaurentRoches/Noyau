<?php

declare(strict_types=1);

namespace App\Persistence;

/**
 * Levée quand la base ne correspond pas à la version de schéma attendue.
 *
 * Étend `RuntimeException` et non `LogicException` à dessein : `Router` mappe
 * `LogicException` vers 409, or ce refus n'est pas un conflit d'état de
 * requête mais une incapacité du service à démarrer. Il doit traverser le
 * routeur sans être transformé en réponse 409, et le bootstrap le convertit
 * lui-même en 503.
 */
final class ObsoleteSchemaException extends \RuntimeException
{
}
