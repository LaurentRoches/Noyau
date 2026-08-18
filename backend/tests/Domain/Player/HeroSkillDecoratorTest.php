<?php

declare(strict_types=1);

namespace App\Tests\Domain\Player;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\HeroSkillType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Item;
use App\Domain\Player\HeroSkillDecorator;
use PHPUnit\Framework\TestCase;

final class HeroSkillDecoratorTest extends TestCase
{
    /**
     * @param Effect[] $effects
     */
    private function createItem(
        string $id = 'item',
        ItemSize $size = ItemSize::ONE_HAND,
        int $cooldownTicks = 5,
        array $effects = [],
    ): Item {
        return new Item(
            id: $id,
            name: "Item {$id}",
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: $size,
            cooldownTicks: $cooldownTicks,
            effects: $effects,
        );
    }

    /**
     * @param Action[] $actions
     */
    private function createEffect(array $actions): Effect
    {
        return new Effect(trigger: Trigger::EVERY_N_TICKS, actions: $actions);
    }

    public function testDecorateReducesCooldownForOneHandItemsWithFranticSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $item = $this->createItem(id: 'dagger', cooldownTicks: 10);

        $decorated = $decorator->decorate(HeroSkillType::FRANTIC, $item);

        self::assertSame(8, $decorated->cooldownTicks);
    }

    public function testDecorateIgnoresTwoHandItemsWithFranticSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $item = $this->createItem(id: 'longsword', size: ItemSize::TWO_HAND, cooldownTicks: 10);

        $decorated = $decorator->decorate(HeroSkillType::FRANTIC, $item);

        self::assertSame(10, $decorated->cooldownTicks);
    }

    public function testDecorateAddsPoisonStackForActionsApplyingPoisonStatusWithVirulentSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $poisonAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 2,
            durationTicks: 30,
        );
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $item = $this->createItem(
            id: 'venom_blade',
            effects: [$this->createEffect([$poisonAction, $damageAction])],
        );

        $decorated = $decorator->decorate(HeroSkillType::VIRULENT, $item);

        self::assertSame(3, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(10, $decorated->effects[0]->actions[1]->value);
        self::assertNull($decorated->effects[0]->actions[1]->status);
    }

    public function testDecorateLeavesNonPoisonActionsUnchangedWithVirulentSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY);
        $item = $this->createItem(id: 'plain_dagger', effects: [$this->createEffect([$damageAction])]);

        $decorated = $decorator->decorate(HeroSkillType::VIRULENT, $item);

        self::assertSame(15, $decorated->effects[0]->actions[0]->value);
        self::assertNull($decorated->effects[0]->actions[0]->status);
    }

    public function testDecorateIncreasesGainShieldValueForActionsWithStalwartSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $shieldAction = new Action(type: ActionType::GAIN_SHIELD, value: 7, target: Target::SELF);
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $item = $this->createItem(
            id: 'iron_buckler',
            effects: [$this->createEffect([$shieldAction, $damageAction])],
        );

        $decorated = $decorator->decorate(HeroSkillType::STALWART, $item);

        self::assertSame(9, $decorated->effects[0]->actions[0]->value); // 7 × 1.2 = 8.4 → ceil → 9
        self::assertSame(10, $decorated->effects[0]->actions[1]->value);
    }

    public function testDecorateLeavesNonShieldActionsUnchangedWithStalwartSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 12, target: Target::ENEMY);
        $item = $this->createItem(id: 'plain_sword', effects: [$this->createEffect([$damageAction])]);

        $decorated = $decorator->decorate(HeroSkillType::STALWART, $item);

        self::assertSame(12, $decorated->effects[0]->actions[0]->value);
    }

    public function testDecorateIncreasesHealValueWithVitalicSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $healAction = new Action(type: ActionType::HEAL, value: 7, target: Target::SELF);
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $item = $this->createItem(
            id: 'healing_charm',
            effects: [$this->createEffect([$healAction, $damageAction])],
        );

        $decorated = $decorator->decorate(HeroSkillType::VITALIC, $item);

        self::assertSame(9, $decorated->effects[0]->actions[0]->value); // 7 × 1.2 = 8.4 → ceil → 9
        self::assertSame(10, $decorated->effects[0]->actions[1]->value);
    }

    public function testDecorateLeavesNonHealActionsUnchangedWithVitalicSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 12, target: Target::ENEMY);
        $item = $this->createItem(id: 'plain_staff', effects: [$this->createEffect([$damageAction])]);

        $decorated = $decorator->decorate(HeroSkillType::VITALIC, $item);

        self::assertSame(12, $decorated->effects[0]->actions[0]->value);
    }

    public function testDecorateAddsBurnStackForActionsApplyingBurnStatusWithSearingSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $burnAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::BURN,
            stacks: 2,
            durationTicks: 20,
        );
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $item = $this->createItem(
            id: 'ember_blade',
            effects: [$this->createEffect([$burnAction, $damageAction])],
        );

        $decorated = $decorator->decorate(HeroSkillType::SEARING, $item);

        self::assertSame(3, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(10, $decorated->effects[0]->actions[1]->value);
        self::assertNull($decorated->effects[0]->actions[1]->status);
    }

    public function testDecorateLeavesPoisonActionsUnchangedWithSearingSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $poisonAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 2,
            durationTicks: 30,
        );
        $item = $this->createItem(id: 'venom_charm', effects: [$this->createEffect([$poisonAction])]);

        $decorated = $decorator->decorate(HeroSkillType::SEARING, $item);

        self::assertSame(2, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(StatusType::POISON, $decorated->effects[0]->actions[0]->status);
    }

    public function testDecorateLeavesBurnActionsUnchangedWithVirulentSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $burnAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::BURN,
            stacks: 2,
            durationTicks: 20,
        );
        $item = $this->createItem(id: 'ember_charm', effects: [$this->createEffect([$burnAction])]);

        $decorated = $decorator->decorate(HeroSkillType::VIRULENT, $item);

        self::assertSame(2, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(StatusType::BURN, $decorated->effects[0]->actions[0]->status);
    }

    public function testDecorateAddsWardStackForActionsApplyingWardStatusWithWardenSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $wardAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::SELF,
            status: StatusType::WARD,
            stacks: 2,
            durationTicks: 15,
        );
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $item = $this->createItem(
            id: 'guardian_shield',
            effects: [$this->createEffect([$wardAction, $damageAction])],
        );

        $decorated = $decorator->decorate(HeroSkillType::WARDEN, $item);

        self::assertSame(3, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(10, $decorated->effects[0]->actions[1]->value);
        self::assertNull($decorated->effects[0]->actions[1]->status);
    }

    public function testDecorateLeavesPoisonActionsUnchangedWithWardenSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $poisonAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 2,
            durationTicks: 30,
        );
        $item = $this->createItem(id: 'venom_relic', effects: [$this->createEffect([$poisonAction])]);

        $decorated = $decorator->decorate(HeroSkillType::WARDEN, $item);

        self::assertSame(2, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(StatusType::POISON, $decorated->effects[0]->actions[0]->status);
    }

    public function testDecorateAddsRegenStackForActionsApplyingRegenStatusWithResurgentSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $regenAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::SELF,
            status: StatusType::REGEN,
            stacks: 2,
            durationTicks: 25,
        );
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $item = $this->createItem(
            id: 'phoenix_feather',
            effects: [$this->createEffect([$regenAction, $damageAction])],
        );

        $decorated = $decorator->decorate(HeroSkillType::RESURGENT, $item);

        self::assertSame(3, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(10, $decorated->effects[0]->actions[1]->value);
        self::assertNull($decorated->effects[0]->actions[1]->status);
    }

    public function testDecorateLeavesPoisonActionsUnchangedWithResurgentSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $poisonAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 2,
            durationTicks: 30,
        );
        $item = $this->createItem(id: 'venom_totem', effects: [$this->createEffect([$poisonAction])]);

        $decorated = $decorator->decorate(HeroSkillType::RESURGENT, $item);

        self::assertSame(2, $decorated->effects[0]->actions[0]->stacks);
        self::assertSame(StatusType::POISON, $decorated->effects[0]->actions[0]->status);
    }

    public function testDecorateIncreasesDealDamageValueWithSavageSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $damageAction = new Action(type: ActionType::DEAL_DAMAGE, value: 10, target: Target::ENEMY);
        $shieldAction = new Action(type: ActionType::GAIN_SHIELD, value: 7, target: Target::SELF);
        $item = $this->createItem(
            id: 'brutal_axe',
            effects: [$this->createEffect([$damageAction, $shieldAction])],
        );

        $decorated = $decorator->decorate(HeroSkillType::SAVAGE, $item);

        self::assertSame(12, $decorated->effects[0]->actions[0]->value); // 10 × 1.2 = 12 → ceil → 12
        self::assertSame(7, $decorated->effects[0]->actions[1]->value);
    }

    public function testDecorateLeavesGainShieldActionsUnchangedWithSavageSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $shieldAction = new Action(type: ActionType::GAIN_SHIELD, value: 7, target: Target::SELF);
        $item = $this->createItem(id: 'plain_shield', effects: [$this->createEffect([$shieldAction])]);

        $decorated = $decorator->decorate(HeroSkillType::SAVAGE, $item);

        self::assertSame(7, $decorated->effects[0]->actions[0]->value);
    }
}
