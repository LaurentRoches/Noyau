<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Cli;

use App\Domain\Engine\EngineVersion;
use PHPUnit\Framework\TestCase;

/**
 * Le câblage de `bin/corebound-engine`, dans un vrai processus (`04` §4.3).
 *
 * **Ce fichier ne reteste pas le contrat** : `EngineCommandTest` le couvre
 * hors processus, code d'erreur par code d'erreur. Il vérifie ce que seul un
 * processus montre — que stdin est lu en entier, que les octets rendus
 * arrivent sur le bon flux sans rien d'ajouté, et que le code de sortie est
 * celui de la commande.
 *
 * **Ce n'est pas encore le test de parité.** Il lance le script avec le PHP
 * qui exécute les tests, pas le PHAR ni le binaire `phpmicro` : ces deux
 * exécutions appartiennent à `ci/determinism-parity` (`07`, chantier 1a,
 * point 5).
 */
final class EngineEntryPointTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../Determinism/fixtures/reference-combat/';
    private const string ENTRY_POINT = __DIR__ . '/../../../bin/corebound-engine';

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

    private function referenceRequest(): string
    {
        /** @var array{combatSeed: string} $meta */
        $meta = json_decode($this->fixture('meta.json'), true, 512, JSON_THROW_ON_ERROR);

        return json_encode([
            'combatSeed' => $meta['combatSeed'],
            'firstSnapshot' => json_decode($this->fixture('board-a.json'), true, 512, JSON_THROW_ON_ERROR),
            'secondSnapshot' => json_decode($this->fixture('board-b.json'), true, 512, JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Lance le point d'entrée avec le PHP courant, lui passe `$stdin`, et rend
     * ce qu'il a écrit.
     *
     * La commande est un tableau et non une chaîne : aucun shell n'intervient,
     * donc aucun échappement — le dépôt vit sous un chemin qui contient des
     * espaces. stdin est écrit en entier puis fermé avant de lire stdout : le
     * script lit jusqu'à la fin de stdin avant d'écrire quoi que ce soit.
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runEntryPoint(string $stdin): array
    {
        $process = proc_open(
            [PHP_BINARY, self::ENTRY_POINT],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        self::assertIsResource($process, 'Le processus n\'a pas démarré.');

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return ['exitCode' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * EX-J0-01 sur le PHP de développement : le résultat complet, sans un
     * octet de plus — ni saut de ligne final, ni avertissement égaré sur
     * stdout.
     */
    public function testTheEntryPointWritesTheResultOnStdoutAndExitsWithZero(): void
    {
        $process = $this->runEntryPoint($this->referenceRequest());

        self::assertSame('', $process['stderr']);
        self::assertSame(0, $process['exitCode']);
        self::assertSame(
            '{"combatLog":' . $this->fixture('combat-log.json')
            . ',"engineVersion":' . EngineVersion::CURRENT
            . ',"firstBoardSide":"A","resolution":"KNOCKOUT","totalTicks":48,"winnerSide":"B"}',
            $process['stdout'],
        );
    }

    /**
     * Le chemin d'échec traverse lui aussi le processus : stdout vide, la
     * ligne d'erreur sur stderr, et le code de sortie de la commande.
     */
    public function testTheEntryPointWritesAFailureOnStderrAndExitsWithItsCode(): void
    {
        $process = $this->runEntryPoint('not json');

        self::assertSame(1, $process['exitCode']);
        self::assertSame('', $process['stdout']);
        self::assertStringStartsWith('{"code":"INVALID_JSON","message":', $process['stderr']);
        self::assertStringEndsWith("}\n", $process['stderr']);
    }
}
