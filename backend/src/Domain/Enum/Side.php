<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Côté d'un plateau dans un combat, en libellés neutres (D-19).
 *
 * `PLAYER`/`OPPONENT` a disparu pour une raison de PvP : le serveur simule
 * **une seule fois** et les deux joueurs regardent le même journal. Un côté
 * nommé « joueur » y est faux pour l'un des deux. Avec un seul Vestige au
 * catalogue, un combat miroir oppose de surcroît deux cibles portant le
 * **même identifiant** — seul le côté les distingue.
 *
 * Traduire A et B en « toi » et « ton adversaire » est le travail du client,
 * à partir du `viewerSide` que l'API lui donne. Le journal, lui, ne connaît
 * aucun spectateur — c'est ce qui le rend archivable et rejouable.
 *
 * **L'attribution est aujourd'hui positionnelle** : A est le plateau passé en
 * premier à `Simulator::run()`. Elle deviendra canonique (comparaison des
 * snapshots) au commit qui suit celui du format de snapshot, et ce fichier-ci
 * n'en sera pas affecté.
 */
enum Side: string
{
    case A = 'A';
    case B = 'B';
}
