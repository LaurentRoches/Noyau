<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Cli;

use App\Domain\Engine\CombatLog;
use App\Domain\Engine\EngineVersion;
use App\Domain\Engine\SimulationResult;
use App\Domain\Enum\Resolution;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Snapshot\BoardHydrator;
use App\Presentation\Cli\CommandResult;
use App\Presentation\Cli\EngineCommand;
use App\Presentation\Cli\EngineErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le contrat du moteur embarqué, hors processus (`04` §4.3, chantier 1a).
 *
 * **Un test par code d'erreur, et chaque échec vérifié en entier** : code de
 * sortie, stdout vide, une seule ligne JSON sur stderr. La coquille lira ces
 * trois choses ; un échec qui en respecte deux sur trois est un échec que la
 * coquille lira mal.
 *
 * Les messages ne sont figés que par sous-chaîne — la clé ou le chemin fautif.
 * Ils sont destinés à un humain et peuvent changer ; les codes, non.
 */
final class EngineCommandTest extends TestCase
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

    /**
     * La requête de la fixture de référence, `board-a` en premier.
     *
     * @return array<string, mixed>
     */
    private function referenceRequest(): array
    {
        /** @var array{combatSeed: string} $meta */
        $meta = json_decode($this->fixture('meta.json'), true, 512, JSON_THROW_ON_ERROR);

        return [
            'combatSeed' => $meta['combatSeed'],
            'firstSnapshot' => json_decode($this->fixture('board-a.json'), true, 512, JSON_THROW_ON_ERROR),
            'secondSnapshot' => json_decode($this->fixture('board-b.json'), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function encode(array $request): string
    {
        return json_encode($request, JSON_THROW_ON_ERROR);
    }

    private function assertFailure(
        CommandResult $result,
        EngineErrorCode $code,
        int $exitCode,
        string $messageExcerpt,
    ): void {
        self::assertSame($exitCode, $result->exitCode);
        self::assertSame('', $result->stdout, 'Un échec n\'écrit rien sur stdout.');

        self::assertStringEndsWith("\n", $result->stderr);
        self::assertSame(1, substr_count($result->stderr, "\n"), 'stderr porte une seule ligne.');

        /** @var array{code: string, message: string} $error */
        $error = json_decode($result->stderr, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['code', 'message'], array_keys($error));
        self::assertSame($code->value, $error['code']);
        self::assertStringContainsString($messageExcerpt, $error['message']);
    }

    // --- Succès -----------------------------------------------------------

    /**
     * EX-J0-01, hors processus : la requête de la fixture rend le résultat
     * complet, journal compris, octet pour octet, et rien d'autre.
     */
    public function testItRunsTheReferenceCombat(): void
    {
        $result = (new EngineCommand())->handle($this->encode($this->referenceRequest()));

        self::assertSame('', $result->stderr);
        self::assertSame(0, $result->exitCode);
        self::assertSame(
            '{"combatLog":' . $this->fixture('combat-log.json')
            . ',"engineVersion":' . EngineVersion::CURRENT
            . ',"firstBoardSide":"A","resolution":"KNOCKOUT","totalTicks":48,"winnerSide":"B"}',
            $result->stdout,
        );
    }

    // --- INVALID_JSON -----------------------------------------------------

    public function testItRejectsARequestThatIsNotJson(): void
    {
        $result = (new EngineCommand())->handle('not json');

        $this->assertFailure($result, EngineErrorCode::INVALID_JSON, 1, 'not valid JSON');
    }

    /**
     * Le contrat dit UTF-8 **sans BOM**. Un éditeur ou un shell Windows peut
     * en ajouter un ; il doit être refusé et nommé comme tel, pas accepté par
     * tolérance ici et refusé ailleurs.
     */
    public function testItRejectsAByteOrderMark(): void
    {
        $result = (new EngineCommand())->handle("\xEF\xBB\xBF" . $this->encode($this->referenceRequest()));

        $this->assertFailure($result, EngineErrorCode::INVALID_JSON, 1, 'not valid JSON');
    }

    // --- INVALID_REQUEST --------------------------------------------------

    public function testItRejectsARequestThatIsNotAnObject(): void
    {
        $result = (new EngineCommand())->handle('"a string"');

        $this->assertFailure($result, EngineErrorCode::INVALID_REQUEST, 1, 'JSON object');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredKeys(): iterable
    {
        yield 'combatSeed' => ['combatSeed'];
        yield 'firstSnapshot' => ['firstSnapshot'];
        yield 'secondSnapshot' => ['secondSnapshot'];
    }

    #[DataProvider('requiredKeys')]
    public function testItRejectsAMissingKey(string $key): void
    {
        $request = $this->referenceRequest();
        unset($request[$key]);

        $result = (new EngineCommand())->handle($this->encode($request));

        $this->assertFailure($result, EngineErrorCode::INVALID_REQUEST, 1, '"' . $key . '"');
    }

    public function testItRejectsASeedThatIsNotAString(): void
    {
        $request = $this->referenceRequest();
        $request['combatSeed'] = 46;

        $result = (new EngineCommand())->handle($this->encode($request));

        $this->assertFailure($result, EngineErrorCode::INVALID_REQUEST, 1, '"combatSeed"');
    }

    /**
     * La forme écartée par le cadrage : une chaîne JSON imbriquée dans du JSON.
     */
    public function testItRejectsASnapshotPassedAsAString(): void
    {
        $request = $this->referenceRequest();
        $request['firstSnapshot'] = $this->fixture('board-a.json');

        $result = (new EngineCommand())->handle($this->encode($request));

        $this->assertFailure($result, EngineErrorCode::INVALID_REQUEST, 1, '"firstSnapshot"');
    }

    /**
     * Un flottant n'a pas de forme canonique (`04` §3.4). `CanonicalJson` le
     * refuse en nommant son chemin, et le message dit en plus quel snapshot.
     */
    public function testItRejectsAFloatInASnapshot(): void
    {
        $request = $this->referenceRequest();
        /** @var array{board: array<string, mixed>} $snapshot */
        $snapshot = $request['firstSnapshot'];
        $snapshot['board']['goldAtCombatStart'] = 10.5;
        $request['firstSnapshot'] = $snapshot;

        $result = (new EngineCommand())->handle($this->encode($request));

        $this->assertFailure($result, EngineErrorCode::INVALID_REQUEST, 1, 'goldAtCombatStart');
        self::assertStringContainsString('firstSnapshot', $result->stderr);
    }

    // --- UNREADABLE_BOARD_RECORD ------------------------------------------

    public function testItReportsABoardRecordTheHydratorRefuses(): void
    {
        $request = $this->referenceRequest();
        /** @var array<string, mixed> $snapshot */
        $snapshot = $request['secondSnapshot'];
        $snapshot['formatVersion'] = 999;
        $request['secondSnapshot'] = $snapshot;

        $result = (new EngineCommand())->handle($this->encode($request));

        $this->assertFailure($result, EngineErrorCode::UNREADABLE_BOARD_RECORD, 1, 'secondSnapshot');
    }

    // --- INTERNAL_ERROR ---------------------------------------------------

    public function testAFailureDuringTheSimulationIsAnInternalError(): void
    {
        $command = new EngineCommand(static function (): SimulationResult {
            throw new \RuntimeException('engine exploded');
        });

        $result = $command->handle($this->encode($this->referenceRequest()));

        $this->assertFailure($result, EngineErrorCode::INTERNAL_ERROR, 2, 'engine exploded');
    }

    /**
     * **Le cas qui fonde la règle de classement.** `sideOf()` lève une
     * `\InvalidArgumentException` — la même classe que `CanonicalJson` sur un
     * flottant de la requête. Levée ici, après la lecture, elle signale un
     * défaut du moteur : code 2, pas 1.
     */
    public function testAnInvalidArgumentRaisedAfterTheRequestIsReadIsInternal(): void
    {
        $strangerA = BoardHydrator::fromCanonicalJson($this->fixture('board-a.json'));
        $strangerB = BoardHydrator::fromCanonicalJson($this->fixture('board-b.json'));

        $command = new EngineCommand(
            static fn (CombatBoard $first, CombatBoard $second, string $seed): SimulationResult => new SimulationResult(
                winner: $strangerB,
                resolution: Resolution::KNOCKOUT,
                totalTicks: 1,
                log: new CombatLog(),
                boardA: $strangerA,
                boardB: $strangerB,
            ),
        );

        $result = $command->handle($this->encode($this->referenceRequest()));

        $this->assertFailure($result, EngineErrorCode::INTERNAL_ERROR, 2, 'did not take part');
    }
}
