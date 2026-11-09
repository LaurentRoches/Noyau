<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

final readonly class CombatBoard
{
    /**
     * Les objets du plateau, déduits de ses héros (D-28, `04` §5.5).
     *
     * @var list<CombatItem>
     */
    private array $items;

    /**
     * **Le plateau ne reçoit plus d'objets, il les lit sur ses héros** (D-28).
     * Chaque `CombatHero` porte les siens, si bien qu'aucun objet ne peut être
     * sans héros. La liste plate est calculée une fois, ici : le roster
     * d'abord, l'ordre d'affectation ensuite. C'est exactement l'ordre que
     * recevait l'ancien paramètre `$items`, donc celui de l'enregistrement
     * auprès du dispatcher et de la décrémentation des cooldowns (`04` §3.3).
     *
     * @param array<CombatHero> $heroes
     */
    public function __construct(
        private CombatVestige $vestige,
        private array $heroes,
        private int $goldAtCombatStart,
    ) {
        $items = [];
        foreach ($this->heroes as $hero) {
            foreach ($hero->getItems() as $item) {
                $items[] = $item;
            }
        }
        $this->items = $items;

        if (count($this->heroes) < 1 || count($this->heroes) > 3) {
            throw new \InvalidArgumentException(sprintf(
                'A CombatBoard must have between 1 and 3 heroes, %d given.',
                count($this->heroes)
            ));
        }

        // Fail-fast plutôt que tolérance : Wallet refuse déjà un solde initial
        // négatif et spend() ne peut pas passer sous zéro, donc un or négatif
        // ici ne peut venir que d'un défaut de câblage. Le laisser entrer le
        // figerait dans une photographie que personne ne pourra plus rejouer.
        if ($this->goldAtCombatStart < 0) {
            throw new \InvalidArgumentException(sprintf(
                'A CombatBoard cannot start a combat with negative gold, %d given.',
                $this->goldAtCombatStart
            ));
        }
    }

    public function getVestige(): CombatVestige
    {
        return $this->vestige;
    }

    /**
     * @return array<CombatHero>
     */
    public function getHeroes(): array
    {
        return $this->heroes;
    }

    /**
     * Tous les objets du plateau, héros par héros dans l'ordre du roster, puis
     * dans l'ordre d'affectation. Pour savoir à qui appartient un objet,
     * passer par `getHeroes()`.
     *
     * @return list<CombatItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Solde du portefeuille au lancement du combat (D-16, `04` §5.5).
     *
     * **Pourquoi c'est un état de plateau et non une donnée passée au
     * sérialiseur.** `02` §2.3.1 classe `AURIC` parmi les deux seules
     * compétences du pool cible qui n'agissent pas par décoration : elle lit
     * l'or **pendant** le combat. Une compétence qui lit l'or au tick N le lit
     * sur le plateau — le producteur de snapshot n'est pas dans la boucle.
     *
     * *(Ce docblock a longtemps signalé que `02` §9 et `07` §6 disaient
     * l'inverse — « `AURIC` agit à l'assemblage du plateau ». **Les deux ont
     * été corrigés**, `07` le 21/09/2026 et `02` en sa révision 3.3, et ce
     * commentaire les a accusés à tort quelques jours de plus. La conclusion
     * qu'ils portaient — rien ne se gagne en or pendant le combat, donc
     * `GAIN_GOLD` reste écartée — n'a jamais bougé ; c'est la prémisse qui
     * était périmée.)*
     *
     * Aucune mécanique ne le lit aujourd'hui : le champ est posé d'avance
     * parce que le format de snapshot est irréversible (`02` §4.1).
     */
    public function getGoldAtCombatStart(): int
    {
        return $this->goldAtCombatStart;
    }

    /**
     * Retourne uniquement les objets dont le cooldown est à zéro et prêts à se déclencher.
     *
     * @return array<CombatItem>
     */
    public function getReadyItems(): array
    {
        return array_values(
            array_filter(
                $this->items,
                static fn (CombatItem $item): bool => $item->isReady()
            )
        );
    }

    /**
     * Indique de façon factuelle si le vestige du plateau est encore debout.
     */
    public function isAlive(): bool
    {
        return $this->vestige->isAlive();
    }
}
