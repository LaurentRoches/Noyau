<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Model\Effect;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatItem;

/**
 * Traduit les effets d'un objet en intentions d'action (`PendingAction`).
 *
 * **Une liste plate, et non une table indexée par `Trigger`.** L'index par
 * déclencheur était une dépendance cachée à l'ordre des arguments de
 * `Simulator::run()` : les deux plateaux s'y enregistrent dans cet ordre, les
 * clés de la table se créaient donc dans l'ordre des triggers rencontrés, et
 * `dispatchForItem()` parcourant ces clés, un objet dont les effets portent
 * **deux triggers différents** voyait ses effets dépliés dans un ordre qui
 * dépendait de quel plateau s'était enregistré le premier — et de quels
 * triggers portait l'objet de l'autre plateau.
 *
 * Conséquence mesurée le 26/09/2026 : `run($a, $b)` et `run($b, $a)`
 * produisaient deux journaux différents à l'octet près, sur les mêmes plateaux
 * et la même graine. NF-01 tombait, et la correction d'attribution canonique de
 * `SimulationContext::getBoards()` — nécessaire — ne suffisait pas.
 * `07` anomalie E-15.
 *
 * **Le défaut était latent.** Les trente objets du catalogue portent exactement
 * un effet, et `HeroSkillDecorator` n'en crée pas : il les mappe un à un en
 * conservant leur `trigger`. Aucun journal ne change donc aujourd'hui, ce qui
 * est précisément la raison de corriger maintenant — relever `EngineVersion`
 * coûte zéro tant qu'aucun corpus n'existe, et tous les fantômes archivés
 * ensuite.
 *
 * **L'ordre devient celui de l'objet.** Les effets se déplient dans l'ordre où
 * l'objet les porte, donc dans l'ordre où la photographie les archive
 * (D-16, `04` §5.5). Il ne dépend plus de rien d'autre — pas même de l'ordre
 * canonique des côtés. Réordonner l'enregistrement aurait aussi refermé le cas
 * connu ; supprimer la dépendance referme la classe entière.
 *
 * **Le `Trigger` n'est pas perdu**, il reste porté par l'`Effect`. Il n'a
 * simplement jamais servi ici : `dispatchForItem()` est appelé par `TickEngine`
 * quand le cooldown d'un objet atteint zéro, et le déclencheur déclaré n'a
 * aucun effet sur la cadence. Dette consignée en `04` §3.4, traitée au
 * chantier 3.
 */
final class EventDispatcher
{
    /**
     * @var list<array{sourceBoard: CombatBoard, sourceItem: CombatItem, effect: Effect}>
     */
    private array $listeners = [];

    public function registerBoard(CombatBoard $board): void
    {
        foreach ($board->getItems() as $item) {
            foreach ($item->getEffects() as $effect) {
                $this->register($board, $item, $effect);
            }
        }
    }

    /**
     * Seul point de déclenchement du moteur : les effets d'un objet précis,
     * appelé par `TickEngine` quand le cooldown de cet objet atteint zéro.
     *
     * @return list<PendingAction>
     */
    public function dispatchForItem(CombatBoard $sourceBoard, CombatItem $sourceItem): array
    {
        $matchingListeners = [];

        foreach ($this->listeners as $listener) {
            if ($listener['sourceItem'] === $sourceItem && $listener['sourceBoard'] === $sourceBoard) {
                $matchingListeners[] = $listener;
            }
        }

        return $this->toPendingActions($matchingListeners);
    }

    private function register(
        CombatBoard $sourceBoard,
        CombatItem $sourceItem,
        Effect $effect
    ): void {
        $this->listeners[] = [
            'sourceBoard' => $sourceBoard,
            'sourceItem' => $sourceItem,
            'effect' => $effect,
        ];
    }

    /**
     * Déplie chaque effet des listeners reçus en une liste d'intentions individuelles (PendingAction).
     *
     * @param list<array{sourceBoard: CombatBoard, sourceItem: CombatItem, effect: Effect}> $listeners
     *
     * @return list<PendingAction>
     */
    private function toPendingActions(array $listeners): array
    {
        $pendingActions = [];

        foreach ($listeners as $listener) {
            foreach ($listener['effect']->actions as $action) {
                $pendingActions[] = new PendingAction(
                    action: $action,
                    sourceItem: $listener['sourceItem'],
                    sourceBoard: $listener['sourceBoard']
                );
            }
        }

        return $pendingActions;
    }
}
