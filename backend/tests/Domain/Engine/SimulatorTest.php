<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\EnrageProcessor;
use App\Domain\Engine\SimulationResult;
use App\Domain\Engine\Simulator;
use App\Domain\Enum\ActionType;
use App\Domain\Enum\EventType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\Resolution;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Event\CombatEvent;
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

final class SimulatorTest extends TestCase
{
    private const string COMBAT_SEED = '2a1fc9b42d6f7deabb34ec8d303950e95a203eb05bfec19c42e1eb7ac1fca71a';

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

    /**
     * Unique événement de départage du journal, ou null s'il n'y en a pas.
     *
     * Un combat ne peut se départager qu'une fois : en trouver deux serait un
     * défaut, d'où l'assertion plutôt qu'un simple `array_values()[0]`.
     */
    private function tiebreakEventOf(SimulationResult $result): ?CombatEvent
    {
        $events = array_values(array_filter(
            $result->log->getEvents(),
            static fn (CombatEvent $event): bool => $event->type === EventType::RESOLUTION_TIEBREAK,
        ));

        self::assertLessThanOrEqual(1, count($events), 'Un combat ne se départage qu\'une fois.');

        return $events[0] ?? null;
    }

    /**
     * @return list<CombatEvent>
     */
    private function eventsOfType(SimulationResult $result, EventType $type): array
    {
        return array_values(array_filter(
            $result->log->getEvents(),
            static fn (CombatEvent $event): bool => $event->type === $type,
        ));
    }

