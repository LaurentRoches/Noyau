<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enum;

use App\Domain\Enum\StatusType;
use PHPUnit\Framework\TestCase;

final class StatusTypeTest extends TestCase
{
    public function testPoisonAndBurnAreHostile(): void
    {
        self::assertTrue(StatusType::POISON->isHostile());
        self::assertTrue(StatusType::BURN->isHostile());
    }

    public function testRegenAndWardAreNotHostile(): void
    {
        self::assertFalse(StatusType::REGEN->isHostile());
        self::assertFalse(StatusType::WARD->isHostile());
    }

    public function testEveryStatusTypeDeclaresACamp(): void
    {
        // Le match de isHostile() est sans branche par défaut : ce test échoue
        // par UnhandledMatchError si un statut est ajouté sans que son camp
        // soit tranché. C'est ce qui tient la promesse de 07 §6, une seule
        // source de vérité au lieu d'une liste dispersée dans le moteur.
        foreach (StatusType::cases() as $type) {
            $type->isHostile();
        }

        self::assertCount(4, StatusType::cases());
    }
}
