<?php

declare(strict_types=1);

namespace App\Tests\Domain\Model\Draw;

use App\Domain\Model\Draw\WeightedDraw;
use App\Domain\Model\Hero;
use PHPUnit\Framework\TestCase;

final class WeightedDrawTest extends TestCase
{
    private static function createHero(string $id): Hero
    {
        return new Hero(
            id: $id,
            name: $id,
            affinity: 'shadow',
            itemSlots: 2,
        );
    }

    public function testPickTopOrdersCandidatesByDescendingKeyWhenWeightsAreEqual(): void
    {
        $heroA = self::createHero('hero_a');
        $heroB = self::createHero('hero_b');
        $heroC = self::createHero('hero_c');

        // Poids égaux (1.0) : la clé équivaut directement à u, donc l'ordre
        // attendu est simplement l'ordre décroissant des u fournis.
        $result = WeightedDraw::pickTop(
            candidates: [$heroA, $heroB, $heroC],
            weights: [1.0, 1.0, 1.0],
            randomFloats: [0.5, 0.9, 0.1],
            count: 3,
        );

        self::assertSame([$heroB, $heroA, $heroC], $result);
    }

    public function testPickTopFavorsHigherWeightedCandidateDespiteLowerRandomValue(): void
    {
        $heroA = self::createHero('hero_a');
        $heroB = self::createHero('hero_b');

        // heroA reçoit un u plus BAS que heroB (0.3 < 0.5), ce qui le
        // classerait derrière si les poids étaient égaux. Mais son poids
        // (2.0, contre 1.0 pour heroB) doit inverser ce résultat :
        // key_A = 0.3^(1/2.0) ≈ 0.5477
        // key_B = 0.5^(1/1.0) = 0.5
        // key_A > key_B : heroA doit être sélectionné malgré son u plus faible.
        $result = WeightedDraw::pickTop(
            candidates: [$heroA, $heroB],
            weights: [2.0, 1.0],
            randomFloats: [0.3, 0.5],
            count: 1,
        );

        self::assertSame([$heroA], $result);
    }
}
