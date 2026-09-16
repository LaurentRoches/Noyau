<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Enum\StatusType;
use App\Domain\Model\Vestige;

final class CombatVestige
{
    private int $currentHp;
    private int $currentShield;

    /** @var array<string, list<ActiveStatus>> */
    private array $statuses = [];

    public function __construct(
        private readonly Vestige $definition,
    ) {
        $this->currentHp = $this->definition->baseHp;
        $this->currentShield = $this->definition->baseShield;
    }

    public function getId(): string
    {
        return $this->definition->id;
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
        $this->currentHp = min($this->definition->baseHp, $this->currentHp + $effectiveHeal);
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
     * Toutes les instances vivantes, tous types confondus, dans l'ordre
     * d'apparition des types puis d'insertion des instances.
     *
     * @return list<ActiveStatus>
     */
    public function getStatuses(): array
    {
        $all = [];

        foreach ($this->statuses as $instances) {
            foreach ($instances as $instance) {
                $all[] = $instance;
            }
        }

        return $all;
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
