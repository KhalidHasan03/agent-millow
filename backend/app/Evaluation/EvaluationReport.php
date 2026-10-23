<?php

declare(strict_types=1);

namespace App\Evaluation;

final readonly class EvaluationReport
{
    /**
     * @param  list<EvaluationScenarioResult>  $scenarios
     * @param  array<string, int|float>  $thresholds
     */
    public function __construct(
        public string $suiteVersion,
        public string $generatedAt,
        public array $scenarios,
        public array $thresholds,
    ) {}

    public function checkCount(): int
    {
        $count = 0;

        foreach ($this->scenarios as $scenario) {
            $count += count($scenario->checks);
        }

        return $count;
    }

    public function passedCheckCount(): int
    {
        $count = 0;

        foreach ($this->scenarios as $scenario) {
            foreach ($scenario->checks as $check) {
                if ($check->passed) {
                    $count++;
                }
            }
        }

        return $count;
    }

    public function passRate(): float
    {
        $total = $this->checkCount();

        if ($total === 0) {
            return 0.0;
        }

        return round($this->passedCheckCount() / $total, 4);
    }

    public function scenariosPassed(): int
    {
        $count = 0;

        foreach ($this->scenarios as $scenario) {
            if ($scenario->passed()) {
                $count++;
            }
        }

        return $count;
    }

    public function allPassed(): bool
    {
        foreach ($this->scenarios as $scenario) {
            if (! $scenario->passed()) {
                return false;
            }
        }

        return $this->scenarios !== [];
    }

    /**
     * @param  list<string>  $criticalScenarios
     */
    public function criticalFailures(array $criticalScenarios): int
    {
        $failures = 0;

        foreach ($this->scenarios as $scenario) {
            if (! in_array($scenario->key, $criticalScenarios, true)) {
                continue;
            }

            if (! $scenario->passed()) {
                $failures++;
            }
        }

        return $failures;
    }

    /**
     * @param  list<string>  $criticalScenarios
     * @return array{scenarios: int, scenarios_passed: int, checks: int, checks_passed: int, pass_rate: float, critical_failures: int, passed: bool}
     */
    public function summary(array $criticalScenarios): array
    {
        return [
            'scenarios' => count($this->scenarios),
            'scenarios_passed' => $this->scenariosPassed(),
            'checks' => $this->checkCount(),
            'checks_passed' => $this->passedCheckCount(),
            'pass_rate' => $this->passRate(),
            'critical_failures' => $this->criticalFailures($criticalScenarios),
            'passed' => $this->allPassed(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $baseline
     * @return array<string, mixed>
     */
    public function toArray(?array $baseline = null): array
    {
        $data = [
            'suite_version' => $this->suiteVersion,
            'generated_at' => $this->generatedAt,
            'environment' => 'offline-sqlite-memory',
            'network' => 'forbidden',
            'thresholds' => $this->thresholds,
            'scenarios' => array_map(
                static fn (EvaluationScenarioResult $scenario): array => $scenario->toArray(),
                $this->scenarios,
            ),
        ];

        if ($baseline !== null) {
            $data['baseline'] = $baseline;
        }

        return $data;
    }
}
