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
        //
        // Le camp de chaque statut est désormais relevé et comparé à la table
        // complète, au lieu d'être calculé puis jeté : un appel dont on ignore
        // le résultat ne vérifie que l'absence d'exception, et PHPStan le
        // signalait. Un statut ajouté **avec** son camp fait rougir la table,
        // ce que faisait le compte de quatre qu'elle remplace.
        $camps = [];
        foreach (StatusType::cases() as $type) {
            $camps[$type->name] = $type->isHostile();
        }
        ksort($camps);

        self::assertSame(
            ['BURN' => true, 'POISON' => true, 'REGEN' => false, 'WARD' => false],
            $camps,
        );
    }
}