    private function silentEnrageSimulator(int $maxTicks): Simulator
    {
        return new Simulator(
            maxTicks: $maxTicks,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
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
            self::COMBAT_SEED
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
            self::COMBAT_SEED
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
            self::COMBAT_SEED
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

        $result = $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        self::assertNotNull($result->winner, 'Un vainqueur doit être forcé, pas de stalemate infini malgré deux builds purement défensifs.');
        self::assertLessThan(60, $result->totalTicks);
    }

    public function testASimultaneousStatusDeathIsResolvedInsteadOfLeftWithoutAWinner(): void
    {
        $playerBoard = $this->createBoard('player', 3);
        $opponentBoard = $this->createBoard('opponent', 3);

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 5, durationTicks: 10, sourceId: 'venomous_vial')
        );
        $opponentBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 5, durationTicks: 10, sourceId: 'venomous_vial')
        );

        $simulator = new Simulator(maxTicks: 10);

        $result = $simulator->run(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );

        // `StatusProcessor` n'a toujours aucune garde entre les deux plateaux de
        // sa boucle : les deux Vestiges meurent du même pulse de poison, au
        // même tick, sans biais entre eux. Ce qui change, c'est la suite.
        self::assertSame(1, $result->totalTicks);
        self::assertFalse($playerBoard->isAlive());
        self::assertFalse($opponentBoard->isAlive());

        // Le match nul n'existe plus (D-15) : ce combat a un vainqueur.
        self::assertContains($result->winner, [$playerBoard, $opponentBoard]);
        self::assertSame(Resolution::SIMULTANEOUS_RESOLVED, $result->resolution);

        // **Le critère est provisoire, et le test le dit.** Les PV sont bornés
        // à zéro : après une double mort les deux Vestiges sont à 0, et l'état
        // final ne départage rien. D-14 remplacera ce critère par l'état relevé
        // AVANT la phase, qui tranchera presque toujours avant le tirage.
        // Jusque-là, une double mort se joue à pile ou face — c'est un choix
        // assumé, pas un oubli, et c'est cette ligne qui bougera au commit
        // suivant.
        // `resolution` est répété ici alors qu'il vit déjà sur SimulationResult
        // (`04` §3.5). Le CombatLog est le seul artefact que le client reçoit,
        // qu'on archive et qu'on rejoue : sans ce champ, un départage au tirage
        // sur double KO au tick 7 et un départage au tirage sur timeout au tick
        // 500 produiraient exactement le même événement.
        $tiebreak = $this->tiebreakEventOf($result);
        self::assertNotNull($tiebreak);
        self::assertSame([
            'criterion' => 'FINAL_HP_AND_SHIELD',
            'decidedBy' => 'RANDOM',
            'resolution' => 'SIMULTANEOUS_RESOLVED',
            'valueA' => 0,
            'valueB' => 0,
            'winnerSide' => $result->sideOf($result->winner)->value,
        ], $tiebreak->payload);
    }

    public function testAKnockoutIsReportedAsSuchAndEmitsNoTiebreak(): void
    {
        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 10,
            target: Target::ENEMY
        );
        $daggerDef = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$action])]
        );

        $playerBoard = $this->createBoard('player', 100, [new CombatItem($daggerDef)]);
        $opponentBoard = $this->createBoard('opponent', 10, []);

        $result = $this->silentEnrageSimulator(50)->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        self::assertSame($playerBoard, $result->winner);
        self::assertSame(Resolution::KNOCKOUT, $result->resolution);

        // Un KO ne se départage pas : émettre l'événement quand même
        // apprendrait au joueur qu'il a gagné « au départage » une victoire
        // franche.
        self::assertNull($this->tiebreakEventOf($result));
    }

    public function testATimeoutIsDecidedOnFinalHpAndShieldAndRecordsTheComparison(): void
    {
        // Aucun objet des deux côtés, enrage neutralisé : rien ne se passe
        // pendant cinq ticks et les deux plateaux sont vivants à l'échéance.
        $playerBoard = $this->createBoard('player', 100);
        $opponentBoard = $this->createBoard('opponent', 50);

        $result = $this->silentEnrageSimulator(5)->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        self::assertTrue($playerBoard->isAlive());
        self::assertTrue($opponentBoard->isAlive());

        self::assertSame($playerBoard, $result->winner);
        self::assertSame(Resolution::TIMEOUT_RESOLVED, $result->resolution);

        $tiebreak = $this->tiebreakEventOf($result);
        self::assertNotNull($tiebreak);
        self::assertSame([
            'criterion' => 'FINAL_HP_AND_SHIELD',
            'decidedBy' => 'COMPARISON',
            'resolution' => 'TIMEOUT_RESOLVED',
            'valueA' => 100,
            'valueB' => 50,
            'winnerSide' => 'A',
        ], $tiebreak->payload);
    }

    public function testAStrictlyEqualTimeoutFallsBackToADrawOnTheOrderStream(): void
    {
        $playerBoard = $this->createBoard('player', 100);
        $opponentBoard = $this->createBoard('opponent', 100);

        $result = $this->silentEnrageSimulator(5)->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        // Le cas que `02` §7.5 nomme explicitement : le miroir strict, où tout
        // critère fondé sur le contenu des plateaux donne une égalité. Sans le
        // tirage, la règle n'aurait pas de réponse ici.
        self::assertContains($result->winner, [$playerBoard, $opponentBoard]);
        self::assertSame(Resolution::TIMEOUT_RESOLVED, $result->resolution);

        $tiebreak = $this->tiebreakEventOf($result);
        self::assertNotNull($tiebreak);
        self::assertSame([
            'criterion' => 'FINAL_HP_AND_SHIELD',
            'decidedBy' => 'RANDOM',
            'resolution' => 'TIMEOUT_RESOLVED',
            'valueA' => 100,
            'valueB' => 100,
            'winnerSide' => $result->sideOf($result->winner)->value,
        ], $tiebreak->payload);
    }

    public function testTheDrawDependsOnTheCombatSeed(): void
    {
        $sides = [];

        for ($i = 1; $i <= 8; ++$i) {
            $result = $this->silentEnrageSimulator(5)->run(
                $this->createBoard('player', 100),
                $this->createBoard('opponent', 100),
                hash('sha256', (string) $i),
            );

            $sides[] = $result->sideOf($result->winner)->value;
        }

        // Huit graines **fixes** : le test reste parfaitement déterministe.
        // Ce qu'il interdit, c'est un départage codé en dur — lequel rendrait
        // les huit résultats identiques. Un vrai tirage qui donnerait huit fois
        // le même côté serait un hasard à 1/128 ; si cela arrivait, ce sont les
        // graines qu'il faudrait changer, pas la règle.
        self::assertContains('A', $sides);
        self::assertContains('B', $sides);
    }

    public function testTheSameCombatSeedAlwaysDrawsTheSameWinner(): void
    {
        $first = $this->silentEnrageSimulator(5)->run(
            $this->createBoard('player', 100),
            $this->createBoard('opponent', 100),
            self::COMBAT_SEED,
        );
        $second = $this->silentEnrageSimulator(5)->run(
            $this->createBoard('player', 100),
            $this->createBoard('opponent', 100),
            self::COMBAT_SEED,
        );

        // NF-01 : le départage consomme de l'aléa, donc il doit le consommer
        // sur la graine du combat et nulle part ailleurs.
        self::assertSame(
            $first->sideOf($first->winner),
            $second->sideOf($second->winner),
        );
    }

    public function testVenomousVialStabilizesAtTwoPoisonInstances(): void
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

        $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        // Modèle par instances (D-20) : chaque activation crée une instance
        // indépendante, aucune fusion. cooldownTicks (20) < durationTicks (30),
        // donc le régime permanent se stabilise de lui-même à
        // ceil(30 / 20) = 2 instances vivantes, sans qu'aucun plafond soit écrit.
        // Activations aux ticks 20, 40 et 60 ; celle du tick 20 a expiré au
        // tick 50. Restent celles des ticks 40 (10 ticks restants) et 60 (30).
        $vestige = $opponentBoard->getVestige();
        $poison = $vestige->getAggregatedStatus(StatusType::POISON);
        self::assertCount(2, $vestige->getStatusInstances(StatusType::POISON));
        self::assertSame(2, $poison->stacks);
        self::assertSame(30, $poison->remainingTicks);
    }

    public function testFiresteelKeepsASingleBurnInstanceAtBaseStacks(): void
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

        $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        // cooldownTicks (20) == durationTicks (20) : l'instance expire et est
        // purgée à la phase de statuts du tick où l'objet se réactive, donc
        // avant la phase d'actions qui en crée une nouvelle. Il n'y a jamais
        // plus d'une instance vivante, et les stacks restent à leur valeur de
        // base. Valeurs inchangées par le passage au modèle par instances.
        $vestige = $opponentBoard->getVestige();
        $burn = $vestige->getAggregatedStatus(StatusType::BURN);
        self::assertCount(1, $vestige->getStatusInstances(StatusType::BURN));
        self::assertSame(2, $burn->stacks);
        self::assertSame(20, $burn->remainingTicks);
    }

    public function testMolotovCocktailKeepsASingleBurnInstanceAtBaseStacks(): void
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

        $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        // Même structure que firesteel : cooldown et durée égaux, une seule
        // instance vivante à tout instant.
        $vestige = $opponentBoard->getVestige();
        $burn = $vestige->getAggregatedStatus(StatusType::BURN);
        self::assertCount(1, $vestige->getStatusInstances(StatusType::BURN));
        self::assertSame(3, $burn->stacks);
        self::assertSame(20, $burn->remainingTicks);
    }

    public function testShadowArmorStabilizesAtTwoWardInstancesWithBoundedShield(): void
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

        $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        // Modèle par instances (D-20) : cooldownTicks (18) < durationTicks (30),
        // régime permanent à ceil(30 / 18) = 2 instances. Activations aux ticks
        // 18, 36 et 54 ; celle du tick 18 a expiré au tick 48.
        //
        // Bouclier : 3 activations × 17 de GAIN_SHIELD direct = 51, plus les
        // pulses de WARD. Chaque instance pulse 1 par tick vécu : 30 pour celle
        // du tick 18, 24 pour celle du tick 36, 6 pour celle du tick 54, soit
        // 60 stack-ticks. Total 111, contre 123 sous l'ancien modèle à fusion
        // où les stacks cumulés faisaient grossir le pulse lui-même.
        // gainShield() reste sans plafond, décision de design (corebound-affinities §2).
        $vestige = $playerBoard->getVestige();
        $ward = $vestige->getAggregatedStatus(StatusType::WARD);
        self::assertCount(2, $vestige->getStatusInstances(StatusType::WARD));
        self::assertSame(2, $ward->stacks);
        self::assertSame(24, $ward->remainingTicks);
        self::assertSame(111, $vestige->getShield());
    }

    /**
     * Test de non-régression et valeur de référence du chantier 3b.
     *
     * Toute variation du chiffre ci-dessous signale un changement du modèle de
     * statut, pas un ajustement d'équilibrage : les valeurs de shadow_armor
     * relèvent du chantier 10.
     */
    public function testShadowArmorProducesABoundedReferenceShieldOverFiveHundredTicks(): void
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
            maxTicks: 500,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );

        $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        // 27 activations (ticks 18 à 486), soit 27 × 17 = 459 de GAIN_SHIELD
        // direct, plus 794 stack-ticks de WARD : total 1253.
        //
        // Le compte d'instances OSCILLE entre floor(30 / 18) = 1 et
        // ceil(30 / 18) = 2 : ceil est le pic atteint juste après une
        // application, pas un régime constant. La moyenne réelle vaut
        // 30 / 18 = 1,67 stack par tick, ce qui est précisément ce que le
        // modèle par instances borne et que la fusion laissait diverger.
        //
        // Pour mémoire, sur ce même scénario :
        //   - fusion sans borne (avant 3b)        : 7155
        //   - plafond à 2 stacks (D-13, périmée)  : 1405
        //   - instances indépendantes (D-20)      : 1253
        // D-20 est donc plus conservateur de 11 % que la solution qu'il remplace.
        $vestige = $playerBoard->getVestige();
        self::assertSame(1253, $vestige->getShield());

        // État terminal : l'instance du tick 468 a expiré au tick 498, seule
        // celle du tick 486 survit, avec 30 - 14 = 16 ticks restants.
        $ward = $vestige->getAggregatedStatus(StatusType::WARD);
        self::assertCount(1, $vestige->getStatusInstances(StatusType::WARD));
        self::assertSame(1, $ward->stacks);
        self::assertSame(16, $ward->remainingTicks);
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
            self::COMBAT_SEED
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

    /**
     * Puce de caractérisation ajoutée par `07` révision 3.0, écrite le
     * 20/09/2026 — la dernière du chantier 0 à manquer.
     *
     * La direction du biais d'enrage n'était figée que par un test **unitaire**
     * d'`EnrageProcessor`. Rien ne la vérifiait **à travers `Simulator::run()`**,
     * c'est-à-dire là où elle décide réellement d'une victoire. C'est
     * précisément le comportement que D-14 va renverser : l'enrage passera en
     * résolution simultanée, les deux plateaux subiront toute la phase, et les
     * morts seront constatées à la fin.
     *
     * **Ce test affirme donc un comportement que le corpus juge faux.** C'est
     * l'objet d'une caractérisation : sans elle, le diff du commit qui applique
     * D-14 ne montrerait pas ce qui a changé ni dans quel sens.
     */
    public function testCharacterizesOpponentBoardSurvivingASimultaneouslyLethalEnrage(): void
    {
        // Deux plateaux strictement identiques, une fureur assez forte pour
        // tuer n'importe lequel des deux : rien ne les départage, sinon
        // l'ordre dans lequel EnrageProcessor les parcourt.
        $playerBoard = $this->createBoard('player', 30);
        $opponentBoard = $this->createBoard('opponent', 30);

        $simulator = new Simulator(
            maxTicks: 10,
            enrageProcessor: new EnrageProcessor(triggerTick: 1, baseDamage: 100)
        );

        $result = $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        // Le plateau du joueur est frappé en premier, meurt, et la garde
        // « pas de frappe sur cadavre » d'EnrageProcessor épargne l'autre.
        self::assertFalse($playerBoard->isAlive());
        self::assertTrue($opponentBoard->isAlive());
        self::assertSame(
            30,
            $opponentBoard->getVestige()->getHp(),
            "L'adversaire sort de la fureur **intact** : il n'a jamais été frappé."
        );

        // Une seule frappe de fureur au journal, et elle vise le côté A.
        $enrageEvents = $this->eventsOfType($result, EventType::ENRAGE_DAMAGE_DEALT);
        self::assertCount(1, $enrageEvents);
        self::assertSame('A', $enrageEvents[0]->payload['targetSide']);

        // Conséquence : ce n'est pas une double mort départagée, c'est un KO.
        // La fureur, censée être symétrique, désigne un vainqueur.
        self::assertSame($opponentBoard, $result->winner);
        self::assertSame(Resolution::KNOCKOUT, $result->resolution);
        self::assertNull($this->tiebreakEventOf($result));
    }

    /**
     * Seconde puce manquante : la **position** de l'enrage dans le tick.
     *
     * `testCharacterizesPhaseOrderWithinATickAsStatusesBeforeActions` neutralise
     * la fureur et ne pince que « statuts avant actions » ;
     * `testPlayerWinsWhenPoisonKillsOpponentEvenIfEnrageWouldTriggerSameTick`
     * pince « statuts avant fureur ». Il manquait le dernier maillon,
     * **fureur avant actions**, sans lequel l'ordre complet n'est pas figé.
     */
    public function testCharacterizesEnrageRunningBeforePendingActionsWithinATick(): void
    {
        $action = new Action(
            type: ActionType::DEAL_DAMAGE,
            value: 5,
            target: Target::ENEMY
        );
        $dagger = new Item(
            id: 'dagger',
            name: 'Dagger',
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: 1,
            effects: [new Effect(Trigger::EVERY_N_TICKS, [$action])]
        );

        // Le joueur encaisse largement la fureur ; l'adversaire y succombe.
        $playerBoard = $this->createBoard('player', 1000, [new CombatItem($dagger)]);
        $opponentBoard = $this->createBoard('opponent', 10, []);

        $simulator = new Simulator(
            maxTicks: 10,
            enrageProcessor: new EnrageProcessor(triggerTick: 1, baseDamage: 50)
        );

        $result = $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        self::assertSame(950, $playerBoard->getVestige()->getHp());
        self::assertFalse($opponentBoard->isAlive());

        // **Le cœur du test.** La dague a un cooldown de 1 : TickEngine a bien
        // produit son intention à ce tick. Elle n'a pourtant jamais frappé,
        // parce que la fureur a tué l'adversaire avant que Simulator n'exécute
        // les PendingAction.
        //
        // Dans l'ordre inverse — actions puis fureur — la dague aurait frappé
        // un adversaire à 10 PV, laissant un DAMAGE_DEALT au journal. Son
        // absence est donc la signature de l'ordre réel, et non un hasard de
        // mise en place.
        self::assertCount(
            0,
            $this->eventsOfType($result, EventType::DAMAGE_DEALT),
            'Une frappe de dague au journal signifierait que les actions passent avant la fureur.'
        );
        self::assertCount(2, $this->eventsOfType($result, EventType::ENRAGE_DAMAGE_DEALT));

        self::assertSame($playerBoard, $result->winner);
        self::assertSame(Resolution::KNOCKOUT, $result->resolution);
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
            new ActiveStatus(StatusType::REGEN, stacks: 5, durationTicks: 30, sourceId: 'panacee')
        );

        // Enrage neutralisé : maxTicks=1 donnerait par défaut triggerTick=1
        // (max(1, maxTicks-50)) et déclencherait l'enrage dès ce tick, ce qui
        // perturberait ce test dont l'objet est uniquement l'ordre statuts/actions.
        $simulator = new Simulator(
            maxTicks: 1,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );

        $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        self::assertSame(990, $opponentBoard->getVestige()->getHp());
    }

    public function testPlayerWinsWhenPoisonKillsOpponentEvenIfEnrageWouldTriggerSameTick(): void
    {
        $playerBoard = $this->createBoard('player', 50, []);
        $opponentBoard = $this->createBoard('opponent', 3, []);

        $opponentBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 5, durationTicks: 10, sourceId: 'venomous_vial')
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
            self::COMBAT_SEED
        );

        // Comportement ATTENDU (E-03) : l'adversaire meurt du poison avant que
        // l'enrage n'ait la moindre chance de s'exécuter sur ce tick. Le joueur
        // doit gagner et rester vivant, pas subir un enrage sur cadavre adverse.
        self::assertSame($playerBoard, $result->winner);
        self::assertTrue($playerBoard->isAlive());
        self::assertFalse($opponentBoard->isAlive());
    }

    /**
     * Nombre d'instances vivantes du statut sur le Vestige porteur, après un
     * combat de $maxTicks ticks alimenté par un seul objet.
     *
     * Seule l'action APPLY_STATUS de l'objet est reproduite : les actions de
     * dégâts ou de soin qui l'accompagnent dans items.json n'influent pas sur
     * le nombre d'instances, et les omettre évite qu'une cible meure avant la
     * fin de l'échantillonnage.
     *
     * Le Trigger déclaré est celui de items.json, mais il n'a aucun effet sur
     * la cadence : TickEngine active un objet dès que son cooldown atteint
     * zéro, puis passe par EventDispatcher::dispatchForItem(), qui parcourt
     * tous les triggers de l'objet sans filtrer.
     */
    private function countLivingInstances(
        string $itemId,
        Trigger $trigger,
        int $cooldownTicks,
        StatusType $status,
        int $stacks,
        int $durationTicks,
        Target $target,
        int $maxTicks
    ): int {
        $item = new Item(
            id: $itemId,
            name: $itemId,
            rarity: Rarity::COMMON,
            affinity: 'neutral',
            size: ItemSize::ONE_HAND,
            cooldownTicks: $cooldownTicks,
            effects: [new Effect($trigger, [new Action(
                type: ActionType::APPLY_STATUS,
                target: $target,
                status: $status,
                stacks: $stacks,
                durationTicks: $durationTicks
            )])]
        );

        $playerBoard = $this->createBoard('player', 1_000_000, [new CombatItem($item)]);
        $opponentBoard = $this->createBoard('opponent', 1_000_000);

        $simulator = new Simulator(
            maxTicks: $maxTicks,
            enrageProcessor: new EnrageProcessor(triggerTick: 1_000_000)
        );
        $simulator->run($playerBoard, $opponentBoard, self::COMBAT_SEED);

        $carrier = $target === Target::SELF ? $playerBoard : $opponentBoard;

        return count($carrier->getVestige()->getStatusInstances($status));
    }

    // === Second critère de sortie du chantier 3b ===
    //
    // Le nombre d'instances vivantes est encadré par floor(durée / cooldown) et
    // ceil(durée / cooldown), la borne haute étant atteinte juste après une
    // application. Le compte n'est CONSTANT que si le cooldown divise la durée :
    // trois objets sur huit seulement.

    public function testVenomousVialOscillatesBetweenOneAndTwoPoisonInstances(): void
    {
        // 30 / 20 = 1,5 : pic de 2 au tick 60, juste après l'application ;
        // plancher de 1 au tick 50, l'instance du tick 20 venant d'expirer.
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'venomous_vial',
            Trigger::ON_ATTACK,
            20,
            StatusType::POISON,
            1,
            30,
            Target::ENEMY,
            $maxTicks
        );

        self::assertSame(2, $count(60));
        self::assertSame(1, $count(50));
    }

    public function testShadowVenomousVialOscillatesBetweenOneAndTwoPoisonInstances(): void
    {
        // Mêmes cadence et durée que venomous_vial, seuls les stacks diffèrent
        // (2 au lieu de 1) : le nombre d'instances est identique.
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'shadow_venomous_vial',
            Trigger::ON_ATTACK,
            20,
            StatusType::POISON,
            2,
            30,
            Target::ENEMY,
            $maxTicks
        );

        self::assertSame(2, $count(60));
        self::assertSame(1, $count(50));
    }

    public function testFiresteelKeepsExactlyOneBurnInstanceAtAllTimes(): void
    {
        // 20 / 20 = 1 exactement : le cooldown divise la durée, donc le compte
        // est constant. L'instance expire à la phase de statuts du tick où
        // l'objet se réactive, la nouvelle naît à la phase d'actions du même tick.
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'firesteel',
            Trigger::ON_ATTACK,
            20,
            StatusType::BURN,
            2,
            20,
            Target::ENEMY,
            $maxTicks
        );

        self::assertSame(1, $count(39));
        self::assertSame(1, $count(40));
        self::assertSame(1, $count(100));
    }

    public function testMolotovCocktailKeepsExactlyOneBurnInstanceAtAllTimes(): void
    {
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'molotov_cocktail',
            Trigger::ON_ATTACK,
            20,
            StatusType::BURN,
            3,
            20,
            Target::ENEMY,
            $maxTicks
        );

        self::assertSame(1, $count(39));
        self::assertSame(1, $count(40));
        self::assertSame(1, $count(100));
    }

    public function testNightfangKeepsExactlyThreePoisonInstancesAtAllTimes(): void
    {
        // 30 / 10 = 3 exactement : l'objet le plus rapide du catalogue, et celui
        // dont l'ancien modèle à fusion divergeait le plus vite. Plancher et pic
        // confondus, donc compte constant.
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'nightfang',
            Trigger::ON_ATTACK,
            10,
            StatusType::POISON,
            1,
            30,
            Target::ENEMY,
            $maxTicks
        );

        self::assertSame(3, $count(30));
        self::assertSame(3, $count(35));
        self::assertSame(3, $count(39));
        self::assertSame(3, $count(40));
        self::assertSame(3, $count(100));
    }

    public function testSilentDeathLetsItsBurnLapseBetweenTwoActivations(): void
    {
        // 20 / 30 = 0,67 : la durée est PLUS COURTE que le cooldown, donc le
        // statut s'éteint complètement entre deux activations. Plancher 0, pic 1.
        // Comportement voulu, à ne pas confondre avec une régression.
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'silent_death',
            Trigger::ON_ATTACK,
            30,
            StatusType::BURN,
            4,
            20,
            Target::ENEMY,
            $maxTicks
        );

        self::assertSame(1, $count(30));
        self::assertSame(0, $count(50));
        self::assertSame(0, $count(55));
        self::assertSame(1, $count(60));
    }

    public function testPanaceeLetsItsRegenLapseBetweenTwoActivations(): void
    {
        // 30 / 40 = 0,75 : même cas que silent_death, sur un statut bénéfique
        // appliqué à soi-même.
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'panacee',
            Trigger::EVERY_N_TICKS,
            40,
            StatusType::REGEN,
            1,
            30,
            Target::SELF,
            $maxTicks
        );

        self::assertSame(1, $count(40));
        self::assertSame(0, $count(70));
        self::assertSame(0, $count(75));
        self::assertSame(1, $count(80));
    }

    public function testShadowArmorOscillatesBetweenOneAndTwoWardInstances(): void
    {
        // 30 / 18 = 1,67 : c'est cette moyenne, et non le pic de 2, qui produit
        // le bouclier de référence de 1253 sur 500 ticks.
        $count = fn (int $maxTicks): int => $this->countLivingInstances(
            'shadow_armor',
            Trigger::EVERY_N_TICKS,
            18,
            StatusType::WARD,
            1,
            30,
            Target::SELF,
            $maxTicks
        );

        self::assertSame(2, $count(54));
        self::assertSame(1, $count(66));
        self::assertSame(2, $count(72));
    }
}
