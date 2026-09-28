<?php

declare(strict_types=1);

namespace App\Presentation\Cli;

use App\Domain\Engine\CanonicalJson;
use App\Domain\Engine\CombatLogSerializer;
use App\Domain\Engine\EngineVersion;
use App\Domain\Engine\SimulationResult;
use App\Domain\Runtime\CombatBoard;

/**
 * Ce que le moteur embarqué écrit sur stdout (`04` §4.3, chantier 1a).
 *
 * **Le journal seul ne suffit pas.** Il ne porte que les événements ; le
 * vainqueur, la résolution et le nombre de ticks vivent à côté de lui, dans
 * `SimulationResult`. La coquille a besoin des trois, et du côté de son propre
 * plateau, que l'attribution canonique (`04` §3.6) ne lui laisse pas deviner.
 *
 * **Pourquoi ici et pas dans `Domain/Engine/`.** Tout changement de code sous
 * ce chemin impose de relever `EngineVersion`, ce qui prive de leur déroulé
 * tous les fantômes archivés. Cette sortie n'est pas un format de parité
 * irréversible : la coquille et le binaire partent dans le même build. Le
 * journal qu'elle embarque, lui, est produit par le Domaine et n'est pas
 * retouché.
 */
final class CombatResultSerializer
{
    public static function serialize(SimulationResult $result, CombatBoard $firstBoard): string
    {
        return CanonicalJson::encode([
            'combatLog' => self::embeddedLog($result),
            'engineVersion' => EngineVersion::CURRENT,
            'firstBoardSide' => $result->sideOf($firstBoard)->value,
            'resolution' => $result->resolution->value,
            'totalTicks' => $result->totalTicks,
            'winnerSide' => $result->sideOf($result->winner)->value,
        ]);
    }

    /**
     * Le journal du Domaine, **relu en objets et non en tableaux**.
     *
     * `CanonicalJson` ne descend pas dans un `stdClass` : le journal ressort
     * donc tel que `CombatLogSerializer` l'a écrit, clés déjà triées. Relu en
     * tableaux, une charge utile vide `{}` redeviendrait `[]` au ré-encodage.
     * Les deux encodages partagent les options de `CanonicalJson`, ce qui rend
     * l'aller-retour exact pour les chaînes, les entiers et les booléens — les
     * seuls types qu'une charge utile admet.
     */
    private static function embeddedLog(SimulationResult $result): object
    {
        /** @var \stdClass $log L'enveloppe du journal est toujours un objet JSON. */
        $log = json_decode(CombatLogSerializer::serialize($result->log), false, 512, JSON_THROW_ON_ERROR);

        return $log;
    }
}
