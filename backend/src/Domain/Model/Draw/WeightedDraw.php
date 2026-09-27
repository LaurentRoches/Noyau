<?php

declare(strict_types=1);

namespace App\Domain\Model\Draw;

use App\Domain\Model\Hero;

final class WeightedDraw
{
    /**
     * Sélectionne les $count héros de plus haut rang selon un tirage pondéré
     * sans remise (algorithme d'Efraimidis-Spirakis) : chaque candidat reçoit
     * une clé u^(1/w), où u est un flottant uniforme déjà tiré dans [0, 1)
     * et w son poids. Les clés les plus hautes sont retenues.
     *
     * Précondition : tous les poids doivent être strictement positifs (un
     * poids nul ou négatif rendrait l'exposant 1/w indéfini ou incohérent).
     *
     * @param list<Hero> $candidates
     * @param list<float> $weights
     * @param list<float> $randomFloats
     * @return list<Hero>
     */
    public static function pickTop(
        array $candidates,
        array $weights,
        array $randomFloats,
        int $count,
    ): array {
        $keyed = [];
        foreach ($candidates as $index => $candidate) {
            $key = $randomFloats[$index] ** (1 / $weights[$index]);
            $keyed[] = ['hero' => $candidate, 'key' => $key];
        }

        usort($keyed, static fn (array $a, array $b): int => $b['key'] <=> $a['key']);

        return array_map(
            static fn (array $entry): Hero => $entry['hero'],
            array_slice($keyed, 0, $count),
        );
    }
}
