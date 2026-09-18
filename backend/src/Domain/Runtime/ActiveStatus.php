<?php

declare(strict_types=1);

namespace App\Domain\Runtime;

use App\Domain\Enum\StatusType;

final class ActiveStatus
{
    private int $remainingTicks;

    public function __construct(
        private readonly StatusType $type,
        private int $stacks,
        int $durationTicks,
        private readonly string $sourceId,
    ) {
        $this->remainingTicks = $durationTicks;
    }

    public function getType(): StatusType
    {
        return $this->type;
    }

    /**
     * Identifiant de l'objet à l'origine de cette application (D-20).
     *
     * Champ en écriture seule en V1 : aucun CombatEvent ne l'expose, la somme
     * des stacks ne le lit pas, et la stabilisation à ceil(durée / cooldown)
     * par source est émergente, pas calculée. Conservé parce que le chantier 2
     * fige le format de snapshot de l'état de statut.
     */
    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getStacks(): int
    {
        return $this->stacks;
    }

    public function getRemainingTicks(): int
    {
        return $this->remainingTicks;
    }

    public function decrementDuration(int $ticks = 1): void
    {
        $this->remainingTicks = max(0, $this->remainingTicks - $ticks);
    }

    /**
     * Retire un stack, sans descendre sous zéro (D-21).
     *
     * Le retrait de l'instance vidée n'appartient pas à l'instance elle-même :
     * c'est `CombatVestige` qui tient la liste et qui l'applique.
     */
    public function removeStack(): void
    {
        $this->stacks = max(0, $this->stacks - 1);
    }

    public function isExpired(): bool
    {
        return $this->remainingTicks === 0;
    }

}
