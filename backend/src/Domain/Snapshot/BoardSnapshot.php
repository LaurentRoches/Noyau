<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

use App\Domain\Engine\CanonicalJson;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Item;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\HeroProfile;
use App\Domain\Runtime\VestigeProfile;

/**
 * Photographie d'un plateau de combat (D-16, `04` §5.5).
 *
 * **Une photographie, pas une recette.** Un fantôme PvP enregistré avant un
 * rééquilibrage rejoue avec ses chiffres d'origine : ce sont les `Item`
 * **déjà décorés**, rangés sous leur héros dans l'ordre d'affectation (D-28,
 * format 2), qui font foi. `CombatBoardFactory`
 * applique `HeroSkillDecorator` avant de construire le plateau, donc le
 * plateau ne contient que des objets résolus — c'est ce qui rend la
 * photographie possible, et c'est aussi ce qui sort le décorateur du chemin
 * de rejeu.
 *
 * **Elle ne lit que des valeurs de départ** — les profils `VestigeProfile` et
 * `HeroProfile`, et l'`Item` déjà décoré —, jamais l'état de runtime. Ce n'est
 * pas une préférence de style :
 * `SimulationContext::getSide()` comparera des photographies, et
 * `Simulator::groupActionsBySide()` l'appelle une fois par action en attente,
 * à chaque tick. Bâtie sur `CombatVestige::getHp()`, elle changerait au
 * premier point de dégât et l'attribution des côtés basculerait en plein
 * combat, sans qu'aucune exception ne soit levée.
 *
 * **Champs absents plutôt que `null`.** D-19 n'autorise que `int`, `string` et
 * `bool` ; et `04` §5.3 désigne l'absence comme le mécanisme de migration du
 * format — « donner une valeur par défaut aux champs absents ». Encoder
 * `"value":null` fermerait cette porte tout en alourdissant chaque unité.
 *
 * **Ce qui n'y est pas.** Le nom et l'affinité du Vestige, son or de départ et
 * son revenu : aucun n'entre dans un calcul de combat, et le client les relit
 * du catalogue. L'identifiant, lui, y est — les `CombatEvent` en émettent, et
 * un rejeu octet pour octet doit les reproduire. C'est un ajout à la table de
 * §5.5, qui ne nommait que `baseHp` et `baseShield`.
 *
 * **Elle photographie un profil, pas une entrée de catalogue.** `vestige()` et
 * `hero()` reçoivent `VestigeProfile` et `HeroProfile` plutôt que `Vestige` et
 * `Hero` : les champs écrits sont exactement ceux que ces deux contrats
 * exposent, si bien que la forme sérialisée et le type d'entrée ne peuvent plus
 * diverger. C'est aussi ce qui rendra le rejeu possible — un plateau hydraté
 * depuis son archive satisfait ces contrats, là où il ne pourrait pas
 * reconstituer une entrée de catalogue sans inventer des champs que la
 * photographie ne porte pas.
 *
 * **Taille mesurée le 21/09/2026, remesurée le 09/11/2026 au format 2** :
 * 349 octets pour un plateau de manche 1, inchangé puisqu'un plateau à un
 * seul héros garde sa taille, et 2 149 pour le maximum structurel (3 héros ×
 * 2 emplacements, objets légendaires à deux actions), contre 2 129 au
 * format 1. `BoardSnapshotTest` épingle la seconde valeur.
 */
final readonly class BoardSnapshot
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    public static function fromBoard(CombatBoard $board): self
    {
        $heroes = [];
        foreach ($board->getHeroes() as $hero) {
            $items = [];
            foreach ($hero->getItems() as $item) {
                $items[] = self::item($item->getItem());
            }

            $heroes[] = self::hero($hero->getProfile(), $items);
        }

        return new self([
            'vestige' => self::vestige($board->getVestige()->getProfile()),
            'heroes' => $heroes,
            'goldAtCombatStart' => $board->getGoldAtCombatStart(),
        ]);
    }

    /**
     * La photographie sous forme de tableau, pour que `BoardRecord` l'imbrique
     * dans son enveloppe sans la ré-encoder.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * La forme qui voyage, qu'on archive et que le moteur embarqué doit
     * reproduire à l'octet près (NF-01). L'ordre des clés est celui que
     * `CanonicalJson` impose, pas celui dans lequel ce fichier les écrit :
     * réordonner la construction ci-dessus ne change pas un octet.
     */
    public function toCanonicalJson(): string
    {
        return CanonicalJson::encode($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function vestige(VestigeProfile $vestige): array
    {
        return [
            'id' => $vestige->getId(),
            'baseHp' => $vestige->getBaseHp(),
            'baseShield' => $vestige->getBaseShield(),
        ];
    }

    /**
     * Identifiant, compétence et objets. La compétence est là pour `OPENING`
     * et `AURIC`, les deux seules du pool cible qui agissent pendant le combat
     * au lieu d'être résolues à l'assemblage (`02` §2.3.1). Aucune ne l'est
     * encore : le champ est posé d'avance parce que le format est
     * irréversible.
     *
     * **Les objets sont rangés sous leur héros** (D-28, format 2), dans
     * l'ordre d'affectation. `ON_SIBLING_TRIGGERED` lit cette appartenance
     * pendant le combat (`02` §7.7), et la liste plate du format 1 la perdait.
     * Un héros sans objet porte `items: []`, jamais une clé absente :
     * l'hydrateur doit pouvoir distinguer un héros désarmé d'une archive
     * tronquée.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private static function hero(HeroProfile $hero, array $items): array
    {
        return self::withoutNulls([
            'id' => $hero->getId(),
            'skill' => $hero->getSkill()?->value,
            'items' => $items,
        ]);
    }

    /**
     * L'objet en entier — c'est le cœur de la photographie.
     *
     * @return array<string, mixed>
     */
    private static function item(Item $item): array
    {
        $effects = [];

        /** @var Effect $effect */
        foreach ($item->effects as $effect) {
            $actions = [];

            /** @var Action $action */
            foreach ($effect->actions as $action) {
                $actions[] = self::action($action);
            }

            $effects[] = [
                'trigger' => $effect->trigger->value,
                'actions' => $actions,
            ];
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'rarity' => $item->rarity->value,
            'affinity' => $item->affinity,
            'size' => $item->size->value,
            'cooldownTicks' => $item->cooldownTicks,
            'effects' => $effects,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function action(Action $action): array
    {
        return self::withoutNulls([
            'type' => $action->type->value,
            'value' => $action->value,
            'target' => $action->target?->value,
            'status' => $action->status?->value,
            'stacks' => $action->stacks,
            'durationTicks' => $action->durationTicks,
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $fields): array
    {
        return array_filter($fields, static fn (mixed $value): bool => $value !== null);
    }
}
