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
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30, sourceId: 'venomous_vial');

        self::assertSame(StatusType::POISON, $status->getType());
        self::assertSame(2, $status->getStacks());
        self::assertSame(30, $status->getRemainingTicks());
    }

    public function testConstructorRetainsTheSourceIdentifier(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30, sourceId: 'venomous_vial');

        self::assertSame('venomous_vial', $status->getSourceId());
    }

    public function testTwoInstancesOfTheSameTypeKeepTheirOwnSourceIdentifiers(): void
    {
        $fromVial = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30, sourceId: 'venomous_vial');
        $fromFang = new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 10, sourceId: 'nightfang');

        self::assertSame('venomous_vial', $fromVial->getSourceId());
        self::assertSame('nightfang', $fromFang->getSourceId());
    }

    public function testDecrementDurationReducesRemainingTicksAndStopsAtZero(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30, sourceId: 'venomous_vial');

        $status->decrementDuration();
        self::assertSame(29, $status->getRemainingTicks());

        $status->decrementDuration(29);
        self::assertSame(0, $status->getRemainingTicks());

        $status->decrementDuration(5);
        self::assertSame(0, $status->getRemainingTicks());
    }

    public function testIsExpiredReturnsTrueOnlyWhenRemainingTicksIsZero(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 2, sourceId: 'venomous_vial');

        self::assertFalse($status->isExpired());

        $status->decrementDuration(1);
        self::assertFalse($status->isExpired());

        $status->decrementDuration(1);
        self::assertTrue($status->isExpired());
    }

    public function testRemoveStackDecrementsAndStopsAtZero(): void
    {
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 30, sourceId: 'venomous_vial');

        $status->removeStack();
        self::assertSame(1, $status->getStacks());

        $status->removeStack();
        self::assertSame(0, $status->getStacks());

        $status->removeStack();
        self::assertSame(0, $status->getStacks());
    }

    public function testRemoveStackLeavesTheDurationUntouched(): void
    {
        // Une instance vidée de ses stacks garde son compteur : c'est
        // CombatVestige qui la retire (D-21, règle 4), pas l'instance.
        $status = new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 30, sourceId: 'venomous_vial');

        $status->removeStack();

        self::assertSame(0, $status->getStacks());
        self::assertSame(30, $status->getRemainingTicks());
        self::assertFalse($status->isExpired());
    }
}
