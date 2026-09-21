<?php

declare(strict_types=1);

namespace App\Tests\Domain\Snapshot;

use App\Domain\Engine\EngineVersion;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
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
use App\Domain\Snapshot\SnapshotRecipe;
use PHPUnit\Framework\TestCase;

/**
 * L'enveloppe de provenance autour d'une photographie (D-16, `04` §5.5).
 *
 * **Pourquoi elle est séparée de `BoardSnapshot`.** La photographie fait foi
 * au rejeu ; la recette, la version de contenu et les versions n'y servent à
 * rien. C'est aussi ce qui permet à `SimulationContext` de comparer des
 * photographies **nues** pour attribuer les côtés (§3.6) : y mêler une
 * provenance ferait dépendre l'attribution d'une donnée qui ne décrit pas le
 * combat.
 *
 * **Pourquoi `BoardRecord` et non `CombatSnapshot`.** `04` §6 nomme
 * « enregistrement de combat » la structure à **deux** plateaux — snapshots A
 * et B, `combatSeed`, `engineVersion`, `resolution`, `winnerSide` — qui
 * arrivera au commit 10. Deux noms quasi identiques pour un plateau et pour
 * un combat seraient une confusion programmée.
 *
 * **Pourquoi deux versions et non une** (`04` §5.3). `engineVersion` dit avec
 * quelles *règles* le journal a été produit ; la version de format dit comment
 * *lire cette enveloppe*. Dès le chantier 4, `Item`, `Effect` et `Action`
 * changent de forme sans que le moteur bouge — et le moteur peut être corrigé
 * sans que la forme change. Un seul champ forcerait à invalider l'un pour
 * l'autre.
 */
final class BoardRecordTest extends TestCase
{
    private function board(int $heroCount = 1, int $itemCount = 1): CombatBoard
    {
        $heroes = [];
        for ($i = 0; $i < $heroCount; $i++) {
            $heroes[] = new CombatHero(new Hero(
                id: 'shadow_bearer',
                name: "Shadow's Bearer",
                affinity: 'shadow',
                itemSlots: 2
            ));
        }

        $items = [];
        for ($i = 0; $i < $itemCount; $i++) {
            $items[] = new CombatItem(new Item(
                id: 'rusty_dagger',
                name: 'Rusty Dagger',
                rarity: Rarity::COMMON,
                affinity: 'neutral',
                size: ItemSize::ONE_HAND,
                cooldownTicks: 4,
                effects: [new Effect(Trigger::ON_ATTACK, [
                    new Action(type: ActionType::DEAL_DAMAGE, value: 15, target: Target::ENEMY),
                ])]
            ));
        }

        return new CombatBoard(
            new CombatVestige(new Vestige(
                id: 'shadow_vestige',
                name: 'Shadow Vestige',
                affinity: 'shadow',
                baseHp: 100,
                baseShield: 10,
                startingGold: 20,
                startingIncome: 5
            )),
            $heroes,
            $items,
            goldAtCombatStart: 40
        );
    }

    private function recipe(): SnapshotRecipe
    {
        return new SnapshotRecipe(
            vestigeId: 'shadow_vestige',
            heroIds: ['shadow_bearer'],
            itemIdsByHero: ['shadow_bearer' => ['rusty_dagger']],
        );
    }

    private const string PHOTOGRAPH = '"board":{"goldAtCombatStart":40,"heroes":[{"id":"shadow_bearer"}],'
        . '"items":[{"affinity":"neutral","cooldownTicks":4,"effects":[{"actions":[{"target":"ENEMY",'
        . '"type":"DEAL_DAMAGE","value":15}],"trigger":"ON_ATTACK"}],"id":"rusty_dagger","name":"Rusty Dagger",'
        . '"rarity":"COMMON","size":"ONE_HAND"}],"vestige":{"baseHp":100,"baseShield":10,"id":"shadow_vestige"}}';

