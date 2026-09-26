<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Application\Factory\GameRunFactory;
use App\Application\GameRun;
use App\Infrastructure\Content\ContentCatalogReader;

final class GameRunReplayer
{
    public function __construct(
        private readonly GameRunRepository $runRepository,
        private readonly GameRunActionsRepository $actionsRepository,
        private readonly string $configPath,
        private readonly ContentCatalogReader $contentCatalogReader,
    ) {
    }

    /**
     * Reconstruit une run depuis son journal d'actions.
     *
     * **La porte de version de contenu vit ici, et nulle part ailleurs**
     * (`04` §6.3, D-18). `replay()` est l'entonnoir unique : les six points
     * d'entrée de `RunController` y passent tous. Répartir le contrôle sur six
     * méthodes, ce serait six occasions d'en oublier une, et celle qu'on
     * oublierait servirait un état faux sans rien signaler.
     *
     * **Le contrôle précède la reconstruction**, avant même de savoir s'il y a
     * des actions à rejouer. Une run sans action a déjà un état — offre de
     * héros initiale, bourse — reconstruit depuis les catalogues. Une porte qui
     * ne se déclencherait qu'à la première action laisserait passer les
     * `GET /runs/{id}` et afficherait cet état sous le mauvais contenu.
     *
     * **Pourquoi refuser plutôt que migrer.** Le journal stocke des décisions,
     * pas des états : « acheté l'offre 0 » ne dit ni quel objet ni à quel prix.
     * Rejouer contre un catalogue modifié rend une autre partie que celle qui a
     * été jouée, sans erreur et sans trace. Aucune correction automatique n'est
     * possible — l'information manquante n'a jamais été écrite.
     *
     * @throws RunNotFoundException              si l'identifiant est inconnu
     * @throws ContentVersionMismatchException   si la run a été créée sous un autre contenu
     */
    public function replay(string $runId): GameRun
    {
        $record = $this->runRepository->find($runId)
            ?? throw new RunNotFoundException($runId);

        $currentContentVersion = $this->contentCatalogReader->version();

        if ($record->contentVersion !== $currentContentVersion) {
            throw new ContentVersionMismatchException(
                $runId,
                $record->contentVersion,
                $currentContentVersion,
            );
        }

        // L'empreinte vient de l'enregistrement de run, pas du lecteur. Elle
        // vaut la même chose — la porte ci-dessus vient de le vérifier — mais
        // c'est la sienne : les plateaux archivés d'une run portent la
        // provenance de CETTE run, pas celle du serveur au moment du rejeu.
        $gameRun = (new GameRunFactory($this->configPath))->create(
            $record->seed,
            $record->vestigeId,
            $record->contentVersion,
        );

        $applier = new GameRunActionApplier();
        foreach ($this->actionsRepository->findAllForRun($runId) as $action) {
            $applier->apply($gameRun, $action->type, $action->payload);
        }

        return $gameRun;
    }
}
