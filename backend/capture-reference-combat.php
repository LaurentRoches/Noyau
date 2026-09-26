<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Application\Factory\GameRunFactory;
use App\Application\GameRun;
use App\Domain\Engine\CombatLogSerializer;
use App\Domain\Engine\SimulationResult;
use App\Domain\Snapshot\CombatRecord;
use App\Infrastructure\Content\ContentCatalogReader;

/**
 * Capture du combat de référence rejoué par `ReferenceCombatReplayTest`
 * (NF-01, D-16).
 *
 * **Pourquoi un script et non un test.** La fixture est une entrée, au même
 * titre que `config/game` : elle doit être figée dans le dépôt et relue telle
 * quelle. Un test qui la régénérerait à chaque exécution ne comparerait le
 * moteur qu'à lui-même, et ne verrait jamais une dérive.
 *
 * **Pourquoi elle sort d'une vraie run.** Roster, achats, adversaire scripté,
 * décoration par compétence, or au lancement : tout vient du chemin réel, pas
 * d'un montage. Le seul raccourci est de ne pas passer par HTTP.
 *
 * **Quand la régénérer.** Quand `EngineVersion` est relevée délibérément, et
 * à ce moment-là seulement. Si `ReferenceCombatReplayTest` rougit sans qu'on
 * ait touché au moteur, ce n'est pas la fixture qu'il faut refaire : c'est le
 * moteur qu'il faut regarder. Même doctrine que les 2 129 octets de
 * `BoardSnapshotTest`.
 *
 * Usage :
 *   php capture-reference-combat.php --scan [--seeds=8] [--rounds=6]
 *   php capture-reference-combat.php --seed=42 --round=4
 */

const FIXTURE_DIR = __DIR__ . '/tests/Determinism/fixtures/reference-combat';
const DEFAULT_SEED = 42;
const DEFAULT_ROUND = 1;
const VESTIGE_ID = 'shadow_vestige';

/**
 * @return array<string, string>
 */
function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (!str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? '1';
    }

    return $options;
}

function createRun(int $seed, string $configPath, string $contentVersion): GameRun
{
    return (new GameRunFactory($configPath))->create($seed, VESTIGE_ID, $contentVersion);
}

/**
 * Consomme l'offre de héros en attente, s'il y en a une.
 *
 * Le premier candidat est celui d'affinité garantie. Le choix est arbitraire
 * mais fixe : c'est celui que `CreatesRealGameRun` fait déjà, et une fixture a
 * besoin d'être reproductible, pas représentative.
 */
function chooseHeroIfOffered(GameRun $gameRun): void
{
    $offer = $gameRun->getPendingHeroOffer();

    if ($offer !== null) {
        $gameRun->chooseHero($offer->candidates[0]->id);
    }
}

/**
 * Achète tout ce que la bourse permet dans la boutique **déjà ouverte**.
 *
 * `getCurrentShop()` et non `openShop()` : rouvrir la boutique consommerait des
 * tirages sur le `Randomizer` du run et décalerait tout ce qui suit. `run.php`
 * la rouvre, et c'est un écart qu'on ne reproduit pas ici — une fixture doit
 * suivre le chemin que le jeu emprunte, pas celui de sa CLI de démonstration.
 */
function buyWhatWeCan(GameRun $gameRun): void
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
 * Joue la run jusqu'à la manche voulue et rend ce que cette manche a produit.
 *
 * @return array{SimulationResult, CombatRecord, int}|null null si la run se
 *                                                         termine avant
 */
function playUpTo(GameRun $gameRun, int $targetRound): ?array
{
    while (!$gameRun->isOver()) {
        chooseHeroIfOffered($gameRun);
        buyWhatWeCan($gameRun);

        $round = $gameRun->getCurrentRound();
        $result = $gameRun->playRound();
        $record = $gameRun->getLastCombatRecord();

        if ($round === $targetRound && $record !== null) {
            return [$result, $record, $round];
        }

        if ($round >= $targetRound) {
            return null;
        }
    }

    return null;
}

/**
 * Types d'événement présents au journal, et leur compte.
 *
 * C'est la mesure qui décide quelle manche capturer : la valeur d'une ancre de
 * non-régression se lit au nombre de chemins du moteur que son journal
 * traverse.
 *
 * @return array<string, int>
 */
