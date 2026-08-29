<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public static function send(ApiResponse $response): never
    {
        http_response_code($response->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
