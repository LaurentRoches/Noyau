<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Json;

use App\Domain\Model\Hero;

final class JsonHeroRepository
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function find(string $id): Hero
    {
        if (!file_exists($this->filePath)) {
            throw new \InvalidArgumentException("File not found: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        /** @var list<array{id: string, name: string, affinity: string, itemSlots: int}>|null $data */
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON format in {$this->filePath}");
        }

        foreach ($data as $heroData) {
            if ($heroData['id'] === $id) {
                return new Hero(
                    id: $heroData['id'],
                    name: $heroData['name'],
                    affinity: $heroData['affinity'],
                    itemSlots: $heroData['itemSlots'],
                );
            }
        }

        throw new \InvalidArgumentException("Hero with ID '{$id}' not found in {$this->filePath}");
    }
}
