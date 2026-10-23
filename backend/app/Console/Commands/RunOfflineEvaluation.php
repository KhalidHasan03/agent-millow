<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Evaluation\EvaluationBaseline;
use App\Evaluation\EvaluationReport;
use App\Evaluation\OfflineEvaluationRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use Throwable;

final class RunOfflineEvaluation extends Command
{
    protected $signature = 'eval:offline
        {--baseline= : Baseline JSON path; defaults to the configured offline baseline}
        {--report= : Report output path; defaults to the configured evaluations path}
        {--update-baseline : Rewrite the baseline from a fully passing report}
        {--json : Emit the machine-readable report instead of human-readable output}';

    protected $description = 'Run the deterministic offline evaluation suite and compare it with a versioned baseline.';

    public function handle(OfflineEvaluationRunner $runner): int
    {
        $baselinePath = (string) ($this->option('baseline') ?: config('evaluation.baseline_path'));
        $reportPath = (string) ($this->option('report') ?: config('evaluation.report_path'));
        $updateBaseline = (bool) $this->option('update-baseline');
        $json = (bool) $this->option('json');

        try {
            $baseline = EvaluationBaseline::fromFile($baselinePath);
            $report = $runner->run(thresholds: $baseline->thresholds);
            $comparison = $baseline->compare($report);
        } catch (Throwable $exception) {
            $this->error(sprintf('Offline evaluation could not run: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $updated = false;
        $updateRefusal = null;

        if ($updateBaseline) {
            $updateRefusal = $this->baselineUpdateBlocker($report, $comparison);

            if ($updateRefusal === null) {
                $this->writeJson($baselinePath, $baseline->toArrayFor($report));
                $updated = true;
            }
        }

        $payload = $report->toArray($comparison);
        $payload['baseline_updated'] = $updated;

        if ($updateRefusal !== null) {
            $payload['baseline_update_refused'] = $updateRefusal;
        }

        try {
            $this->writeJson($reportPath, $payload);
        } catch (JsonException $exception) {
            $this->error(sprintf('Could not write the evaluation report: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        if ($json) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->renderHumanReport($report, $comparison, $reportPath, $baselinePath, $updated, $updateRefusal);
        }

        return $comparison['passed'] && $updateRefusal === null ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{passed: bool, reasons: list<string>, suite_version: string, baseline_version: int, thresholds: array<string, int|float>}  $comparison
     * @return string|null A refusal reason, or null when the update is safe.
     */
    private function baselineUpdateBlocker(EvaluationReport $report, array $comparison): ?string
    {
        if (! $report->allPassed()) {
            return 'the current report has failed scenarios or checks.';
        }

        if (! $comparison['passed']) {
            return 'the report does not satisfy the current baseline contract: '.implode(' ', $comparison['reasons']);
        }

        foreach ($report->scenarios as $scenario) {
            if ($scenario->checks === []) {
                return sprintf('scenario [%s] produced no checks.', $scenario->key);
            }
        }

        return null;
    }

    /**
     * @param  array{passed: bool, reasons: list<string>, suite_version: string, baseline_version: int, thresholds: array<string, int|float>}  $comparison
     */
    private function renderHumanReport(
        EvaluationReport $report,
        array $comparison,
        string $reportPath,
        string $baselinePath,
        bool $updated,
        ?string $updateRefusal,
    ): void {
        $this->info(sprintf('Offline evaluation %s', $report->suiteVersion));
        $this->line(sprintf(
            'Scenarios: %d/%d, checks: %d/%d, pass rate: %.2f%%',
            $report->scenariosPassed(),
            count($report->scenarios),
            $report->passedCheckCount(),
            $report->checkCount(),
            $report->passRate() * 100,
        ));

        foreach ($report->scenarios as $scenario) {
            if ($scenario->passed()) {
                continue;
            }

            $this->error(sprintf(
                'FAIL %s%s',
                $scenario->key,
                $scenario->error !== null ? ' ('.$scenario->error.')' : '',
            ));

            foreach ($scenario->failedChecks() as $check) {
                $this->line(sprintf('  - %s: %s', $check->name, $check->detail));
            }

            if ($scenario->checks === [] && $scenario->error === null) {
                $this->line('  - scenario produced no checks');
            }
        }

        $this->line(sprintf('Baseline %s: %s', $baselinePath, $comparison['passed'] ? 'passed' : 'failed'));

        foreach ($comparison['reasons'] as $reason) {
            $this->line('  - '.$reason);
        }

        $this->line(sprintf('Report written to %s', $reportPath));

        if ($updated) {
            $this->warn('Baseline updated from this fully passing report.');
        }

        if ($updateRefusal !== null) {
            $this->error(sprintf('Refusing to update the baseline: %s', $updateRefusal));
        }

        if (! $comparison['passed'] || $updateRefusal !== null) {
            $this->error('Offline evaluation failed.');
        } else {
            $this->info('Offline evaluation passed.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION).PHP_EOL,
        );
    }
}
