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

    public function getHp(): int
    {
        return $this->currentHp;
    }

    public function getShield(): int
    {
        return $this->currentShield;
    }

    public function takeDamage(int $damage): void
    {
        // Absorption par le bouclier
        $shieldDamage = min($this->currentShield, $damage);
        $this->currentShield -= $shieldDamage;

        // Dégâts restants appliqués aux PV
        $remainingDamage = $damage - $shieldDamage;
        $hpDamage = min($this->currentHp, $remainingDamage);
        $this->currentHp -= $hpDamage;
    }

    public function receiveHeal(int $heal): void
    {
        $this->currentHp += $heal;
        if ($this->currentHp > $this->definition->baseHp) {
            $this->currentHp = $this->definition->baseHp;
        }
    }

    public function gainShield(int $shield): void
    {
        $this->currentShield += $shield;
    }
}
