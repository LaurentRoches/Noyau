<?php

declare(strict_types=1);

namespace App\Presentation\Cli;

/**
 * Codes d'échec du moteur embarqué, écrits sur stderr (`04` §4.3).
 *
 * **Stables par contrat** : la coquille les lit. Le message qui les accompagne
 * est destiné à un humain et peut changer ; le code, non.
 *
 * **Un échec se classe par l'étape où il survient, pas par la classe de
 * l'exception.** `\InvalidArgumentException` est levée aussi bien par
 * `CanonicalJson` sur un flottant de la requête — faute de l'appelant — que par
 * `SimulationResult::sideOf()` sur un plateau étranger au combat — défaut du
 * moteur. C'est `EngineCommand` qui décide, selon l'étape.
 */
enum EngineErrorCode: string
{
    /** stdin n'est pas du JSON valide — un BOM compris. */
    case INVALID_JSON = 'INVALID_JSON';

    /** Clé manquante, type faux, ou flottant dans un snapshot. */
    case INVALID_REQUEST = 'INVALID_REQUEST';

    /** L'hydrateur a refusé une enveloppe de plateau. */
    case UNREADABLE_BOARD_RECORD = 'UNREADABLE_BOARD_RECORD';

    /** Toute erreur survenue une fois la requête lue : un défaut du moteur. */
    case INTERNAL_ERROR = 'INTERNAL_ERROR';

    /**
     * 1 quand la faute est dans la requête, 2 quand elle est dans le moteur.
     * 0 n'appartient à aucun code : un succès n'écrit rien sur stderr.
     */
    public function exitCode(): int
    {
        return $this === self::INTERNAL_ERROR ? 2 : 1;
    }
}
