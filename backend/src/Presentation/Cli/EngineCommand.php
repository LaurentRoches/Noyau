<?php

declare(strict_types=1);

namespace App\Presentation\Cli;

use App\Domain\Engine\CanonicalJson;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Snapshot\BoardHydrator;
use App\Domain\Snapshot\UnreadableBoardRecordException;

/**
 * Le moteur embarqué : une requête JSON en entrée, un résultat canonique en
 * sortie (`04` §4.3, chantier 1a).
 *
 * **Deux étapes, deux familles d'échec.** Tout ce qui échoue en lisant la
 * requête est une faute de l'appelant (code de sortie 1). Tout ce qui échoue
 * ensuite est un défaut du moteur (code 2), quelle que soit la classe de
 * l'exception.
 *
 * **En cas d'échec, stdout reste vide** : la coquille n'a jamais à se demander
 * si des octets partiels sont un résultat.
 */
final class EngineCommand
{
    /**
     * @var \Closure(CombatBoard, CombatBoard, string): SimulationResult
     */
    private \Closure $simulate;

    /**
     * La simulation est injectable pour une seule raison : rendre testable le
     * code 2, qu'aucune requête valide ne sait provoquer. `Simulator` est
     * `final`, et le rester vaut mieux qu'une doublure.
     *
     * @param (\Closure(CombatBoard, CombatBoard, string): SimulationResult)|null $simulate
     */
    public function __construct(?\Closure $simulate = null)
    {
        $this->simulate = $simulate ?? static fn (
            CombatBoard $firstBoard,
            CombatBoard $secondBoard,
            string $combatSeed,
        ): SimulationResult => (new Simulator())->run($firstBoard, $secondBoard, $combatSeed);
    }

    public function handle(string $stdin): CommandResult
    {
        try {
            [$firstBoard, $secondBoard, $combatSeed] = self::readRequest($stdin);
        } catch (EngineRequestRejected $rejection) {
            return self::failure($rejection->errorCode, $rejection->getMessage());
        }

        try {
            $result = ($this->simulate)($firstBoard, $secondBoard, $combatSeed);

            return new CommandResult(0, CombatResultSerializer::serialize($result, $firstBoard), '');
        } catch (\Throwable $exception) {
            return self::failure(EngineErrorCode::INTERNAL_ERROR, $exception->getMessage());
        }
    }

    /**
     * @return array{CombatBoard, CombatBoard, string}
     */
    private static function readRequest(string $stdin): array
    {
        try {
            $request = json_decode($stdin, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new EngineRequestRejected(
                EngineErrorCode::INVALID_JSON,
                'The request is not valid JSON: ' . $exception->getMessage() . '.',
                $exception,
            );
        }

        if (!\is_array($request)) {
            throw new EngineRequestRejected(EngineErrorCode::INVALID_REQUEST, sprintf(
                'The request should be a JSON object, got %s.',
                get_debug_type($request),
            ));
        }

        // La graine est opaque pour le Domaine, qui la hache avant usage :
        // valider sa forme ici ajouterait au binaire une règle que le serveur
        // n'a pas.
        $combatSeed = self::requireKey($request, 'combatSeed');
        if (!\is_string($combatSeed)) {
            throw new EngineRequestRejected(EngineErrorCode::INVALID_REQUEST, sprintf(
                '"combatSeed" should be a string, got %s.',
                get_debug_type($combatSeed),
            ));
        }

        return [
            self::readBoard($request, 'firstSnapshot'),
            self::readBoard($request, 'secondSnapshot'),
            $combatSeed,
        ];
    }

    /**
     * Le snapshot arrive en objet, et repart en chaîne vers l'hydrateur.
     *
     * `BoardHydrator` n'accepte qu'une chaîne, par principe (D-16). Il la
     * décode en tableaux : le ré-encodage ne change donc rien de ce qu'il lit.
     * Il passe par `CanonicalJson` pour une raison de plus — le refus des
     * flottants, qui nomme le chemin fautif.
     *
     * @param array<array-key, mixed> $request
     */
    private static function readBoard(array $request, string $key): CombatBoard
    {
        $snapshot = self::requireKey($request, $key);
        if (!\is_array($snapshot)) {
            throw new EngineRequestRejected(EngineErrorCode::INVALID_REQUEST, sprintf(
                '"%s" should be the board record as a JSON object, not a string holding it; got %s.',
                $key,
                get_debug_type($snapshot),
            ));
        }

        try {
            $json = CanonicalJson::encode($snapshot);
        } catch (\InvalidArgumentException $exception) {
            throw new EngineRequestRejected(
                EngineErrorCode::INVALID_REQUEST,
                sprintf('"%s": %s', $key, $exception->getMessage()),
                $exception,
            );
        }

        try {
            return BoardHydrator::fromCanonicalJson($json);
        } catch (UnreadableBoardRecordException $exception) {
            throw new EngineRequestRejected(
                EngineErrorCode::UNREADABLE_BOARD_RECORD,
                sprintf('"%s": %s', $key, $exception->getMessage()),
                $exception,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $request
     */
    private static function requireKey(array $request, string $key): mixed
    {
        if (!\array_key_exists($key, $request)) {
            throw new EngineRequestRejected(
                EngineErrorCode::INVALID_REQUEST,
                sprintf('The request is missing "%s".', $key),
            );
        }

        return $request[$key];
    }

    /**
     * Une seule ligne sur stderr, en JSON canonique, et rien sur stdout.
     */
    private static function failure(EngineErrorCode $code, string $message): CommandResult
    {
        return new CommandResult(
            $code->exitCode(),
            '',
            CanonicalJson::encode(['code' => $code->value, 'message' => $message]) . "\n",
        );
    }
}
