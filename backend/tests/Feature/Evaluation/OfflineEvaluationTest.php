<?php

declare(strict_types=1);

namespace Tests\Feature\Evaluation;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationBaseline;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Evaluation\OfflineEvaluationRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

final class OfflineEvaluationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/evaluation-'.Str::uuid()->toString());
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_offline_evaluation_passes_without_network_or_application_database_writes(): void
    {
        Http::fake();

        $reportPath = $this->directory.'/report.json';
        $toolsBefore = DB::table('tool_calls')->count();
        $auditBefore = DB::table('audit_events')->count();

        $exitCode = Artisan::call('eval:offline', [
            '--report' => $reportPath,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        Http::assertNothingSent();
        $this->assertFileExists($reportPath);

        $payload = $this->decode(File::get($reportPath));
        $this->assertSame('offline-v1', $payload['suite_version']);
        $this->assertSame('offline-sqlite-memory', $payload['environment']);
        $this->assertSame('forbidden', $payload['network']);
        $this->assertFalse($payload['baseline_updated']);
        $this->assertTrue($payload['baseline']['passed']);

        $this->assertCount(8, $payload['scenarios']);
        $checks = 0;

        foreach ($payload['scenarios'] as $scenario) {
            $this->assertTrue($scenario['passed']);
            $checks += count($scenario['checks']);
        }

        $this->assertSame(50, $checks);
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('agent_millow_test', DB::connection()->getDatabaseName());
        $this->assertFalse(Cache::has('agent_millow.tools.registry.v1'));
        $this->assertSame($toolsBefore, DB::table('tool_calls')->count());
        $this->assertSame($auditBefore, DB::table('audit_events')->count());
    }

    public function test_baseline_contract_mismatch_fails_the_command(): void
    {
        $baseline = $this->baselineData();
        $baseline['suite_version'] = 'offline-v2';
        $baselinePath = $this->writeBaseline($baseline, 'mismatch.json');
        $baselineBefore = File::get($baselinePath);
        $reportPath = $this->directory.'/mismatch-report.json';

        $exitCode = Artisan::call('eval:offline', [
            '--baseline' => $baselinePath,
            '--report' => $reportPath,
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode, Artisan::output());
        $this->assertSame($baselineBefore, File::get($baselinePath));

        $payload = $this->decode(File::get($reportPath));
        $this->assertFalse($payload['baseline']['passed']);
        $this->assertStringContainsString(
            'Suite version mismatch',
            implode(' ', $payload['baseline']['reasons']),
        );
    }

    public function test_unsafe_baseline_update_is_refused_and_leaves_the_baseline_unchanged(): void
    {
        $baseline = $this->baselineData();
        $baseline['thresholds']['minimum_checks'] = 999;
        $baselinePath = $this->writeBaseline($baseline, 'unsafe.json');
        $baselineBefore = File::get($baselinePath);
        $reportPath = $this->directory.'/unsafe-report.json';

        $exitCode = Artisan::call('eval:offline', [
            '--baseline' => $baselinePath,
            '--report' => $reportPath,
            '--update-baseline' => true,
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode, Artisan::output());
        $this->assertSame($baselineBefore, File::get($baselinePath));

        $payload = $this->decode(File::get($reportPath));
        $this->assertFalse($payload['baseline_updated']);
        $this->assertArrayHasKey('baseline_update_refused', $payload);
        $this->assertStringContainsString('minimum', $payload['baseline_update_refused']);
    }

    public function test_fully_passing_report_can_update_a_compatible_baseline(): void
    {
        $baseline = $this->baselineData();
        array_pop($baseline['expected_checks']['provider-normalization']);
        $baselinePath = $this->writeBaseline($baseline, 'update.json');
        $reportPath = $this->directory.'/update-report.json';

        $exitCode = Artisan::call('eval:offline', [
            '--baseline' => $baselinePath,
            '--report' => $reportPath,
            '--update-baseline' => true,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $payload = $this->decode(File::get($reportPath));
        $this->assertTrue($payload['baseline_updated']);

        $updated = $this->decode(File::get($baselinePath));
        $this->assertCount(6, $updated['expected_checks']['provider-normalization']);
        $this->assertSame($baseline['thresholds'], $updated['thresholds']);
        $this->assertSame($baseline['critical_scenarios'], $updated['critical_scenarios']);
    }

    public function test_failing_safety_check_is_detected_and_network_is_blocked(): void
    {
        Http::fake(['https://allowed.invalid/*' => Http::response()]);

        $scenario = new class implements EvaluationScenario
        {
            public function key(): string
            {
                return 'safety-probe';
            }

            public function label(): string
            {
                return 'Safety probe';
            }

            public function run(EvaluationContext $context): array
            {
                $blocked = false;

                try {
                    Http::get('https://example.invalid/collect');
                } catch (Throwable) {
                    $blocked = true;
                }

                return [
                    $blocked
                        ? EvaluationCheck::pass('network_blocked', 'Network access is blocked.')
                        : EvaluationCheck::fail('network_blocked', 'Network access was not blocked.'),
                    EvaluationCheck::fail('safety_boundary', 'A deliberate safety regression was detected.'),
                ];
            }
        };

        $report = app(OfflineEvaluationRunner::class)->run([$scenario]);

        Http::assertNothingSent();
        $this->assertFalse($report->allPassed());
        $this->assertSame(2, $report->checkCount());
        $this->assertSame(1, $report->passedCheckCount());
        $this->assertSame(0.5, $report->passRate());

        $baselinePath = $this->writeBaseline([
            'baseline_version' => 1,
            'suite_version' => 'offline-v1',
            'thresholds' => [
                'minimum_pass_rate' => 1.0,
                'maximum_failed_checks' => 0,
                'minimum_checks' => 2,
                'maximum_critical_failures' => 0,
            ],
            'critical_scenarios' => ['safety-probe'],
            'required_scenarios' => ['safety-probe'],
            'expected_checks' => ['safety-probe' => ['network_blocked', 'safety_boundary']],
        ], 'safety.json');

        $comparison = EvaluationBaseline::fromFile($baselinePath)->compare($report);

        $this->assertFalse($comparison['passed']);
        $this->assertStringContainsString(
            'Expected check [safety_boundary] in scenario [safety-probe] failed.',
            implode(' ', $comparison['reasons']),
        );
        $this->assertSame('mysql', config('database.default'));
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineData(): array
    {
        return $this->decode(File::get((string) config('evaluation.baseline_path')));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeBaseline(array $data, string $name): string
    {
        $path = $this->directory.'/'.$name;
        File::put($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
