<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ApiResponse;
use PHPUnit\Framework\TestCase;

final class ApiResponseTest extends TestCase
{
    public function testItBuildsAJsonResponseWithDefaultStatus(): void
    {
        $response = ApiResponse::json(['round' => 1]);

        self::assertSame(200, $response->statusCode);
        self::assertSame(['round' => 1], $response->body);
    }

    public function testItBuildsAnErrorResponse(): void
    {
        $response = ApiResponse::error('Invalid slot index', 400);

        self::assertSame(400, $response->statusCode);
        self::assertSame(['error' => 'Invalid slot index'], $response->body);
    }

    /**
     * Un code machine facultatif, pour les refus que le statut HTTP ne
     * distingue pas (`04` §7).
     *
     * Le 409 sert déjà à tout conflit d'état : une action jouée hors séquence,
     * un achat sur une boutique fermée. Un refus pour contenu périmé est d'une
     * autre nature — il ne se corrige pas en rejouant autrement, il ne se
     * corrige pas du tout — et le client doit pouvoir le reconnaître sans lire
     * une phrase en anglais.
     *
     * `assertSame` sur le tableau entier, clés et ordre compris : ce qui est
     * figé ici n'est pas seulement la présence de `code`, c'est que `error`
     * reste premier. Une réponse existante ne doit pas changer d'un octet
     * parce qu'un paramètre facultatif est apparu.
     */
    public function testItBuildsAnErrorResponseCarryingAMachineCode(): void
    {
        $response = ApiResponse::error('Stale content', 409, 'CONTENT_VERSION_MISMATCH');

        self::assertSame(409, $response->statusCode);
        self::assertSame(
            ['error' => 'Stale content', 'code' => 'CONTENT_VERSION_MISMATCH'],
            $response->body,
        );
    }
}
