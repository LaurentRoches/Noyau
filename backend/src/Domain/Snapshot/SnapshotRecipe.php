<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

/**
 * La recette d'un plateau : de quoi il a été assemblé (D-16, `04` §5.5).
 *
 * **Provenance seulement, aucun rôle au rejeu.** Ce sont les `Item` déjà
 * décorés de la photographie qui font foi ; la recette sert à savoir d'où
 * vient un fantôme, pas à le rejouer. C'est elle qui rend traçable un
 * déséquilibre repéré après coup.
 *
 * **Elle ne peut pas être dérivée du plateau.** L'association héros ↔ objet
 * est perdue dans la liste plate de `CombatBoard` : seul l'appelant qui a
 * assemblé le plateau la connaît. C'est d'ailleurs le constat qui avait fait
 * conclure à tort, en `07` révision 2.0, que « la recette est la seule forme
 * viable » — le constat était juste, la conclusion non, la décoration
 * précédant la construction.
 */
final readonly class SnapshotRecipe
{
    /**
     * @param list<string> $heroIds dans l'ordre du roster
     * @param array<string, list<string>> $itemIdsByHero identifiants d'objets par héros
     */
    public function __construct(
        public string $vestigeId,
        public array $heroIds,
        public array $itemIdsByHero,
    ) {
    }

    public function itemCount(): int
    {
        $total = 0;

        foreach ($this->itemIdsByHero as $itemIds) {
            $total += count($itemIds);
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vestigeId' => $this->vestigeId,
            'heroIds' => $this->heroIds,
            'itemIdsByHero' => $this->itemIdsByHero,
        ];
    }
}
