<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\CombatLog;
use App\Domain\Engine\SimulationResult;
use App\Domain\Enum\Resolution;
use App\Domain\Enum\Side;
use App\Domain\Model\Hero;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

/**
 * Le résultat porte l'attribution des côtés (D-19).
 *
 * Pourquoi ici et pas dans SimulationContext. Le contexte disparaît à la
 * sortie de Simulator::run() : sans cette information dans le résultat,
 * l'Application n'a aucun moyen de savoir quel côté a reçu son plateau, et
 * la couche Http devrait écrire « A » en dur. Ce serait vrai aujourd'hui,
 * où l'attribution est positionnelle, et faux sans le moindre test rouge le
 * jour où l'attribution canonique arrive.
 */
final class SimulationResultTest extends TestCase
{
    private function createBoard(string $vestigeId): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: $vestigeId,
            name: "Vestige {$vestigeId}",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 0,
            startingGold: 0,
            startingIncome: 0
        );
        $heroDef = new Hero(
            id: "hero_{$vestigeId}",
            name: "Hero {$vestigeId}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            [new CombatHero($heroDef)],
            [],
            goldAtCombatStart: 0
        );
    }

    private function createResult(CombatBoard $boardA, CombatBoard $boardB): SimulationResult
    {
        return new SimulationResult(
            winner: $boardA,
            resolution: Resolution::KNOCKOUT,
            totalTicks: 10,
            log: new CombatLog(),
            boardA: $boardA,
            boardB: $boardB,
        );
    }

    public function testItReportsTheSideEachBoardWasAssigned(): void
    {
        $boardA = $this->createBoard('shadow_vestige');
        $boardB = $this->createBoard('other_vestige');

        $result = $this->createResult($boardA, $boardB);

        self::assertSame(Side::A, $result->sideOf($boardA));
        self::assertSame(Side::B, $result->sideOf($boardB));
    }

    /**
     * Le cas qui justifie D-19 à lui seul.
     *
     * Avec un seul Vestige au catalogue, un combat miroir oppose deux plateaux
     * dont les Vestiges portent le **même identifiant**. Toute attribution
     * indexée sur cet identifiant confondrait les deux côtés — et le journal
     * deviendrait illisible pour l'un des deux joueurs. L'attribution doit
     * donc se faire sur l'identité d'objet.
     */
    public function testTwoMirrorBoardsSharingAVestigeIdStillGetDistinctSides(): void
    {
        $boardA = $this->createBoard('shadow_vestige');
        $boardB = $this->createBoard('shadow_vestige');

        self::assertSame(
            $boardA->getVestige()->getId(),
            $boardB->getVestige()->getId(),
            'Prérequis du test : les deux plateaux doivent bien partager un identifiant de Vestige.'
        );

        $result = $this->createResult($boardA, $boardB);

        self::assertSame(Side::A, $result->sideOf($boardA));
        self::assertSame(Side::B, $result->sideOf($boardB));
    }

    public function testItRejectsABoardThatDidNotTakePartInTheCombat(): void
    {
        $boardA = $this->createBoard('shadow_vestige');
        $boardB = $this->createBoard('other_vestige');
        $stranger = $this->createBoard('third_vestige');

        $result = $this->createResult($boardA, $boardB);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('did not take part in this combat');

        $result->sideOf($stranger);
    }

    public function testTheWinnerCanBeNamedBySideWithoutComparingBoards(): void
    {
        $boardA = $this->createBoard('shadow_vestige');
        $boardB = $this->createBoard('other_vestige');

        $result = new SimulationResult(
            winner: $boardB,
            resolution: Resolution::KNOCKOUT,
            totalTicks: 42,
            log: new CombatLog(),
            boardA: $boardA,
            boardB: $boardB,
        );

        // Plus d'assertNotNull : $winner n'est plus nullable (D-15). Le match
        // nul n'existant plus, un résultat sans vainqueur n'est plus un état
        // représentable — c'est tout l'objet du changement de type.
        self::assertSame(Side::B, $result->sideOf($result->winner));
    }

    /**
     * Le vainqueur seul ne dit pas comment le combat s'est fini.
     *
     * Une victoire par KO et une victoire au départage d'une double mort sont
     * deux issues différentes pour le joueur, et `winner` ne les distingue
     * pas. C'est `resolution` qui porte cette information (D-15, `02` §7.5).
     */
    public function testItReportsHowTheCombatWasResolved(): void
    {
        $boardA = $this->createBoard('shadow_vestige');
        $boardB = $this->createBoard('other_vestige');

        $result = new SimulationResult(
            winner: $boardA,
            resolution: Resolution::SIMULTANEOUS_RESOLVED,
            totalTicks: 7,
            log: new CombatLog(),
            boardA: $boardA,
            boardB: $boardB,
        );

        self::assertSame(Resolution::SIMULTANEOUS_RESOLVED, $result->resolution);
    }
}
