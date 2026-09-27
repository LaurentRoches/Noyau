<?php

declare(strict_types=1);

namespace App\Tests\Determinism;

use App\Application\GameRun;
use App\Domain\Engine\CombatLogSerializer;
use App\Domain\Engine\Simulator;
use App\Domain\Snapshot\BoardHydrator;
use App\Domain\Snapshot\BoardSnapshot;
use App\Persistence\CombatRecordsRepository;
use App\Tests\Support\CreatesInMemoryDatabase;
use App\Tests\Support\CreatesRealGameRun;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Ce que la chaîne réelle archive est rejouable (NF-01, D-16, `04` §6).
 *
 * **Le pendant dynamique de `ReferenceCombatReplayTest`.** Celui-ci compare le
 * moteur à un journal figé ; celui-là compare un combat **à lui-même**, à travers
 * l'archive : une vraie run joue une manche, `CombatRecordsRepository` l'écrit,
 * le test relit la ligne en SQL direct, hydrate les deux enveloppes et refait le
 * combat.
 *
 * **Il n'a aucune constante figée, et c'est délibéré.** Un rééquilibrage du
 * catalogue au chantier 10 changera le combat que cette graine produit — sans
 * rien changer à ce que ce test affirme, puisqu'il compare le rejeu au journal
 * qu'il vient lui-même d'obtenir. C'est la fixture figée qui est sensible au
 * catalogue, pas ce fichier.
 *
 * **Ce qu'il garde que l'autre ne garde pas.** Que `RunController` archive
 * aujourd'hui quelque chose de relisible. Une fixture figée, elle, resterait verte
 * si `BoardRecord` gagnait un champ ou si le chemin d'archivage dérivait : elle ne
 * relit qu'un blob que le dépôt transporte, pas ce que la production écrit.
 *
 * **La lecture se fait en SQL direct**, comme `GameRunRepositoryTest` le fait déjà
 * pour sa colonne. `CombatRecordsRepository` est en écriture seule tant que
 * personne n'a besoin d'une forme de retour, et lui en inventer une pour ce test
 * figerait un contrat avant de savoir ce qu'on en attend.
 */
final class ArchivedCombatReplayTest extends TestCase
{
    use CreatesInMemoryDatabase;
    use CreatesRealGameRun;

    private const int RUN_SEED = 46;
    private const string RUN_ID = 'run-under-replay';

    /**
     * Achète tout ce que la bourse permet dans la boutique déjà ouverte.
     *
     * Sans achat, le plateau du joueur à la manche 1 n'a aucun objet et le combat
     * archivé serait dégénéré — il prouverait que la plomberie transporte du vide.
     * Les gardes sont celles de `run.php`, qui fait exactement cette boucle.
     *
     * `getCurrentShop()` et non `openShop()` : rouvrir la boutique consommerait des
     * tirages sur le `Randomizer` du run.
     */
    private function buyWhatWeCan(GameRun $gameRun): void
    {
        $shop = $gameRun->getCurrentShop();

        if ($shop === null) {
            return;
        }

        foreach ($shop->getOffers() as $index => $offer) {
            if ($gameRun->getStash()->isFull()) {
                return;
            }

            if ($offer->isPurchased() || !$gameRun->getWallet()->canAfford($offer->getPrice())) {
                continue;
            }

            $gameRun->purchaseItem($index);
        }
    }

