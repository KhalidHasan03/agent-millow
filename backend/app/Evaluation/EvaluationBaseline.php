<?php

declare(strict_types=1);

namespace App\Evaluation;

use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

final readonly class EvaluationBaseline
{
    /**
     * @param  array<string, int|float>  $thresholds
     * @param  list<string>  $criticalScenarios
     * @param  list<string>  $requiredScenarios
     * @param  array<string, list<string>>  $expectedChecks
     */
    private function __construct(
        public string $suiteVersion,
        public array $thresholds,
        public array $criticalScenarios,
        public array $requiredScenarios,
        public array $expectedChecks,
        public string $path,
    ) {}

    public static function fromFile(string $path): self
    {
        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('Evaluation baseline [%s] does not exist.', $path));
        }

        try {
            $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Evaluation baseline [%s] is not valid JSON.', $path), 0, $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException(sprintf('Evaluation baseline [%s] must contain a JSON object.', $path));
        }

        $suiteVersion = $data['suite_version'] ?? null;
        $thresholds = $data['thresholds'] ?? null;
        $criticalScenarios = $data['critical_scenarios'] ?? null;
        $requiredScenarios = $data['required_scenarios'] ?? null;
        $expectedChecks = $data['expected_checks'] ?? [];

        if (! is_string($suiteVersion) || $suiteVersion === '') {
            throw new RuntimeException('Evaluation baseline is missing suite_version.');
        }

        if (! is_array($thresholds)) {
            throw new RuntimeException('Evaluation baseline is missing thresholds.');
        }

        $normalizedThresholds = [];

        foreach ($thresholds as $key => $value) {
            if (! is_string($key) || (! is_int($value) && ! is_float($value))) {
                throw new RuntimeException('Evaluation baseline thresholds must be numeric.');
            }

            $normalizedThresholds[$key] = $value;
        }

        return new self(
            suiteVersion: $suiteVersion,
            thresholds: $normalizedThresholds,
            criticalScenarios: self::stringList($criticalScenarios, 'critical_scenarios'),
            requiredScenarios: self::stringList($requiredScenarios, 'required_scenarios'),
            expectedChecks: self::expectedChecks($expectedChecks),
            path: $path,
        );
    }

    /**
     * @return array{passed: bool, reasons: list<string>, suite_version: string, baseline_version: int, thresholds: array<string, int|float>}
     */
    public function compare(EvaluationReport $report): array
    {
        $reasons = [];

        if ($report->suiteVersion !== $this->suiteVersion) {
            $reasons[] = sprintf(
                'Suite version mismatch: report [%s], baseline [%s].',
                $report->suiteVersion,
                $this->suiteVersion,
            );
        }

        $actualScenarios = [];

        foreach ($report->scenarios as $scenario) {
            $actualScenarios[$scenario->key] = $scenario;
        }

        foreach ($this->requiredScenarios as $key) {
            if (! array_key_exists($key, $actualScenarios)) {
                $reasons[] = sprintf('Required scenario [%s] did not run.', $key);

                continue;
            }

            if (! $actualScenarios[$key]->passed()) {
                $reasons[] = sprintf('Required scenario [%s] failed.', $key);
            }
        }

        foreach ($this->expectedChecks as $scenarioKey => $checkNames) {
            $scenario = $actualScenarios[$scenarioKey] ?? null;

            if ($scenario === null) {
                $reasons[] = sprintf('Baseline references missing scenario [%s].', $scenarioKey);

                continue;
            }

            $actualChecks = [];

            foreach ($scenario->checks as $check) {
                $actualChecks[$check->name] = $check;
            }

            foreach ($checkNames as $checkName) {
                if (! array_key_exists($checkName, $actualChecks)) {
                    $reasons[] = sprintf('Expected check [%s] in scenario [%s] did not run.', $checkName, $scenarioKey);

                    continue;
                }

                if (! $actualChecks[$checkName]->passed) {
                    $reasons[] = sprintf('Expected check [%s] in scenario [%s] failed.', $checkName, $scenarioKey);
                }
            }
        }

        $minimumPassRate = $this->threshold('minimum_pass_rate', 1.0);

        if ($report->passRate() < $minimumPassRate) {
            $reasons[] = sprintf(
                'Pass rate %.4f is below the required minimum %.4f.',
                $report->passRate(),
                $minimumPassRate,
            );
        }

        $maximumFailedChecks = (int) $this->threshold('maximum_failed_checks', 0);

        if ($report->checkCount() - $report->passedCheckCount() > $maximumFailedChecks) {
            $reasons[] = sprintf(
                'Failed check count exceeds the maximum of %d.',
                $maximumFailedChecks,
            );
        }

        $minimumChecks = (int) $this->threshold('minimum_checks', 0);

        if ($report->checkCount() < $minimumChecks) {
            $reasons[] = sprintf(
                'Check count %d is below the required minimum %d.',
                $report->checkCount(),
                $minimumChecks,
            );
        }

        $maximumCriticalFailures = (int) $this->threshold('maximum_critical_failures', 0);

        if ($report->criticalFailures($this->criticalScenarios) > $maximumCriticalFailures) {
            $reasons[] = sprintf(
                'Critical failure count exceeds the maximum of %d.',
                $maximumCriticalFailures,
            );
        }

        return [
            'passed' => $reasons === [],
            'reasons' => $reasons,
            'suite_version' => $this->suiteVersion,
            'baseline_version' => $this->baselineVersion(),
            'thresholds' => $this->thresholds,
        ];
    }

    /**
     * Build a baseline only from a fully passing report. Existing thresholds and
     * critical-scenario designations are retained; check names are the new
     * contract for future runs.
     *
     * @return array<string, mixed>
     */
    public function toArrayFor(EvaluationReport $report): array
    {
        $requiredScenarios = [];
        $expectedChecks = [];

        foreach ($report->scenarios as $scenario) {
            $requiredScenarios[] = $scenario->key;
            $expectedChecks[$scenario->key] = array_map(
                static fn (EvaluationCheck $check): string => $check->name,
                $scenario->checks,
            );
        }

        return [
            'baseline_version' => $this->baselineVersion(),
            'suite_version' => $report->suiteVersion,
            'created_at' => gmdate('Y-m-d'),
            'thresholds' => $this->thresholds,
            'critical_scenarios' => $this->criticalScenarios,
            'required_scenarios' => $requiredScenarios,
            'expected_checks' => $expectedChecks,
        ];
    }

    private function threshold(string $key, int|float $default): float
    {
        $value = $this->thresholds[$key] ?? $default;

        return (float) $value;
    }

    private function baselineVersion(): int
    {
        $data = json_decode(File::get($this->path), true);

        if (! is_array($data)) {
            return 1;
        }

        $version = $data['baseline_version'] ?? 1;

        return is_int($version) ? $version : 1;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new RuntimeException(sprintf('Evaluation baseline field [%s] must be an array.', $field));
        }

        $list = [];

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw new RuntimeException(sprintf('Evaluation baseline field [%s] must contain strings.', $field));
            }

            $list[] = $item;
        }

        return $list;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function expectedChecks(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('Evaluation baseline field [expected_checks] must be an object.');
        }

        $result = [];

        foreach ($value as $scenario => $checks) {
            if (! is_string($scenario) || $scenario === '') {
                throw new RuntimeException('Evaluation baseline expected_checks keys must be scenario names.');
            }

            $result[$scenario] = self::stringList($checks, 'expected_checks.'.$scenario);
        }

        return $result;
    }
}
