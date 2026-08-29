<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly ?string $rawBody,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $rawBody = file_get_contents('php://input');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $uri,
            $rawBody !== false ? $rawBody : null,
        );
    }

    public static function fake(string $method = 'GET', string $uri = '/', ?string $rawBody = null): self
    {
        return new self(strtoupper($method), $uri, $rawBody);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        if ($this->rawBody === null || $this->rawBody === '') {
            return null;
        }

        $decoded = json_decode($this->rawBody, true);

        return is_array($decoded) ? $decoded : null;
    }
}
