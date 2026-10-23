<?php

declare(strict_types=1);

namespace App\Evaluation\Scenarios;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Models\Tool;
use App\Models\ToolApproval;
use App\Models\ToolCall;
use App\Models\ToolVersion;
use App\Services\Tools\Orchestrator\ToolOrchestrator;
use App\Services\Tools\Registry\ToolRegistry;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApprovalEnforcementScenario implements EvaluationScenario
{
    public function key(): string
    {
        return 'approval-enforcement';
    }

    public function label(): string
    {
        return 'Approval enforcement';
    }

    /**
     * @return list<EvaluationCheck>
     */
    public function run(EvaluationContext $context): array
    {
        $tool = Tool::query()->where('name', 'echo')->firstOrFail();
        $current = ToolVersion::query()->where('tool_id', $tool->id)->orderByDesc('version')->firstOrFail();

        ToolVersion::query()->create([
            'tool_id' => $tool->id,
            'version' => $current->version + 1,
            'input_schema' => $current->input_schema,
            'output_schema' => $current->output_schema,
            'risk_level' => 'high',
            'required_scopes' => [],
            'timeout_seconds' => 10,
            'approval_required' => true,
            'limits' => [],
            'metadata' => ['evaluation_only' => true],
            'enabled' => true,
        ]);

        $registry = app(ToolRegistry::class);
        $registry->clearCache();
        $orchestrator = app(ToolOrchestrator::class);

        $proposal = $orchestrator->propose($context->owner, 'echo', ['message' => 'guarded']);
        $call = ToolCall::query()->findOrFail($proposal['tool_call_id']);
        $approval = ToolApproval::query()->where('tool_call_id', $call->id)->first();

        $beforeApproval = [
            'status' => $call->status,
            'executed_at' => $call->executed_at,
            'output' => $call->output,
            'approval_status' => $approval?->status,
        ];

        $otherDenied = null;

        try {
            $orchestrator->approve($context->other, $call);
        } catch (HttpExceptionInterface $exception) {
            $otherDenied = $exception->getStatusCode();
        }

        $call->refresh();

        $afterOtherAttempt = [
            'status' => $call->status,
            'approval_status' => $approval?->fresh()?->status,
        ];

        $approved = $orchestrator->approve($context->owner, $call);
        $call->refresh();
        $approval?->refresh();

        return [
            ($proposal['status'] ?? null) === 'pending_approval'
                ? EvaluationCheck::pass('high_risk_pending', 'High-risk calls stop at pending approval.')
                : EvaluationCheck::fail('high_risk_pending', 'High-risk call did not require approval.'),
            $beforeApproval['status'] === 'pending_approval'
                && $beforeApproval['executed_at'] === null
                && $beforeApproval['output'] === null
                ? EvaluationCheck::pass('no_execution_before_approval', 'No execution occurred before approval.')
                : EvaluationCheck::fail('no_execution_before_approval', 'A high-risk call executed before approval.'),
            $beforeApproval['approval_status'] === 'pending'
                ? EvaluationCheck::pass('approval_record_pending', 'A pending approval record was created.')
                : EvaluationCheck::fail('approval_record_pending', 'Pending approval record was missing.'),
            $otherDenied === 403
                && $afterOtherAttempt['status'] === 'pending_approval'
                && $afterOtherAttempt['approval_status'] === 'pending'
                ? EvaluationCheck::pass('cross_user_approval_denied', 'Another user cannot approve the call.')
                : EvaluationCheck::fail('cross_user_approval_denied', 'Cross-user approval was not denied.'),
            ($approved['status'] ?? null) === 'succeeded' && $call->status === 'succeeded'
                ? EvaluationCheck::pass('approved_execution', 'Approved call executed successfully.')
                : EvaluationCheck::fail('approved_execution', 'Approved call did not execute successfully.'),
            $call->approved_at !== null && $call->executed_at !== null && $call->output === ['echo' => 'guarded', 'user_id' => $context->owner->id]
                ? EvaluationCheck::pass('approval_state_recorded', 'Approval and execution state are recorded with the safe output.')
                : EvaluationCheck::fail('approval_state_recorded', 'Approval or execution state was not recorded correctly.'),
            $approval !== null && $approval->status === 'approved' && $approval->decided_at !== null
                ? EvaluationCheck::pass('approval_record_decided', 'The approval record was marked approved.')
                : EvaluationCheck::fail('approval_record_decided', 'The approval record was not marked approved.'),
        ];
    }
}
