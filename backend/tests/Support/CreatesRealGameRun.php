<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Factory\GameRunFactory;
use App\Application\GameRun;

trait CreatesRealGameRun
{
    /**
     * Un GameRun réellement frais : roster vide, offre de héros initiale en
     * attente, aucune boutique ouverte. C'est l'état exact qui sort du
     * constructeur — utile pour tester ce contrat de départ lui-même.
     */
    private function createRealGameRun(int $seed, string $vestigeId = 'shadow_vestige'): GameRun
    {
        $configPath = dirname(__DIR__, 2) . '/config/game';

        return (new GameRunFactory($configPath))->create($seed, $vestigeId);
    }

    /**
     * Un GameRun prêt à jouer : l'offre initiale a été consommée en
     * choisissant son premier candidat (le héros d'affinité garantie), ce qui
     * ouvre automatiquement la boutique. C'est l'état attendu par les tests
     * qui portent sur le déroulé du jeu après ce choix (achat, swap, combat).
     */
    private function createRealGameRunReadyToPlay(int $seed, string $vestigeId = 'shadow_vestige'): GameRun
    {
        $gameRun = $this->createRealGameRun($seed, $vestigeId);

        $offer = $gameRun->getPendingHeroOffer();
        $gameRun->chooseHero($offer->candidates[0]->id);

        return $gameRun;
    }
}
