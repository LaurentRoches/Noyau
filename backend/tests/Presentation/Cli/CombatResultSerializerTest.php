<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Cli;

use App\Domain\Engine\CombatLog;
use App\Domain\Engine\EngineVersion;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Enum\EventType;
use App\Domain\Enum\Resolution;
use App\Domain\Event\CombatEvent;
use App\Domain\Snapshot\BoardHydrator;
use App\Presentation\Cli\CombatResultSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Sortie du moteur embarqué (`04` §4.3, chantier 1a).
 *
 * **Ce fichier fige des chaînes entières**, comme `CombatLogSerializerTest` :
 * la sortie est l'objet de la comparaison octet pour octet d'EX-J0-01, et une
 * assertion par propriété laisserait passer un espace, un saut de ligne final
 * ou une clé en trop.
 *
 * **L'invariant qui porte ce fichier.** La valeur de `combatLog` est, octet
 * pour octet, la chaîne que rend `CombatLogSerializer::serialize()`. Le premier
 * test le vérifie sur la fixture de référence, le troisième sur le seul cas que
 * la fixture n'exerce pas : une charge utile vide.
 *
 * **Pourquoi cette classe n'est pas dans `Domain/Engine/`.** `EngineVersion`
 * impose un relèvement pour tout changement de code sous ce chemin, et un
 * relèvement prive de leur déroulé tous les fantômes archivés. Le format de la
 * sortie n'est pas un format de parité irréversible — la coquille et le binaire
 * partent dans le même build (`04` §4.3) — et le journal qu'elle embarque est
 * produit par le Domaine, sans y être retouché.
 */
final class CombatResultSerializerTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../Determinism/fixtures/reference-combat/';

    /**
     * Le saut de ligne final n'appartient pas à la charge utile — même
     * convention que `ReferenceCombatReplayTest`.
     */
    private function fixture(string $name): string
    {
        $contents = file_get_contents(self::FIXTURES . $name);

        self::assertIsString($contents, sprintf('Fixture introuvable : %s', $name));

        return rtrim($contents, "\n");
    }

    private function combatSeed(): string
    {
        /** @var array{combatSeed: string} $meta */
        $meta = json_decode($this->fixture('meta.json'), true, 512, JSON_THROW_ON_ERROR);

        return $meta['combatSeed'];
    }

    /**
     * Les champs qui suivent le journal, dans l'ordre canonique des clés.
     */
    private function tail(string $firstBoardSide, string $resolution, int $totalTicks, string $winnerSide): string
    {
        return ',"engineVersion":' . EngineVersion::CURRENT
            . ',"firstBoardSide":"' . $firstBoardSide . '"'
            . ',"resolution":"' . $resolution . '"'
            . ',"totalTicks":' . $totalTicks
            . ',"winnerSide":"' . $winnerSide . '"}';
    }

    /**
     * La fixture a été capturée côté par côté : `board-a.json` est le plateau
     * qui occupait A. Passé en premier, il doit donc y revenir — **si** les deux
     * photographies diffèrent, sans quoi l'ordre des arguments trancherait et
     * ce test ne le distinguerait pas du suivant.
     */
    public function testItSerializesTheReferenceCombatByteForByte(): void
    {
        $first = BoardHydrator::fromCanonicalJson($this->fixture('board-a.json'));
        $second = BoardHydrator::fromCanonicalJson($this->fixture('board-b.json'));

        $result = (new Simulator())->run($first, $second, $this->combatSeed());

        self::assertSame(
            '{"combatLog":' . $this->fixture('combat-log.json') . $this->tail('A', 'KNOCKOUT', 48, 'B'),
            CombatResultSerializer::serialize($result, $first),
        );
    }

    /**
     * **La raison d'être de `firstBoardSide`.** L'attribution est canonique
     * (`04` §3.6) : inverser les plateaux ne change ni le journal ni le
     * vainqueur, mais le premier plateau reçu occupe désormais B. Sans ce
     * champ, la coquille ne saurait pas quel côté est le sien.
     */
    public function testFirstBoardSideFollowsTheBoardPassedFirstAndNothingElseMoves(): void
    {
        $first = BoardHydrator::fromCanonicalJson($this->fixture('board-b.json'));
        $second = BoardHydrator::fromCanonicalJson($this->fixture('board-a.json'));

        $result = (new Simulator())->run($first, $second, $this->combatSeed());

        self::assertSame(
            '{"combatLog":' . $this->fixture('combat-log.json') . $this->tail('B', 'KNOCKOUT', 48, 'B'),
            CombatResultSerializer::serialize($result, $first),
        );
    }

    /**
     * **Le piège que l'invariant désamorce, et que la fixture n'exerce pas** :
     * aucun de ses 53 événements n'a de charge utile vide.
     *
     * `CombatLogSerializer` écrit `{}` pour une charge utile vide, par un cast
     * en objet. Décoder sa sortie en tableaux puis la ré-encoder donnerait
     * `[]` : le journal embarqué ne serait plus celui du Domaine, et NF-01
     * tomberait sur le premier `STATUS_EXPIRED` sans charge utile.
     *
     * Le résultat est construit à la main : seul le journal importe ici, et la
     * simulation n'a aucun moyen d'en produire un aussi court.
     */
    public function testAnEmptyPayloadStaysAnObject(): void
    {
        $boardA = BoardHydrator::fromCanonicalJson($this->fixture('board-a.json'));
        $boardB = BoardHydrator::fromCanonicalJson($this->fixture('board-b.json'));

        $log = new CombatLog();
        $log->addEvent(new CombatEvent(tick: 12, type: EventType::STATUS_EXPIRED));

        $result = new SimulationResult(
            winner: $boardB,
            resolution: Resolution::TIMEOUT_RESOLVED,
            totalTicks: 500,
            log: $log,
            boardA: $boardA,
            boardB: $boardB,
        );

        self::assertSame(
            '{"combatLog":{"events":[{"payload":{},"tick":12,"type":"STATUS_EXPIRED"}],"formatVersion":1}'
            . $this->tail('A', 'TIMEOUT_RESOLVED', 500, 'B'),
            CombatResultSerializer::serialize($result, $boardA),
        );
    }
}
