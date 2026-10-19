<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * La fixture de référence (`tests/Determinism/fixtures/reference-combat/`),
 * lue de la même façon par tous les tests qui s'en servent.
 *
 * **Extrait le 16/10/2026.** Quatre fichiers de test en portaient chacun une
 * copie — `ReferenceCombatReplayTest` et les trois tests de la CLI —, et le
 * test de parité du moteur embarqué en aurait été la cinquième.
 */
trait ReadsReferenceCombat
{
    /**
     * Le saut de ligne final n'appartient pas à la charge utile.
     *
     * Les trois fichiers de données portent la chaîne canonique **exacte** que le
     * système produit. Le script de capture leur ajoute un saut de ligne final
     * parce qu'un fichier qui n'en a pas est un piège connu de ce dépôt
     * (`06` §4.4) ; il est retiré ici, explicitement, plutôt que toléré par un
     * `trim` qui avalerait aussi une vraie altération.
     */
    private function fixture(string $name): string
    {
        $path = dirname(__DIR__) . '/Determinism/fixtures/reference-combat/' . $name;
        $contents = file_get_contents($path);

        Assert::assertIsString($contents, sprintf('Fixture introuvable : %s', $path));

        return rtrim($contents, "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        /** @var array<string, mixed> $meta */
        $meta = json_decode($this->fixture('meta.json'), true, 512, JSON_THROW_ON_ERROR);

        return $meta;
    }

    /**
     * La graine de combat enregistrée à la capture.
     */
    private function referenceCombatSeed(): string
    {
        $combatSeed = $this->meta()['combatSeed'];

        Assert::assertIsString($combatSeed, 'meta.json doit porter une graine de combat en chaîne.');

        return $combatSeed;
    }

    /**
     * La requête du moteur embarqué (`04` §4.3) sur les deux plateaux de la
     * fixture : par défaut la graine enregistrée, et `board-a` en premier.
     *
     * Les snapshots sont décodés en tableaux, comme le ferait la coquille avant
     * de réencoder la requête ; `BoardHydrator` les décode de toute façon ainsi.
     *
     * @return array<string, mixed>
     */
    private function referenceRequest(?string $combatSeed = null, bool $boardBFirst = false): array
    {
        $boardA = json_decode($this->fixture('board-a.json'), true, 512, JSON_THROW_ON_ERROR);
        $boardB = json_decode($this->fixture('board-b.json'), true, 512, JSON_THROW_ON_ERROR);

        return [
            'combatSeed' => $combatSeed ?? $this->referenceCombatSeed(),
            'firstSnapshot' => $boardBFirst ? $boardB : $boardA,
            'secondSnapshot' => $boardBFirst ? $boardA : $boardB,
        ];
    }
}
