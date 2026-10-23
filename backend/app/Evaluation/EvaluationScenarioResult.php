<?php

declare(strict_types=1);

namespace App\Evaluation;

final readonly class EvaluationScenarioResult
{
    /**
     * @param  list<EvaluationCheck>  $checks
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $checks,
        public ?string $error = null,
    ) {}

    public function passed(): bool
    {
        if ($this->error !== null) {
            return false;
        }

        foreach ($this->checks as $check) {
            if (! $check->passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<EvaluationCheck>
     */
    public function failedChecks(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (EvaluationCheck $check): bool => ! $check->passed,
        ));
    }

    /**
     * @return array{key: string, label: string, passed: bool, error: string|null, checks: list<array{name: string, passed: bool, detail: string}>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'passed' => $this->passed(),
            'error' => $this->error,
            'checks' => array_map(
                static fn (EvaluationCheck $check): array => $check->toArray(),
                $this->checks,
            ),
        ];
    }
}
