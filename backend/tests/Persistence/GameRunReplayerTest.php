<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Application\Factory\GameRunFactory;
use App\Infrastructure\Content\ContentCatalogReader;
use App\Persistence\ContentVersionMismatchException;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Persistence\RunNotFoundException;
use App\Tests\Support\CreatesInMemoryDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Le rejeu, et la porte qui le garde (`04` §6.3, D-18).
 *
 * **Pourquoi la porte est ici et pas dans le contrôleur.** `replay()` est
 * l'entonnoir unique : les six points d'entrée de `RunController` y passent
 * tous, y compris la création. Un contrôle réparti sur six méthodes serait six
 * occasions d'en oublier une, et celle qu'on oublierait servirait un état faux
 * sans rien signaler.
 *
 * **Ce que la porte protège.** La base ne stocke pas d'états, elle stocke des
 * décisions. Une offre de boutique, un prix, les valeurs d'un objet acheté sont
 * reconstruits depuis les catalogues courants. Si les catalogues ont changé, les
 * mêmes décisions produisent une autre partie — sans erreur, sans trace, sans
 * que le joueur puisse s'en apercevoir.
 */
final class GameRunReplayerTest extends TestCase
{
    use CreatesInMemoryDatabase;

    private function configPath(): string
    {
        return dirname(__DIR__, 2) . '/config/game';
    }

    /**
     * Le lecteur est construit sur le **vrai** `config/game`, celui que le
     * replayer sert déjà à `GameRunFactory`. Un répertoire de test rendrait
     * l'accord entre les deux trivial et ne prouverait rien.
     *
     * @return array{GameRunReplayer, GameRunRepository, GameRunActionsRepository, ContentCatalogReader}
     */
    private function createReplayer(): array
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $contentCatalogReader = new ContentCatalogReader($this->configPath());
        $replayer = new GameRunReplayer(
            $runRepository,
            $actionsRepository,
            $this->configPath(),
            $contentCatalogReader,
        );

        return [$replayer, $runRepository, $actionsRepository, $contentCatalogReader];
    }

    public function testItReplaysARunFromItsActionLog(): void
    {
        [$replayer, $runRepository, $actionsRepository, $contentCatalogReader] = $this->createReplayer();

        // Un même seed/vestigeId produit toujours la même offre initiale
        // (Randomizer déterministe) : on la construit une fois à part pour
        // connaître l'id d'un candidat valide, sans dupliquer la logique de
        // tirage dans le test lui-même.
        //
        // L'empreinte passée à cette sonde ne sert à rien — on n'en lit que
        // l'offre de héros, jamais une archive de combat — mais elle vient
        // quand même du lecteur : une constante inventée ici serait la seule
        // ligne du fichier à prétendre qu'une run peut naître sans provenance.
        $probeGameRun = (new GameRunFactory($this->configPath()))->create(
            42,
            'shadow_vestige',
            $contentCatalogReader->version(),
        );
        $heroId = $probeGameRun->getPendingHeroOffer()->candidates[0]->id;

        // L'empreinte vient du lecteur, pas d'une constante. C'est le chemin
        // nominal : une run créée sous le contenu courant se rejoue.
        $runRepository->create('run-123', 42, 'shadow_vestige', $contentCatalogReader->version());
        $actionsRepository->append('run-123', 1, GameRunActionType::CHOOSE_HERO, ['heroId' => $heroId]);
        $actionsRepository->append('run-123', 2, GameRunActionType::PURCHASE, ['slotIndex' => 0]);

        $gameRun = $replayer->replay('run-123');

        self::assertNotNull($gameRun->getCurrentShop());
        self::assertTrue($gameRun->getCurrentShop()->getOffers()[0]->isPurchased());
    }

    public function testItThrowsRunNotFoundExceptionForAnUnknownRunId(): void
    {
        [$replayer] = $this->createReplayer();

        $this->expectException(RunNotFoundException::class);

        $replayer->replay('does-not-exist');
    }

    // === Porte de version de contenu — commit 12 =========================

    /**
     * Le refus, qui est toute la raison d'être des trois briques précédentes.
     *
     * La run n'a **aucune action** journalisée : le refus doit tomber avant
     * même qu'il y ait quelque chose à rejouer. Une porte qui ne se déclenche
     * qu'au premier `CHOOSE_HERO` laisserait passer les `GET /runs/{id}`, donc
     * afficherait un état reconstruit depuis le mauvais catalogue.
     *
     * `LogicException` et non `RuntimeException` : la requête est bien formée
     * et la ressource existe. Ce qui est en conflit, c'est l'état du serveur
     * avec ce que la run suppose — un 409, que le `Router` produit déjà pour
     * cette hiérarchie.
     */
    public function testItRefusesToReplayARunCreatedUnderAnotherContentVersion(): void
    {
        [$replayer, $runRepository] = $this->createReplayer();

        $runRepository->create('run-123', 42, 'shadow_vestige', 'a-stale-content-version');

        $this->expectException(ContentVersionMismatchException::class);

        $replayer->replay('run-123');
    }

    /**
     * Le message nomme la run et les deux empreintes.
     *
     * Sans les deux valeurs, le refus est indiscernable d'un bug : on sait que
     * quelque chose ne correspond pas, sans savoir quoi ni depuis quand. Avec
     * elles, la ligne suffit à décider — base de développement à jeter, ou
     * catalogue modifié par erreur.
     *
     * L'assertion porte sur une sous-chaîne **contiguë** qui traverse les deux
     * empreintes : elle fige donc aussi leur ordre, l'enregistrée d'abord.
     * L'inverse se lirait comme l'accusation contraire.
     */
    public function testTheRefusalNamesTheRunAndBothContentVersions(): void
    {
        [$replayer, $runRepository, , $contentCatalogReader] = $this->createReplayer();

        $runRepository->create('run-123', 42, 'shadow_vestige', 'a-stale-content-version');

        $this->expectException(ContentVersionMismatchException::class);
        $this->expectExceptionMessage(sprintf(
            'Run "run-123" was created under content version a-stale-content-version, '
            . 'but the current content is %s.',
            $contentCatalogReader->version(),
        ));

        $replayer->replay('run-123');
    }
}
