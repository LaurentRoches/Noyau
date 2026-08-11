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
    public function testApplyStatusAddsNewStatus(): void
    {
        $vestige = new CombatVestige(new Vestige('v1', 'Test', 'neutral', 100, 0));
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);

        $vestige->applyStatus($status);

        $this->assertCount(1, $vestige->getStatuses());
        $this->assertSame($status, $vestige->getStatuses()[0]);
    }

    public function testApplyStatusMergesWhenSameTypeAlreadyExists(): void
    {
        $vestige = new CombatVestige(new Vestige('v1', 'Test', 'neutral', 100, 0));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 35));

        $statuses = $vestige->getStatuses();
        $this->assertCount(1, $statuses);
        $this->assertSame(5, $statuses[0]->getStacks());
        $this->assertSame(35, $statuses[0]->getRemainingTicks());
    }

    public function testGetStatusReturnsActiveStatusOrNull(): void
    {
        $vestige = new CombatVestige(new Vestige('v1', 'Test', 'neutral', 100, 0));

        $this->assertNull($vestige->getStatus(StatusType::POISON));

        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);
        $vestige->applyStatus($status);

        $this->assertSame($status, $vestige->getStatus(StatusType::POISON));
        $this->assertNull($vestige->getStatus(StatusType::BURN));
    }

    public function testRemoveExpiredStatusesPurgesZeroTickStatuses(): void
    {
        $vestige = new CombatVestige(new Vestige('v1', 'Test', 'neutral', 100, 0));
        $poison = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);
        $burn = new ActiveStatus(StatusType::BURN, stacks: 1, durationTicks: 1);

        $vestige->applyStatus($poison);
        $vestige->applyStatus($burn);

        $burn->decrementDuration(1);

        $vestige->removeExpiredStatuses();

        $statuses = $vestige->getStatuses();
        $this->assertCount(1, $statuses);
        $this->assertSame(StatusType::POISON, $statuses[0]->getType());
    }
}
