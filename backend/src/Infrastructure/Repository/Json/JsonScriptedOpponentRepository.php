<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Json;

final class JsonScriptedOpponentRepository
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function findAll(): array
    {
        if (!file_exists($this->filePath)) {
            throw new \InvalidArgumentException("File not found: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        /** @var list<array{heroId: string, itemIds: list<string>}>|null $data */
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON format in {$this->filePath}");
        }

        $result = [];
        foreach ($data as $slot) {
            $result[$slot['heroId']] = $slot['itemIds'];
        }

        return $result;
    }
}
