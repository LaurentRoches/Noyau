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
        $status = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'venomous_vial');

        $vestige->applyStatus($status);

        self::assertCount(1, $vestige->getStatuses());
        self::assertSame($status, $vestige->getStatuses()[0]);
    }

    public function testApplyStatusCreatesASecondInstanceInsteadOfMerging(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'venomous_vial'));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 35, sourceId: 'nightfang'));

        $instances = $vestige->getStatusInstances(StatusType::POISON);

        self::assertCount(2, $instances);
        self::assertSame('venomous_vial', $instances[0]->getSourceId());
        self::assertSame(2, $instances[0]->getStacks());
        self::assertSame(20, $instances[0]->getRemainingTicks());
        self::assertSame('nightfang', $instances[1]->getSourceId());
        self::assertSame(3, $instances[1]->getStacks());
        self::assertSame(35, $instances[1]->getRemainingTicks());
    }

    public function testGetStatusInstancesReturnsAnEmptyListForAnAbsentType(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);

        self::assertSame([], $vestige->getStatusInstances(StatusType::POISON));

        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'venomous_vial'));

        self::assertCount(1, $vestige->getStatusInstances(StatusType::POISON));
        self::assertSame([], $vestige->getStatusInstances(StatusType::BURN));
    }

    public function testGetAggregatedStatusSumsStacksAndKeepsTheLongestRemainingDuration(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 28, sourceId: 'venomous_vial'));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 14, sourceId: 'nightfang'));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 2, sourceId: 'shadow_venomous_vial'));

        $aggregated = $vestige->getAggregatedStatus(StatusType::POISON);

        self::assertSame(6, $aggregated->stacks);
        self::assertSame(28, $aggregated->remainingTicks);
    }

    public function testGetAggregatedStatusReturnsZeroesForAnAbsentType(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);

        $aggregated = $vestige->getAggregatedStatus(StatusType::POISON);

        self::assertSame(0, $aggregated->stacks);
        self::assertSame(0, $aggregated->remainingTicks);
    }

    public function testRemoveExpiredStatusesPurgesZeroTickStatuses(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);
        $poison = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'venomous_vial');
        $burn = new ActiveStatus(StatusType::BURN, stacks: 1, durationTicks: 1, sourceId: 'firesteel');

        $vestige->applyStatus($poison);
        $vestige->applyStatus($burn);

        $burn->decrementDuration(1);

        $vestige->removeExpiredStatuses();

        $statuses = $vestige->getStatuses();
        self::assertCount(1, $statuses);
        self::assertSame(StatusType::POISON, $statuses[0]->getType());
        self::assertSame([], $vestige->getStatusInstances(StatusType::BURN));
    }

    public function testRemoveExpiredStatusesPurgesOnlyTheExpiredInstancesOfAType(): void
    {
        $vestigeDefinition = $this->createVestigeDefinition();
        $vestige = new CombatVestige($vestigeDefinition);
        $short = new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 1, sourceId: 'nightfang');
        $long = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'venomous_vial');

        $vestige->applyStatus($short);
        $vestige->applyStatus($long);

        $short->decrementDuration(1);

        $vestige->removeExpiredStatuses();

        $instances = $vestige->getStatusInstances(StatusType::POISON);
        self::assertCount(1, $instances);
        self::assertSame($long, $instances[0]);
        self::assertSame(2, $vestige->getAggregatedStatus(StatusType::POISON)->stacks);
    }

    public function testTakeBurnDamageAttenuatesToSeventyPercentAgainstNoShield(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());

        $boosted = $vestige->takeBurnDamage(10);

        // 10 stacks -> 15 majorés, rien à absorber, intdiv(15 * 7, 15) = 7 PV.
        // Soit 70 % exactement des stacks, la brûlure étant faible sur les PV nus.
        self::assertSame(15, $boosted);
        self::assertSame(0, $vestige->getShield());
        self::assertSame(93, $vestige->getHp());
    }

    public function testTakeBurnDamageSplitsBetweenShieldAndHpAgainstAPartialShield(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $vestige->gainShield(8);

        $boosted = $vestige->takeBurnDamage(10);

        // Exemple de 02 §7.4 : 15 majorés, 8 absorbés, 7 de surplus,
        // intdiv(7 * 7, 15) = intdiv(49, 15) = 3 PV.
        self::assertSame(15, $boosted);
        self::assertSame(0, $vestige->getShield());
        self::assertSame(97, $vestige->getHp());
    }

    public function testTakeBurnDamageIsFullyAbsorbedByASufficientShield(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $vestige->gainShield(20);

        $boosted = $vestige->takeBurnDamage(10);

        // Bouclier plein : les 15 majorés sont absorbés en entier, soit 150 %
        // des stacks. C'est le cas où la brûlure est la plus rentable.
        self::assertSame(15, $boosted);
        self::assertSame(5, $vestige->getShield());
        self::assertSame(100, $vestige->getHp());
    }

    public function testTakeBurnDamageRoundsDownInFavourOfTheDefender(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());

        // 1 stack -> intdiv(3, 2) = 1 majoré, puis intdiv(7, 15) = 0 PV.
        // Un stack isolé sur une cible nue n'inflige RIEN : conséquence assumée
        // de l'arithmétique entière, les deux divisions arrondissant au plancher.
        self::assertSame(1, $vestige->takeBurnDamage(1));
        self::assertSame(100, $vestige->getHp());

        // 2 stacks -> 3 majorés, intdiv(21, 15) = 1 PV.
        self::assertSame(3, $vestige->takeBurnDamage(2));
        self::assertSame(99, $vestige->getHp());
    }

    public function testTakeBurnDamageNeverPushesHpBelowZero(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $vestige->takeRawDamage(98);

        $vestige->takeBurnDamage(100);

        self::assertSame(0, $vestige->getHp());
        self::assertFalse($vestige->isAlive());
    }

    public function testCleanseRemovesOneStackFromTheLongestRunningInstance(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $short = new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 5, sourceId: 'nightfang');
        $long = new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 28, sourceId: 'venomous_vial');
        $vestige->applyStatus($short);
        $vestige->applyStatus($long);

        $cleansed = $vestige->cleanseHostileStatuses();

        // Retirer d'abord une instance sur le point d'expirer seule n'aurait
        // aucun effet mesurable : c'est la plus longue qui perd un stack.
        self::assertSame(['POISON' => 1], $cleansed);
        self::assertSame(3, $short->getStacks());
        self::assertSame(2, $long->getStacks());
    }

    public function testCleanseBreaksDurationTiesByInsertionOrder(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $first = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'venomous_vial');
        $second = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'nightfang');
        $vestige->applyStatus($first);
        $vestige->applyStatus($second);

        $vestige->cleanseHostileStatuses();

        self::assertSame(1, $first->getStacks());
        self::assertSame(2, $second->getStacks());
    }

    public function testCleanseRemovesAnInstanceEmptiedOfItsStacksEvenWithTicksLeft(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 30, sourceId: 'venomous_vial'));

        $vestige->cleanseHostileStatuses();

        // Règle 4 de D-21 : le compteur de ticks est encore à 30, l'instance
        // part quand même.
        self::assertSame([], $vestige->getStatusInstances(StatusType::POISON));
        self::assertSame(0, $vestige->getAggregatedStatus(StatusType::POISON)->stacks);
    }

    public function testCleanseKeepsTheOtherInstancesOfTheSameType(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $survivor = new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 10, sourceId: 'nightfang');
        $vestige->applyStatus($survivor);
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 30, sourceId: 'venomous_vial'));

        $vestige->cleanseHostileStatuses();

        $instances = $vestige->getStatusInstances(StatusType::POISON);
        self::assertCount(1, $instances);
        self::assertSame($survivor, $instances[0]);
    }

    public function testCleanseTouchesEveryHostileTypeAtOnceAndSparesTheBeneficialOnes(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 30, sourceId: 'venomous_vial'));
        $vestige->applyStatus(new ActiveStatus(StatusType::BURN, stacks: 4, durationTicks: 20, sourceId: 'firesteel'));
        $vestige->applyStatus(new ActiveStatus(StatusType::REGEN, stacks: 2, durationTicks: 30, sourceId: 'panacee'));
        $vestige->applyStatus(new ActiveStatus(StatusType::WARD, stacks: 2, durationTicks: 30, sourceId: 'shadow_armor'));

        $cleansed = $vestige->cleanseHostileStatuses();

        self::assertSame(['POISON' => 1, 'BURN' => 1], $cleansed);
        self::assertSame(2, $vestige->getAggregatedStatus(StatusType::POISON)->stacks);
        self::assertSame(3, $vestige->getAggregatedStatus(StatusType::BURN)->stacks);
        self::assertSame(2, $vestige->getAggregatedStatus(StatusType::REGEN)->stacks);
        self::assertSame(2, $vestige->getAggregatedStatus(StatusType::WARD)->stacks);
    }

    public function testCleanseReturnsAnEmptyMapWhenNoHostileStatusIsPresent(): void
    {
        $vestige = new CombatVestige($this->createVestigeDefinition());
        $vestige->applyStatus(new ActiveStatus(StatusType::WARD, stacks: 2, durationTicks: 30, sourceId: 'shadow_armor'));

        self::assertSame([], $vestige->cleanseHostileStatuses());
        self::assertSame(2, $vestige->getAggregatedStatus(StatusType::WARD)->stacks);
    }
}
