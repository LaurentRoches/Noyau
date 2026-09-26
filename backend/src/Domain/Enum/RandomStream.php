<?php

declare(strict_types=1);

namespace App\Domain\Enum;

use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

/**
 * Les deux flux aléatoires indépendants d'un combat (D-22, chantier 2).
 *
 * `ORDER` alimente l'ordre d'initiative entre les deux plateaux (D-14),
 * `EFFECTS` les effets aléatoires à venir — le critique du chantier 4 en
 * premier. Les séparer garantit qu'ajouter une ligne de critique à un objet
 * ne décale pas les ordres d'initiative de tous les ticks suivants, ni
 * l'inverse.
 *
 * **Format irréversible** (`07` §8, point 3). Cinq éléments le composent :
 * l'étiquette portée par `value`, l'algorithme de hachage, le séparateur, la
 * troncature à 16 octets, et le nom des deux cas. Changer l'un d'eux rejoue
 * tout le corpus différemment.
 */
enum RandomStream: string
{
    case ORDER = 'order';
    case EFFECTS = 'effects';

    /**
     * Dérive un flux neuf à partir de la graine de combat.
     *
     * `PcgOneseq128XslRr64` porte un état de 128 bits, que 16 octets
     * remplissent exactement ; il lève une `ValueError` sur toute autre
     * longueur, si bien qu'une troncature erronée casse à la construction au
     * lieu de produire silencieusement un autre flux.
     *
     * La graine est **opaque** : elle est hachée avant usage, donc aucune
     * forme n'est exigée ni validée. `CombatSeed` en est le seul producteur
     * légitime.
     *
     * Chaque appel rend une instance **neuve**, repartant du premier tirage.
     * C'est pourquoi `SimulationContext` mémoïse : redériver à chaque tick
     * figerait le tirage sans que rien ne le signale.
     */
    public function randomizerFor(string $combatSeed): Randomizer
    {
        return new Randomizer(new PcgOneseq128XslRr64(
            substr(hash('sha256', $this->value . '|' . $combatSeed, true), 0, 16),
        ));
    }
}
