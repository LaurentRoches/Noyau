<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

final readonly class CombatBoard
{
    /**
     * `$items` a perdu sa valeur par défaut `[]` en même temps que
     * `$goldAtCombatStart` est apparu : PHP déprécie tout paramètre requis
     * déclaré après un paramètre optionnel. Ne pas la restaurer — le plateau
     * sans objet s'écrit `[]` explicitement, ce qui ne coûte rien.
     *
     * @param array<CombatHero> $heroes
     * @param array<CombatItem> $items
     */
    public function __construct(
        private CombatVestige $vestige,
        private array $heroes,
        private array $items,
        private int $goldAtCombatStart,
    ) {
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
     * @return array<CombatItem>
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
     * *(Deux lignes du corpus disent encore l'inverse — `02` §9 et `07` §6 :
     * « `AURIC` agit à l'assemblage du plateau ». C'est la justification
     * d'avant la révision 3.0 de `02`, restée en place. Sa conclusion — rien
     * ne se gagne en or pendant le combat, donc `GAIN_GOLD` reste écartée —
     * tient dans les deux lectures ; sa prémisse, non.)*
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
