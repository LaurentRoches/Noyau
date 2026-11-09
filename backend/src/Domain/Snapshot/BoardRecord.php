<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

use App\Domain\Engine\CanonicalJson;
use App\Domain\Engine\EngineVersion;
use App\Domain\Runtime\CombatBoard;

/**
 * Un plateau tel qu'on l'archive : sa photographie, plus sa provenance
 * (D-16, `04` §5.5).
 *
 * **Pourquoi la photographie reste une classe à part.** `SimulationContext`
 * compare des photographies **nues** pour attribuer les côtés (`04` §3.6). Y
 * mêler une provenance ferait dépendre l'attribution d'une donnée qui ne
 * décrit pas le combat — deux plateaux identiques issus de deux runs
 * différentes cesseraient d'être un miroir.
 *
 * **Pourquoi `BoardRecord` et non `CombatSnapshot`.** `04` §6 nomme
 * « enregistrement de combat » la structure à **deux** plateaux — snapshots A
 * et B, `combatSeed`, `engineVersion`, `resolution`, `winnerSide` — qui
 * arrivera au commit 10. Deux noms quasi identiques pour un plateau et pour un
 * combat seraient une confusion programmée.
 *
 * **Deux versions, pas une** (`04` §5.3). `engineVersion` dit avec quelles
 * règles le journal a été produit ; `FORMAT_VERSION` dit comment lire cette
 * enveloppe. Dès le chantier 4, `Item`, `Effect` et `Action` changent de forme
 * sans que le moteur bouge — et le moteur peut être corrigé sans que la forme
 * change. Un seul champ forcerait à invalider l'un pour l'autre.
 *
 * **Version 2 depuis le chantier 3** (D-28). La photographie range chaque
 * objet sous son héros (`heroes[].items[]`) au lieu d'une liste plate. Les
 * règles du combat n'ont pas changé pour autant : c'est exactement le cas que
 * la séparation des deux versions prévoyait. `BoardHydrator` ne relit que la
 * version courante, et aucune enveloppe en version 1 n'a à survivre (D-18).
 *
 * **`contentVersion` est fourni, pas calculé.** C'est l'empreinte SHA-256 des
 * quatre catalogues canonicalisés (`04` §6.3), que rien ne calcule encore. Le
 * champ existe malgré tout : le format est irréversible, et le jour où la
 * valeur arrive, aucune ligne de lecture ne bouge.
 */
final readonly class BoardRecord
{
    public const int FORMAT_VERSION = 2;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * **Pourquoi la recette est contrôlée contre le plateau.** Une provenance
     * qui ment est pire qu'une provenance absente : elle sera crue, et un
     * déséquilibre remonté depuis le corpus mènerait à la mauvaise cause.
     *
     * Le contrôle reste volontairement grossier — nombre de héros, nombre
     * d'objets. Un contrôle héros par héros est devenu possible depuis D-28 :
     * le plateau connaît l'appartenance, et `HeroSkillDecorator` conserve
     * l'identifiant de l'objet qu'il décore (`07` §4.2). Il n'est pas fait
     * ici, faute d'une règle qui le demande : `04` §5.5 garde la recette comme
     * provenance, sans en durcir le contrôle.
     */
    public static function fromBoard(
        CombatBoard $board,
        SnapshotRecipe $recipe,
        ?string $contentVersion,
    ): self {
        if (count($recipe->heroIds) !== count($board->getHeroes())) {
            throw new \InvalidArgumentException(sprintf(
                'Recipe describes %d heroes but the board carries %d.',
                count($recipe->heroIds),
                count($board->getHeroes()),
            ));
        }

        if ($recipe->itemCount() !== count($board->getItems())) {
            throw new \InvalidArgumentException(sprintf(
                'Recipe describes %d items but the board carries %d.',
                $recipe->itemCount(),
                count($board->getItems()),
            ));
        }

        $data = [
            'formatVersion' => self::FORMAT_VERSION,
            'engineVersion' => EngineVersion::CURRENT,
            'board' => BoardSnapshot::fromBoard($board)->toArray(),
            'recipe' => $recipe->toArray(),
        ];

        // Absent plutôt que `null` : D-19 n'autorise que int, string et bool,
        // et `04` §5.3 fait de l'absence le mécanisme de migration du format.
        if ($contentVersion !== null) {
            $data['contentVersion'] = $contentVersion;
        }

        return new self($data);
    }

    public function toCanonicalJson(): string
    {
        return CanonicalJson::encode($this->data);
    }
}
