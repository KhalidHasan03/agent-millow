<?php

declare(strict_types=1);

namespace App\Evaluation\Contracts;

use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;

interface EvaluationScenario
{
    public function key(): string;

    public function label(): string;

    /**
     * @return list<EvaluationCheck>
     */
    public function run(EvaluationContext $context): array;
}
