<?php

declare(strict_types=1);

namespace App\Application;

/**
 * L'issue d'une manche, du point de vue du joueur (D-18 volet 1, `04` §6.2).
 *
 * **Pourquoi cette valeur est enregistrée.** Le journal de run ne stocke pas
 * d'états, il stocke des décisions, et `RESOLVE_ROUND` n'en portait aucune :
 * chaque rejeu **resimulait** les combats passés avec le moteur courant. Dès
 * que le moteur change — et le chantier 2 l'a changé deux fois — une manche
 * gagnée peut revenir perdue, le compteur de victoires diverge, les offres de
 * héros des manches 3 et 5 apparaissent ou disparaissent, et une action
 * `CHOOSE_HERO` journalisée peut lever au rejeu (`07` E-11).
 *
 * **Pourquoi le point de vue du joueur, et non « qui a gagné ».** Le côté
 * vainqueur (`A` ou `B`) ne veut rien dire sans le côté du joueur, et les deux
 * ensemble demanderaient au rejeu de refaire une comparaison que le serveur a
 * déjà faite. Une seconde dérivation du vainqueur est un second endroit où
 * elle peut se tromper. Ce que l'état de la run consomme, c'est une victoire
 * ou une défaite — rien d'autre.
 *
 * **Pourquoi un enum et non un booléen.** `applyRecordedRound(true)` est
 * illisible sur un site d'appel (`06` §3), et `{"playerWon":true}` dans le
 * journal demande de savoir qui est « player ». `{"outcome":"VICTORY"}` se lit
 * seul, et `tryFrom()` rejette toute autre valeur sans qu'aucune validation
 * soit écrite à la main.
 *
 * **Ce que cet enum ne porte pas, délibérément.** Ni `winnerSide`, ni
 * `resolution`, ni `combatSeed`. Ces données décrivent le **combat** et
 * appartiennent à l'enregistrement de combat ; le journal d'actions ne porte
 * que ce dont le rejeu a besoin. Une donnée présente mais inutilisée finit
 * toujours par être utilisée.
 */
enum RoundOutcome: string
{
    case VICTORY = 'VICTORY';
    case DEFEAT = 'DEFEAT';
}
