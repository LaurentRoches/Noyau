<?php

declare(strict_types=1);

namespace App\Tests\Domain\Snapshot;

use App\Domain\Engine\EngineVersion;
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
use PHPUnit\Framework\TestCase;

/**
 * L'enregistrement d'un combat : deux plateaux archivés et ce qui les a
 * opposés (`04` §6, `07` §6 commit 13).
 *
 * **Pourquoi `CombatRecord` et non `CombatSnapshot`.** Le docblock de
 * `BoardRecord` le dit déjà : deux noms quasi identiques pour un plateau et
 * pour un combat seraient une confusion programmée. `BoardRecord` archive **un**
 * plateau ; celui-ci archive **la rencontre**.
 *
 * **Ce qu'il n'a pas, délibérément.** Aucune sérialisation canonique. Les deux
 * enveloppes portent déjà la leur, et la table stocke ces deux chaînes plus des
 * colonnes scalaires. Une troisième forme canonique serait un format de plus à
 * figer, donc une décision de plus à ne jamais pouvoir reprendre — pour aucun
 * appelant.
 *
 * **Ni `viewerSide`, ni « joueur ».** Les enveloppes sont rangées par côté A/B.
 * Un enregistrement archivé ne connaît aucun spectateur : c'est ce qui le rend
 * exploitable par les deux joueurs d'un futur PvP, et c'est la raison d'être
 * des libellés neutres de D-19.
 */
final class CombatRecordTest extends TestCase
{
    private function board(string $vestigeId, int $gold): CombatBoard
    {
        return new CombatBoard(
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
    }

    private function boardRecord(string $vestigeId, int $gold): BoardRecord
    {
        return BoardRecord::fromBoard(
            $this->board($vestigeId, $gold),
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

    public function testItCarriesWhatOpposedTheTwoBoards(): void
    {
        $record = $this->record();

        self::assertSame(str_repeat('ab', 32), $record->combatSeed);
        self::assertSame(Resolution::TIMEOUT_RESOLVED, $record->resolution);
        self::assertSame(Side::B, $record->winnerSide);
        self::assertStringContainsString('"goldAtCombatStart":40', $record->boardA->toCanonicalJson());
        self::assertStringContainsString('"goldAtCombatStart":0', $record->boardB->toCanonicalJson());
    }

    /**
     * La version de moteur est estampillée par l'enregistrement lui-même, pas
     * fournie par l'appelant.
     *
     * Un enregistrement n'est créé qu'au moment où le combat est simulé : la
     * version courante **est** la bonne, et la laisser passer par un paramètre
     * ouvrirait la seule façon de se tromper.
     *
     * **La redondance avec les deux enveloppes est assumée.** Chaque
     * `BoardRecord` porte déjà son `engineVersion`. Le champ existe ici parce
     * que la colonne qui le portera doit pouvoir filtrer un bassin
     * d'appariement au chantier 11 sans décoder cinq mille JSON. Ce test fige
     * la contrainte qui rend la redondance sûre : les deux valeurs ne peuvent
     * pas diverger.
     */
    public function testItStampsTheCurrentEngineVersionAndAgreesWithItsBoards(): void
    {
        $record = $this->record();

        self::assertSame(EngineVersion::CURRENT, $record->engineVersion);
        self::assertStringContainsString(
            '"engineVersion":' . $record->engineVersion,
            $record->boardA->toCanonicalJson(),
        );
        self::assertStringContainsString(
            '"engineVersion":' . $record->engineVersion,
            $record->boardB->toCanonicalJson(),
        );
    }
}
