<?php

declare(strict_types=1);

namespace App\Tests\Determinism;

use App\Presentation\Cli\EngineCommand;
use App\Tests\Support\ReadsReferenceCombat;
use PHPUnit\Framework\TestCase;

/**
 * NF-01 entre le serveur et ce que le joueur exécute (`07`, chantier 1a,
 * point 5).
 *
 * **Trois exécutions, une seule sortie attendue.** La référence est
 * `EngineCommand` appelé en processus — le code du serveur, sur le PHP qui
 * fait tourner les tests. Le PHAR est lancé avec ce même PHP : il isole ce qui
 * revient à Box. Le binaire embarque son propre PHP : il isole ce qui revient
 * à `static-php-cli`. La comparaison porte sur la **sortie entière**, octet
 * pour octet, et pas seulement sur le journal.
 *
 * **Soixante-six requêtes** : la graine enregistrée de la fixture et trente-deux
 * graines dérivées, chacune dans les deux ordres des plateaux — le second ordre
 * fait sortir `firstBoardSide` à `B`. Les graines dérivées sont écrites ici,
 * par une règle fixe : aucune fixture permanente nouvelle (`07`).
 *
 * **Sans ses variables d'environnement, ce test est ignoré.** Il exige un PHAR
 * et un binaire construits, ce que la suite locale ordinaire ne fait pas. En
 * CI, PHPUnit tourne avec `--fail-on-skipped` : un test ignoré y fait échouer
 * le job au lieu de passer en silence.
 *
 * **Une boucle et non un fournisseur de données.** Un fournisseur aurait donné
 * cent trente-deux tests ignorés à chaque lancement local, et un échec aurait
 * arrêté la lecture au premier écart. Ici, deux tests seulement, et chaque
 * échec liste toutes les requêtes qui divergent.
 */
final class EmbeddedEngineParityTest extends TestCase
{
    use ReadsReferenceCombat;

    private const int DERIVED_SEEDS = 32;

    public function testThePharRendersExactlyTheServerOutput(): void
    {
        $phar = $this->executableFromEnvironment('COREBOUND_ENGINE_PHAR');

        $this->assertParity([PHP_BINARY, $phar], 'PHAR');
    }

    public function testTheBinaryRendersExactlyTheServerOutput(): void
    {
        $binary = $this->executableFromEnvironment('COREBOUND_ENGINE_BINARY');

        $this->assertParity([$binary], 'binaire');
    }

    /**
     * Absente, la variable fait ignorer le test ; présente, elle doit désigner
     * un fichier qui existe — une faute de frappe dans un chemin ne doit pas
     * passer pour un test ignoré.
     */
    private function executableFromEnvironment(string $variable): string
    {
        $path = getenv($variable);

        if ($path === false || $path === '') {
            self::markTestSkipped(sprintf(
                '%s n\'est pas défini : ce test compare un exécutable construit au moteur serveur.',
                $variable,
            ));
        }

        self::assertFileExists($path, sprintf('%s désigne un fichier absent.', $variable));

        return $path;
    }

    /**
     * @return iterable<string, string> La requête JSON, par cas lisible
     */
    private function requests(): iterable
    {
        $combatSeeds = ['graine enregistrée' => $this->referenceCombatSeed()];

        for ($i = 1; $i <= self::DERIVED_SEEDS; ++$i) {
            $combatSeeds['parity|' . $i] = hash('sha256', 'parity|' . $i);
        }

        foreach ($combatSeeds as $label => $combatSeed) {
            foreach ([false, true] as $boardBFirst) {
                $case = sprintf('%s, %s en premier', $label, $boardBFirst ? 'board-b' : 'board-a');

                yield $case => json_encode($this->referenceRequest($combatSeed, $boardBFirst), JSON_THROW_ON_ERROR);
            }
        }
    }

    /**
     * @param list<string> $command
     */
    private function assertParity(array $command, string $label): void
    {
        $mismatches = [];
        $distinctOutputs = [];
        $count = 0;

        foreach ($this->requests() as $case => $request) {
            ++$count;

            $expected = (new EngineCommand())->handle($request);
            self::assertSame(0, $expected->exitCode, sprintf('La référence elle-même échoue : %s.', $case));
            $distinctOutputs[hash('sha256', $expected->stdout)] = true;

            [$exitCode, $stdout, $stderr] = $this->runEngine($command, $request);

            if ($exitCode !== 0 || $stderr !== '' || $stdout !== $expected->stdout) {
                $mismatches[] = sprintf(
                    '%s : code %d, stderr %s, stdout %s',
                    $case,
                    $exitCode,
                    $stderr === '' ? 'vide' : trim($stderr),
                    $stdout === $expected->stdout
                        ? 'identique'
                        : sprintf('différent (%d octets, %d attendus)', strlen($stdout), strlen($expected->stdout)),
                );
            }
        }

        self::assertSame(2 * (1 + self::DERIVED_SEEDS), $count);

        // Des graines qui ne changeraient rien au combat feraient soixante-six
        // fois la même comparaison : le test n'en vaudrait qu'une.
        self::assertGreaterThan(1, count($distinctOutputs), 'Toutes les requêtes produisent la même sortie.');

        self::assertSame([], $mismatches, sprintf(
            'Le %s diverge du moteur serveur sur %d requêtes sur %d.',
            $label,
            count($mismatches),
            $count,
        ));
    }

    /**
     * Même lancement que `EngineEntryPointTest` : commande en tableau, donc
     * sans shell ni échappement ; stdin écrit en entier et fermé avant de lire
     * stdout.
     *
     * @param list<string> $command
     *
     * @return array{int, string, string} Code de sortie, stdout, stderr
     */
    private function runEngine(array $command, string $stdin): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        self::assertIsResource($process, 'Le processus n\'a pas démarré.');

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [proc_close($process), $stdout, $stderr];
    }
}
