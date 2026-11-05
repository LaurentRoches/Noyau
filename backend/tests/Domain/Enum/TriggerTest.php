<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enum;

use App\Domain\Enum\Trigger;
use PHPUnit\Framework\TestCase;

/**
 * Le vocabulaire des déclencheurs est un format (D-23, `07` §8, point 11).
 *
 * Chaque photographie archive le déclencheur de chaque effet, et
 * `BoardHydrator` refuse une valeur qu'il ne connaît pas. Ajouter une valeur ne
 * casse rien ; en retirer ou en renommer une rend illisible toute archive qui
 * la porte. Ce test fige donc la liste entière, pour qu'une modification se lise
 * au diff au lieu de se découvrir au rejeu d'un fantôme.
 */
final class TriggerTest extends TestCase
{
    /**
     * Deux cadences, `EVERY_N_TICKS` et `ON_COMBAT_START`, puis les trois
     * déclencheurs réactifs (`02` §7.7).
     *
     * `ON_ATTACK`, `ON_KILL` et `ON_DEATH` n'y sont plus : le premier est fondu
     * dans `EVERY_N_TICKS`, dont il ne se distinguait par aucun comportement, les
     * deux autres ne sont portés par aucun objet. Ils sortent avant qu'un corpus
     * existe, parce qu'après il serait trop tard.
     */
    public function testTheVocabularyIsClosedToTheFiveDecidedValues(): void
    {
        self::assertSame(
            [
                'EVERY_N_TICKS',
                'ON_COMBAT_START',
                'ON_SIBLING_TRIGGERED',
                'ON_STATUS_APPLIED',
                'ON_THRESHOLD',
            ],
            array_map(static fn (Trigger $trigger): string => $trigger->value, Trigger::cases()),
        );
    }
}
