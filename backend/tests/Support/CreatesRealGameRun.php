<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Factory\GameRunFactory;
use App\Application\GameRun;

trait CreatesRealGameRun
{
    /**
     * Empreinte de contenu factice. Elle n'est pas calculée depuis le vrai
     * catalogue à dessein : ce que ces tests vérifient est le déroulé d'une
     * run, pas la provenance de ses archives, et une valeur reconnaissable
     * dit tout de suite d'où elle vient si elle apparaît quelque part.
     */
    private const string CONTENT_VERSION = 'test-content-version';

    /**
     * Un GameRun réellement frais : roster vide, offre de héros initiale en
     * attente, aucune boutique ouverte. C'est l'état exact qui sort du
     * constructeur — utile pour tester ce contrat de départ lui-même.
     */
    private function createRealGameRun(int $seed, string $vestigeId = 'shadow_vestige'): GameRun
    {
        $configPath = dirname(__DIR__, 2) . '/config/game';

        return (new GameRunFactory($configPath))->create($seed, $vestigeId, self::CONTENT_VERSION);
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
