<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Enum\StatusType;

/**
 * Un Vestige engagé dans un combat : son profil de départ, et l'état qui dérive
 * de lui.
 *
 * **Il ne retient qu'un profil.** Trois valeurs entrent dans un calcul de
 * combat — identifiant, PV et bouclier de départ — et ce sont exactement celles
 * que porte la photographie (D-16, `04` §5.5). Le nom, l'affinité, l'or de
 * départ et le revenu appartiennent au catalogue ; les exiger ici rendrait un
 * plateau archivé impossible à rejouer sans les inventer.
 */
final class CombatVestige
{
    private int $currentHp;
    private int $currentShield;

    /** @var array<string, list<ActiveStatus>> */
    private array $statuses = [];

    public function __construct(
        private readonly VestigeProfile $profile,
    ) {
        $this->currentHp = $this->profile->getBaseHp();
        $this->currentShield = $this->profile->getBaseShield();
    }

    public function getId(): string
    {
        return $this->profile->getId();
    }

    /**
     * Le profil de combat du Vestige — identifiant, PV et bouclier de départ.
     *
     * **Pourquoi il est exposé.** `getHp()` et `getShield()` rendent l'état
     * **courant** ; la photographie de plateau (D-16) a besoin des valeurs de
     * départ. Et pas seulement au lancement : `SimulationContext::getSide()`
     * compare des photographies à chaque tick, donc une photographie bâtie sur
     * l'état courant ferait basculer l'attribution des côtés en plein combat.
     *
     * **Un profil, plus une définition de catalogue.** Ce qui est rendu ici
     * n'est plus forcément un `Vestige` : c'est le contrat minimal que le
     * combat consomme, et qu'un plateau hydraté depuis son archive peut honorer
     * sans inventer les quatre champs que la photographie ne porte pas.
     *
     * `CombatHero` expose déjà le sien à l'identique.
     */
    public function getProfile(): VestigeProfile
    {
        return $this->profile;
    }

    public function getHp(): int
    {
        return $this->currentHp;
    }

    public function getShield(): int
    {
        return $this->currentShield;
    }

    public function isAlive(): bool
    {
        return $this->currentHp > 0;
    }

    public function takeDamage(int $damage): void
    {
        $effectiveDamage = max(0, $damage);

        $shieldDamage = min($this->currentShield, $effectiveDamage);
        $this->currentShield -= $shieldDamage;

        $remainingDamage = $effectiveDamage - $shieldDamage;
        $hpDamage = min($this->currentHp, $remainingDamage);
        $this->currentHp -= $hpDamage;
    }

    public function receiveHeal(int $heal): void
    {
        $effectiveHeal = max(0, $heal);
        $this->currentHp = min($this->profile->getBaseHp(), $this->currentHp + $effectiveHeal);
    }

    public function gainShield(int $shield): void
    {
        $effectiveShield = max(0, $shield);
        $this->currentShield += $effectiveShield;
    }

    /**
     * Chaque application crée une instance indépendante (D-20).
     * Aucune fusion : deux applications du même type coexistent.
     */
    public function applyStatus(ActiveStatus $status): void
    {
        $this->statuses[$status->getType()->value][] = $status;
    }

    /**
     * @return list<ActiveStatus>
     */
    public function getStatusInstances(StatusType $type): array
    {
        return $this->statuses[$type->value] ?? [];
    }

    /**
     * Types ayant au moins une instance, dans l'ordre de première application.
     *
     * L'ordre canonique des clés relève de D-19, chantier 2 : ne pas le figer ici.
     *
     * @return list<StatusType>
     */
    public function getStatusTypes(): array
    {
        return array_map(
            static fn (string $key): StatusType => StatusType::from($key),
            array_keys($this->statuses)
        );
    }

