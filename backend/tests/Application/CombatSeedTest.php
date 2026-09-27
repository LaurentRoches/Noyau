<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\CombatSeed;
use PHPUnit\Framework\TestCase;

/**
 * Calcul de la graine de combat (D-22, chantier 2).
 *
 * Le calcul appartient à l'Application, la dérivation des flux au Domaine.
 * C'est ce découpage qui permet au moteur embarqué de résoudre un combat
 * **sans jamais connaître la seed du run** : la CLI du chantier 1a reçoit
 * `{ snapshotA, snapshotB, combatSeed }` et rien d'autre.
 *
 * Forme retenue : digest SHA-256 en hexadécimal, 64 caractères. Elle est
 * stockée dans chaque snapshot, donc elle traverse JSON — une chaîne binaire
 * brute n'y survivrait pas, et le format canonique de D-19 n'admet que
 * `int`, `string` et `bool`.
 *
 * Format irréversible : `07` §8, point 3.
 */
final class CombatSeedTest extends TestCase
{
    public function testItProducesASixtyFourCharacterHexadecimalDigest(): void
    {
        $seed = CombatSeed::forRound(42, 3);

        self::assertSame(64, strlen($seed));
        self::assertTrue(ctype_xdigit($seed), 'La graine traverse JSON : elle doit rester hexadécimale.');
    }

    public function testItIsDeterministic(): void
    {
        self::assertSame(
            CombatSeed::forRound(42, 3),
            CombatSeed::forRound(42, 3),
        );
    }

    public function testTwoRoundsOfTheSameRunProduceTwoSeeds(): void
    {
        self::assertNotSame(
            CombatSeed::forRound(42, 3),
            CombatSeed::forRound(42, 4),
        );
    }

    public function testTwoRunsAtTheSameRoundProduceTwoSeeds(): void
    {
        self::assertNotSame(
            CombatSeed::forRound(42, 3),
            CombatSeed::forRound(43, 3),
        );
    }

    /**
     * Le cas qui impose un séparateur explicite.
     *
     * Sans lui, la concaténation de deux entiers est ambiguë : `12` suivi de
     * `3` et `1` suivi de `23` donneraient la même chaîne, donc la même
     * graine, donc le même combat pour deux manches de deux runs différentes.
     * L'encodage décimal ASCII et le séparateur `|` font partie du format
     * irréversible au même titre que l'algorithme de hachage.
     */
    public function testConcatenationIsUnambiguous(): void
    {
        self::assertNotSame(
            CombatSeed::forRound(12, 3),
            CombatSeed::forRound(1, 23),
        );
    }

    /**
     * La graine de run est un entier signé qui peut être négatif ou extrême :
     * `RunController::create()` tire dans `random_int(0, PHP_INT_MAX)`, mais
     * rien n'empêche une seed fournie par un test ou, après E-13, par le
     * corps d'une requête.
     */
    public function testItHandlesExtremeRunSeeds(): void
    {
        self::assertSame(64, strlen(CombatSeed::forRound(0, 1)));
        self::assertSame(64, strlen(CombatSeed::forRound(PHP_INT_MAX, 1)));
        self::assertSame(64, strlen(CombatSeed::forRound(PHP_INT_MIN, 1)));

        self::assertNotSame(
            CombatSeed::forRound(0, 1),
            CombatSeed::forRound(PHP_INT_MAX, 1),
        );
    }
}
