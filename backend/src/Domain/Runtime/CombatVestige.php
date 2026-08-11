<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Enum\StatusType;
use App\Domain\Model\Vestige;

final class CombatVestige
{
    private int $currentHp;
    private int $currentShield;

    /** @var array<string, ActiveStatus> */
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

    public function applyStatus(ActiveStatus $status): void
    {
        $key = $status->getType()->value;

        if (isset($this->statuses[$key])) {
            $this->statuses[$key]->mergeWith($status);

            return;
        }

        $this->statuses[$key] = $status;
    }

    public function getStatus(StatusType $type): ?ActiveStatus
    {
        return $this->statuses[$type->value] ?? null;
    }

    /**
     * @return list<ActiveStatus>
     */
    public function getStatuses(): array
    {
        return array_values($this->statuses);
    }

    public function removeExpiredStatuses(): void
    {
        $this->statuses = array_filter(
            $this->statuses,
            static fn (ActiveStatus $status): bool => !$status->isExpired()
        );
    }
}
