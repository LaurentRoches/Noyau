<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

use App\Domain\Engine\CanonicalJson;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Hero;
use App\Domain\Model\Item;
use App\Domain\Model\Vestige;
use App\Domain\Runtime\CombatBoard;

/**
 * Photographie d'un plateau de combat (D-16, `04` §5.5).
 *
 * **Une photographie, pas une recette.** Un fantôme PvP enregistré avant un
 * rééquilibrage rejoue avec ses chiffres d'origine : ce sont les `Item`
 * **déjà décorés**, dans l'ordre du plateau, qui font foi. `CombatBoardFactory`
 * applique `HeroSkillDecorator` avant de construire le plateau, donc le
 * plateau ne contient que des objets résolus — c'est ce qui rend la
 * photographie possible, et c'est aussi ce qui sort le décorateur du chemin
 * de rejeu.
 *
 * **Elle ne lit que des objets immuables** — `Vestige`, `Hero`, `Item` —,
 * jamais l'état de runtime. Ce n'est pas une préférence de style :
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
 * **Taille mesurée le 21/09/2026** : 349 octets pour un plateau de manche 1,
 * 2 129 pour le maximum structurel (3 héros × 2 emplacements, objets
 * légendaires à deux actions). `BoardSnapshotTest` épingle la seconde valeur.
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
            $heroes[] = self::hero($hero->getDefinition());
        }

        $items = [];
        foreach ($board->getItems() as $item) {
            $items[] = self::item($item->getItem());
        }

        return new self([
            'vestige' => self::vestige($board->getVestige()->getDefinition()),
            'heroes' => $heroes,
            'items' => $items,
            'goldAtCombatStart' => $board->getGoldAtCombatStart(),
        ]);
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
    private static function vestige(Vestige $vestige): array
    {
        return [
            'id' => $vestige->id,
            'baseHp' => $vestige->baseHp,
            'baseShield' => $vestige->baseShield,
        ];
    }

    /**
     * Identifiant et compétence, rien d'autre. La compétence est là pour
     * `OPENING` et `AURIC`, les deux seules du pool cible qui agissent
     * pendant le combat au lieu d'être résolues à l'assemblage (`02` §2.3.1).
     * Aucune ne l'est encore : le champ est posé d'avance parce que le format
     * est irréversible.
     *
     * @return array<string, mixed>
     */
    private static function hero(Hero $hero): array
    {
        return self::withoutNulls([
            'id' => $hero->id,
            'skill' => $hero->skill?->value,
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
