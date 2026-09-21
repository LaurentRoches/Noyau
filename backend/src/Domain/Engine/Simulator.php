<?php

declare(strict_types=1);

namespace App\Domain\Engine;

use App\Domain\Enum\EventType;
use App\Domain\Enum\RandomStream;
use App\Domain\Enum\Resolution;
use App\Domain\Enum\Side;
use App\Domain\Event\CombatEvent;
use App\Domain\Runtime\CombatBoard;

final class Simulator
{
    private const int ENRAGE_WINDOW_TICKS = 50;
    private const int ENRAGE_BASE_DAMAGE = 5;

    private ActionProcessor $actionProcessor;
    private StatusProcessor $statusProcessor;
    private EnrageProcessor $enrageProcessor;

    public function __construct(
        private readonly int $maxTicks = 500,
        ?ActionProcessor $actionProcessor = null,
        ?StatusProcessor $statusProcessor = null,
        ?EnrageProcessor $enrageProcessor = null
    ) {
        $this->actionProcessor = $actionProcessor ?? new ActionProcessor();
        $this->statusProcessor = $statusProcessor ?? new StatusProcessor();
        $this->enrageProcessor = $enrageProcessor ?? new EnrageProcessor(
            triggerTick: max(1, $this->maxTicks - self::ENRAGE_WINDOW_TICKS),
            baseDamage: self::ENRAGE_BASE_DAMAGE
        );
    }

    /**
     * `CombatLog = f(playerBoard, opponentBoard, combatSeed)`.
     *
     * Le combat reçoit une graine **propre à lui** et non plus le `Randomizer`
     * du run (D-22). Ce découplage a trois effets : le contenu d'une boutique
     * ne dépend plus du déroulé du combat précédent, un combat se rejoue
     * isolément — ce qu'exige le moteur embarqué du chantier 1a —, et
     * l'entrée devient sérialisable, un `Randomizer` ne traversant pas stdin.
     *
     * La graine est opaque pour le Domaine : elle est hachée par
     * `RandomStream` avant usage, donc aucune forme n'est exigée ni validée.
     */
    public function run(
        CombatBoard $playerBoard,
        CombatBoard $opponentBoard,
        string $combatSeed
    ): SimulationResult {
        // 1. Setup initial
        $dispatcher = new EventDispatcher();
        $dispatcher->registerBoard($playerBoard);
        $dispatcher->registerBoard($opponentBoard);

        $tickEngine = new TickEngine($dispatcher);
        $context = new SimulationContext($playerBoard, $opponentBoard, $combatSeed);

        // Vitalité des deux plateaux relevée juste avant la phase simultanée qui
        // a tué tout le monde. Reste null si le combat ne finit pas ainsi.
        $prePhaseVitality = null;

        // 2. Boucle de combat
        while (
            $context->getCurrentTick() < $this->maxTicks
            && $this->bothBoardsAlive($playerBoard, $opponentBoard)
        ) {
            // TickEngine avance le temps, décrémente les cooldowns, détecte les objets
            // prêts et renvoie leurs intentions SANS les exécuter.
            $pendingActions = $tickEngine->tick($context);

            // --- Phase de statuts : SIMULTANÉE (D-14) --------------------------
            //
            // Les deux plateaux subissent l'intégralité de la phase et les morts
            // ne sont constatées qu'à la fin. Ce n'est pas un oubli de garde :
            // aucun plateau ne frappe l'autre ici, chaque Vestige subit son
            // propre poison et sa propre brûlure. Une garde entre les deux
            // plateaux ferait mourir le premier de la boucle en premier, ce qui
            // est exactement le biais que D-14 supprime.
            //
            // Le relevé précède la phase parce que les PV sont bornés à zéro : après
            // une double mort, l'état final vaut 0 contre 0 et ne départage rien.
            $vitalityBeforeStatuses = $this->vitalitySnapshot($context);

            foreach ($this->statusProcessor->processTick($context) as $statusEvent) {
                $context->getLog()->addEvent($statusEvent);
            }

            if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                $prePhaseVitality = $vitalityBeforeStatuses;
                break;
            }

            // --- Phase d'enrage : SIMULTANÉE (D-14) ----------------------------
            //
            // Même nature, même traitement : les deux Vestiges subissent le même
            // effet de fin de combat. `EnrageProcessor` a perdu sa garde « pas de
            // frappe sur cadavre » pour cette raison.
            $vitalityBeforeEnrage = $this->vitalitySnapshot($context);

            foreach ($this->enrageProcessor->processTick($context) as $enrageEvent) {
                $context->getLog()->addEvent($enrageEvent);
            }

            if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                $prePhaseVitality = $vitalityBeforeEnrage;
                break;
            }

