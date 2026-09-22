<?php

declare(strict_types=1);

namespace App\Persistence;

/**
 * Une ligne de `runs`, telle qu'elle est stockée.
 *
 * `$contentVersion` est l'empreinte des catalogues sous lesquels la run a été
 * créée (`04` §6.3). Elle n'est pas une donnée de jeu : elle ne décide de rien
 * pendant la partie, elle dit seulement à quel contenu le journal d'actions
 * renvoie. Sans elle, une run rejouée après un rééquilibrage produirait
 * silencieusement une autre partie que celle qui a été jouée.
 */
final readonly class GameRunRecord
{
    public function __construct(
        public string $id,
        public int $seed,
        public string $vestigeId,
        public string $contentVersion,
    ) {
    }
}
