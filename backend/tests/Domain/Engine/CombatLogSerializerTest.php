<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\CombatLog;
use App\Domain\Engine\CombatLogSerializer;
use App\Domain\Enum\EventType;
use App\Domain\Event\CombatEvent;
use PHPUnit\Framework\TestCase;

/**
 * Forme canonique du `CombatLog` (D-19, chantier 2 commit 2).
 *
 * C'est l'objet de la comparaison octet pour octet d'EX-J0-01 : le serveur et
 * le binaire embarqué doivent produire exactement la même chaîne. Ce fichier
 * fige donc des chaînes entières, pas des propriétés — c'est délibéré, et c'est
 * ce qui distingue ce test d'un test de présentation.
 *
 * Trois règles, et une seule d'entre elles a une exception.
 *
 *  1. Toute table à clés texte est triée par `ksort` en `SORT_STRING` —
 *     enveloppe et objet d'événement compris, sans exception. Une seule règle
 *     vaut mieux qu'une règle et une liste de cas particuliers à retenir.
 *  2. Les **listes** ne sont jamais triées. L'ordre des événements est une
 *     donnée, pas une présentation.
 *  3. Seuls `int`, `string` et `bool` sont admis en valeur. Un flottant, un
 *     `null` ou un tableau lève. La charge utile est donc structurellement
 *     **plate** : il n'existe aucun second niveau à trier, ce qui rend la
 *     règle 1 non récursive.
 *
 * Pourquoi les flottants sont refusés, pour deux raisons indépendantes et
 * chacune suffisante : l'écriture JSON d'un flottant dépend de
 * `serialize_precision`, réglage d'exécution que le serveur et le binaire
 * `static-php-cli` peuvent ne pas partager ; et l'enrage inflige
 * `5 × 2^stage`, qui bascule silencieusement en flottant au-delà de
 * `PHP_INT_MAX`. Une exception à la sérialisation transforme une corruption
 * silencieuse en échec visible.
 *
 * Note sur la maintenance : un changement de `FORMAT_VERSION` casse
 * volontairement toutes les chaînes figées ici. Ce n'est pas une fragilité,
 * c'est le point de contrôle — un format ne change pas sans relecture.
 *
 * Trou de couverture connu : la parité réelle serveur / binaire embarqué n'est
 * pas exercée ici, le binaire n'existant pas encore. Ce fichier fige la forme
 * d'un seul côté ; la comparaison des deux appartient au chantier 1a.
 */
