<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database.status', 'ok')
            ->assertJsonPath('database.database', 'agent_millow_test')
            ->assertJsonStructure([
                'status',
                'app',
                'environment',
                'timestamp',
                'database' => ['status', 'driver', 'database', 'version'],
                'runtime' => ['php', 'laravel'],
                'queue',
                'cache',
            ]);

        $this->assertStringStartsWith('8.4.', (string) $response->json('database.version'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_up_health_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_api_not_found_response_is_json(): void
    {
        $this->getJson('/api/v1/missing')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
