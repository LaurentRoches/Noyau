<?php

declare(strict_types=1);

namespace App\Tests\Domain\Engine;

use App\Domain\Engine\EnrageProcessor;
use App\Domain\Engine\SimulationContext;
use App\Domain\Enum\EventType;
use App\Domain\Model\Hero;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatVestige;
use PHPUnit\Framework\TestCase;

final class EnrageProcessorTest extends TestCase
{
    private const string COMBAT_SEED = '2a1fc9b42d6f7deabb34ec8d303950e95a203eb05bfec19c42e1eb7ac1fca71a';

    private function createBoard(string $id, int $baseHp = 1000, int $baseShield = 0): CombatBoard
    {
        $vestigeDef = new Vestige(
            id: $id,
            name: "Vestige {$id}",
            affinity: 'shadow',
            baseHp: $baseHp,
            baseShield: $baseShield,
            startingGold: 0,
            startingIncome: 0
        );
        $heroDef = new Hero(
            id: "{$id}_hero",
            name: "Hero {$id}",
            affinity: 'shadow',
            itemSlots: 6
        );

        return new CombatBoard(new CombatVestige($vestigeDef), [new CombatHero($heroDef)], [], goldAtCombatStart: 0);
    }

    private function contextAtTick(int $tick, CombatBoard $playerBoard, CombatBoard $opponentBoard): SimulationContext
    {
        $context = new SimulationContext($playerBoard, $opponentBoard, self::COMBAT_SEED);

        for ($i = 0; $i < $tick; $i++) {
            $context->advanceTick();
        }

        return $context;
    }

    public function testProcessTickReturnsNoEventsBeforeTriggerTick(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10);
        $context = $this->contextAtTick(9, $this->createBoard('player'), $this->createBoard('opponent'));

        self::assertSame([], $processor->processTick($context));
    }

    public function testProcessTickAppliesDamageToShieldBeforeHpOnBothBoards(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10, baseDamage: 5);
        $playerBoard = $this->createBoard('player', baseShield: 20);
        $opponentBoard = $this->createBoard('opponent', baseShield: 0);
        $context = $this->contextAtTick(10, $playerBoard, $opponentBoard);

        $events = $processor->processTick($context);

        self::assertCount(2, $events);
        self::assertSame(EventType::ENRAGE_DAMAGE_DEALT, $events[0]->type);
        self::assertSame(15, $playerBoard->getVestige()->getShield());
        self::assertSame(1000, $playerBoard->getVestige()->getHp());
        self::assertSame(995, $opponentBoard->getVestige()->getHp());
        // `SimulationContext::getBoards()` rend désormais les plateaux dans
        // l'ordre canonique A puis B (D-19), et non plus dans l'ordre des
        // arguments : le premier événement est donc toujours celui du côté A.
        // C'est ce qui rend l'ordre des événements indépendant de la façon
        // dont l'appelant a rangé ses plateaux — sans quoi run($a, $b) et
        // run($b, $a) produiraient deux journaux différents octet pour octet.
        //
        // Ici l'adversaire occupe A : `opponent_hero` trie avant `player_hero`.
        self::assertSame([
            'amount' => 5,
            'shieldDamage' => 0,
            'hpDamage' => 5,
            'target' => 'opponent',
            'targetSide' => 'A',
        ], $events[0]->payload);
        self::assertSame([
            'amount' => 5,
            'shieldDamage' => 5,
            'hpDamage' => 0,
            'target' => 'player',
            'targetSide' => 'B',
        ], $events[1]->payload);
    }

    public function testProcessTickDamageDoublesEachTickAfterTrigger(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10, baseDamage: 5);
        $context = $this->contextAtTick(13, $this->createBoard('player'), $this->createBoard('opponent'));

        $events = $processor->processTick($context);

        // stage = 13 - 10 = 3 -> 5 * 2^3 = 40
        self::assertSame(40, $events[0]->payload['amount']);
    }

    /**
     * La fureur ne connaît pas la « frappe sur cadavre » (D-14).
     *
     * Ce test disait l'inverse jusqu'au 20/09/2026 : le second plateau était
     * épargné dès que le premier mourait. La garde y avait été copiée depuis
     * `Simulator`, où elle a un sens — un plateau y **frappe** l'autre, et voir
     * un adversaire déjà mort porter un coup est illisible. Ici personne ne
     * frappe personne : les deux Vestiges subissent le même effet de fin de
     * combat. Épargner le second au motif que le premier vient de mourir du
     * **même** coup offrait une victoire à un ordre de boucle.
     *
     * La double mort qui en résulte n'est pas un trou : `Simulator` la
     * départage sur l'état relevé avant la phase (`02` §7.5).
     */
    public function testProcessTickStrikesBothBoardsEvenWhenTheFirstOneDies(): void
    {
        $processor = new EnrageProcessor(triggerTick: 10, baseDamage: 100);
        $playerBoard = $this->createBoard('player', baseHp: 50);
        $opponentBoard = $this->createBoard('opponent', baseHp: 50);
        $context = $this->contextAtTick(10, $playerBoard, $opponentBoard);

        $events = $processor->processTick($context);

        self::assertCount(2, $events, 'Une phase simultanée frappe les deux plateaux, quoi qu\'il advienne du premier.');
        self::assertFalse($playerBoard->getVestige()->isAlive());
        self::assertFalse($opponentBoard->getVestige()->isAlive());
    }
}
