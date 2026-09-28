<?php

declare(strict_types=1);

namespace App\Presentation\Cli;

/**
 * Une requête refusée avant la simulation.
 *
 * Interne à `EngineCommand`, qui la traduit en code de sortie : elle ne
 * franchit jamais la frontière du processus. Elle porte son `EngineErrorCode`
 * pour que la lecture de la requête reste une suite d'étapes lisible, sans un
 * `return` d'échec à chaque ligne.
 */
final class EngineRequestRejected extends \RuntimeException
{
    public function __construct(
        public readonly EngineErrorCode $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
