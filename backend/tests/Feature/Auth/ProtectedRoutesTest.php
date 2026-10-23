<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProtectedRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function protectedRoutes(): array
    {
        return [
            ['GET', '/api/v1/auth/user'],
            ['POST', '/api/v1/auth/logout'],
            ['GET', '/api/v1/me'],
            ['PATCH', '/api/v1/me'],
            ['PUT', '/api/v1/me/password'],
            ['DELETE', '/api/v1/me'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_guests_cannot_access_protected_routes(string $method, string $uri): void
    {
        $this->json($method, $uri)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonStructure(['message', 'request_id']);
    }

    public function test_unauthenticated_response_is_json_with_request_id(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonStructure(['message', 'request_id']);
    }

    public function test_health_endpoint_remains_public(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
    }
}
