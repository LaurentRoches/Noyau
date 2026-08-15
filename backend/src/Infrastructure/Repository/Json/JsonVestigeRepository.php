<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Json;

use App\Domain\Model\Vestige;

final class JsonVestigeRepository
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function find(string $id): Vestige
    {
        if (!file_exists($this->filePath)) {
            throw new \InvalidArgumentException("File not found: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        /** @var list<array{id: string, name: string, affinity: string, baseHp: int, baseShield?: int, startingGold: int, startingIncome: int}>|null $data */
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON format in {$this->filePath}");
        }

        foreach ($data as $vestigeData) {
            if ($vestigeData['id'] === $id) {
                return new Vestige(
                    id: $vestigeData['id'],
                    name: $vestigeData['name'],
                    affinity: $vestigeData['affinity'],
                    baseHp: $vestigeData['baseHp'],
                    baseShield: $vestigeData['baseShield'] ?? 0,
                    startingGold: $vestigeData['startingGold'],
                    startingIncome: $vestigeData['startingIncome']
                );
            }
        }

        throw new \InvalidArgumentException("Vestige with ID '{$id}' not found in {$this->filePath}");
    }
}