function eventTypeCoverage(SimulationResult $result): array
{
    $coverage = [];

    foreach ($result->log->getEvents() as $event) {
        $coverage[$event->type->value] = ($coverage[$event->type->value] ?? 0) + 1;
    }

    ksort($coverage, SORT_STRING);

    return $coverage;
}

// --------------------------------------------------------------------------

$options = parseOptions($argv);
$configPath = __DIR__ . '/config/game';
$contentVersion = (new ContentCatalogReader($configPath))->version();

if (isset($options['scan'])) {
    $seedCount = (int) ($options['seeds'] ?? 8);
    $roundCount = (int) ($options['rounds'] ?? 6);

    echo "Reconnaissance — {$seedCount} graines x {$roundCount} manches\n";
    echo "graine  manche  ticks  resolution              types  evts  detail\n";

    for ($seed = DEFAULT_SEED; $seed < DEFAULT_SEED + $seedCount; $seed++) {
        for ($round = 1; $round <= $roundCount; $round++) {
            $captured = playUpTo(createRun($seed, $configPath, $contentVersion), $round);

            if ($captured === null) {
                printf("%6d  %6d  %s\n", $seed, $round, '— run terminée avant cette manche');

                continue;
            }

            [$result, , ] = $captured;
            $coverage = eventTypeCoverage($result);

            printf(
                "%6d  %6d  %5d  %-22s  %5d  %4d  %s\n",
                $seed,
                $round,
                $result->totalTicks,
                $result->resolution->value,
                count($coverage),
                array_sum($coverage),
                implode(' ', array_keys($coverage)),
            );
        }
    }

    exit(0);
}

$seed = (int) ($options['seed'] ?? DEFAULT_SEED);
$round = (int) ($options['round'] ?? DEFAULT_ROUND);

$captured = playUpTo(createRun($seed, $configPath, $contentVersion), $round);

if ($captured === null) {
    fwrite(STDERR, sprintf("La run de graine %d ne joue pas la manche %d.\n", $seed, $round));

    exit(1);
}

[$result, $record, $capturedRound] = $captured;
$coverage = eventTypeCoverage($result);

if (!is_dir(FIXTURE_DIR) && !mkdir(FIXTURE_DIR, 0o775, true) && !is_dir(FIXTURE_DIR)) {
    fwrite(STDERR, sprintf("Impossible de créer %s\n", FIXTURE_DIR));

    exit(1);
}

/**
 * Les trois premiers fichiers portent la chaîne canonique **exacte** que le
 * système produit : c'est elle que le test compare à l'octet près, et c'est
 * elle que le moteur embarqué devra reproduire (NF-01). Le saut de ligne final
 * n'en fait pas partie — il est ajouté parce qu'un fichier qui n'en a pas est
 * un piège connu de ce dépôt (`06` §4.4), et le test le retire avant de
 * comparer.
 */
$files = [
    'board-a.json' => $record->boardA->toCanonicalJson(),
    'board-b.json' => $record->boardB->toCanonicalJson(),
    'combat-log.json' => CombatLogSerializer::serialize($result->log),
    'meta.json' => json_encode([
        'capturedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        'combatSeed' => $record->combatSeed,
        'contentVersion' => $contentVersion,
        'engineVersion' => $record->engineVersion,
        'eventTypes' => $coverage,
        'resolution' => $record->resolution->value,
        'round' => $capturedRound,
        'runSeed' => $seed,
        'totalTicks' => $result->totalTicks,
        'vestigeId' => VESTIGE_ID,
        'winnerSide' => $record->winnerSide->value,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
];

foreach ($files as $name => $contents) {
    file_put_contents(FIXTURE_DIR . '/' . $name, $contents . "\n");
    printf("%-16s %6d octets\n", $name, strlen($contents));
}

echo "\n";
printf("graine %d, manche %d, %d ticks, %s, vainqueur %s\n",
    $seed, $capturedRound, $result->totalTicks, $record->resolution->value, $record->winnerSide->value);
printf("empreinte de contenu : %s\n", $contentVersion);
printf("%d evenements, %d types :\n", array_sum($coverage), count($coverage));
foreach ($coverage as $type => $count) {
    printf("  %-24s %4d\n", $type, $count);
}