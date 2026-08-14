<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Json;

use App\Domain\Enum\ActionType;
use App\Domain\Enum\Rarity;
use App\Domain\Enum\StatusType;
use App\Domain\Enum\Target;
use App\Domain\Enum\Trigger;
use App\Domain\Model\Action;
use App\Domain\Model\Effect;
use App\Domain\Model\Item;

final class JsonItemRepository
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function find(string $id): Item
    {
        if (!file_exists($this->filePath)) {
            throw new \InvalidArgumentException("File not found: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        /** @var list<array<string, mixed>>|null $data */
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON format in {$this->filePath}");
        }

        foreach ($data as $itemData) {
            if (($itemData['id'] ?? null) === $id) {
                return $this->mapToItem($itemData);
            }
        }

        throw new \InvalidArgumentException("Item with ID '{$id}' not found in {$this->filePath}");
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapToItem(array $data): Item
    {
        /** @var list<array<string, mixed>> $rawEffects */
        $rawEffects = is_array($data['effects'] ?? null) ? $data['effects'] : [];

        $effects = array_map(
            fn (array $effectData): Effect => $this->mapToEffect($effectData),
            $rawEffects
        );

        return new Item(
            id: (string) $data['id'],
            name: (string) $data['name'],
            rarity: Rarity::from((string) $data['rarity']),
            affinity: (string) $data['affinity'],
            cooldownTicks: (int) $data['cooldownTicks'],
            effects: $effects,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapToEffect(array $data): Effect
    {
        /** @var list<array<string, mixed>> $rawActions */
        $rawActions = is_array($data['actions'] ?? null) ? $data['actions'] : [];

        $actions = array_map(
            fn (array $actionData): Action => $this->mapToAction($actionData),
            $rawActions
        );

        return new Effect(
            trigger: Trigger::from((string) $data['trigger']),
            actions: $actions,
            intervalTicks: isset($data['intervalTicks']) ? (int) $data['intervalTicks'] : null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapToAction(array $data): Action
    {
        $target = isset($data['target']) && is_string($data['target'])
            ? Target::from($data['target'])
            : null;

        $status = isset($data['status']) && is_string($data['status'])
            ? StatusType::from($data['status'])
            : null;

        return new Action(
            type: ActionType::from((string) $data['type']),
            value: isset($data['value']) ? (int) $data['value'] : null,
            target: $target,
            status: $status,
            stacks: isset($data['stacks']) ? (int) $data['stacks'] : null,
            durationTicks: isset($data['durationTicks']) ? (int) $data['durationTicks'] : null,
        );
    }

    /**
     * @return list<Item>
     */
    public function findAll(): array
    {
        if (!file_exists($this->filePath)) {
            throw new \InvalidArgumentException("File not found: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        /** @var list<array<string, mixed>>|null $data */
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON format in {$this->filePath}");
        }

        return array_map(
            fn (array $itemData): Item => $this->mapToItem($itemData),
            $data
        );
    }
}
