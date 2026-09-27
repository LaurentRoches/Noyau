<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\Resolution;
use App\Domain\Enum\Side;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use App\Domain\Snapshot\BoardRecord;
use App\Domain\Snapshot\CombatRecord;
use App\Domain\Snapshot\SnapshotRecipe;
use App\Persistence\CombatRecordsRepository;
use App\Tests\Support\CreatesInMemoryDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * L'écriture des archives de combat (`04` §6, D-16).
 *
 * **Écriture seule, délibérément.** Personne ne relit encore ces lignes : le
 * corpus PvP est un chantier 11, et le rejeu octet pour octet est le commit
 * suivant. Une méthode de lecture écrite aujourd'hui serait une méthode
 * publique sans appelant — donc une forme de retour à figer avant de savoir ce
 * qu'on en attend. Ces tests lisent la ligne en SQL direct, comme
 * `GameRunRepositoryTest` le fait déjà pour sa colonne.
 *
 * **Ce qui est stocké tel quel.** Les deux enveloppes en JSON canonique, dans
 * deux colonnes `TEXT`. Le dépôt ne les décode pas, ne les reformate pas et ne
 * les valide pas : le format **est** le sujet du chantier, et toute
 * transformation à l'écriture le rendrait irreproductible à la lecture.
 */
final class CombatRecordsRepositoryTest extends TestCase
{
    use CreatesInMemoryDatabase;

    private function boardRecord(string $vestigeId, int $gold): BoardRecord
    {
        $board = new CombatBoard(
            new CombatVestige(new Vestige(
                id: $vestigeId,
                name: 'Vestige ' . $vestigeId,
                affinity: 'shadow',
                baseHp: 100,
                baseShield: 10,
                startingGold: 20,
                startingIncome: 5
            )),
            [new CombatHero(new Hero(
                id: 'shadow_bearer',
                name: "Shadow's Bearer",
                affinity: 'shadow',
                itemSlots: 2
            ))],
            [new CombatItem(new Item(
                id: 'rusty_dagger',
                name: 'Rusty Dagger',
                rarity: Rarity::COMMON,
                affinity: 'neutral',
                size: ItemSize::ONE_HAND,
                cooldownTicks: 4,
                effects: [new Effect(Trigger::ON_ATTACK, [
                    new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY),
                ])]
            ))],
            goldAtCombatStart: $gold
        );

        return BoardRecord::fromBoard(
            $board,
            new SnapshotRecipe(
                vestigeId: $vestigeId,
                heroIds: ['shadow_bearer'],
                itemIdsByHero: ['shadow_bearer' => ['rusty_dagger']],
            ),
            contentVersion: 'a1b2c3',
        );
    }

    private function record(): CombatRecord
    {
        return new CombatRecord(
            boardA: $this->boardRecord('shadow_vestige', 40),
            boardB: $this->boardRecord('other_vestige', 0),
            combatSeed: str_repeat('ab', 32),
            resolution: Resolution::TIMEOUT_RESOLVED,
            winnerSide: Side::B,
        );
    }

    /**
     * La ligne écrite, colonne par colonne.
     *
     * Les deux enveloppes sont comparées à leur JSON canonique **exact** : ce
     * qui sort de la base doit être ce qui y est entré, à l'octet près. C'est
     * toute la promesse du chantier, et un dépôt qui reformaterait au passage
     * la casserait sans qu'aucune exception ne soit levée.
     */
    public function testItSavesACombatRecordAsOneRow(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $repository = new CombatRecordsRepository($pdo);
        $record = $this->record();

        $repository->save('run-123', 4, $record);

        $statement = $pdo->prepare('SELECT * FROM combat_records WHERE run_id = :id');
        $statement->execute(['id' => 'run-123']);
        /** @var array<string, string> $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertSame('run-123', $row['run_id']);
        self::assertSame(4, (int) $row['round']);
        self::assertSame($record->boardA->toCanonicalJson(), $row['board_a']);
        self::assertSame($record->boardB->toCanonicalJson(), $row['board_b']);
        self::assertSame(str_repeat('ab', 32), $row['combat_seed']);
        self::assertSame($record->engineVersion, (int) $row['engine_version']);
        self::assertSame('TIMEOUT_RESOLVED', $row['resolution']);
        self::assertSame('B', $row['winner_side']);
        self::assertNotSame('', $row['created_at']);
    }

    /**
     * Les deux enveloppes ne sont pas interchangeables.
     *
     * Un dépôt qui les écrirait dans le mauvais ordre produirait un corpus
     * entier de combats en miroir, et rien ne le signalerait : les deux
     * colonnes ont le même type et la même forme. L'or d'entrée de combat est
     * le seul discriminant qui ne suppose rien du catalogue.
     */
    public function testItDoesNotSwapTheTwoSides(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $repository = new CombatRecordsRepository($pdo);

        $repository->save('run-123', 1, $this->record());

        $statement = $pdo->prepare('SELECT board_a, board_b FROM combat_records WHERE run_id = :id');
        $statement->execute(['id' => 'run-123']);
        /** @var array{board_a: string, board_b: string} $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertStringContainsString('"goldAtCombatStart":40', $row['board_a']);
        self::assertStringContainsString('"goldAtCombatStart":0', $row['board_b']);
    }

    /**
     * Une manche ne s'archive qu'une fois.
     *
     * La clé composite `(run_id, round)` transforme une double écriture en
     * erreur franche plutôt qu'en doublon silencieux. Ce n'est pas théorique :
     * `04` §7 relève qu'aucune garde anti-double-soumission n'existe sur
     * `resolveRound`, et c'est la base qui tient ici le rôle de dernier filet.
     */
    public function testItRefusesASecondRecordForTheSameRunAndRound(): void
    {
        $pdo = $this->createInMemoryDatabase();
        $repository = new CombatRecordsRepository($pdo);

        $repository->save('run-123', 1, $this->record());

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed');

        $repository->save('run-123', 1, $this->record());
    }
}
