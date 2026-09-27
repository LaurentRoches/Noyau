<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

use App\Domain\Engine\EngineVersion;
use App\Domain\Enum\Resolution;
use App\Domain\Enum\Side;

/**
 * L'archive d'un combat : deux plateaux et ce qui les a opposés
 * (`04` §6, D-16).
 *
 * **Pourquoi `CombatRecord` et non `CombatSnapshot`.** `BoardRecord` archive
 * **un** plateau ; celui-ci archive **la rencontre**. Son propre docblock
 * prévenait que deux noms quasi identiques pour l'un et pour l'autre seraient
 * une confusion programmée.
 *
 * **Rangé par côté, jamais par « joueur ».** Les libellés A/B de D-19 existent
 * parce que le serveur simule une seule fois et que les deux joueurs d'un
 * futur PvP regardent le même journal : un côté nommé « joueur » y serait faux
 * pour l'un des deux. Une archive ne connaît aucun spectateur, et c'est ce qui
 * la rend rejouable par n'importe qui. Le côté du joueur courant voyage
 * ailleurs — dans le champ `viewerSide` de la réponse HTTP.
 *
 * **Aucune sérialisation canonique ici, délibérément.** Les deux enveloppes
 * portent déjà la leur, et la table stocke ces deux chaînes plus des colonnes
 * scalaires. Une troisième forme canonique serait un format de plus à figer —
 * donc une décision de plus à ne jamais pouvoir reprendre — pour aucun
 * appelant.
 *
 * **`engineVersion` est estampillée ici, pas fournie.** Un enregistrement
 * n'existe qu'à l'instant où le combat est simulé : la version courante *est*
 * la bonne, et la faire passer par un paramètre ouvrirait la seule façon de se
 * tromper. La redondance avec les deux enveloppes, qui la portent déjà, est
 * assumée : la colonne qui la recevra doit pouvoir filtrer un bassin
 * d'appariement au chantier 11 sans décoder cinq mille JSON.
 */
final readonly class CombatRecord
{
    public int $engineVersion;

    public function __construct(
        public BoardRecord $boardA,
        public BoardRecord $boardB,
        public string $combatSeed,
        public Resolution $resolution,
        public Side $winnerSide,
    ) {
        $this->engineVersion = EngineVersion::CURRENT;
    }
}
