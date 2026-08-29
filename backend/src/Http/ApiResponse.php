<?php

declare(strict_types=1);

namespace App\Http;

final readonly class ApiResponse
{
    /**
     * @param array<string, mixed> $body
     */
    private function __construct(
        public int $statusCode,
        public array $body,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function json(array $body, int $statusCode = 200): self
    {
        return new self($statusCode, $body);
    }

    public static function error(string $message, int $statusCode): self
    {
        return new self($statusCode, ['error' => $message]);
    }
}
