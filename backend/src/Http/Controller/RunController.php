<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Event\CombatEvent;
use App\Domain\Model\Hero;
use App\Http\ApiResponse;
use App\Http\Request;
use App\Infrastructure\Content\ContentCatalogReader;
use App\Persistence\GameRunActionApplier;
use App\Persistence\GameRunActionsRepository;
use App\Persistence\GameRunActionType;
use App\Persistence\GameRunReplayer;
use App\Persistence\GameRunRepository;
use App\Presentation\CombatEventPresenter;
use App\Presentation\HeroPresenter;
use App\Presentation\OpponentInventoryPresenter;
use App\Presentation\RunStatePresenter;

final class RunController
{
    private const string VESTIGE_ID = 'shadow_vestige';

    public function __construct(
        private readonly GameRunRepository $runRepository,
        private readonly GameRunActionsRepository $actionsRepository,
        private readonly GameRunReplayer $replayer,
        private readonly ContentCatalogReader $contentCatalogReader,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function create(array $params, Request $request): ApiResponse
    {
        $runId = bin2hex(random_bytes(16));
        $seed = $this->resolveSeed($request);

        // L'empreinte du contenu est épinglée ici, et nulle part ailleurs
        // (`04` §6.3). C'est le seul instant où l'on sait de source sûre sous
        // quel catalogue la run commence ; après, il faudrait le deviner. Le
        // lecteur gèle sa valeur au premier appel, donc le rejeu qui suit deux
        // lignes plus bas compare bien à la même.
        $this->runRepository->create($runId, $seed, self::VESTIGE_ID, $this->contentCatalogReader->version());

        // Aucune action n'est journalisée ici : le run naît avec une offre de
        // héros en attente (voir GameRun::__construct()), pas de boutique.
        // C'est CHOOSE_HERO qui ouvrira la boutique, comme pour les manches 3/5.
        $gameRun = $this->replayer->replay($runId);

        return ApiResponse::json([
            'run_id' => $runId,
            'state' => RunStatePresenter::toArray($gameRun),
        ], 201);
    }

    /**
     * Graine de la run, lue dans le corps JSON (E-13, chantier 2).
     *
     * Le corps est la **seule** source. La lecture de `$params['seed']` a été
     * retirée : elle n'était atteignable par aucun client — `POST /runs` n'a
     * aucun placeholder, donc `$params` est toujours vide — et conserver deux
     * canaux pour la même valeur est précisément l'ambiguïté qui a rendu E-13
     * invisible. `Request::fromGlobals()` coupant par ailleurs la chaîne de
     * requête sans la conserver, `?seed=42` n'a jamais fonctionné non plus.
     *
     * **Rejet strict plutôt que cast silencieux.** `(int) 'abc'` vaut 0 et
     * produirait une run parfaitement déterministe sur la mauvaise graine ;
     * un repli silencieux sur l'aléatoire serait pire encore, le client
     * croyant sa run reproductible sans qu'elle le soit. JSON distingue 42 de
     * "42", le client est le nôtre, et la tolérance ici recréerait du flou là
     * où ce commit existe pour en retirer.
     *
     * `{"seed": null}` est en revanche traité comme une **absence** : c'est
     * l'encodage naturel de « pas de graine » chez un client typé.
     *
     * @throws \InvalidArgumentException mappée en 400 par le Router
     */
    private function resolveSeed(Request $request): int
    {
        $seed = ($request->json() ?? [])['seed'] ?? null;

        if ($seed === null) {
            return random_int(0, PHP_INT_MAX);
        }

        if (!is_int($seed)) {
            throw new \InvalidArgumentException(sprintf(
                'The "seed" field must be an integer, %s given. A run seed makes the whole '
                . 'run reproducible, so it is rejected rather than coerced: a silent cast '
                . 'would produce a perfectly deterministic run on the wrong seed.',
                get_debug_type($seed),
            ));
        }

        return $seed;
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): ApiResponse
    {
        $gameRun = $this->replayer->replay($params['runId']);

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function chooseHero(array $params, Request $request): ApiResponse
    {
        $runId = $params['runId'];
        $payload = $request->json() ?? [];

        $gameRun = $this->replayer->replay($runId);

        (new GameRunActionApplier())->apply($gameRun, GameRunActionType::CHOOSE_HERO, $payload);

        $sequence = $this->actionsRepository->countForRun($runId) + 1;
        $this->actionsRepository->append($runId, $sequence, GameRunActionType::CHOOSE_HERO, $payload);

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function buyItem(array $params, Request $request): ApiResponse
    {
        $runId = $params['runId'];
        $payload = $request->json() ?? [];

        $gameRun = $this->replayer->replay($runId);

        (new GameRunActionApplier())->apply($gameRun, GameRunActionType::PURCHASE, $payload);

        $sequence = $this->actionsRepository->countForRun($runId) + 1;
        $this->actionsRepository->append($runId, $sequence, GameRunActionType::PURCHASE, $payload);

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function swapItem(array $params, Request $request): ApiResponse
    {
        $runId = $params['runId'];
        $payload = $request->json() ?? [];

        $gameRun = $this->replayer->replay($runId);

        (new GameRunActionApplier())->apply($gameRun, GameRunActionType::SWAP, $payload);

        $sequence = $this->actionsRepository->countForRun($runId) + 1;
        $this->actionsRepository->append($runId, $sequence, GameRunActionType::SWAP, $payload);

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
        ]);
    }

    /**
     * Le `Request` est accepté pour respecter le contrat de handler du Router,
     * mais il n'est **jamais lu** : la charge utile journalisée est `[]` en dur.
     *
     * C'est ce qui satisfait déjà, par construction, la garde de D-18 volet 1 —
     * « l'issue d'un combat journalisée ne vient jamais du client ». Le risque
     * n'est pas d'avoir à filtrer quelque chose aujourd'hui, c'est qu'au commit
     * de l'issue enregistrée quelqu'un câble `$request->json()` ici parce que
     * le paramètre est là et qu'il ne sert à rien.
     *
     * @param array<string, string> $params
     */
    public function resolveRound(array $params, Request $request): ApiResponse
    {
        $runId = $params['runId'];

        $gameRun = $this->replayer->replay($runId);

        (new GameRunActionApplier())->apply($gameRun, GameRunActionType::RESOLVE_ROUND, []);

        $sequence = $this->actionsRepository->countForRun($runId) + 1;
        $this->actionsRepository->append($runId, $sequence, GameRunActionType::RESOLVE_ROUND, []);

        $combatResult = $gameRun->getLastCombatResult();
        $opponentRoster = $gameRun->getLastOpponentRoster();
        $opponentAssignments = $gameRun->getLastOpponentAssignments();

        return ApiResponse::json([
            'state' => RunStatePresenter::toArray($gameRun),
            'combatLog' => $combatResult !== null
                ? array_map(
                    static fn (CombatEvent $event): array => CombatEventPresenter::toArray($event),
                    $combatResult->log->getEvents(),
                )
                : [],
            'opponentRoster' => $opponentRoster !== null
                ? array_map(
                    static fn (Hero $hero): array => HeroPresenter::toArray($hero),
                    $opponentRoster,
                )
                : [],
            'opponentInventory' => OpponentInventoryPresenter::toArray($opponentAssignments ?? []),

            // Côté occupé par ce joueur dans le combat qui vient d'être résolu
            // (D-19). Le journal ne le dit plus : il parle en A et B, pour que
            // les deux joueurs d'un futur PvP puissent lire le même. Sans ce
            // champ, le client ne peut plus écrire « ton Vestige ».
            //
            // La valeur vient de GameRun, qui la tient du moteur. Écrire 'A'
            // ici serait exact tant que l'attribution est positionnelle, et
            // faux dès qu'elle deviendra canonique — sans un test pour le dire.
            'viewerSide' => $gameRun->getLastPlayerSide()?->value,
        ]);
    }
}
