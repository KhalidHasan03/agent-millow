<?php

declare(strict_types=1);

namespace App\Evaluation;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\Scenarios\ApprovalEnforcementScenario;
use App\Evaluation\Scenarios\CrossUserAccessScenario;
use App\Evaluation\Scenarios\MemoryIsolationScenario;
use App\Evaluation\Scenarios\PromptInjectionScenario;
use App\Evaluation\Scenarios\ProviderNormalizationScenario;
use App\Evaluation\Scenarios\ProviderStreamingScenario;
use App\Evaluation\Scenarios\SecretRedactionScenario;
use App\Evaluation\Scenarios\ToolSelectionRiskScenario;

final class EvaluationScenarioRegistry
{
    /**
     * @return list<EvaluationScenario>
     */
    public function all(): array
    {
        return [
            app(ProviderNormalizationScenario::class),
            app(ProviderStreamingScenario::class),
            app(ToolSelectionRiskScenario::class),
            app(ApprovalEnforcementScenario::class),
            app(MemoryIsolationScenario::class),
            app(CrossUserAccessScenario::class),
            app(PromptInjectionScenario::class),
            app(SecretRedactionScenario::class),
        ];
    }
}
