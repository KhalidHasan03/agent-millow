<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class HealthReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_reports_database_cache_and_queue_checks(): void
    {
        config(['logging.default' => 'null']);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.cache.store', 'array')
            ->assertJsonPath('checks.queue.status', 'ok')
            ->assertJsonPath('checks.queue.connection', 'sync')
            ->assertJsonPath('checks.queue.pending', 0)
            ->assertJsonPath('queue', 'sync')
            ->assertJsonPath('cache', 'array')
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database' => ['status', 'driver', 'database', 'version'],
                    'cache' => ['status', 'store'],
                    'queue' => ['status', 'connection', 'pending'],
                ],
                'runtime' => ['php', 'laravel'],
            ]);
    }

    public function test_readiness_reports_degraded_when_the_cache_is_unavailable(): void
    {
        config(['logging.default' => 'null']);

        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('put')->once()->andThrow(new RuntimeException('cache down'));

        Cache::shouldReceive('store')->once()->andReturn($store);

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.cache.status', 'unavailable')
            ->assertJsonPath('checks.database.status', 'ok');
    }

    public function test_readiness_reports_degraded_when_the_queue_connection_is_misconfigured(): void
    {
        config([
            'logging.default' => 'null',
            'queue.default' => 'does-not-exist',
        ]);

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue.status', 'unavailable')
            ->assertJsonPath('checks.cache.status', 'ok');
    }

    public function test_readiness_reports_degraded_when_the_database_is_unavailable(): void
    {
        config(['logging.default' => 'null']);

        DB::shouldReceive('connection')->andThrow(new RuntimeException('database down'));

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database.status', 'unavailable');
    }

    public function test_readiness_hides_database_details_outside_local_environments(): void
    {
        config(['logging.default' => 'null']);

        $this->app['env'] = 'production';

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        $database = $response->json('checks.database');

        $this->assertIsArray($database);
        $this->assertArrayNotHasKey('database', $database);
        $this->assertArrayNotHasKey('version', $database);
        $this->assertArrayNotHasKey('runtime', (array) $response->json());
    }
}
