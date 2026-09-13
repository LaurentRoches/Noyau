<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\EnrageProcessor;
use App\Domain\Engine\Simulator;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class SimulatorTest extends TestCase
{
    private function createBoard(string $id, int $hp, array $items = []): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: "vestige_{$id}",
            name: "Vestige {$id}",
            affinity: 'shadow',
            baseHp: $hp,
            baseShield: 0,
            startingGold: 0,
            startingIncome: 0
        );
        $heroDef = new Hero(
            id: $id,
            name: "Hero {$id}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(
            new CombatVestige($vestigeDef),
            [new CombatHero($heroDef)],
            $items
        );
    }

    public function testRunExecutesCombatUntilHeroDefeat(): void
    {
        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );
        $effect = new Effect(
            trigger: Trigger::EVERY_N_TICKS,
            actions: [$action]
        );
        $itemDef = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [$effect]
        );

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($itemDef)]);
        $opponentBoard = $this->createBoard('opponent', 10, []);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        self::assertSame($playerBoard, $result->winner);
        self::assertSame(1, $result->totalTicks);
        self::assertFalse($opponentBoard->isAlive());
        self::assertTrue($playerBoard->isAlive());

        $events = $result->log->getEvents();
        self::assertCount(1, $events);
        self::assertSame(EventType::DAMAGE_DEALT, $events[0]->type);
    }

    public function testRunExecutesSymmetricalCombatAndStopsOnDefeat(): void
    {
        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );
        $effect = new Effect(
            trigger: Trigger::EVERY_N_TICKS,
            actions: [$action]
        );
        $daggerDef = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [$effect]
        );

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($daggerDef)]);
        $opponentBoard = $this->createBoard('opponent', 20, [new CombatItem($daggerDef)]);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        self::assertSame($playerBoard, $result->winner);
        self::assertSame(2, $result->totalTicks);
        self::assertSame(85, $playerBoard->getVestige()->getHp());
        self::assertSame(0, $opponentBoard->getVestige()->getHp());
    }

    public function testRunExecutesCombatWithDamageShieldAndHeal(): void
    {
        $damageAction = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 15,
            target: Target::ENEMY
        );
        $shieldAction = new Action(
            type: ActionType::GAIN_SHIELD,
            value: 5,
            target: Target::SELF
        );
        $opponentDamageAction = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 10,
            target: Target::ENEMY
        );
        $healAction = new Action(
            type: ActionType::HEAL,
            value: 10,
            target: Target::SELF
        );

        $dagger = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$damageAction])]
        );
        $shield = new Item(
            id: 'shield',
            name: 'Shield',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$shieldAction])]
        );
        $wand = new Item(
            id: 'wand',
            name: 'Wand',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$opponentDamageAction])]
        );
        $potion = new Item(
            id: 'potion',
            name: 'Potion',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$healAction])]
        );

        $playerBoard = $this->createBoard('player', 50, [new CombatItem($dagger), new CombatItem($shield)]);
        $opponentBoard = $this->createBoard('opponent', 30, [new CombatItem($wand), new CombatItem($potion)]);

        $simulator = new Simulator(maxTicks: 100);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        self::assertSame($playerBoard, $result->winner);
        self::assertSame(4, $result->totalTicks);
        self::assertSame(35, $playerBoard->getVestige()->getHp());
        self::assertSame(0, $opponentBoard->getVestige()->getHp());

        $eventTypes = array_map(fn ($e) => $e->type, $result->log->getEvents());
        self::assertContains(EventType::DAMAGE_DEALT, $eventTypes);
        self::assertContains(EventType::SHIELD_GAINED, $eventTypes);
        self::assertContains(EventType::HEAL_RECEIVED, $eventTypes);
    }

    public function testEnrageForcesAResolutionBetweenTwoPurelyDefensiveBoards(): void
    {
        $wardAction = new Action(
            type: ActionType::GAIN_SHIELD,
            value: 5,
            target: Target::SELF
        );
        $wardItem = new Item(
            id: 'ward_totem',
            name: 'Ward Totem',
            rarity: Rarity::COMMON,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 5,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$wardAction])]
        );

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($wardItem)]);
        // Bouclier de départ légèrement inférieur : garantit un vainqueur déterministe
        // plutôt qu'un double-KO simultané au même tick d'enrage.
        $opponentBoard = $this->createBoard('opponent', 95, [new CombatItem($wardItem)]);

        $simulator = new Simulator(maxTicks: 60);

        $result = $simulator->run($playerBoard, $opponentBoard, new Randomizer(new PcgOneseq128XslRr64(1)));

        self::assertNotNull($result->winner, 'Un vainqueur doit être forcé, pas de stalemate infini malgré deux builds purement défensifs.');
        self::assertLessThan(60, $result->totalTicks);
    }

    public function testCharacterizesSimultaneousStatusDeathAsNullWinnerRecordedAsDefeat(): void
    {
        $playerBoard = $this->createBoard('player', 3);
        $opponentBoard = $this->createBoard('opponent', 3);

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 5, durationTicks: 10)
        );
        $opponentBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 5, durationTicks: 10)
        );

        $simulator = new Simulator(maxTicks: 10);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        // Caractérisation du comportement ACTUEL (E-03/D-14) : StatusProcessor
        // n'a aucune garde entre les deux boards de sa boucle foreach. Les deux
        // vestiges meurent du même pulse de poison, au même tick, sans biais
        // entre eux — contrairement à Simulator (actions) et EnrageProcessor
        // (enrage), qui ont chacun une garde "pas de frappe sur cadavre".
        // Un double KO simultané par statut donne donc un vainqueur nul, que
        // GameRun::playRound() comptabilise comme une défaite pour le joueur
        // (result->winner !== $playerBoard). Ce test documente l'état actuel ;
        // il n'affirme pas que ce résultat est souhaitable.
        self::assertNull($result->winner);
        self::assertSame(1, $result->totalTicks);
        self::assertFalse($playerBoard->isAlive());
        self::assertFalse($opponentBoard->isAlive());
    }

    public function testCharacterizesUnboundedPoisonStackAccumulationForVenomousVial(): void
    {
        $poisonAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::POISON,
            stacks: 1,
            durationTicks: 30
        );
        $venomousVial = new Item(
            id: 'venomous_vial',
            name: 'Venomous vial',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 20,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$poisonAction])]
        );

        $playerBoard = $this->createBoard('player', 1000, [new CombatItem($venomousVial)]);
        $opponentBoard = $this->createBoard('opponent', 1000, []);

        // Enrage neutralisé : seule l'accumulation du statut nous intéresse ici.
        $simulator = new Simulator(
            maxTicks: 60,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );

        $simulator->run($playerBoard, $opponentBoard, new Randomizer(new PcgOneseq128XslRr64(1)));

        // Caractérisation du comportement ACTUEL (dette notée en 04 §3.4) :
        // cooldownTicks (20) < durationTicks (30), donc chaque réapplication
        // fusionne avec un statut encore actif (ActiveStatus::mergeWith fait
        // stacks +=). Après 3 activations (ticks 20, 40, 60), le stack a
        // grossi sans borne au lieu de rester à sa valeur de base (1).
        $poison = $opponentBoard->getVestige()->getStatus(StatusType::POISON);
        self::assertNotNull($poison);
        self::assertSame(3, $poison->getStacks());
        self::assertSame(30, $poison->getRemainingTicks());
    }

    public function testCharacterizesExactStackBoundingForFiresteel(): void
    {
        $burnAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::BURN,
            stacks: 2,
            durationTicks: 20
        );
        $firesteel = new Item(
            id: 'firesteel',
            name: 'Firesteel',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 20,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$burnAction])]
        );

        $playerBoard = $this->createBoard('player', 1000, [new CombatItem($firesteel)]);
        $opponentBoard = $this->createBoard('opponent', 1000, []);

        $simulator = new Simulator(
            maxTicks: 60,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );

        $simulator->run($playerBoard, $opponentBoard, new Randomizer(new PcgOneseq128XslRr64(1)));

        // cooldownTicks (20) == durationTicks (20) : le statut expire pile au
        // tick où l'objet se réactive, removeExpiredStatuses() le purge avant
        // la fusion → chaque réapplication repart à neuf. Après 3 activations
        // (ticks 20, 40, 60), le stack reste exactement à sa valeur de base.
        $burn = $opponentBoard->getVestige()->getStatus(StatusType::BURN);
        self::assertNotNull($burn);
        self::assertSame(2, $burn->getStacks());
        self::assertSame(20, $burn->getRemainingTicks());
    }

    public function testCharacterizesExactStackBoundingForMolotovCocktail(): void
    {
        $burnAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::ENEMY,
            status: StatusType::BURN,
            stacks: 3,
            durationTicks: 20
        );
        $molotov = new Item(
            id: 'molotov_cocktail',
            name: 'Molotov Cocktail',
            rarity: Rarity::RARE,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 20,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$burnAction])]
        );

        $playerBoard = $this->createBoard('player', 1000, [new CombatItem($molotov)]);
        $opponentBoard = $this->createBoard('opponent', 1000, []);

        $simulator = new Simulator(
            maxTicks: 60,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );

        $simulator->run($playerBoard, $opponentBoard, new Randomizer(new PcgOneseq128XslRr64(1)));

        $burn = $opponentBoard->getVestige()->getStatus(StatusType::BURN);
        self::assertNotNull($burn);
        self::assertSame(3, $burn->getStacks());
        self::assertSame(20, $burn->getRemainingTicks());
    }

    public function testCharacterizesUnboundedWardStackAndCompoundingShieldForShadowArmor(): void
    {
        $shieldAction = new Action(
            type: ActionType::GAIN_SHIELD,
            value: 17,
            target: Target::SELF
        );
        $wardAction = new Action(
            type: ActionType::APPLY_STATUS,
            target: Target::SELF,
            status: StatusType::WARD,
            stacks: 1,
            durationTicks: 30
        );
        $shadowArmor = new Item(
            id: 'shadow_armor',
            name: 'Shadow armor',
            rarity: Rarity::LEGENDARY,
            affinity: 'shadow',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 18,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$shieldAction, $wardAction])]
        );

        $playerBoard = $this->createBoard('player', 1000, [new CombatItem($shadowArmor)]);
        $opponentBoard = $this->createBoard('opponent', 1000, []);

        $simulator = new Simulator(
            maxTicks: 60,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );

        $simulator->run($playerBoard, $opponentBoard, new Randomizer(new PcgOneseq128XslRr64(1)));

        // Caractérisation du comportement ACTUEL (dette notée en 04 §3.4) :
        // WARD s'accumule sans borne comme les autres statuts (cooldownTicks 18
        // < durationTicks 30, jamais expiré à la réapplication). Mais WARD se
        // pulse lui-même chaque tick (StatusProcessor::pulseWard), donc l'effet
        // composé : plus les stacks grossissent, plus le gain de bouclier passif
        // par tick grossit avec eux. gainShield() n'a pas de plafond (design
        // intentionnel, cf. design-rules.md).
        $ward = $playerBoard->getVestige()->getStatus(StatusType::WARD);
        self::assertNotNull($ward);
        self::assertSame(3, $ward->getStacks());
        self::assertSame(24, $ward->getRemainingTicks());
        self::assertSame(123, $playerBoard->getVestige()->getShield());
    }

    public function testCharacterizesPlayerBoardPriorityOnSimultaneousActionDeath(): void
    {
        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 100,
            target: Target::ENEMY
        );
        $lethalItem = new Item(
            id: 'lethal_dagger',
            name: 'Lethal Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$action])]
        );

        $playerBoard = $this->createBoard('player', 50, [new CombatItem($lethalItem)]);
        $opponentBoard = $this->createBoard('opponent', 50, [new CombatItem($lethalItem)]);

        $simulator = new Simulator(maxTicks: 10);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        // Caractérisation du comportement ACTUEL (D-14, sens "action d'objet") :
        // getBoards() retourne [player, opponent] ; TickEngine génère donc les
        // PendingAction du joueur avant celles de l'adversaire pour un même
        // tick. L'action du joueur tue l'adversaire en premier ; le break de
        // Simulator::run() empêche ensuite l'action de l'adversaire (déjà
        // générée, en attente dans $pendingActions) de s'exécuter. Le joueur
        // gagne sur une mort simultanée par action — sens opposé à l'enrage
        // (cf. EnrageProcessorTest::testProcessTickStopsBeforeSecondBoardWhenFirstDies),
        // c'est précisément l'écart D-14.
        self::assertSame($playerBoard, $result->winner);
        self::assertTrue($playerBoard->isAlive());
        self::assertFalse($opponentBoard->isAlive());
    }

    public function testCharacterizesPhaseOrderWithinATickAsStatusesBeforeActions(): void
    {
        $damageAction = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 10,
            target: Target::ENEMY
        );
        $dagger = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$damageAction])]
        );

        $playerBoard = $this->createBoard('player', 1000, [new CombatItem($dagger)]);
        $opponentBoard = $this->createBoard('opponent', 1000, []);

        $opponentBoard->getVestige()->takeRawDamage(2); // HP = 998, proche du plafond de 1000
        $opponentBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::REGEN, stacks: 5, durationTicks: 30)
        );

        // Enrage neutralisé : maxTicks=1 donnerait par défaut triggerTick=1
        // (max(1, maxTicks-50)) et déclencherait l'enrage dès ce tick, ce qui
        // perturberait ce test dont l'objet est uniquement l'ordre statuts/actions.
        $simulator = new Simulator(
            maxTicks: 1,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );

        $simulator->run($playerBoard, $opponentBoard, new Randomizer(new PcgOneseq128XslRr64(1)));

        self::assertSame(990, $opponentBoard->getVestige()->getHp());
    }

    public function testPlayerWinsWhenPoisonKillsOpponentEvenIfEnrageWouldTriggerSameTick(): void
    {
        $playerBoard = $this->createBoard('player', 50, []);
        $opponentBoard = $this->createBoard('opponent', 3, []);

        $opponentBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 5, durationTicks: 10)
        );

        // Enrage volontairement dévastateur et déclenché dès ce tick : s'il
        // s'exécute malgré la mort de l'adversaire par poison, il tuera aussi
        // le joueur (50 HP < 1000 de dégâts) et transformera une victoire en
        // double KO. C'est exactement le scénario d'E-03.
        $simulator = new Simulator(
            maxTicks: 1,
            enrageProcessor: new EnrageProcessor(triggerTick: 1, baseDamage: 1000)
        );

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            new Randomizer(new PcgOneseq128XslRr64(1))
        );

        // Comportement ATTENDU (E-03) : l'adversaire meurt du poison avant que
        // l'enrage n'ait la moindre chance de s'exécuter sur ce tick. Le joueur
        // doit gagner et rester vivant, pas subir un enrage sur cadavre adverse.
        self::assertSame($playerBoard, $result->winner);
        self::assertTrue($playerBoard->isAlive());
        self::assertFalse($opponentBoard->isAlive());
    }
}
