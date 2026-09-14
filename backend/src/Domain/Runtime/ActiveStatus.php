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

    public function isExpired(): bool
    {
        return $this->remainingTicks === 0;
    }

    public function mergeWith(ActiveStatus $other): void
    {
        if ($this->type !== $other->type) {
            throw new \InvalidArgumentException(
                sprintf('Cannot merge status of type %s with %s', $this->type->value, $other->type->value)
            );
        }

        $this->stacks += $other->stacks;
        $this->remainingTicks = max($this->remainingTicks, $other->remainingTicks);
    }
}
