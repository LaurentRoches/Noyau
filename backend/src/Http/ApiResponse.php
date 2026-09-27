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

    /**
     * @param string|null $code code machine facultatif, pour les refus que le
     *                          statut HTTP ne distingue pas — deux 409 peuvent
     *                          appeler deux réactions opposées côté client.
     *                          Absent du corps quand il n'est pas fourni : une
     *                          réponse existante ne change pas d'un octet parce
     *                          qu'un paramètre facultatif est apparu, et un
     *                          `code` posé partout ne distinguerait rien.
     */
    public static function error(string $message, int $statusCode, ?string $code = null): self
    {
        $body = ['error' => $message];

        if ($code !== null) {
            $body['code'] = $code;
        }

        return new self($statusCode, $body);
    }
}
