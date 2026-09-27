<?php

declare(strict_types=1);

namespace App\Domain\Snapshot;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\HeroSkillType;
use App\Domain\Enum\ItemSize;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Item;
use App\Domain\Runtime\CombatBoard;
use App\Domain\Runtime\CombatHero;
use App\Domain\Runtime\CombatItem;
use App\Domain\Runtime\CombatVestige;

/**
 * Le chemin de retour : une enveloppe archivée redevient un plateau jouable
 * (D-16, `04` §5.5).
 *
 * **Il ne reçoit qu'une chaîne.** Pas de tableau déjà décodé, pas de chemin de
 * configuration, pas de dépôt. L'isolement est structurel et non déclaratif :
 * une signature qui n'accepte qu'un `string` rend *impossible*, et pas
 * seulement déconseillée, la relecture d'un catalogue au rejeu. C'est la
 * première borne de D-16 — un fantôme archivé avant un rééquilibrage rejoue
 * avec ses chiffres d'origine — et aucune discipline ne la garantit si le type
 * d'entrée laisse la porte ouverte.
 *
 * **Ce qu'il ne refait pas.** `HeroSkillDecorator` n'est pas réappliqué : la
 * photographie porte les objets **déjà décorés** (`04` §5.5), et les redécorer
 * les décorerait deux fois. Le budget d'emplacements n'est pas revérifié :
 * `CombatBoardFactory` l'a contrôlé avant que le plateau n'existe, et une
 * archive n'est pas un assemblage à valider mais un fait à restituer.
 *
 * **Deux versions, deux rôles** (`04` §5.3). `formatVersion` dit comment lire
 * l'enveloppe : une valeur inconnue signifie que la disposition des champs a
 * changé, et deviner produirait un plateau plausible et faux — la seule issue
 * dont on ne se relève pas sur un format de parité. `engineVersion` dit sous
 * quelles règles le journal a été produit : c'est la question du droit de
 * **resimuler** (`04` §6), tranchée par l'appelant, qui la lit dans la colonne
 * `engine_version` de `combat_records` sans décoder l'enveloppe. Reconstruire
 * un plateau est une question de format. Cette classe ne lit donc pas
 * `engineVersion`, et un test l'épingle pour que personne ne « corrige »
 * l'oubli.
 *
 * **Ni recette ni version de contenu.** Les deux voyagent dans l'enveloppe et
 * relèvent de la provenance. La recette n'est d'ailleurs pas contrôlable ici :
 * l'association héros ↔ objet est perdue dans la liste plate de `CombatBoard`,
 * ce qui est précisément la raison pour laquelle elle est archivée. Elle est
 * donc ignorée franchement plutôt que crue à moitié.
 *
 * **`tryFrom` et jamais `from`.** Une valeur retirée du jeu après qu'un corpus
 * l'a enregistrée est un cas certain, pas une hypothèse. `from` lèverait un
 * `ValueError` qui ne dirait ni quelle archive ni quel champ ; `tryFrom` rend
 * `null`, et le refus qui suit nomme le chemin exact — seule chose qui rende
 * une ligne de corpus réparable.
 */
