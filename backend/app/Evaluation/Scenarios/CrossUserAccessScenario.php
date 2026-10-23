<?php

declare(strict_types=1);

namespace App\Evaluation\Scenarios;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ToolCall;
use App\Services\Conversations\ConversationService;
use App\Services\Tools\Orchestrator\ToolOrchestrator;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class CrossUserAccessScenario implements EvaluationScenario
{
    public function key(): string
    {
        return 'cross-user-access';
    }

    public function label(): string
    {
        return 'Cross-user access prevention';
    }

    /**
     * @return list<EvaluationCheck>
     */
    public function run(EvaluationContext $context): array
    {
        $conversation = Conversation::factory()->for($context->owner)->create(['title' => 'Owner conversation']);
        $message = Message::factory()->for($conversation)->create([
            'user_id' => $context->owner->id,
            'content' => 'Owner-only content.',
            'role' => 'user',
        ]);

        $otherConversations = app(ConversationService::class)->paginate($context->other);
        $otherConversationIds = array_map(
            static fn (Conversation $item): int => (int) $item->id,
            $otherConversations->items(),
        );

        $proposal = app(ToolOrchestrator::class)->propose($context->owner, 'echo', ['message' => 'owner call']);
        $toolCall = ToolCall::query()->findOrFail($proposal['tool_call_id']);

        $toolDenied = null;

        try {
            app(ToolOrchestrator::class)->approve($context->other, $toolCall);
        } catch (HttpExceptionInterface $exception) {
            $toolDenied = $exception->getStatusCode();
        }

        return [
            Gate::forUser($context->other)->denies('view', $conversation)
                && Gate::forUser($context->other)->denies('update', $conversation)
                && Gate::forUser($context->other)->denies('delete', $conversation)
                ? EvaluationCheck::pass('conversation_access_denied', 'Cross-user conversation access is denied.')
                : EvaluationCheck::fail('conversation_access_denied', 'Cross-user conversation access was allowed.'),
            ! in_array((int) $conversation->id, $otherConversationIds, true)
                ? EvaluationCheck::pass('conversation_list_isolated', 'Conversation listings are user scoped.')
                : EvaluationCheck::fail('conversation_list_isolated', 'A user can list another user\'s conversation.'),
            Gate::forUser($context->other)->denies('view', $message)
                && Gate::forUser($context->other)->denies('delete', $message)
                ? EvaluationCheck::pass('message_access_denied', 'Cross-user message access is denied.')
                : EvaluationCheck::fail('message_access_denied', 'Cross-user message access was allowed.'),
            $toolDenied === 403
                ? EvaluationCheck::pass('tool_call_access_denied', 'Cross-user tool approval is denied.')
                : EvaluationCheck::fail('tool_call_access_denied', 'Cross-user tool approval was not denied.'),
            $toolCall->user_id === $context->owner->id
                ? EvaluationCheck::pass('tool_call_ownership', 'Tool calls remain owned by the proposing user.')
                : EvaluationCheck::fail('tool_call_ownership', 'Tool call ownership changed unexpectedly.'),
        ];
    }
}
