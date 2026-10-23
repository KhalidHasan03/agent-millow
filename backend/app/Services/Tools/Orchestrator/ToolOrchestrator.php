<?php

declare(strict_types=1);

namespace App\Services\Tools\Orchestrator;

use App\Enums\ToolCallStatus;
use App\Models\Conversation;
use App\Models\Tool;
use App\Models\ToolApproval;
use App\Models\ToolCall;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tools\Contracts\ToolExecutor;
use App\Services\Tools\Executors\EchoToolExecutor;
use App\Services\Tools\Policy\ToolRiskPolicy;
use App\Services\Tools\Redaction\ToolRedactor;
use App\Services\Tools\Registry\ToolRegistry;
use App\Services\Tools\Validation\ToolInputValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ToolOrchestrator
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolInputValidator $validator,
        private readonly ToolRiskPolicy $riskPolicy,
        private readonly ToolRedactor $redactor,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function propose(User $user, string $toolName, array $input, ?Conversation $conversation = null): array
    {
        $definition = $this->registry->get($toolName);
        if ($definition === null) {
            abort(404, 'Unknown or disabled tool.');
        }

        $validation = $this->validator->validate($definition['input_schema'], $input);
        if (! $validation['valid']) {
            abort(422, 'Tool input validation failed.');
        }

        $this->assertWithinQuota($user);

        $risk = $this->riskPolicy->classify($user, $definition, $input);
        $redactedInput = $this->redactor->redact($input);

        $toolCall = DB::transaction(function () use ($user, $conversation, $definition, $input, $redactedInput, $risk): ToolCall {
            $toolCall = ToolCall::query()->create([
                'user_id' => $user->id,
                'conversation_id' => $conversation?->id,
                'tool_id' => $definition['tool_id'],
                'tool_version_id' => $definition['version_id'],
                'request_id' => Str::uuid()->toString(),
                'status' => $risk['approval_required'] ? ToolCallStatus::PendingApproval->value : ToolCallStatus::Pending->value,
                'risk_level' => $risk['risk_level'],
                'input' => $input,
                'redacted_input' => $redactedInput,
            ]);

            if ($risk['approval_required']) {
                ToolApproval::query()->create([
                    'tool_call_id' => $toolCall->id,
                    'user_id' => $user->id,
                    'status' => 'pending',
                ]);
            }

            return $toolCall;
        });

        $this->audit->record('tool.proposed', $user, $toolCall, [
            'tool' => $toolName,
            'risk_level' => $risk['risk_level'],
            'approval_required' => $risk['approval_required'],
            'conversation_id' => $conversation?->id,
        ], request());

        if ($risk['approval_required']) {
            return [
                'status' => 'pending_approval',
                'tool_call_id' => $toolCall->id,
                'request_id' => $toolCall->request_id,
            ];
        }

        return $this->execute($toolCall, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function execute(ToolCall $toolCall, array $definition): array
    {
        $toolCall->loadMissing(['user', 'tool']);

        if (! $toolCall->tool && isset($definition['tool_id'])) {
            $toolCall->setRelation('tool', Tool::query()->find($definition['tool_id']));
        }
        if (! $toolCall->user) {
            $toolCall->setRelation('user', User::query()->find($toolCall->user_id));
        }

        $toolCall->update([
            'status' => ToolCallStatus::Running->value,
            'executed_at' => now(),
        ]);

        $start = microtime(true);

        try {
            $executor = $this->resolveExecutor($toolCall->tool->name);
            $input = $toolCall->input;
            if (is_string($input)) {
                $decoded = json_decode($input, true);
                $input = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($input)) {
                $input = [];
            }
            $user = ($toolCall->relationLoaded('user') && $toolCall->user) ? $toolCall->user : User::query()->find($toolCall->user_id);
            $output = $executor->execute($user, $input);
            $redactedOutput = $this->redactor->redact($output);

            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $timeoutSeconds = (int) ($definition['timeout_seconds'] ?? 30);
            if ($durationMs > $timeoutSeconds * 1000) {
                $toolCall->update([
                    'status' => ToolCallStatus::TimedOut->value,
                    'duration_ms' => $durationMs,
                    'completed_at' => now(),
                    'error_message' => 'Tool execution exceeded timeout.',
                ]);

                $actor = ($toolCall->relationLoaded('user') && $toolCall->user) ? $toolCall->user : User::query()->find($toolCall->user_id);
                $this->audit->record('tool.timed_out', $actor, $toolCall, [
                    'tool' => $toolCall->tool?->name,
                    'duration_ms' => $durationMs,
                ], request());

                return [
                    'status' => 'timed_out',
                    'tool_call_id' => $toolCall->id,
                    'request_id' => $toolCall->request_id,
                ];
            }

            $toolCall->update([
                'status' => ToolCallStatus::Succeeded->value,
                'output' => $output,
                'redacted_output' => $redactedOutput,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $actor = ($toolCall->relationLoaded('user') && $toolCall->user) ? $toolCall->user : User::query()->find($toolCall->user_id);
            $this->audit->record('tool.succeeded', $actor, $toolCall, [
                'tool' => $toolCall->tool?->name,
                'duration_ms' => $durationMs,
            ], request());

            return [
                'status' => 'succeeded',
                'tool_call_id' => $toolCall->id,
                'request_id' => $toolCall->request_id,
                'result' => $redactedOutput,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $toolCall->update([
                'status' => ToolCallStatus::Failed->value,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
                'error_message' => 'Tool execution failed.',
            ]);

            $actor = ($toolCall->relationLoaded('user') && $toolCall->user) ? $toolCall->user : User::query()->find($toolCall->user_id);
            $this->audit->record('tool.failed', $actor, $toolCall, [
                'tool' => $toolCall->tool?->name,
                'duration_ms' => $durationMs,
            ], request());

            Log::error('tool.failed', [
                'tool' => $toolCall->tool?->name,
                'tool_call_id' => $toolCall->getKey(),
                'user_id' => $toolCall->user_id,
                'duration_ms' => $durationMs,
                'exception' => $e::class,
            ]);

            return [
                'status' => 'failed',
                'tool_call_id' => $toolCall->id,
                'request_id' => $toolCall->request_id,
            ];
        }
    }

    private function resolveExecutor(string $name): ToolExecutor
    {
        if (in_array($name, ['echo', 'echo_guarded'], true)) {
            return app(EchoToolExecutor::class);
        }

        abort(500, 'No executor registered for tool.');
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(User $user, ToolCall $toolCall, ?string $reason = null): array
    {
        $this->authorizeOwnership($user, $toolCall);

        if ($toolCall->status !== ToolCallStatus::PendingApproval->value) {
            abort(409, 'Tool call is not pending approval.');
        }

        $toolCall->loadMissing(['approval', 'tool']);

        DB::transaction(function () use ($toolCall, $reason): void {
            $toolCall->update([
                'status' => ToolCallStatus::Approved->value,
                'approved_at' => now(),
            ]);

            if ($toolCall->approval) {
                $toolCall->approval->update([
                    'status' => 'approved',
                    'reason' => $reason,
                    'decided_at' => now(),
                ]);
            }
        });

        $actor = $user;
        $this->audit->record('tool.approved', $actor, $toolCall, [
            'tool' => $toolCall->tool?->name,
        ], request());

        Log::info('tool.approved', [
            'tool' => $toolCall->tool?->name,
            'tool_call_id' => $toolCall->getKey(),
            'user_id' => $user->getKey(),
            'risk_level' => $toolCall->risk_level,
            'tool_request_id' => $toolCall->request_id,
        ]);

        $definition = $this->registry->get($toolCall->tool->name) ?? [];
        if (empty($definition)) {
            abort(500, 'Tool definition missing.');
        }

        return $this->execute($toolCall, $definition);
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(User $user, ToolCall $toolCall, ?string $reason = null): array
    {
        $this->authorizeOwnership($user, $toolCall);

        if ($toolCall->status !== ToolCallStatus::PendingApproval->value) {
            abort(409, 'Tool call is not pending approval.');
        }

        $toolCall->loadMissing(['approval', 'tool']);

        DB::transaction(function () use ($toolCall, $reason): void {
            $toolCall->update([
                'status' => ToolCallStatus::Rejected->value,
                'completed_at' => now(),
            ]);

            if ($toolCall->approval) {
                $toolCall->approval->update([
                    'status' => 'rejected',
                    'reason' => $reason,
                    'decided_at' => now(),
                ]);
            }
        });

        $actor = $user;
        $this->audit->record('tool.rejected', $actor, $toolCall, [
            'tool' => $toolCall->tool?->name,
        ], request());

        Log::info('tool.rejected', [
            'tool' => $toolCall->tool?->name,
            'tool_call_id' => $toolCall->getKey(),
            'user_id' => $user->getKey(),
            'risk_level' => $toolCall->risk_level,
            'tool_request_id' => $toolCall->request_id,
        ]);

        return [
            'status' => 'rejected',
            'tool_call_id' => $toolCall->id,
            'request_id' => $toolCall->request_id,
        ];
    }

    private function authorizeOwnership(User $user, ToolCall $toolCall): void
    {
        if ($toolCall->user_id !== $user->id) {
            abort(403, 'Forbidden.');
        }
    }

    private function assertWithinQuota(User $user): void
    {
        $maxPerHour = (int) config('agent_millow.tools.max_calls_per_hour', 60);

        if ($maxPerHour > 0) {
            $recent = ToolCall::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recent >= $maxPerHour) {
                abort(429, 'Tool call quota exceeded. Try again later.');
            }
        }

        $maxPending = (int) config('agent_millow.tools.max_pending_approvals', 20);

        if ($maxPending > 0) {
            $pending = ToolCall::query()
                ->where('user_id', $user->id)
                ->where('status', ToolCallStatus::PendingApproval->value)
                ->count();

            if ($pending >= $maxPending) {
                abort(429, 'Too many pending tool approvals.');
            }
        }
    }
}
