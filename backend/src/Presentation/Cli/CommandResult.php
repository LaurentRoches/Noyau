<?php

declare(strict_types=1);

namespace App\Presentation\Cli;

/**
 * Ce que le processus rendra : un code de sortie et les octets de ses deux flux.
 *
 * **Une valeur, pas une écriture.** `EngineCommand` ne touche à aucun flux ; le
 * point d'entrée de `bin/` écrit ces octets tels quels. La commande se teste
 * donc sans lancer de processus, et le test de processus n'a plus qu'à
 * vérifier le câblage.
 */
final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }
}
