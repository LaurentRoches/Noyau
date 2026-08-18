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
    public function testDecorateReducesCooldownForOneHandItemsWithFranticSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $item = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 10,
            effects: []
        );

        $decorated = $decorator->decorate(HeroSkillType::FRANTIC, $item);

        self::assertSame(8, $decorated->cooldownTicks);
    }

    public function testDecorateIgnoresTwoHandItemsWithFranticSkill(): void
    {
        $decorator = new HeroSkillDecorator();
        $item = new Item(
            id: 'longsword',
            name: 'Longsword',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::TWO_HAND,
            cooldownTicks: 10,
            effects: []
        );

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
        $effect = new Effect(trigger: Trigger::EVERY_N_TICKS, actions: [$poisonAction, $damageAction]);
        $item = new Item(
            id: 'venom_blade',
            name: 'Venom Blade',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 5,
            effects: [$effect],
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
        $effect = new Effect(trigger: Trigger::EVERY_N_TICKS, actions: [$damageAction]);
        $item = new Item(
            id: 'plain_dagger',
            name: 'Plain Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 5,
            effects: [$effect],
        );

        $decorated = $decorator->decorate(HeroSkillType::VIRULENT, $item);

        self::assertSame(15, $decorated->effects[0]->actions[0]->value);
        self::assertNull($decorated->effects[0]->actions[0]->status);
    }
}
