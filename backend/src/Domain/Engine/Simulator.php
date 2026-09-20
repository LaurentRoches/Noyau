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

        // 2. Boucle de combat
        while (
            $context->getCurrentTick() < $this->maxTicks
            && $this->bothBoardsAlive($playerBoard, $opponentBoard)
        ) {
            // TickEngine avance le temps, décrémente les cooldowns, détecte les objets
            // prêts et renvoie leurs intentions SANS les exécuter.
            $pendingActions = $tickEngine->tick($context);

            // Pulsation des statuts déjà actifs, au tick courant qui vient d'être avancé
            // (avant les objets, pour qu'un statut fraîchement appliqué n'attende pas
            // moins d'un tick avant sa première pulsation).
            foreach ($this->statusProcessor->processTick($context) as $statusEvent) {
                $context->getLog()->addEvent($statusEvent);
            }

            // Un statut (Poison/Burn) peut avoir achevé un vestige : ne pas laisser
            // l'enrage s'exécuter sur un combat déjà résolu (pas de "frappe sur
            // cadavre" — même principe que la garde après enrage, appliqué un cran
            // plus tôt).
            if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                break;
            }

            foreach ($this->enrageProcessor->processTick($context) as $enrageEvent) {
                $context->getLog()->addEvent($enrageEvent);
            }

            // L'enrage peut avoir achevé un vestige : ne pas exécuter les
            // PendingAction restantes (pas de "frappe sur cadavre").
            if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                break;
            }

            foreach ($pendingActions as $pendingAction) {
                $event = $this->actionProcessor->process($pendingAction, $context);
                $context->getLog()->addEvent($event);

                if (!$this->bothBoardsAlive($playerBoard, $opponentBoard)) {
                    break;
                }
            }
        }

        // 3. Résolution du résultat
        $playerAlive = $playerBoard->isAlive();
        $opponentAlive = $opponentBoard->isAlive();

        if ($playerAlive !== $opponentAlive) {
            $winner = $playerAlive ? $playerBoard : $opponentBoard;
            $resolution = Resolution::KNOCKOUT;
        } else {
            // Les deux vivants : l'échéance est tombée. Les deux morts : ils se
            // sont éteints dans la même phase. Dans les deux cas il faut un
            // vainqueur, le match nul n'existant plus (D-15).
            $resolution = $playerAlive
                ? Resolution::TIMEOUT_RESOLVED
                : Resolution::SIMULTANEOUS_RESOLVED;
            $winner = $this->breakTie($context, $resolution);
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
     * **Le critère est provisoire et le restera un commit.** Les PV sont bornés
     * à zéro (`02` §2.1) : après une double mort, les deux Vestiges sont à 0 et
     * l'état final ne départage rien — toute double mort tombe donc aujourd'hui
     * sur le tirage. D-14 remplacera ce critère par l'état relevé **avant la
     * phase**, qui tranchera presque toujours avant. Pour un timeout, en
     * revanche, l'état final est le bon critère et ne changera pas : les deux
     * plateaux sont vivants, leur état courant est disponible et signifiant.
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
    private function breakTie(SimulationContext $context, Resolution $resolution): CombatBoard
    {
        $boardA = $context->getBoardOnSide(Side::A);
        $boardB = $context->getBoardOnSide(Side::B);

        $valueA = $this->remainingVitality($boardA);
        $valueB = $this->remainingVitality($boardB);

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
                // 'PRE_PHASE_HP_AND_SHIELD' rejoindra cette valeur au commit
                // qui applique la règle hybride par phase (D-14).
                'criterion' => 'FINAL_HP_AND_SHIELD',
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