    /**
     * Somme des stacks vivants et maximum des durées restantes, lus sur le
     * même état de la liste. C'est la seule projection exposée aux CombatEvent.
     */
    public function getAggregatedStatus(StatusType $type): AggregatedStatus
    {
        $stacks = 0;
        $remainingTicks = 0;

        foreach ($this->statuses[$type->value] ?? [] as $instance) {
            $stacks += $instance->getStacks();
            $remainingTicks = max($remainingTicks, $instance->getRemainingTicks());
        }

        return new AggregatedStatus($stacks, $remainingTicks);
    }

    /**
     * Nettoyage par le soin (D-21). Retire 1 stack de chaque statut hostile
     * présent, sur l'instance à la plus longue durée restante.
     *
     * Trois des quatre règles de détail de D-21 vivent ici :
     *  - l'égalité de durée est départagée par l'ordre d'insertion, ce que
     *    garantit la comparaison stricte de la boucle ;
     *  - une instance vidée de ses stacks est retirée même si son compteur de
     *    ticks n'est pas à zéro ;
     *  - `REGEN` et `WARD` ne sont jamais touchés, par `StatusType::isHostile()`.
     *
     * La quatrième, le calcul sur le soin tenté, appartient à l'appelant :
     * cette méthode ne sait rien du soin.
     *
     * @return array<string, int> stacks retirés par type ; un type absent ou
     *                            bénéfique ne figure pas dans le tableau
     */
    public function cleanseHostileStatuses(): array
    {
        $cleansed = [];

        foreach ($this->getStatusTypes() as $type) {
            if (!$type->isHostile()) {
                continue;
            }

            $instances = $this->getStatusInstances($type);
            if ($instances === []) {
                continue;
            }

            $target = $instances[0];
            foreach ($instances as $instance) {
                // Strictement supérieur : à durée égale, la première insérée gagne.
                if ($instance->getRemainingTicks() > $target->getRemainingTicks()) {
                    $target = $instance;
                }
            }

            $target->removeStack();
            $cleansed[$type->value] = 1;

            if ($target->getStacks() > 0) {
                continue;
            }

            $alive = array_values(array_filter(
                $instances,
                static fn (ActiveStatus $instance): bool => $instance !== $target
            ));

            if ($alive === []) {
                unset($this->statuses[$type->value]);

                continue;
            }

            $this->statuses[$type->value] = $alive;
        }

        return $cleansed;
    }

    public function removeExpiredStatuses(): void
    {
        foreach ($this->statuses as $key => $instances) {
            $alive = array_values(array_filter(
                $instances,
                static fn (ActiveStatus $status): bool => !$status->isExpired()
            ));

            if ($alive === []) {
                unset($this->statuses[$key]);

                continue;
            }

            $this->statuses[$key] = $alive;
        }
    }

    /**
     * Brûlure : brise-défense (02 §7.4, tranché le 13/09/2026).
     *
     * La valeur majorée à 150 % frappe le bouclier ; le surplus repasse à
     * 100 % puis est atténué à 70 %, soit x7/15, avant de toucher les PV.
     *
     * Arithmétique entière exclusivement, aucun flottant : les deux divisions
     * arrondissent au plancher, donc en faveur du défenseur. Un flottant ici
     * casserait la parité serveur / moteur embarqué exigée par EX-J0-01.
     *
     * Renvoie la valeur majorée plutôt que rien, pour que l'appelant puisse la
     * journaliser sans réimplémenter la formule de son côté.
     *
     * @return int la valeur majorée, avant absorption
     */
    public function takeBurnDamage(int $stacks): int
    {
        $boosted = intdiv(max(0, $stacks) * 3, 2);

        $absorbed = min($this->currentShield, $boosted);
        $this->currentShield -= $absorbed;

        $leftover = $boosted - $absorbed;
        $hpDamage = min($this->currentHp, intdiv($leftover * 7, 15));
        $this->currentHp -= $hpDamage;

        return $boosted;
    }

    public function takeRawDamage(int $damage): void
    {
        $effectiveDamage = max(0, $damage);
        $hpDamage = min($this->currentHp, $effectiveDamage);
        $this->currentHp -= $hpDamage;
    }
}