final class CombatLogSerializerTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    private function logWith(int $tick, EventType $type, array $payload): CombatLog
    {
        $log = new CombatLog();
        $log->addEvent(new CombatEvent(tick: $tick, type: $type, payload: $payload));

        return $log;
    }

    // --- Enveloppe --------------------------------------------------------

    public function testItSerializesAnEmptyLog(): void
    {
        self::assertSame(
            '{"events":[],"formatVersion":1}',
            CombatLogSerializer::serialize(new CombatLog()),
        );
    }

    public function testTheEnvelopeCarriesTheFormatVersion(): void
    {
        $decoded = json_decode(CombatLogSerializer::serialize(new CombatLog()), true);

        self::assertSame(CombatLogSerializer::FORMAT_VERSION, $decoded['formatVersion']);
    }

    // --- Tri des clés -----------------------------------------------------

    /**
     * La charge utile réelle de `ActionProcessor::processDealDamage()`, dans
     * son ordre d'insertion d'origine. La sortie doit être alphabétique à
     * trois niveaux : enveloppe, objet d'événement, charge utile.
     */
    public function testItSortsEveryStringKeyedMap(): void
    {
        $log = $this->logWith(40, EventType::DAMAGE_DEALT, [
            'amount' => 15,
            'shieldDamage' => 0,
            'hpDamage' => 15,
            'target' => 'opponent_vestige',
            'targetSide' => 'OPPONENT',
            'sourceSide' => 'PLAYER',
            'sourceItemId' => 'shadow_dagger',
        ]);

        self::assertSame(
            '{"events":[{"payload":{"amount":15,"hpDamage":15,"shieldDamage":0,'
            . '"sourceItemId":"shadow_dagger","sourceSide":"PLAYER",'
            . '"target":"opponent_vestige","targetSide":"OPPONENT"},'
            . '"tick":40,"type":"DAMAGE_DEALT"}],"formatVersion":1}',
            CombatLogSerializer::serialize($log),
        );
    }

    /**
     * Le cas qui justifie tout ce commit.
     *
     * Deux charges utiles portant exactement les mêmes données, insérées dans
     * deux ordres différents, doivent produire la même chaîne. Sans tri, elles
     * en produisent deux — et NF-01 tombe sans qu'aucun calcul ne soit faux.
     */
    public function testKeyOrderAtInsertionDoesNotChangeTheOutput(): void
    {
        $first = $this->logWith(1, EventType::SHIELD_GAINED, [
            'amount' => 20,
            'shieldGained' => 20,
            'target' => 'player_vestige',
        ]);

        $second = $this->logWith(1, EventType::SHIELD_GAINED, [
            'target' => 'player_vestige',
            'amount' => 20,
            'shieldGained' => 20,
        ]);

        self::assertSame(
            CombatLogSerializer::serialize($first),
            CombatLogSerializer::serialize($second),
        );
    }

    // --- Listes -----------------------------------------------------------

    /**
     * L'ordre des événements est une donnée de jeu. Les ticks décroissants
     * sont volontaires : un tri sur la liste les réordonnerait, et le test le
     * verrait.
     */
    public function testItNeverSortsTheEventList(): void
    {
        $log = new CombatLog();
        $log->addEvent(new CombatEvent(tick: 2, type: EventType::SHIELD_GAINED, payload: ['amount' => 5]));
        $log->addEvent(new CombatEvent(tick: 1, type: EventType::HEAL_RECEIVED, payload: ['amount' => 3]));

        self::assertSame(
            '{"events":[{"payload":{"amount":5},"tick":2,"type":"SHIELD_GAINED"},'
            . '{"payload":{"amount":3},"tick":1,"type":"HEAL_RECEIVED"}],"formatVersion":1}',
            CombatLogSerializer::serialize($log),
        );
    }

    // --- Charge utile vide ------------------------------------------------

    /**
     * En PHP un tableau vide est ambigu : `json_encode([])` produit `[]`, pas
     * `{}`. Sans forçage, `payload` serait tantôt un objet, tantôt un tableau,
     * selon qu'il est rempli ou non — et `STATUS_EXPIRED` est justement émis
     * sans charge utile. `JSON_FORCE_OBJECT` n'est pas la réponse : il
     * transformerait aussi la liste d'événements en objet indexé.
     */
    public function testItSerializesAnEmptyPayloadAsAnObjectAndNotAnArray(): void
    {
        $log = $this->logWith(12, EventType::STATUS_EXPIRED, []);

        self::assertSame(
            '{"events":[{"payload":{},"tick":12,"type":"STATUS_EXPIRED"}],"formatVersion":1}',
            CombatLogSerializer::serialize($log),
        );
    }

    // --- Types admis et refusés -------------------------------------------

    public function testItAcceptsBooleans(): void
    {
        // Aucune charge utile actuelle ne porte de booléen. Le type est admis
        // parce que le format le prévoit, et ce test fige cette admission
        // avant qu'un premier usage ne l'exerce.
        $log = $this->logWith(1, EventType::DAMAGE_DEALT, ['critical' => true]);

        self::assertSame(
            '{"events":[{"payload":{"critical":true},"tick":1,"type":"DAMAGE_DEALT"}],"formatVersion":1}',
            CombatLogSerializer::serialize($log),
        );
    }

    public function testItRejectsAFloat(): void
    {
        $log = $this->logWith(1, EventType::DAMAGE_DEALT, ['amount' => 12.5]);

        $this->expectException(\InvalidArgumentException::class);
        // Correspondance par sous-chaîne : seule la clé fautive est figée,
        // pas la phrase qui la porte.
        $this->expectExceptionMessage('amount');

        CombatLogSerializer::serialize($log);
    }

    public function testItRejectsNull(): void
    {
        $log = $this->logWith(1, EventType::DAMAGE_DEALT, ['sourceItemId' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sourceItemId');

        CombatLogSerializer::serialize($log);
    }

    /**
     * La charge utile est plate par contrat. Un tableau en valeur est refusé
     * au même titre qu'un flottant — c'est ce refus qui garantit qu'il
     * n'existe aucun second niveau, donc que le tri n'a pas à être récursif.
     */
    public function testItRejectsANestedArray(): void
    {
        $log = $this->logWith(1, EventType::STATUS_APPLIED, ['instances' => ['a', 'b']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('instances');

        CombatLogSerializer::serialize($log);
    }

    // --- Options d'encodage -----------------------------------------------

    public function testItEscapesNeitherSlashesNorUnicode(): void
    {
        $log = $this->logWith(1, EventType::DAMAGE_DEALT, ['note' => 'côté a/b']);

        self::assertSame(
            '{"events":[{"payload":{"note":"côté a/b"},"tick":1,"type":"DAMAGE_DEALT"}],"formatVersion":1}',
            CombatLogSerializer::serialize($log),
        );
    }

    // --- Invariants -------------------------------------------------------

    public function testItIsDeterministicAcrossCalls(): void
    {
        $log = $this->logWith(7, EventType::STATUS_APPLIED, [
            'status' => 'POISON',
            'stacksApplied' => 2,
            'totalStacks' => 5,
        ]);

        self::assertSame(
            CombatLogSerializer::serialize($log),
            CombatLogSerializer::serialize($log),
        );
    }

    /**
     * Le sérialiseur ne mute pas l'événement qu'il lit.
     *
     * C'est ce qui garantit que les 294 tests antérieurs ne peuvent pas être
     * affectés par ce commit : ils assertent tous sur `$event->payload`, dont
     * l'ordre d'insertion doit rester intact.
     */
    public function testItLeavesTheEventPayloadUntouched(): void
    {
        $payload = [
            'amount' => 15,
            'shieldDamage' => 0,
            'hpDamage' => 15,
            'target' => 'opponent_vestige',
        ];

        $event = new CombatEvent(tick: 1, type: EventType::DAMAGE_DEALT, payload: $payload);
        $log = new CombatLog();
        $log->addEvent($event);

        CombatLogSerializer::serialize($log);

        self::assertSame($payload, $event->payload);
    }
}