    private const string RECIPE = '"recipe":{"heroIds":["shadow_bearer"],'
        . '"itemIdsByHero":{"shadow_bearer":["rusty_dagger"]},"vestigeId":"shadow_vestige"}';

    /**
     * La forme complète, épinglée octet pour octet.
     *
     * `engineVersion` et `formatVersion` y figurent en littéral : les relever
     * est un acte délibéré, et voir ce test rougir est exactement le signal
     * attendu — pas un test à rafistoler.
     */
    public function testItWrapsThePhotographWithItsProvenance(): void
    {
        $record = BoardRecord::fromBoard($this->board(), $this->recipe(), contentVersion: null);

        self::assertSame(
            '{' . self::PHOTOGRAPH . ',"engineVersion":1,"formatVersion":1,' . self::RECIPE . '}',
            $record->toCanonicalJson()
        );
    }

    /**
     * `contentVersion` est fourni par l'appelant, pas calculé ici.
     *
     * C'est l'empreinte des quatre catalogues (`04` §6.3), que personne ne
     * calcule encore. Le champ existe malgré tout, pour la même raison que
     * `viewerSide` trois commits plus tôt : le jour où la valeur arrive,
     * aucune ligne de lecture ne bouge.
     */
    public function testItCarriesTheContentVersionWhenTheCallerSuppliesOne(): void
    {
        $record = BoardRecord::fromBoard($this->board(), $this->recipe(), contentVersion: 'a1b2c3');

        self::assertSame(
            '{' . self::PHOTOGRAPH . ',"contentVersion":"a1b2c3","engineVersion":1,"formatVersion":1,'
            . self::RECIPE . '}',
            $record->toCanonicalJson()
        );
    }

    /**
     * Absent plutôt que `null`, comme partout ailleurs dans le format : D-19
     * n'autorise que `int`, `string` et `bool`, et `04` §5.3 fait de l'absence
     * le mécanisme de migration.
     */
    public function testAnAbsentContentVersionIsOmittedRatherThanEncodedAsNull(): void
    {
        $json = BoardRecord::fromBoard($this->board(), $this->recipe(), contentVersion: null)->toCanonicalJson();

        self::assertStringNotContainsString('contentVersion', $json);
        self::assertStringNotContainsString('null', $json);
    }

    /**
     * La version de moteur vient du moteur, pas d'une constante recopiée.
     *
     * Deux sources distinctes pour deux champs distincts : relever l'une ne
     * doit pas entraîner l'autre.
     */
    public function testTheTwoVersionsComeFromTwoIndependentSources(): void
    {
        $json = BoardRecord::fromBoard($this->board(), $this->recipe(), contentVersion: null)->toCanonicalJson();

        self::assertStringContainsString('"engineVersion":' . EngineVersion::CURRENT, $json);
        self::assertStringContainsString('"formatVersion":' . BoardRecord::FORMAT_VERSION, $json);
    }

    /**
     * Une recette qui ne décrit pas sa photographie est une provenance qui
     * ment — pire qu'une provenance absente, puisqu'elle sera crue.
     *
     * Le contrôle reste volontairement grossier : nombre de héros et nombre
     * d'objets. Comparer les identifiants un à un supposerait que
     * `HeroSkillDecorator` conserve l'identifiant de l'objet qu'il décore, ce
     * qui n'est pas vérifié ici et ne doit pas être supposé.
     */
    public function testItRejectsARecipeThatDescribesADifferentNumberOfHeroes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipe describes 1 heroes but the board carries 2.');

        BoardRecord::fromBoard($this->board(heroCount: 2), $this->recipe(), contentVersion: null);
    }

    public function testItRejectsARecipeThatDescribesADifferentNumberOfItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipe describes 1 items but the board carries 2.');

        BoardRecord::fromBoard($this->board(itemCount: 2), $this->recipe(), contentVersion: null);
    }
}
