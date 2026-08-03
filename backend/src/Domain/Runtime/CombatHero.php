<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Model\Hero;

class CombatHero
{
    private int $currentHp;
    private int $currentShield;

    public function __construct(
        private readonly Hero $definition,
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
        // Empêche un dégât négatif
        $effectiveDamage = max(0, $damage);

        // Absorption par le bouclier
        $shieldDamage = min($this->currentShield, $effectiveDamage);
        $this->currentShield -= $shieldDamage;

        // Dégâts restants appliqués aux PV
        $remainingDamage = $effectiveDamage - $shieldDamage;
        $hpDamage = min($this->currentHp, $remainingDamage);
        $this->currentHp -= $hpDamage;
    }

    public function receiveHeal(int $heal): void
    {
        // Empêche un soin négatif
        $effectiveHeal = max(0, $heal);

        $this->currentHp = min($this->definition->baseHp, $this->currentHp + $effectiveHeal);
    }

    public function gainShield(int $shield): void
    {
        // Empêche un shield négatif
        $effectiveShield = max(0, $shield);

        $this->currentShield += $effectiveShield;
    }
}
