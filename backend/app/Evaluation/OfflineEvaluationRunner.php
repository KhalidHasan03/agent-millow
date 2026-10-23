<?php

declare(strict_types=1);

namespace App\Evaluation;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Models\User;
use App\Services\Tools\Registry\ToolRegistry;
use Database\Seeders\EvaluationToolSeeder;
use Database\Seeders\ToolSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class OfflineEvaluationRunner
{
    public function __construct(
        private readonly EvaluationScenarioRegistry $registry,
    ) {}

    /**
     * @param  iterable<EvaluationScenario>|null  $scenarios
     * @param  array<string, int|float>  $thresholds
     */
    public function run(?iterable $scenarios = null, array $thresholds = []): EvaluationReport
    {
        $originalDefault = (string) config('database.default');
        $originalCache = (string) config('cache.default');
        $originalQueue = (string) config('queue.default');

        $this->bootIsolatedDatabase();

        /** @var array<string, int|float> $configuredThresholds */
        $configuredThresholds = (array) config('evaluation.thresholds', []);

        try {
            $this->migrate();
            $this->seed();

            $owner = User::factory()->create(['email' => 'eval-owner@example.test']);
            $other = User::factory()->create(['email' => 'eval-other@example.test']);
            $admin = User::factory()->admin()->create(['email' => 'eval-admin@example.test']);
            $context = new EvaluationContext($owner, $other, $admin);

            $results = [];

            /** @var iterable<EvaluationScenario> $scenarioList */
            $scenarioList = $scenarios ?? $this->registry->all();

            foreach ($scenarioList as $scenario) {
                DB::beginTransaction();

                try {
                    $checks = $scenario->run($context);
                    $results[] = new EvaluationScenarioResult(
                        key: $scenario->key(),
                        label: $scenario->label(),
                        checks: $checks,
                    );
                } catch (Throwable $exception) {
                    $results[] = new EvaluationScenarioResult(
                        key: $scenario->key(),
                        label: $scenario->label(),
                        checks: [],
                        error: $exception::class,
                    );
                } finally {
                    DB::rollBack();
                    app(ToolRegistry::class)->clearCache();
                }
            }

            return new EvaluationReport(
                suiteVersion: (string) config('evaluation.suite_version', 'offline-v1'),
                generatedAt: now()->toIso8601String(),
                scenarios: $results,
                thresholds: $thresholds === [] ? $configuredThresholds : $thresholds,
            );
        } finally {
            $this->restoreDatabase($originalDefault, $originalCache, $originalQueue);
            Http::allowStrayRequests();
        }
    }

    private function bootIsolatedDatabase(): void
    {
        config([
            'database.default' => 'evaluation',
            'cache.default' => 'array',
            'queue.default' => 'sync',
        ]);

        DB::purge('evaluation');
        DB::setDefaultConnection('evaluation');
        Http::preventStrayRequests();
    }

    private function migrate(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => 'evaluation',
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('The evaluation database migration failed.');
        }
    }

    private function seed(): void
    {
        (new ToolSeeder)->run();
        (new EvaluationToolSeeder)->run();
    }

    private function restoreDatabase(string $default, string $cache, string $queue): void
    {
        app(ToolRegistry::class)->clearCache();

        DB::purge('evaluation');
        DB::setDefaultConnection($default);

        config([
            'database.default' => $default,
            'cache.default' => $cache,
            'queue.default' => $queue,
        ]);
    }
}
