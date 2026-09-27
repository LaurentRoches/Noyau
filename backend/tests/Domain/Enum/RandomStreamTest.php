<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enum;

use App\Domain\Enum\RandomStream;
use PHPUnit\Framework\TestCase;

/**
 * Dérivation des flux aléatoires d'un combat (D-22, chantier 2).
 *
 * Ce que ce fichier fige est un **format irréversible** (`07` §8, point 3).
 * Cinq éléments le composent, et changer l'un d'eux rejoue tout le corpus
 * différemment : l'étiquette du flux, l'algorithme de hachage, la troncature
 * à 16 octets, le séparateur, et le nom des deux flux.
 *
 * Pourquoi deux flux plutôt qu'un : ajouter une ligne de critique à un objet
 * au chantier 4 ne doit pas décaler les ordres d'initiative de tous les ticks
 * suivants, et modifier la règle d'initiative ne doit pas décaler les jets de
 * critique. Les deux usages sont étanches par construction.
 */
final class RandomStreamTest extends TestCase
{
    private const string SEED = '2a1fc9b42d6f7deabb34ec8d303950e95a203eb05bfec19c42e1eb7ac1fca71a';

    /**
     * @return list<int>
     */
    private function draw(RandomStream $stream, string $combatSeed, int $count = 20): array
    {
        $randomizer = $stream->randomizerFor($combatSeed);

        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = $randomizer->getInt(0, 999);
        }

        return $values;
    }

    public function testTheTwoStreamsAreIndependent(): void
    {
        self::assertNotSame(
            $this->draw(RandomStream::ORDER, self::SEED),
            $this->draw(RandomStream::EFFECTS, self::SEED),
            'Les deux flux partagent la graine de combat mais pas leur suite : '
            . 'sans quoi un jet de critique et un ordre d\'initiative seraient corrélés.',
        );
    }

    public function testTheSameStreamAndSeedAlwaysProduceTheSameSequence(): void
    {
        self::assertSame(
            $this->draw(RandomStream::ORDER, self::SEED),
            $this->draw(RandomStream::ORDER, self::SEED),
        );
    }

    public function testTwoSeedsProduceTwoSequences(): void
    {
        self::assertNotSame(
            $this->draw(RandomStream::ORDER, self::SEED),
            $this->draw(RandomStream::ORDER, str_repeat('0', 64)),
        );
    }

    /**
     * Le revers de la propriété ci-dessus, et la raison de la mémoïsation
     * imposée à `SimulationContext`.
     *
     * Deux dérivations successives rendent deux `Randomizer` **neufs**, donc
     * deux suites identiques repartant de zéro. Un appelant qui redériverait à
     * chaque tick figerait son tirage sans que rien ne le signale : le combat
     * resterait parfaitement déterministe et le test de parité d'EX-J0-01
     * passerait. Le défaut n'apparaîtrait qu'en jouant.
     */
    public function testEachDerivationReturnsAFreshStreamRestartingFromTheBeginning(): void
    {
        $first = RandomStream::ORDER->randomizerFor(self::SEED);
        $second = RandomStream::ORDER->randomizerFor(self::SEED);

        self::assertNotSame($first, $second, 'Deux instances distinctes sont attendues.');
        self::assertSame(
            $first->getInt(0, 999),
            $second->getInt(0, 999),
            'Et toutes deux repartent du premier tirage — d\'où la mémoïsation du contexte.',
        );
    }

    /**
     * La graine de combat est une chaîne opaque pour le Domaine : elle est
     * hachée avant usage, donc aucune forme particulière n'est exigée ni
     * validée. `CombatSeed` est le seul producteur légitime ; ce test fige
     * l'absence de validation plutôt que de la laisser à l'interprétation.
     */
    public function testAnyNonEmptySeedIsAccepted(): void
    {
        self::assertNotSame(
            $this->draw(RandomStream::ORDER, 'une-graine-courte', 5),
            $this->draw(RandomStream::ORDER, 'une-autre-graine', 5),
        );
    }

    public function testTheStreamTagsAreStable(): void
    {
        // Les étiquettes entrent dans le hachage : les renommer change toutes
        // les suites déjà produites. Ce test existe pour que ce renommage soit
        // un acte conscient et non un refactoring.
        self::assertSame('order', RandomStream::ORDER->value);
        self::assertSame('effects', RandomStream::EFFECTS->value);
    }
}
