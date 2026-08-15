<?php

declare(strict_types=1);

namespace App\Tests\Domain\Runtime;

use App\Domain\Enum\StatusType;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class CombatVestigeTest extends TestCase
{
    private function createVestigeDefinition(): Vestige
    {
        return $vestige = new Vestige(
            id: 'v1',
            name: 'Test',
            affinity: 'neutral',
            baseHp: 100,
            baseShield: 0,
            startingGold: 0,
            startingIncome: 0
        );
    }
    public function testApplyStatusAddsNewStatus(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);

        $vestige->applyStatus($status);

        self::assertCount(1, $vestige->getStatuses());
        self::assertSame($status, $vestige->getStatuses()[0]);
    }

    public function testApplyStatusMergesWhenSameTypeAlreadyExists(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 35));

        $statuses = $vestige->getStatuses();
        self::assertCount(1, $statuses);
        self::assertSame(5, $statuses[0]->getStacks());
        self::assertSame(35, $statuses[0]->getRemainingTicks());
    }

    public function testGetStatusReturnsActiveStatusOrNull(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);

        self::assertNull($vestige->getStatus(StatusType::POISON));

        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);
        $vestige->applyStatus($status);

        self::assertSame($status, $vestige->getStatus(StatusType::POISON));
        self::assertNull($vestige->getStatus(StatusType::BURN));
    }

    public function testRemoveExpiredStatusesPurgesZeroTickStatuses(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);
        $poison = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);
        $burn = new ActiveStatus(StatusType::BURN, stacks: 1, durationTicks: 1);

        $vestige->applyStatus($poison);
        $vestige->applyStatus($burn);

        $burn->decrementDuration(1);

        $vestige->removeExpiredStatuses();

        $statuses = $vestige->getStatuses();
        self::assertCount(1, $statuses);
        self::assertSame(StatusType::POISON, $statuses[0]->getType());
    }
}
