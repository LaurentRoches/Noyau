<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Application\Factory\CombatBoardFactory;
use App\Application\Factory\HeroRosterFactory;
use App\Application\Factory\ScriptedOpponentFactory;
use App\Application\Factory\ShopFactory;
use App\Application\GameRun;
use App\Domain\Engine\Simulator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonScriptedOpponentRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

// --- Seed : argument CLI optionnel, sinon valeur fixe par défaut ---
const DEFAULT_SEED = 1234567890;
$seed = isset($argv[1]) ? (int) $argv[1] : DEFAULT_SEED;
$randomizer = new Randomizer(new PcgOneseq128XslRr64($seed));

echo "=== Corebound — CLI Run (seed: {$seed}) ===\n\n";

// --- Assemblage des dépendances ---
$configPath = __DIR__ . '/config/game';

$vestigeRepository = new JsonVestigeRepository($configPath . '/vestiges.json');
$heroRepository = new JsonHeroRepository($configPath . '/heroes.json');
$itemRepository = new JsonItemRepository($configPath . '/items.json');
$scriptedOpponentRepository = new JsonScriptedOpponentRepository($configPath . '/scripted_opponent.json');

$combatBoardFactory = new CombatBoardFactory($vestigeRepository, $heroRepository, $itemRepository);
$shopFactory = new ShopFactory($itemRepository);
$opponentFactory = new ScriptedOpponentFactory($combatBoardFactory, $itemRepository, $heroRepository, $scriptedOpponentRepository);
$heroRosterFactory = new HeroRosterFactory($heroRepository);
$simulator = new Simulator();

$vestige = $vestigeRepository->find('shadow_vestige');

$gameRun = new GameRun(
    $vestige,
    $shopFactory,
    $opponentFactory,
    $heroRosterFactory,
    $combatBoardFactory,
    $simulator,
    $randomizer,
);

echo "Roster:\n";
foreach ($gameRun->getRoster() as $hero) {
    echo "  {$hero->name} (affinity: {$hero->affinity}, itemSlots: {$hero->itemSlots})\n";
}
echo "\n";

// --- Boucle de run ---
while (!$gameRun->isOver()) {
    $round = $gameRun->getCurrentRound();
    echo "--- Round {$round} ---\n";

    $shop = $gameRun->openShop();
    echo "Shop offers:\n";
    foreach ($shop->getOffers() as $index => $offer) {
        $status = $offer->isPurchased() ? '(sold)' : '';
        echo "  [{$index}] {$offer->getItem()->name} — {$offer->getPrice()} gold {$status}\n";
    }

    // Stratégie fixe : acheter la première offre abordable, tant que le coffre a de la place
    foreach ($shop->getOffers() as $index => $offer) {
        if ($gameRun->getStash()->isFull()) {
            break;
        }
        if ($offer->isPurchased()) {
            continue;
        }
        if (!$gameRun->getWallet()->canAfford($offer->getPrice())) {
            continue;
        }

        $item = $gameRun->purchaseItem($index);
        echo "  Bought: {$item->name}\n";
    }

    echo "Gold after shopping: {$gameRun->getWallet()->getBalance()}\n";

    $victoriesBefore = $gameRun->getVictories();
    $result = $gameRun->playRound();
    $roundWon = $gameRun->getVictories() > $victoriesBefore;

    // playRound() a déjà avancé les compteurs — on affiche l'état résultant
    echo "Combat result: totalTicks={$result->totalTicks} — " . ($roundWon ? "WON\n" : "LOST\n");
    echo "Victories: {$gameRun->getVictories()} — Defeats: {$gameRun->getDefeats()}\n";
    echo "Gold: {$gameRun->getWallet()->getBalance()}\n\n";
}

echo "=== Run over ===\n";
echo $gameRun->hasWon() ? "Result: WON ({$gameRun->getVictories()} victories)\n" : "Result: LOST ({$gameRun->getDefeats()} defeats)\n";