            // --- Phase d'actions : SÉQUENTIELLE, ordre tiré (D-14) -------------
            //
            // Ici un plateau frappe l'autre, et « pas de frappe sur cadavre » a
            // un sens : voir un adversaire déjà mort porter un coup est
            // illisible. D'où l'interruption à la première mort — laquelle rend
            // par construction la double mort inatteignable dans cette phase.
            $actionsBySide = $this->groupActionsBySide($context, $pendingActions);

            foreach ($this->drawActionOrder($context, $actionsBySide) as $side) {
                foreach ($actionsBySide[$side->value] as $pendingAction) {
                    $event = $this->actionProcessor->process($pendingAction, $context);
                    $context->getLog()->addEvent($event);

                    if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                        break 2;
                    }
                }
            }
        }

        // 3. Résolution du résultat
        $playerAlive = $playerBoard->isAlive();
        $opponentAlive = $opponentBoard->isAlive();

        if ($playerAlive !== $opponentAlive) {
            $winner = $playerAlive ? $playerBoard : $opponentBoard;
            $resolution = Resolution::KNOCKOUT;
        } elseif ($playerAlive) {
            // Les deux vivants : l'échéance est tombée. Leur état courant est
            // disponible et signifiant, il n'y a aucune raison de remonter.
            $resolution = Resolution::TIMEOUT_RESOLVED;
            [$valueA, $valueB] = $this->vitalitySnapshot($context);
            $winner = $this->breakTie($context, $resolution, 'FINAL_HP_AND_SHIELD', $valueA, $valueB);
        } else {
            // Les deux morts dans la même phase simultanée. L'état final vaut
            // 0 contre 0 : on départage sur le relevé d'avant la phase.
            //
            // Le repli sur l'état courant ne sert qu'au cas dégénéré d'un combat
            // dont les deux plateaux sont déjà morts à l'entrée — la boucle ne
            // tourne alors jamais et aucun relevé n'a été pris.
            $resolution = Resolution::SIMULTANEOUS_RESOLVED;
            [$valueA, $valueB] = $prePhaseVitality ?? $this->vitalitySnapshot($context);
            $winner = $this->breakTie($context, $resolution, 'PRE_PHASE_HP_AND_SHIELD', $valueA, $valueB);
        }

        // Le contexte meurt ici : l'attribution des côtés doit donc voyager
        // dans le résultat, sinon l'Application n'a plus aucun moyen de savoir
        // quel côté a reçu son plateau (D-19).
        return new SimulationResult(
            winner: $winner,
            resolution: $resolution,
            totalTicks: $context->getCurrentTick(),
            log: $context->getLog(),
            boardA: $context->getBoardOnSide(Side::A),
            boardB: $context->getBoardOnSide(Side::B),
        );
    }

    /**
     * Départage un combat qu'aucun KO n'a tranché, et journalise sa décision.
     *
     * **Le critère et les valeurs sont fournis par l'appelant**, parce qu'ils
     * dépendent de la façon dont le combat s'est terminé : état final pour un
     * timeout, état relevé avant la phase pour une double mort (`02` §7.5).
     * Les recalculer ici obligerait cette méthode à savoir quelle phase a tué,
     * information qu'elle n'a pas.
     *
     * **Pourquoi un événement et pas seulement un champ.** Un joueur qui revoit
     * un combat perdu au départage doit pouvoir comprendre pourquoi. Le champ
     * `resolution` dit qu'il y a eu départage ; l'événement dit sur quoi, et
     * rend la décision vérifiable au rejeu plutôt que simplement affirmée.
     *
     * La `resolution` est reçue en paramètre plutôt que recalculée : elle est
     * déjà déterminée par l'appelant, et la déduire une seconde fois ici
     * ouvrirait la porte à ce que les deux divergent.
     */
    private function breakTie(
        SimulationContext $context,
        Resolution $resolution,
        string $criterion,
        int $valueA,
        int $valueB,
    ): CombatBoard {
        $boardA = $context->getBoardOnSide(Side::A);
        $boardB = $context->getBoardOnSide(Side::B);

        if ($valueA !== $valueB) {
            $winner = $valueA > $valueB ? $boardA : $boardB;
            $decidedBy = 'COMPARISON';
        } else {
            // Le miroir strict, que `02` §7.5 nomme explicitement : deux
            // plateaux identiques, tout critère fondé sur leur contenu donne
            // une égalité. Le tirage est le dernier filet, et il vient de la
            // graine du combat — donc il est rejouable (NF-01).
            $winner = $context->getRandomizer(RandomStream::ORDER)->getInt(0, 1) === 0
                ? $boardA
                : $boardB;
            $decidedBy = 'RANDOM';
        }

        $context->getLog()->addEvent(new CombatEvent(
            tick: $context->getCurrentTick(),
            type: EventType::RESOLUTION_TIEBREAK,
            payload: [
                'criterion' => $criterion,
                'decidedBy' => $decidedBy,
                // Répété ici alors qu'il vit déjà sur SimulationResult, et
                // c'est voulu (`04` §3.5). Le CombatLog est le seul artefact
                // que le client reçoit, qu'on archive et qu'on rejoue : sans ce
                // champ, un départage au tirage sur double KO au tick 7 et un
                // départage au tirage sur timeout au tick 500 produiraient
                // exactement le même événement. Le journal doit se suffire.
                'resolution' => $resolution->value,
                'valueA' => $valueA,
                'valueB' => $valueB,
                'winnerSide' => $context->getSide($winner)->value,
            ],
        ));

        return $winner;
    }

    /**
     * Vitalité des deux côtés, dans l'ordre A puis B.
     *
     * @return array{int, int}
     */
    private function vitalitySnapshot(SimulationContext $context): array
    {
        return [
            $this->remainingVitality($context->getBoardOnSide(Side::A)),
            $this->remainingVitality($context->getBoardOnSide(Side::B)),
        ];
    }

    /**
     * Range les intentions du tick par côté.
     *
     * `TickEngine` les rend dans une liste **plate**, construite plateau par
     * plateau dans l'ordre de `getBoards()` : le joueur d'abord, toujours.
     * C'était là le biais d'initiative que D-14 supprime — le regroupement est
     * ce qui permet de le remplacer par un tirage.
     *
     * @param list<PendingAction> $pendingActions
     *
     * @return array<string, list<PendingAction>>
     */
    private function groupActionsBySide(SimulationContext $context, array $pendingActions): array
    {
        $grouped = [
            Side::A->value => [],
            Side::B->value => [],
        ];

        foreach ($pendingActions as $pendingAction) {
            $grouped[$context->getSide($pendingAction->sourceBoard)->value][] = $pendingAction;
        }

        return $grouped;
    }

    /**
     * Ordre de passage des deux plateaux pour ce tick.
     *
     * **Le tirage n'a lieu que si les deux plateaux ont au moins une action en
     * attente** (`02` §7.5). Sans cela l'ordre n'a aucun effet observable, et
     * le tirage consommerait de l'aléa pour rien — ce qui déplacerait le flux
     * `order` et changerait tous les tirages suivants du combat.
     *
     * **Pourquoi un tirage et non un critère d'état.** Faire passer le plateau
     * le plus faible en premier serait un rattrapage déguisé : un build aurait
     * intérêt à descendre volontairement en PV pour gagner l'initiative. Le
     * tirage est neutre, et reste strictement déterministe puisqu'il dépend de
     * la graine du combat.
     *
     * **L'ordre compte même sans mort** : `receiveHeal()` est plafonné à
     * `baseHp`, donc recevoir un soin avant ou après des dégâts dans le même
     * tick ne donne pas les mêmes PV.
     *
     * @param array<string, list<PendingAction>> $actionsBySide
     *
     * @return list<Side>
     */
    private function drawActionOrder(SimulationContext $context, array $actionsBySide): array
    {
        $bothBoardsAct = $actionsBySide[Side::A->value] !== [] && $actionsBySide[Side::B->value] !== [];

        if (!$bothBoardsAct) {
            return [Side::A, Side::B];
        }

        return $context->getRandomizer(RandomStream::ORDER)->getInt(0, 1) === 0
            ? [Side::A, Side::B]
            : [Side::B, Side::A];
    }

    /**
     * PV + bouclier restants : ce qu'il restait à entamer.
     *
     * Le bouclier compte à parité avec les PV parce qu'il absorbe des dégâts à
     * parité (`02` §2.1) — l'ignorer avantagerait les plateaux qui finissent
     * blessés mais protégés.
     */
    private function remainingVitality(CombatBoard $board): int
    {
        $vestige = $board->getVestige();

        return $vestige->getHp() + $vestige->getShield();
    }

    /** @phpstan-impure */
    private function bothBoardsAlive(CombatBoard $playerBoard, CombatBoard $opponentBoard): bool
    {
        return $playerBoard->isAlive() && $opponentBoard->isAlive();
    }
}
