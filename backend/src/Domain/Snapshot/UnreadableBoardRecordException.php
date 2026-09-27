<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

/**
 * Une archive de plateau qu'on ne sait pas relire (D-16, `04` §5.3).
 *
 * **Un seul type, quelle que soit la façon dont la ligne est abîmée.** JSON
 * invalide, version de format inconnue, champ absent, type inattendu, valeur
 * qu'aucune énumération ne connaît, plateau injouable : l'appelant du chantier
 * 11 écartera la ligne de son bassin d'appariement sans avoir à distinguer les
 * causes. Six types d'exception lui imposeraient six `catch` dont l'oubli d'un
 * seul ferait tomber une requête d'appariement.
 *
 * **Pourquoi `\RuntimeException` et non `\InvalidArgumentException`.** Une
 * archive est une donnée, pas un argument : elle a été écrite correctement il y
 * a des semaines et c'est le monde qui a changé autour d'elle. `Router` traduit
 * aujourd'hui `\InvalidArgumentException` en 400, ce qui imputerait au client
 * une ligne de corpus abîmée. Le jour où quelque chose d'exposé lira des
 * archives, ce sera à ce moment-là qu'on tranchera son code HTTP — et pas
 * avant, faute de savoir ce que l'appelant voudra en faire.
 */
final class UnreadableBoardRecordException extends \RuntimeException
{
}
