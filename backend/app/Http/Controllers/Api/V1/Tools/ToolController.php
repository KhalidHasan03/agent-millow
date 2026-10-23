<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tools;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tools\DecideToolApprovalRequest;
use App\Http\Requests\Tools\ProposeToolCallRequest;
use App\Models\Conversation;
use App\Models\ToolCall;
use App\Services\Tools\Orchestrator\ToolOrchestrator;
use App\Services\Tools\Registry\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ToolController extends Controller
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolOrchestrator $orchestrator,
    ) {}

    public function index(): JsonResponse
    {
        $tools = $this->registry->all();

        $out = [];
        foreach ($tools as $name => $def) {
            $out[] = [
                'name' => $name,
                'display_name' => $def['display_name'],
                'description' => $def['description'],
                'risk_level' => $def['risk_level'],
                'approval_required' => $def['approval_required'],
                'input_schema' => $def['input_schema'],
            ];
        }

        return response()->json(['data' => $out]);
    }

    public function store(ProposeToolCallRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversation = null;
        $conversationId = $request->integer('conversation_id');
        if ($conversationId > 0) {
            $conversation = Conversation::query()->where('user_id', $user->id)->findOrFail($conversationId);
        }

        $result = $this->orchestrator->propose($user, $request->string('tool')->toString(), $request->input('input', []), $conversation);

        return response()->json($result);
    }

    public function approve(DecideToolApprovalRequest $request, ToolCall $toolCall): JsonResponse
    {
        $result = $this->orchestrator->approve($request->user(), $toolCall, $request->input('reason'));

        return response()->json($result);
    }

    public function reject(DecideToolApprovalRequest $request, ToolCall $toolCall): JsonResponse
    {
        $result = $this->orchestrator->reject($request->user(), $toolCall, $request->input('reason'));

        return response()->json($result);
    }

    public function show(Request $request, ToolCall $toolCall): JsonResponse
    {
        if ($toolCall->user_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        return response()->json([
            'data' => [
                'id' => $toolCall->id,
                'tool' => $toolCall->tool->name,
                'status' => $toolCall->status,
                'risk_level' => $toolCall->risk_level,
                'request_id' => $toolCall->request_id,
                'result' => $toolCall->redacted_output,
                'error_message' => $toolCall->error_message ? 'Tool execution failed.' : null,
                'created_at' => $toolCall->created_at?->toIso8601String(),
                'completed_at' => $toolCall->completed_at?->toIso8601String(),
            ],
        ]);
    }
}
