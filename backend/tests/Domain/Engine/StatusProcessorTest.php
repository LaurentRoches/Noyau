<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\SimulationContext;
use App\Domain\Engine\StatusProcessor;
use App\Domain\Enum\EventType;
use App\Domain\Enum\StatusType;
use App\Domain\Model\Hero;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\ActiveStatus;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class StatusProcessorTest extends TestCase
{
    private const string COMBAT_SEED = '2a1fc9b42d6f7deabb34ec8d303950e95a203eb05bfec19c42e1eb7ac1fca71a';

    private function createBoard(string $vestigeId, string $heroId): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: $vestigeId,
            name: "Vestige {$vestigeId}",
            affinity: 'shadow',
            baseHp: 100,
            baseShield: 20,
            startingGold: 0,
            startingIncome: 0
        );
        $heroDef = new Hero(
            id: $heroId,
            name: "Hero {$heroId}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(new CombatVestige($vestigeDef), [new CombatHero($heroDef)], [], goldAtCombatStart: 0);
    }

    public function testProcessTickAppliesPoisonDamageBypassingShieldAndReturnsEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 20, sourceId: 'venomous_vial')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $processor = new StatusProcessor();
        $events = $processor->processTick($context);

        self::assertSame(97, $playerBoard->getVestige()->getHp());
        self::assertSame(20, $playerBoard->getVestige()->getShield());

        self::assertCount(1, $events);
        self::assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        self::assertSame(1, $events[0]->tick);
        self::assertSame([
            'status' => 'POISON',
            'amount' => 3,
            'shieldDamage' => 0,
            'hpDamage' => 3,
            'remainingStacks' => 3,
            'remainingTicks' => 19,
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[0]->payload);
    }

    public function testProcessTickDecrementsRemainingTicksOnEachCall(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 5, sourceId: 'venomous_vial')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $processor = new StatusProcessor();

        $eventsTick1 = $processor->processTick($context);
        self::assertSame(4, $eventsTick1[0]->payload['remainingTicks']);

        $context->advanceTick();
        $eventsTick2 = $processor->processTick($context);
        self::assertSame(3, $eventsTick2[0]->payload['remainingTicks']);
    }

    public function testProcessTickExpiresStatusAndPurgesItFromVestige(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 1, sourceId: 'venomous_vial')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $processor = new StatusProcessor();
        $events = $processor->processTick($context);

        self::assertCount(2, $events);

        self::assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        self::assertSame(0, $events[0]->payload['remainingTicks']);

        self::assertSame(EventType::STATUS_EXPIRED, $events[1]->type);
        self::assertSame([
            'status' => 'POISON',
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[1]->payload);

        self::assertSame([], $playerBoard->getVestige()->getStatusInstances(StatusType::POISON));
    }

    public function testProcessTickBurnIsFullyAbsorbedByASufficientShield(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        // Bouclier plein à 20 (défini dans createBoard). 5 stacks -> 7 majorés,
        // absorbés en entier. 'amount' porte désormais la valeur majorée, pas
        // les stacks : c'est 'remainingStacks' qui continue de les porter.
        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::BURN, stacks: 5, durationTicks: 20, sourceId: 'firesteel')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $events = (new StatusProcessor())->processTick($context);

        self::assertSame(100, $playerBoard->getVestige()->getHp());
        self::assertSame(13, $playerBoard->getVestige()->getShield());

        self::assertCount(1, $events);
        self::assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        self::assertSame([
            'status' => 'BURN',
            'amount' => 7,
            'shieldDamage' => 7,
            'hpDamage' => 0,
            'remainingStacks' => 5,
            'remainingTicks' => 19,
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[0]->payload);
    }

    public function testProcessTickBurnSplitsBetweenShieldAndHpAgainstAPartialShield(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        // Bouclier ramené à 8 : c'est l'exemple de 02 §7.4, bout en bout.
        // 10 stacks -> 15 majorés, 8 absorbés, 7 de surplus, 3 PV.
        $playerBoard->getVestige()->takeDamage(12);
        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::BURN, stacks: 10, durationTicks: 20, sourceId: 'molotov_cocktail')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $events = (new StatusProcessor())->processTick($context);

        self::assertSame(97, $playerBoard->getVestige()->getHp());
        self::assertSame(0, $playerBoard->getVestige()->getShield());

        self::assertSame([
            'status' => 'BURN',
            'amount' => 15,
            'shieldDamage' => 8,
            'hpDamage' => 3,
            'remainingStacks' => 10,
            'remainingTicks' => 19,
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[0]->payload);
    }

    public function testProcessTickBurnAttenuatesToSeventyPercentAgainstNoShield(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        // Bouclier vidé : 10 stacks -> 15 majorés, rien d'absorbé, 7 PV.
        // C'est le cas le plus faible pour la brûlure, par construction.
        $playerBoard->getVestige()->takeDamage(20);
        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::BURN, stacks: 10, durationTicks: 20, sourceId: 'molotov_cocktail')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $events = (new StatusProcessor())->processTick($context);

        self::assertSame(93, $playerBoard->getVestige()->getHp());
        self::assertSame(0, $playerBoard->getVestige()->getShield());

        self::assertSame([
            'status' => 'BURN',
            'amount' => 15,
            'shieldDamage' => 0,
            'hpDamage' => 7,
            'remainingStacks' => 10,
            'remainingTicks' => 19,
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[0]->payload);
    }

    public function testProcessTickAppliesRegenHealCappedAtBaseHpAndReturnsEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        // baseHp = 100 ; takeRawDamage ignore le bouclier (20) pour bien retirer 5 HP réels
        $playerBoard->getVestige()->takeRawDamage(5);

        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::REGEN, stacks: 8, durationTicks: 30, sourceId: 'panacee')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $processor = new StatusProcessor();
        $events = $processor->processTick($context);

        self::assertSame(100, $playerBoard->getVestige()->getHp());

        self::assertCount(1, $events);
        self::assertSame(EventType::STATUS_HEAL_RECEIVED, $events[0]->type);
        self::assertSame([
            'status' => 'REGEN',
            'amount' => 8,
            'hpHealed' => 5,
            'remainingStacks' => 8,
            'remainingTicks' => 29,
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[0]->payload);
    }

    public function testProcessTickAppliesWardShieldGainAndReturnsEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        // baseShield = 20 (défini dans createBoard)
        $playerBoard->getVestige()->applyStatus(
            new ActiveStatus(StatusType::WARD, stacks: 6, durationTicks: 30, sourceId: 'shadow_armor')
        );

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $processor = new StatusProcessor();
        $events = $processor->processTick($context);

        self::assertSame(26, $playerBoard->getVestige()->getShield());

        self::assertCount(1, $events);
        self::assertSame(EventType::STATUS_SHIELD_GAINED, $events[0]->type);
        self::assertSame([
            'status' => 'WARD',
            'amount' => 6,
            'shieldGained' => 6,
            'remainingStacks' => 6,
            'remainingTicks' => 29,
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[0]->payload);
    }

    public function testProcessTickAggregatesSeveralInstancesIntoASingleEvent(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        // Trois sources distinctes, durées 29 / 15 / 3. Après le décrément du
        // tick, les durées restantes sont 28 / 14 / 2 : la projection expose
        // la somme des stacks et le maximum des durées, soit 6 et 28.
        $vestige = $playerBoard->getVestige();
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 29, sourceId: 'venomous_vial'));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 3, durationTicks: 15, sourceId: 'nightfang'));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 3, sourceId: 'shadow_venomous_vial'));

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $events = (new StatusProcessor())->processTick($context);

        // Un seul événement, pas trois.
        self::assertCount(1, $events);
        self::assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        self::assertSame([
            'status' => 'POISON',
            'amount' => 6,
            'shieldDamage' => 0,
            'hpDamage' => 6,
            'remainingStacks' => 6,
            'remainingTicks' => 28,
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[0]->payload);

        // Le poison ignore le bouclier : 6 dégâts sur les PV, bouclier intact.
        self::assertSame(94, $vestige->getHp());
        self::assertSame(20, $vestige->getShield());
    }

    public function testProcessTickEmitsNoExpiryWhileAtLeastOneInstanceSurvives(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $vestige = $playerBoard->getVestige();
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 1, sourceId: 'nightfang'));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 20, sourceId: 'venomous_vial'));

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $events = (new StatusProcessor())->processTick($context);

        // L'instance courte expire, mais le statut reste actif : aucun
        // STATUS_EXPIRED, qui signifie « ce statut a cessé ».
        self::assertCount(1, $events);
        self::assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        self::assertSame(3, $events[0]->payload['remainingStacks']);

        // L'instance expirée est purgée, la survivante reste.
        $instances = $vestige->getStatusInstances(StatusType::POISON);
        self::assertCount(1, $instances);
        self::assertSame('venomous_vial', $instances[0]->getSourceId());
    }

    public function testProcessTickEmitsASingleExpiryWhenEveryInstanceExpiresOnTheSameTick(): void
    {
        $playerBoard = $this->createBoard('player_vestige', 'player_hero');
        $opponentBoard = $this->createBoard('opponent_vestige', 'opponent_hero');

        $vestige = $playerBoard->getVestige();
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 1, durationTicks: 1, sourceId: 'nightfang'));
        $vestige->applyStatus(new ActiveStatus(StatusType::POISON, stacks: 2, durationTicks: 1, sourceId: 'venomous_vial'));

        $context = new SimulationContext(
            $playerBoard,
            $opponentBoard,
            self::COMBAT_SEED
        );
        $context->advanceTick();

        $events = (new StatusProcessor())->processTick($context);

        // Deux instances expirent au même tick : un seul STATUS_EXPIRED,
        // pas deux événements identiques.
        self::assertCount(2, $events);
        self::assertSame(EventType::STATUS_DAMAGE_DEALT, $events[0]->type);
        self::assertSame(EventType::STATUS_EXPIRED, $events[1]->type);
        self::assertSame([
            'status' => 'POISON',
            'target' => 'player_vestige',
            'targetSide' => 'A',
        ], $events[1]->payload);

        self::assertSame([], $vestige->getStatusInstances(StatusType::POISON));
    }
}
