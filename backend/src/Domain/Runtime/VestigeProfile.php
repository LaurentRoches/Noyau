<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

/**
 * Ce qu'un combat lit d'un Vestige — et rien de plus (D-16, `04` §5.5).
 *
 * **Le port appartient au consommateur.** Cette interface est déclarée dans
 * `Domain\Runtime` et non auprès de `Vestige`, parce que c'est le combat qui
 * énonce son besoin : trois valeurs. `Vestige` s'y conforme, il ne la définit
 * pas. Rangée dans `Domain\Model`, elle aurait fait du catalogue l'auteur du
 * contrat, et le combat serait resté son débiteur — l'inversion serait restée
 * nominale.
 *
 * **Trois accesseurs, exactement ceux que la photographie porte.** `id`,
 * `baseHp`, `baseShield` : c'est la table de `04` §5.5, et c'est aussi ce que
 * `BoardSnapshot::vestige()` écrit. Le nom, l'affinité, l'or de départ et le
 * revenu n'entrent dans aucun calcul de combat et le client les relit du
 * catalogue ; les exiger ici obligerait à les inventer au rejeu, pour des
 * champs qu'aucune archive ne contient et n'a de raison de contenir.
 *
 * **Ce que cela ouvre.** Tant que `CombatVestige` réclamait un `Vestige`,
 * rejouer un plateau archivé imposait de fabriquer une entrée de catalogue
 * entière à partir de trois nombres — donc d'inventer des valeurs que la
 * photographie n'a jamais vues. L'hydrateur du commit suivant n'aura qu'à
 * implémenter ces trois méthodes.
 */
interface VestigeProfile
{
    public function getId(): string;

    public function getBaseHp(): int;

    public function getBaseShield(): int;
}
