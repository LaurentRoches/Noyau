<?php

declare(strict_types=1);

namespace App\Domain\Engine;

/**
 * Version du moteur de combat, embarquée dans chaque snapshot (`04` §5.3).
 *
 * **Pourquoi elle existe dès maintenant.** « Champ `engineVersion` dans chaque
 * snapshot, dès le premier commit PvP. L'ajouter après coup invalide le corpus
 * déjà produit. » Aucun corpus n'existe encore, et c'est précisément le moment
 * de la poser.
 *
 * **Ce qu'elle décide.** `04` §6 : le `CombatLog` d'un combat archivé n'est
 * resimulé que si l'`engineVersion` enregistrée est identique à celle du
 * moteur courant ; sinon l'interface affiche l'issue enregistrée sans le
 * détail. Un fantôme rejoué sous des règles nouvelles combattrait avec ses
 * chiffres d'origine mais un déroulé différent — c'est la première des deux
 * bornes de D-16, et cette version est ce qui l'empêche de passer inaperçue.
 *
 * **Règle d'incrément.** Relever ce nombre dès qu'un changement **peut
 * modifier un `CombatLog`**. En pratique, avant le chantier 11 : tout
 * changement de code sous `Domain/Engine/`, commentaires et docblocks exclus.
 * À partir du chantier 11, la fixture de référence de parité est l'arbitre —
 * si elle bouge, ce nombre bouge.
 *
 * **Pourquoi pas « tout commit sous `Domain/Engine/` », sans exception.**
 * Parce que l'incrément a un coût : il prive de leur déroulé détaillé tous les
 * fantômes déjà archivés. Le payer pour un docblock corrigé serait une perte
 * sèche. La règle de chemin reste le garde-fou de CI — elle est vérifiable
 * mécaniquement, contrairement à « peut modifier un `CombatLog` ».
 *
 * **Distincte de la version de format** (`BoardRecord::FORMAT_VERSION`) :
 * l'une dit avec quelles règles le journal a été produit, l'autre comment lire
 * l'enveloppe. Dès le chantier 4, `Item`, `Effect` et `Action` changent de
 * forme sans que le moteur bouge.
 */
final class EngineVersion
{
    public const int CURRENT = 1;
}
