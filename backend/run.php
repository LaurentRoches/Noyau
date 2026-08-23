<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Application\Factory\GameRunFactory;

// --- Seed : argument CLI optionnel, sinon valeur fixe par défaut ---
const DEFAULT_SEED = 1234567890;
$seed = isset($argv[1]) ? (int) $argv[1] : DEFAULT_SEED;

echo "=== Corebound — CLI Run (seed: {$seed}) ===\n\n";

$gameRunFactory = new GameRunFactory(__DIR__ . '/config/game');
$gameRun = $gameRunFactory->create($seed, 'shadow_vestige');

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

    echo "Combat result: totalTicks={$result->totalTicks} — " . ($roundWon ? "WON\n" : "LOST\n");
    echo "Victories: {$gameRun->getVictories()} — Defeats: {$gameRun->getDefeats()}\n";
    echo "Gold: {$gameRun->getWallet()->getBalance()}\n\n";
}

echo "=== Run over ===\n";
echo $gameRun->hasWon() ? "Result: WON ({$gameRun->getVictories()} victories)\n" : "Result: LOST ({$gameRun->getDefeats()} defeats)\n";