    /**
     * Joue une manche réelle, l'archive, et rend la ligne telle que la base la
     * porte.
     *
     * @return array{\App\Domain\Engine\SimulationResult, array<string, string>}
     */
    private function playAndArchive(): array
    {
        $gameRun = $this->createRealGameRunReadyToPlay(self::RUN_SEED);
        $this->buyWhatWeCan($gameRun);

        // Relevé AVANT playRound() : la manche est incrémentée par la transition
        // de fin de manche, et c'est le numéro joué qu'on archive.
        $round = $gameRun->getCurrentRound();
        $result = $gameRun->playRound();

        $record = $gameRun->getLastCombatRecord();
        self::assertNotNull($record, 'Une manche simulée produit toujours une archive.');

        $pdo = $this->createInMemoryDatabase();
        (new CombatRecordsRepository($pdo))->save(self::RUN_ID, $round, $record);

        $statement = $pdo->prepare(
            'SELECT board_a, board_b, combat_seed, resolution, winner_side FROM combat_records WHERE run_id = :id AND round = :round'
        );
        $statement->execute(['id' => self::RUN_ID, 'round' => $round]);

        /** @var array<string, string> $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return [$result, $row];
    }

    /**
     * **Le test qui ferme le chantier.**
     *
     * Ce que la chaîne réelle a écrit en base, relu sans catalogue et sans
     * `GameRun`, rejoue exactement le combat qui l'a produit.
     *
     * Les assertions descendent du grossier au fin — résolution, vainqueur, nombre
     * d'événements, puis la chaîne entière — parce qu'un diff PHPUnit sur un
     * journal complet est illisible et ne dit pas ce qui a bougé.
     */
    public function testACombatArchivedByTheRealPipelineReplaysByteForByte(): void
    {
        [$result, $row] = $this->playAndArchive();

        $replayed = (new Simulator())->run(
            BoardHydrator::fromCanonicalJson($row['board_a']),
            BoardHydrator::fromCanonicalJson($row['board_b']),
            $row['combat_seed'],
        );

        self::assertSame($result->resolution, $replayed->resolution);
        self::assertSame(
            $result->sideOf($result->winner),
            $replayed->sideOf($replayed->winner),
        );
        self::assertSame($result->log->count(), $replayed->log->count());
        self::assertSame(
            CombatLogSerializer::serialize($result->log),
            CombatLogSerializer::serialize($replayed->log),
        );
    }

    /**
     * L'archive est rangée par **côté**, jamais par « joueur ».
     *
     * Les libellés A/B de D-19 existent parce que le serveur simule une fois et que
     * les deux joueurs d'un futur PvP regardent le même journal : un côté nommé
     * « joueur » y serait faux pour l'un des deux. Ce test vérifie l'invariant là
     * où il peut réellement se rompre — dans le chemin qui écrit —, en comparant
     * chaque colonne à la photographie du plateau que le **moteur** a mis de ce
     * côté, et non à celui que `GameRun` a construit en premier.
     */
    public function testTheArchivedColumnsFollowTheSidesTheEngineAssigned(): void
    {
        [$result, $row] = $this->playAndArchive();

        self::assertStringContainsString(
            BoardSnapshot::fromBoard($result->boardA)->toCanonicalJson(),
            $row['board_a'],
        );
        self::assertStringContainsString(
            BoardSnapshot::fromBoard($result->boardB)->toCanonicalJson(),
            $row['board_b'],
        );
        self::assertSame($result->sideOf($result->winner)->value, $row['winner_side']);
    }

    /**
     * Rejouer une archive ne consulte ni le catalogue ni la run.
     *
     * L'enveloppe est passée comme une chaîne nue : aucun chemin de configuration,
     * aucun dépôt, aucune `GameRun`. Ce test le rend observable en rejouant la même
     * ligne deux fois de suite, sans rien d'autre en main que ses octets, et en
     * exigeant deux journaux identiques.
     *
     * C'est la première borne de D-16 : un fantôme archivé avant un rééquilibrage
     * rejoue avec ses chiffres d'origine.
     */
    public function testTheSameArchivedRowAlwaysReplaysTheSameCombat(): void
    {
        [, $row] = $this->playAndArchive();

        $replay = static fn (): string => CombatLogSerializer::serialize(
            (new Simulator())->run(
                BoardHydrator::fromCanonicalJson($row['board_a']),
                BoardHydrator::fromCanonicalJson($row['board_b']),
                $row['combat_seed'],
            )->log
        );

        self::assertSame($replay(), $replay());
    }
}
