<?php

declare(strict_types=1);

namespace App\Evaluation\Scenarios;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Models\ToolCall;
use App\Services\Tools\Orchestrator\ToolOrchestrator;
use App\Services\Tools\Policy\ToolRiskPolicy;
use App\Services\Tools\Registry\ToolRegistry;
use App\Services\Tools\Validation\ToolInputValidator;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ToolSelectionRiskScenario implements EvaluationScenario
{
    public function key(): string
    {
        return 'tool-selection-risk';
    }

    public function label(): string
    {
        return 'Tool selection and risk classification';
    }

    /**
     * @return list<EvaluationCheck>
     */
    public function run(EvaluationContext $context): array
    {
        $registry = app(ToolRegistry::class);
        $policy = app(ToolRiskPolicy::class);
        $validator = app(ToolInputValidator::class);

        $echo = $registry->get('echo');
        $guarded = $registry->get('eval_guarded');
        $lowRisk = $policy->classify($context->owner, $echo ?? [], ['message' => 'hello']);
        $highRisk = $policy->classify($context->owner, $guarded ?? [], ['message' => 'hello']);
        $criticalRisk = $policy->classify($context->owner, [
            'risk_level' => 'critical',
            'approval_required' => false,
            'limits' => [],
        ], []);
        $adminLimited = $policy->classify($context->admin, [
            'risk_level' => 'low',
            'approval_required' => false,
            'limits' => ['require_approval_for_roles' => ['admin']],
        ], []);
        $ownerLimited = $policy->classify($context->owner, [
            'risk_level' => 'low',
            'approval_required' => false,
            'limits' => ['require_approval_for_roles' => ['admin']],
        ], []);

        $validInput = $validator->validate($echo['input_schema'] ?? [], ['message' => 'hello']);
        $invalidInput = $validator->validate($echo['input_schema'] ?? [], ['message' => 123]);

        $before = ToolCall::query()->count();
        $unknownStatus = null;

        try {
            app(ToolOrchestrator::class)->propose($context->owner, 'delete_everything', []);
        } catch (HttpExceptionInterface $exception) {
            $unknownStatus = $exception->getStatusCode();
        }

        return [
            $echo !== null && ($echo['risk_level'] ?? null) === 'low'
                ? EvaluationCheck::pass('known_tool_selected', 'Echo is selectable from the registry.')
                : EvaluationCheck::fail('known_tool_selected', 'Echo is missing or has the wrong risk level.'),
            $guarded !== null && ($guarded['risk_level'] ?? null) === 'high'
                ? EvaluationCheck::pass('guarded_tool_selected', 'Guarded evaluation tool is registered as high risk.')
                : EvaluationCheck::fail('guarded_tool_selected', 'Guarded evaluation tool is missing or misclassified.'),
            ! $registry->has('delete_everything')
                ? EvaluationCheck::pass('unknown_tool_not_selectable', 'Unknown tools are not in the registry.')
                : EvaluationCheck::fail('unknown_tool_not_selectable', 'An unknown tool was exposed by the registry.'),
            $lowRisk['risk_level'] === 'low' && $lowRisk['approval_required'] === false
                ? EvaluationCheck::pass('low_risk_allowed', 'Low-risk tools execute without approval.')
                : EvaluationCheck::fail('low_risk_allowed', 'Low-risk classification is incorrect.'),
            $highRisk['risk_level'] === 'high' && $highRisk['approval_required'] === true
                ? EvaluationCheck::pass('high_risk_requires_approval', 'High-risk tools require approval.')
                : EvaluationCheck::fail('high_risk_requires_approval', 'High-risk tools are not gated.'),
            $criticalRisk['risk_level'] === 'critical' && $criticalRisk['approval_required'] === true
                ? EvaluationCheck::pass('critical_risk_requires_approval', 'Critical-risk tools require approval.')
                : EvaluationCheck::fail('critical_risk_requires_approval', 'Critical-risk tools are not gated.'),
            $adminLimited['approval_required'] === true && $ownerLimited['approval_required'] === false
                ? EvaluationCheck::pass('role_approval_limit', 'Role-based approval limits are enforced.')
                : EvaluationCheck::fail('role_approval_limit', 'Role-based approval limits are incorrect.'),
            $validInput['valid'] === true && $invalidInput['valid'] === false
                ? EvaluationCheck::pass('schema_validation', 'Tool input schema accepts valid and rejects invalid input.')
                : EvaluationCheck::fail('schema_validation', 'Tool input schema validation is incorrect.'),
            $unknownStatus === 404 && ToolCall::query()->count() === $before
                ? EvaluationCheck::pass('unknown_tool_rejected', 'Unknown tool proposals are rejected without side effects.')
                : EvaluationCheck::fail('unknown_tool_rejected', 'Unknown tool proposal was not rejected cleanly.'),
        ];
    }
}
