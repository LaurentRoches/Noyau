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

        self::assertSame(StatusType::POISON, $status->getType());
        self::assertSame(2, $status->getStacks());
        self::assertSame(30, $status->getRemainingTicks());
    }

    public function testDecrementDurationReducesRemainingTicksAndStopsAtZero(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30);

        $status->decrementDuration();
        self::assertSame(29, $status->getRemainingTicks());

        $status->decrementDuration(29);
        self::assertSame(0, $status->getRemainingTicks());

        $status->decrementDuration(5);
        self::assertSame(0, $status->getRemainingTicks());
    }

    public function testIsExpiredReturnsTrueOnlyWhenRemainingTicksIsZero(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 2);

        self::assertFalse($status->isExpired());

        $status->decrementDuration(1);
        self::assertFalse($status->isExpired());

        $status->decrementDuration(1);
        self::assertTrue($status->isExpired());
    }

    public function testMergeWithCombinesStacksAndKeepsMaxRemainingTicks(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20);
        $other = new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 35);

        $status->mergeWith($other);

        self::assertSame(5, $status->getStacks());
        self::assertSame(35, $status->getRemainingTicks());
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

        self::assertSame(3, $status->getStacks());
        self::assertSame(30, $status->getRemainingTicks());
    }
}