final class BoardHydrator
{
    public static function fromCanonicalJson(string $json): CombatBoard
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new UnreadableBoardRecordException(
                'An archived board record is not valid JSON.',
                previous: $exception,
            );
        }

        if (!\is_array($decoded)) {
            throw new UnreadableBoardRecordException(sprintf(
                'An archived board record should decode to an object, got %s.',
                get_debug_type($decoded),
            ));
        }

        /** @var array<array-key, mixed> $envelope */
        $envelope = $decoded;

        $formatVersion = self::readInt($envelope, 'formatVersion', 'formatVersion');
        if ($formatVersion !== BoardRecord::FORMAT_VERSION) {
            throw new UnreadableBoardRecordException(sprintf(
                'An archived board record is in format version %d, but this build reads version %d.',
                $formatVersion,
                BoardRecord::FORMAT_VERSION,
            ));
        }

        $board = self::readArray($envelope, 'board', 'board');
        $vestige = self::readArray($board, 'vestige', 'board.vestige');

        $heroes = [];
        foreach (self::readArray($board, 'heroes', 'board.heroes') as $index => $hero) {
            $path = 'board.heroes.' . $index;
            if (!\is_array($hero)) {
                throw self::wrongType($path, 'an object', get_debug_type($hero));
            }

            $heroes[] = new CombatHero(new HydratedHeroProfile(
                self::readString($hero, 'id', $path . '.id'),
                self::readOptionalSkill($hero, $path . '.skill'),
            ));
        }

        $items = [];
        foreach (self::readArray($board, 'items', 'board.items') as $index => $item) {
            $path = 'board.items.' . $index;
            if (!\is_array($item)) {
                throw self::wrongType($path, 'an object', get_debug_type($item));
            }

            $items[] = new CombatItem(self::readItem($item, $path));
        }

        $combatVestige = new CombatVestige(new HydratedVestigeProfile(
            self::readString($vestige, 'id', 'board.vestige.id'),
            self::readInt($vestige, 'baseHp', 'board.vestige.baseHp'),
            self::readInt($vestige, 'baseShield', 'board.vestige.baseShield'),
        ));
        $gold = self::readInt($board, 'goldAtCombatStart', 'board.goldAtCombatStart');

        // Les règles de plateau — un à trois héros, or positif — ne sont pas
        // réécrites ici : elles vivent dans CombatBoard et une archive n'a
        // aucun privilège. Elles sont rhabillées pour que l'appelant n'ait
        // toujours qu'un seul type à connaître.
        try {
            return new CombatBoard($combatVestige, $heroes, $items, $gold);
        } catch (\InvalidArgumentException $exception) {
            throw new UnreadableBoardRecordException(
                'An archived board record does not describe a playable board: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readItem(array $data, string $path): Item
    {
        $effects = [];
        foreach (self::readArray($data, 'effects', $path . '.effects') as $index => $effect) {
            $effectPath = $path . '.effects.' . $index;
            if (!\is_array($effect)) {
                throw self::wrongType($effectPath, 'an object', get_debug_type($effect));
            }

            $actions = [];
            foreach (self::readArray($effect, 'actions', $effectPath . '.actions') as $actionIndex => $action) {
                $actionPath = $effectPath . '.actions.' . $actionIndex;
                if (!\is_array($action)) {
                    throw self::wrongType($actionPath, 'an object', get_debug_type($action));
                }

                $actions[] = self::readAction($action, $actionPath);
            }

            $effects[] = new Effect(
                Trigger::tryFrom(self::readString($effect, 'trigger', $effectPath . '.trigger'))
                    ?? throw self::unknownCase($effectPath . '.trigger', Trigger::class),
                $actions,
            );
        }

        return new Item(
            id: self::readString($data, 'id', $path . '.id'),
            name: self::readString($data, 'name', $path . '.name'),
            rarity: Rarity::tryFrom(self::readString($data, 'rarity', $path . '.rarity'))
                ?? throw self::unknownCase($path . '.rarity', Rarity::class),
            affinity: self::readString($data, 'affinity', $path . '.affinity'),
            size: ItemSize::tryFrom(self::readString($data, 'size', $path . '.size'))
                ?? throw self::unknownCase($path . '.size', ItemSize::class),
            cooldownTicks: self::readInt($data, 'cooldownTicks', $path . '.cooldownTicks'),
            effects: $effects,
        );
    }

    /**
     * Les six champs facultatifs d'`Action` sont absents plutôt que `null` dans
     * la photographie (D-19, `04` §5.3). L'absence se retraduit donc en `null`,
     * qui est le défaut du constructeur : rien n'est inventé au passage.
     *
     * @param array<array-key, mixed> $data
     */
    private static function readAction(array $data, string $path): Action
    {
        $target = self::readOptionalString($data, 'target', $path . '.target');
        $status = self::readOptionalString($data, 'status', $path . '.status');

        return new Action(
            type: ActionType::tryFrom(self::readString($data, 'type', $path . '.type'))
                ?? throw self::unknownCase($path . '.type', ActionType::class),
            value: self::readOptionalInt($data, 'value', $path . '.value'),
            target: $target === null
                ? null
                : (Target::tryFrom($target) ?? throw self::unknownCase($path . '.target', Target::class)),
            status: $status === null
                ? null
                : (StatusType::tryFrom($status) ?? throw self::unknownCase($path . '.status', StatusType::class)),
            stacks: self::readOptionalInt($data, 'stacks', $path . '.stacks'),
            durationTicks: self::readOptionalInt($data, 'durationTicks', $path . '.durationTicks'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readOptionalSkill(array $data, string $path): ?HeroSkillType
    {
        $skill = self::readOptionalString($data, 'skill', $path);
        if ($skill === null) {
            return null;
        }

        return HeroSkillType::tryFrom($skill) ?? throw self::unknownCase($path, HeroSkillType::class);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function readArray(array $data, string $key, string $path): array
    {
        $value = self::requireKey($data, $key, $path);
        if (!\is_array($value)) {
            throw self::wrongType($path, 'an object', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readString(array $data, string $key, string $path): string
    {
        $value = self::requireKey($data, $key, $path);
        if (!\is_string($value)) {
            throw self::wrongType($path, 'a string', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readInt(array $data, string $key, string $path): int
    {
        $value = self::requireKey($data, $key, $path);
        if (!\is_int($value)) {
            throw self::wrongType($path, 'an integer', get_debug_type($value));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readOptionalString(array $data, string $key, string $path): ?string
    {
        if (!\array_key_exists($key, $data)) {
            return null;
        }

        return self::readString($data, $key, $path);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readOptionalInt(array $data, string $key, string $path): ?int
    {
        if (!\array_key_exists($key, $data)) {
            return null;
        }

        return self::readInt($data, $key, $path);
    }

    /**
     * `array_key_exists` plutôt que `??` : un champ présent mais `null` est une
     * archive malformée, pas un champ absent, et les deux ne se réparent pas de
     * la même façon. Le message doit dire lequel des deux c'est.
     *
     * @param array<array-key, mixed> $data
     */
    private static function requireKey(array $data, string $key, string $path): mixed
    {
        if (!\array_key_exists($key, $data)) {
            throw new UnreadableBoardRecordException(sprintf(
                'An archived board record is missing "%s".',
                $path,
            ));
        }

        return $data[$key];
    }

    private static function wrongType(string $path, string $expected, string $actual): UnreadableBoardRecordException
    {
        return new UnreadableBoardRecordException(sprintf(
            'An archived board record should carry %s at "%s", got %s.',
            $expected,
            $path,
            $actual,
        ));
    }

    private static function unknownCase(string $path, string $enum): UnreadableBoardRecordException
    {
        return new UnreadableBoardRecordException(sprintf(
            'An archived board record carries a value at "%s" that %s does not know.',
            $path,
            $enum,
        ));
    }
}
