<?php

declare(strict_types=1);

namespace App\Tests\Application\Factory;

use App\Application\Factory\CombatBoardFactory;
use App\Domain\Player\HeroSkillDecorator;
use App\Infrastructure\Repository\Json\JsonHeroRepository;
use App\Infrastructure\Repository\Json\JsonItemRepository;
use App\Infrastructure\Repository\Json\JsonVestigeRepository;
use PHPUnit\Framework\TestCase;

final class CombatBoardFactoryTest extends TestCase
{
    private function createFactory(): CombatBoardFactory
    {
        return new CombatBoardFactory(
            new JsonVestigeRepository(__DIR__ . '/../../Fixtures/vestiges.json'),
            new JsonHeroRepository(__DIR__ . '/../../Fixtures/heroes.json'),
            new JsonItemRepository(__DIR__ . '/../../Fixtures/items.json'),
            new HeroSkillDecorator(),
        );
    }

    public function testCreateBoardAssemblesCombatBoardWithHeroAndItems(): void
    {
        $factory = $this->createFactory();

        $board = $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_bearer' => ['rusty_dagger']]);

        self::assertCount(1, $board->getHeroes());
        self::assertSame('shadow_bearer', $board->getHeroes()[0]->getId());
        self::assertCount(1, $board->getItems());
        self::assertSame('rusty_dagger', $board->getItems()[0]->getItem()->id);
    }

    public function testCreateBoardThrowsExceptionWhenItemSlotsExceeded(): void
    {
        $factory = $this->createFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Hero 'shadow_bearer' cannot equip items totaling 7 slots: exceeds budget of 6.");

        // shadow_bearer possède 6 slots dans heroes.json — 7 objets ONE_HAND dépassent le budget.
        $tooManyItems = array_fill(0, 7, 'rusty_dagger');
        $factory->createBoard('shadow_vestige', ['shadow_bearer'], ['shadow_bearer' => $tooManyItems]);
    }

    public function testCreateBoardCountsTwoHandItemAsTwoSlots(): void
    {
        $factory = $this->createFactory();

        // 1 objet TWO_HAND (coût 2) + 1 objet ONE_HAND (coût 1) = 3 slots sur un budget de 6 : doit passer.
        $board = $factory->createBoard(
            'shadow_vestige',
            ['shadow_bearer'],
            ['shadow_bearer' => ['heavy_greatsword', 'rusty_dagger']],
        );

        self::assertCount(2, $board->getItems());
    }

    public function testCreateBoardThrowsWhenTwoHandItemsExceedHeroBudget(): void
    {
        $factory = $this->createFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Hero 'shadow_bearer' cannot equip items totaling 8 slots: exceeds budget of 6.");

        // 4 objets TWO_HAND (coût 2 chacun) = 8 slots, dépasse le budget de 6 —
        // alors que count() brut (4 items) serait resté sous le budget avec l'ancienne validation.
        $factory->createBoard(
            'shadow_vestige',
            ['shadow_bearer'],
            ['shadow_bearer' => array_fill(0, 4, 'heavy_greatsword')],
        );
    }

    public function testCreateBoardAppliesHeroSkillToAssignedItems(): void
    {
        $factory = $this->createFactory();

        $board = $factory->createBoard(
            'shadow_vestige',
            ['neutral_ironblade'],
            ['neutral_ironblade' => ['rusty_dagger']],
        );

        self::assertSame(1, $board->getItems()[0]->getItem()->cooldownTicks); // 2 × 0.8 = 1.6 → floor → 1
    }

    public function testCreateBoardLeavesItemsUnmodifiedForHeroWithoutSkill(): void
    {
        $factory = $this->createFactory();

        $board = $factory->createBoard(
            'shadow_vestige',
            ['neutral_farshot'],
            ['neutral_farshot' => ['rusty_dagger']],
        );

        self::assertSame(2, $board->getItems()[0]->getItem()->cooldownTicks);
    }

    public function testCreateBoardAppliesRelentlessSkillWhenBothSlotsAreOneHand(): void
    {
        $factory = $this->createFactory();

        $board = $factory->createBoard(
            'shadow_vestige',
            ['shadow_duelist'],
            ['shadow_duelist' => ['rusty_dagger', 'rusty_dagger']],
        );

        self::assertSame(1, $board->getItems()[0]->getItem()->cooldownTicks); // 2 × 0.90 = 1.8 → floor → 1
        self::assertSame(1, $board->getItems()[1]->getItem()->cooldownTicks);
    }

    public function testCreateBoardIgnoresRelentlessSkillWhenLoadoutIsNotFullyOneHand(): void
    {
        $factory = $this->createFactory();

        $board = $factory->createBoard(
            'shadow_vestige',
            ['shadow_duelist'],
            ['shadow_duelist' => ['rusty_dagger']], // 1 slot rempli sur 2 : précondition non remplie
        );

        self::assertSame(2, $board->getItems()[0]->getItem()->cooldownTicks);
    }
}
