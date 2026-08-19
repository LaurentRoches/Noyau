<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Json;

use App\Domain\Enum\HeroSkillType;
use App\Domain\Model\Hero;

final class JsonHeroRepository
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function find(string $id): Hero
    {
        foreach ($this->getRawData() as $heroData) {
            if ($heroData['id'] === $id) {
                return $this->mapToHero($heroData);
            }
        }

        throw new \InvalidArgumentException("Hero with ID '{$id}' not found in {$this->filePath}");
    }

    /**
     * @return list<Hero>
     */
    public function findAll(): array
    {
        return array_map(
            fn (array $heroData): Hero => $this->mapToHero($heroData),
            $this->getRawData(),
        );
    }

    /**
     * @return list<array{id: string, name: string, affinity: string, itemSlots: int, skill?: string}>
     */
    private function getRawData(): array
    {
        if (!file_exists($this->filePath)) {
            throw new \InvalidArgumentException("File not found: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        /** @var list<array{id: string, name: string, affinity: string, itemSlots: int, skill?: string}>|null $data */
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON format in {$this->filePath}");
        }

        return $data;
    }

    /**
     * @param array{id: string, name: string, affinity: string, itemSlots: int, skill?: string} $heroData
     */
    private function mapToHero(array $heroData): Hero
    {
        return new Hero(
            id: $heroData['id'],
            name: $heroData['name'],
            affinity: $heroData['affinity'],
            itemSlots: $heroData['itemSlots'],
            skill: isset($heroData['skill']) ? HeroSkillType::from($heroData['skill']) : null,
        );
    }
}
