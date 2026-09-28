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
 * **L'attribution est canonique** depuis le 21/09/2026 : A est le plateau dont
 * la photographie canonique est la plus petite en octets, et l'ordre des
 * arguments de `Simulator::run()` ne tranche qu'une égalité stricte (`04`
 * §3.6). Elle est calculée une seule fois, par `SimulationContext`. Ce
 * fichier-ci n'en a pas été affecté, comme prévu.
 *
 * *(Corrigé le 27/09/2026 : ce docblock la disait encore « aujourd'hui
 * positionnelle », six jours après qu'elle eut cessé de l'être. L'erreur a
 * failli entrer dans le contrat du moteur embarqué, `07` §2.)*
 */
enum Side: string
{
    case A = 'A';
    case B = 'B';
}
