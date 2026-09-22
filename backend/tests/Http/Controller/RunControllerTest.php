<?php

declare(strict_types=1);

namespace App\Tests\Http\Controller;

use App\Http\Controller\RunController;
use App\Http\Request;
use App\Infrastructure\Content\ContentCatalogReader;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Persistence\RunNotFoundException;
use App\Tests\Support\CreatesInMemoryDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RunControllerTest extends TestCase
{
    use CreatesInMemoryDatabase;

    /**
     * Le lecteur est construit sur le **vrai** `config/game`, comme le
     * replayer l'est déjà. Un répertoire de test donnerait une empreinte
     * fabriquée : ce qu'on veut vérifier, c'est que la run porte l'empreinte du
     * contenu réellement servi, pas qu'une chaîne circule.
     *
     * Le lecteur est ajouté en quatrième position du tuple retourné. Les
     * déstructurations existantes en prennent trois et restent valides.
     *
     * **Une seule instance pour le contrôleur et le replayer**, comme dans
     * `public/index.php`. `create()` épingle une empreinte puis appelle
     * aussitôt `replay()`, qui la compare : deux lecteurs distincts gèleraient
     * chacun la leur, et le jour où ils divergeraient, toute création de run
     * échouerait sur sa propre empreinte.
     */
    private function createController(): array
    {
        $pdo = $this->createInMemoryDatabase();
        $runRepository = new GameRunRepository($pdo);
        $actionsRepository = new GameRunActionsRepository($pdo);
        $configPath = dirname(__DIR__, 3) . '/config/game';
        $contentCatalogReader = new ContentCatalogReader($configPath);
        $replayer = new GameRunReplayer($runRepository, $actionsRepository, $configPath, $contentCatalogReader);
        $controller = new RunController($runRepository, $actionsRepository, $replayer, $contentCatalogReader);

        return [$controller, $runRepository, $actionsRepository, $contentCatalogReader];
    }

    /**
     * @param array<string, mixed> $createResponseBody
     */
    private function chooseFirstOfferedHero(RunController $controller, array $createResponseBody): void
    {
        $runId = $createResponseBody['run_id'];
        $heroId = $createResponseBody['state']['pendingHeroOffer'][0]['id'];

        $request = Request::fake(rawBody: json_encode(['heroId' => $heroId]));
        $controller->chooseHero(['runId' => $runId], $request);
    }

    public function testItCreatesARunWithAPendingHeroOffer(): void
    {
        [$controller, $runRepository, $actionsRepository] = $this->createController();

        $response = $controller->create([], Request::fake());

        self::assertSame(201, $response->statusCode);
        self::assertIsString($response->body['run_id']);
        self::assertNotSame('', $response->body['run_id']);

        $state = $response->body['state'];
        self::assertSame(1, $state['round']);
        self::assertSame(20, $state['wallet']['balance']);
        self::assertNull($state['shop']);
        self::assertCount(3, $state['pendingHeroOffer']);
        self::assertSame([], $state['roster']);

        // Effets de bord réellement persistés, pas juste ce qui est retourné
        $record = $runRepository->find($response->body['run_id']);
        self::assertNotNull($record);
        self::assertSame('shadow_vestige', $record->vestigeId);

        // Rien n'est journalisé à la création : l'offre de héros n'est pas
        // une action, c'est l'état initial du run.
        $actions = $actionsRepository->findAllForRun($response->body['run_id']);
        self::assertCount(0, $actions);
    }

    /**
     * Une run naît avec l'empreinte du contenu sous lequel elle est jouée
     * (`04` §6.3, D-18).
     *
     * C'est le seul moment où l'empreinte est écrite. Tout ce que la brique
     * suivante pourra faire au rejeu — comparer, refuser — dépend de ce que
     * cette ligne a posé ici : une run créée sans empreinte est irrécupérable,
     * puisque rien ne dira jamais sous quel catalogue elle a commencé.
     *
     * L'assertion compare à `$reader->version()` plutôt qu'à une constante. Une
     * constante serait à corriger à chaque retouche d'un catalogue — donc
     * corrigée sans être relue, donc inutile. Ce qui est figé ici, c'est
     * l'identité entre ce que le contrôleur épingle et ce que le lecteur voit,
     * et elle doit tenir quel que soit le contenu.
     */
    public function testItPinsTheContentVersionOfTheCatalogsOnTheRun(): void
    {
        [$controller, $runRepository, , $contentCatalogReader] = $this->createController();

        $response = $controller->create([], Request::fake());

        $record = $runRepository->find($response->body['run_id']);
        self::assertNotNull($record);
        self::assertSame($contentCatalogReader->version(), $record->contentVersion);
    }

    public function testItChoosesAHeroAndOpensTheShop(): void
    {
        [$controller, , $actionsRepository] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];
        $heroId = $createResponse->body['state']['pendingHeroOffer'][0]['id'];

        $request = Request::fake(rawBody: json_encode(['heroId' => $heroId]));
        $response = $controller->chooseHero(['runId' => $runId], $request);

        self::assertSame(200, $response->statusCode);
        self::assertCount(1, $response->body['state']['roster']);
        self::assertSame($heroId, $response->body['state']['roster'][0]['id']);
        self::assertNull($response->body['state']['pendingHeroOffer']);
        self::assertNotNull($response->body['state']['shop']);
        self::assertCount(4, $response->body['state']['shop']['offers']);

        $actions = $actionsRepository->findAllForRun($runId);
        self::assertCount(1, $actions);
        self::assertSame(GameRunActionType::CHOOSE_HERO, $actions[0]->type);
    }

    public function testItShowsAnExistingRun(): void
    {
        [$controller] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];

        $response = $controller->show(['runId' => $runId]);

        self::assertSame(200, $response->statusCode);
        self::assertSame($createResponse->body['state'], $response->body['state']);
    }

    public function testItThrowsForAnUnknownRunOnShow(): void
    {
        [$controller] = $this->createController();

        $this->expectException(RunNotFoundException::class);

        $controller->show(['runId' => 'does-not-exist']);
    }

    public function testItBuysAnItemFromTheShop(): void
    {
        [$controller, , $actionsRepository] = $this->createController();

        $createResponse = $controller->create([], Request::fake(rawBody: json_encode(['seed' => 42])));
        $runId = $createResponse->body['run_id'];
        $this->chooseFirstOfferedHero($controller, $createResponse->body);

        $request = Request::fake(rawBody: json_encode(['slotIndex' => 0]));
        $response = $controller->buyItem(['runId' => $runId], $request);

        self::assertSame(200, $response->statusCode);
        self::assertTrue($response->body['state']['shop']['offers'][0]['purchased']);

        $actions = $actionsRepository->findAllForRun($runId);
        self::assertCount(2, $actions);
        self::assertSame(GameRunActionType::CHOOSE_HERO, $actions[0]->type);
        self::assertSame(GameRunActionType::PURCHASE, $actions[1]->type);
    }

    public function testItDoesNotPersistAFailedPurchase(): void
    {
        [$controller, , $actionsRepository] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];
        $this->chooseFirstOfferedHero($controller, $createResponse->body);

        $request = Request::fake(rawBody: json_encode(['slotIndex' => 99]));

        try {
            $controller->buyItem(['runId' => $runId], $request);
            self::fail('Expected an exception for an invalid slot index.');
        } catch (\InvalidArgumentException) {
            // attendu — Shop::purchase() rejette un index hors bornes
        }

        // Le journal ne doit contenir QUE le CHOOSE_HERO initial — l'achat raté
        // n'a rien laissé derrière lui, sinon tout futur replay() serait cassé.
        $actions = $actionsRepository->findAllForRun($runId);
        self::assertCount(1, $actions);
    }

    public function testItDoesNotPersistAFailedSwap(): void
    {
        [$controller, , $actionsRepository] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];
        $this->chooseFirstOfferedHero($controller, $createResponse->body);

        // Coffre et inventaire vides après le choix du héros — n'importe quel index est invalide.
        $request = Request::fake(rawBody: json_encode([
            'inventoryIndex' => 0,
            'stashIndex' => 0,
            'heroId' => 'does-not-matter',
        ]));

        try {
            $controller->swapItem(['runId' => $runId], $request);
            self::fail('Expected an exception for a swap on an empty inventory/stash.');
        } catch (\InvalidArgumentException) {
            // attendu — Inventory::removeAt()/Stash::removeAt() rejettent un index hors bornes
        }

        $actions = $actionsRepository->findAllForRun($runId);
        self::assertCount(1, $actions); // seul le CHOOSE_HERO initial, rien de plus
    }

    public function testItResolvesARound(): void
    {
        [$controller, , $actionsRepository] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];
        $this->chooseFirstOfferedHero($controller, $createResponse->body);

        $response = $controller->resolveRound(['runId' => $runId], Request::fake());

        self::assertSame(200, $response->statusCode);
        self::assertSame(2, $response->body['state']['round']);
        self::assertSame(
            1,
            $response->body['state']['victories'] + $response->body['state']['defeats'],
        );

        $actions = $actionsRepository->findAllForRun($runId);
        self::assertCount(2, $actions);
        self::assertSame(GameRunActionType::RESOLVE_ROUND, $actions[1]->type);
    }

    public function testItResolvesARoundAndExposesTheCombatLog(): void
    {
        [$controller] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];
        $this->chooseFirstOfferedHero($controller, $createResponse->body);

        $response = $controller->resolveRound(['runId' => $runId], Request::fake());

        self::assertArrayHasKey('combatLog', $response->body);
        self::assertNotEmpty(
            $response->body['combatLog'],
            'Round 1 always assigns at least one item to the scripted opponent, so at least one event is expected.',
        );

        $firstEvent = $response->body['combatLog'][0];
        self::assertArrayHasKey('tick', $firstEvent);
        self::assertArrayHasKey('type', $firstEvent);
        self::assertArrayHasKey('payload', $firstEvent);
        self::assertIsInt($firstEvent['tick']);
        self::assertIsString($firstEvent['type']);
        self::assertIsArray($firstEvent['payload']);
    }

    public function testItResolvesARoundAndExposesTheViewerSide(): void
    {
        [$controller] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];
        $this->chooseFirstOfferedHero($controller, $createResponse->body);

        $response = $controller->resolveRound(['runId' => $runId], Request::fake());

        // Sans ce champ, le client ne peut plus écrire « ton Vestige » : les
        // libellés A/B ne le disent plus. Supposer « A, c'est moi » était exact
        // jusqu'à l'attribution canonique — et faux depuis, en silence. C'est
        // précisément ce que ce champ, posé trois commits plus tôt, évite :
        // aucune ligne de frontend n'a bougé le jour où la valeur a changé.
        //
        // 'B' est une caractérisation dépendante du catalogue, comme dans
        // GameRunTest.
        self::assertArrayHasKey('viewerSide', $response->body);
        self::assertSame('B', $response->body['viewerSide']);
    }

    public function testShowDoesNotExposeAViewerSide(): void
    {
        [$controller] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];

        $response = $controller->show(['runId' => $runId]);

        self::assertArrayNotHasKey('viewerSide', $response->body);
    }

    public function testShowDoesNotExposeACombatLog(): void
    {
        [$controller] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];

        $response = $controller->show(['runId' => $runId]);

        self::assertArrayNotHasKey('combatLog', $response->body);
    }

    public function testItResolvesARoundAndExposesTheOpponentBoard(): void
    {
        [$controller] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];
        $this->chooseFirstOfferedHero($controller, $createResponse->body);

        $response = $controller->resolveRound(['runId' => $runId], Request::fake());

        self::assertArrayHasKey('opponentRoster', $response->body);
        self::assertArrayHasKey('opponentInventory', $response->body);

        self::assertNotEmpty($response->body['opponentRoster']);
        $firstHero = $response->body['opponentRoster'][0];
        self::assertArrayHasKey('id', $firstHero);
        self::assertArrayHasKey('name', $firstHero);

        self::assertArrayHasKey('items', $response->body['opponentInventory']);
        self::assertNotEmpty(
            $response->body['opponentInventory']['items'],
            'Round 1 always assigns at least one item to the scripted opponent, so at least one assignment is expected.',
        );

        $firstAssignment = $response->body['opponentInventory']['items'][0];
        self::assertArrayHasKey('item', $firstAssignment);
        self::assertArrayHasKey('heroId', $firstAssignment);
        self::assertArrayHasKey('id', $firstAssignment['item']);
        self::assertArrayHasKey('name', $firstAssignment['item']);
    }

    public function testShowDoesNotExposeOpponentBoardData(): void
    {
        [$controller] = $this->createController();

        $createResponse = $controller->create([], Request::fake());
        $runId = $createResponse->body['run_id'];

        $response = $controller->show(['runId' => $runId]);

        self::assertArrayNotHasKey('opponentRoster', $response->body);
        self::assertArrayNotHasKey('opponentInventory', $response->body);
    }
    // === Graine de run — E-13, chantier 2 ================================
    //
    // Avant ce commit, la seed etait inatteignable depuis l'API : POST /runs
    // n'a aucun placeholder donc $params restait vide, Request::fromGlobals()
    // coupe la chaine de requete sans la conserver, et la closure de route ne
    // transmettait pas $request. En production, create() retombait donc
    // toujours sur random_int().
    //
    // Le corps JSON devient la SEULE source. La lecture de $params['seed'] est
    // retiree : conserver deux canaux pour la meme valeur est precisement
    // l'ambiguite qui a rendu E-13 invisible.
    //
    // Le code HTTP 400 n'est pas teste ici. Le controleur leve, le Router
    // mappe — et RouterTest::testItMapsInvalidArgumentExceptionTo400 couvre
    // deja ce mapping. Le dupliquer ferait croire a une garde propre au
    // controleur.

    public function testItAcceptsAnIntegerSeedFromTheRequestBody(): void
    {
        [$controller, $runRepository] = $this->createController();

        $response = $controller->create([], Request::fake(rawBody: json_encode(['seed' => 4242])));

        $record = $runRepository->find($response->body['run_id']);
        self::assertNotNull($record);
        self::assertSame(4242, $record->seed);
    }

    /**
     * La propriete qui donne son interet a toute la fonctionnalite : une seed
     * connue rend la run reproductible de bout en bout. C'est ce qui manquait
     * pour un test a travers le routeur, et pour reproduire un rapport de bug.
     */
    public function testTwoRunsWithTheSameSeedProduceTheSameInitialHeroOffer(): void
    {
        [$controller] = $this->createController();

        $first = $controller->create([], Request::fake(rawBody: json_encode(['seed' => 4242])));
        $second = $controller->create([], Request::fake(rawBody: json_encode(['seed' => 4242])));

        $heroIds = static fn (array $body): array => array_map(
            static fn (array $hero): string => $hero['id'],
            $body['state']['pendingHeroOffer'],
        );

        self::assertSame($heroIds($first->body), $heroIds($second->body));
    }

    public function testTwoRunsWithoutASeedGetDifferentSeeds(): void
    {
        [$controller, $runRepository] = $this->createController();

        $first = $controller->create([], Request::fake());
        $second = $controller->create([], Request::fake());

        // random_int(0, PHP_INT_MAX) : une collision reste possible, avec une
        // probabilite de l'ordre de 1e-19. Ce test n'est pas flaky en pratique.
        self::assertNotSame(
            $runRepository->find($first->body['run_id'])->seed,
            $runRepository->find($second->body['run_id'])->seed,
        );
    }

    #[DataProvider('nonIntegerSeeds')]
    public function testItRejectsASeedThatIsNotAnInteger(mixed $seed): void
    {
        [$controller] = $this->createController();

        // Rejet strict plutot que cast silencieux : (int) 'abc' vaut 0, et
        // produirait une run parfaitement deterministe sur la mauvaise graine.
        // Un repli silencieux sur l'aleatoire serait pire encore — le client
        // croirait sa run reproductible sans qu'elle le soit.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('seed');

        $controller->create([], Request::fake(rawBody: json_encode(['seed' => $seed])));
    }

    /**
     * JSON distingue 42 de "42". Le client est le notre, et la tolerance ici
     * recreerait du flou la ou ce commit existe pour en retirer.
     *
     * @return array<string, array{mixed}>
     */
    public static function nonIntegerSeeds(): array
    {
        return [
            'chaine numerique' => ['42'],
            'chaine non numerique' => ['abc'],
            'flottant' => [12.5],
            'booleen' => [true],
            'tableau' => [[1, 2]],
        ];
    }

    /**
     * {"seed": null} est l'encodage naturel de « pas de graine » chez un
     * client type. On retombe sur l'aleatoire plutot que de refuser.
     *
     * C'est ce que isset() fait deja, mais par effet de bord : ce test fige le
     * choix pour qu'un passage ulterieur a array_key_exists() ne le renverse
     * pas sans que rien ne le signale.
     */
    public function testANullSeedIsTreatedAsAbsentRatherThanInvalid(): void
    {
        [$controller, $runRepository] = $this->createController();

        $response = $controller->create([], Request::fake(rawBody: json_encode(['seed' => null])));

        $record = $runRepository->find($response->body['run_id']);
        self::assertNotNull($record);
        self::assertIsInt($record->seed);
    }

    public function testItFallsBackToARandomSeedWhenTheBodyCarriesNoSeedKey(): void
    {
        [$controller, $runRepository] = $this->createController();

        $response = $controller->create([], Request::fake(rawBody: json_encode(['autreChose' => 1])));

        self::assertNotNull($runRepository->find($response->body['run_id']));
    }

    public function testItFallsBackToARandomSeedWhenThereIsNoBodyAtAll(): void
    {
        [$controller, $runRepository] = $this->createController();

        $response = $controller->create([], Request::fake());

        self::assertNotNull($runRepository->find($response->body['run_id']));
    }
}
