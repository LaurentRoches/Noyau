<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\GameRunFactory;
use PHPUnit\Framework\TestCase;

final class GameRunFactoryTest extends TestCase
{
    public function testItCreatesAGameRunForAKnownVestige(): void
    {
        $factory = new GameRunFactory(dirname(__DIR__, 3) . '/config/game');

        $gameRun = $factory->create(seed: 42, vestigeId: 'shadow_vestige', contentVersion: 'cv');

        self::assertSame(1, $gameRun->getCurrentRound());
        self::assertSame(20, $gameRun->getWallet()->getBalance());

        // Le roster est vide à la construction : une offre de héros initiale
        // est en attente, pas encore de choix effectué.
        self::assertSame([], $gameRun->getRoster());
        self::assertNotNull($gameRun->getPendingHeroOffer());
        self::assertCount(3, $gameRun->getPendingHeroOffer()->candidates);
    }

    /**
     * L'empreinte de contenu traverse la fabrique jusqu'aux archives.
     *
     * Elle n'est visible nulle part ailleurs : `GameRun` ne l'expose pas, elle
     * ne sert qu'à la provenance des enveloppes de plateau. Un câblage fautif
     * — le mauvais argument transmis, ou aucun — ne serait donc signalé par
     * rien d'autre que ce test, et produirait un corpus entier de fantômes
     * mal étiquetés.
     */
    public function testItPassesTheContentVersionThroughToTheArchivedBoards(): void
    {
        $factory = new GameRunFactory(dirname(__DIR__, 3) . '/config/game');

        $gameRun = $factory->create(seed: 42, vestigeId: 'shadow_vestige', contentVersion: 'cv-from-factory');
        $gameRun->chooseHero($gameRun->getPendingHeroOffer()->candidates[0]->id);
        $gameRun->playRound();

        $record = $gameRun->getLastCombatRecord();
        self::assertNotNull($record);
        self::assertStringContainsString(
            '"contentVersion":"cv-from-factory"',
            $record->boardA->toCanonicalJson(),
        );
    }
}
