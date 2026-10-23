<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Providers\Llm\ProviderRegistry;
use App\Providers\Llm\ProviderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Readiness probe.
 *
 * Liveness remains the framework `/up` endpoint, which does not touch external
 * services. This endpoint verifies that the application can reach its database,
 * cache, and queue backend, and that the default provider is configured, before
 * it is considered ready to serve traffic. Provider readiness is configuration
 * only and never makes a network request.
 */
final class HealthController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
    ) {}

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseStatus(),
            'cache' => $this->cacheStatus(),
            'queue' => $this->queueStatus(),
            'providers' => $this->providerStatus(),
        ];

        $healthy = true;

        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $healthy = false;
            }
        }

        $payload = [
            'status' => $healthy ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'database' => $checks['database'],
            'queue' => (string) config('queue.default'),
            'cache' => (string) config('cache.default'),
        ];

        if (app()->environment(['local', 'testing'])) {
            $payload['runtime'] = [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ];
        }

        return response()->json($payload, $healthy ? 200 : 503);
    }

    /**
     * @return array{status: string, default: string, default_ready: bool, providers: list<array{name: string, enabled: bool, ready: bool, supports_streaming: bool}>}
     */
    private function providerStatus(): array
    {
        $default = $this->providers->defaultStatus();

        return [
            'status' => $default->ready ? 'ok' : 'unavailable',
            'default' => $default->name,
            'default_ready' => $default->ready,
            'providers' => array_map(
                static fn (ProviderStatus $status): array => $status->toArray(),
                $this->providers->statuses(),
            ),
        ];
    }

    /**
     * @return array{status: string, driver?: string, database?: string|null, version?: string|null}
     */
    private function databaseStatus(): array
    {
        try {
            $connection = DB::connection();
            $row = (array) $connection->selectOne('select version() as version, database() as database_name');

            $status = ['status' => 'ok', 'driver' => $connection->getDriverName()];

            if (app()->environment(['local', 'testing'])) {
                $status['database'] = isset($row['database_name']) ? (string) $row['database_name'] : null;
                $status['version'] = isset($row['version']) ? (string) $row['version'] : null;
            }

            return $status;
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'unavailable'];
        }
    }

    /**
     * @return array{status: string, store: string}
     */
    private function cacheStatus(): array
    {
        $store = (string) config('cache.default');

        try {
            $cache = Cache::store();
            $key = 'health:cache:'.Str::uuid()->toString();
            $cache->put($key, 'ok', 5);

            if ($cache->get($key) !== 'ok') {
                throw new RuntimeException('Cache read-back failed.');
            }

            $cache->forget($key);

            return ['status' => 'ok', 'store' => $store];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'unavailable', 'store' => $store];
        }
    }

    /**
     * @return array{status: string, connection: string, pending?: int}
     */
    private function queueStatus(): array
    {
        $connection = (string) config('queue.default');
        $queue = (string) config('queue.connections.'.$connection.'.queue', 'default');

        try {
            $pending = Queue::connection($connection)->size($queue);

            return ['status' => 'ok', 'connection' => $connection, 'pending' => $pending];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'unavailable', 'connection' => $connection];
        }
    }
}
