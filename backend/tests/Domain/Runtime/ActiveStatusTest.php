<?php

declare(strict_types=1);

namespace App\Tests\Domain\Runtime;

use App\Domain\Enum\StatusType;
use App\Domain\Runtime\ActiveStatus;
use PHPUnit\Framework\TestCase;

final class ActiveStatusTest extends TestCase
{
    public function testConstructorSetsInitialState(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30);

        $this->assertSame(StatusType::POISON, $status->getType());
        $this->assertSame(2, $status->getStacks());
        $this->assertSame(30, $status->getRemainingTicks());
    }

    public function testDecrementDurationReducesRemainingTicksAndStopsAtZero(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30);

        $status->decrementDuration();
        $this->assertSame(29, $status->getRemainingTicks());

        $status->decrementDuration(29);
        $this->assertSame(0, $status->getRemainingTicks());

        $status->decrementDuration(5);
        $this->assertSame(0, $status->getRemainingTicks());
    }

    public function testIsExpiredReturnsTrueOnlyWhenRemainingTicksIsZero(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 2);

        $this->assertFalse($status->isExpired());

        $status->decrementDuration(1);
        $this->assertFalse($status->isExpired());

        $status->decrementDuration(1);
        $this->assertTrue($status->isExpired());
    }

    public function testMergeWithCombinesStacksAndKeepsMaxRemainingTicks(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);
        $other = new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 35);

        $status->mergeWith($other);

        $this->assertSame(5, $status->getStacks());
        $this->assertSame(35, $status->getRemainingTicks());
    }

    public function testMergeWithThrowsExceptionWhenStatusTypesDoNotMatch(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);
        $other = new ActiveStatus(StatusType::BURN, stacks: 1, durationTicks: 10);

        $this->expectException(\InvalidArgumentException::class);

        $status->mergeWith($other);
    }

    public function testMergeWithKeepsOwnRemainingTicksWhenLonger(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30);
        $other = new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 12);

        $status->mergeWith($other);

        $this->assertSame(3, $status->getStacks());
        $this->assertSame(30, $status->getRemainingTicks());
    }
}
