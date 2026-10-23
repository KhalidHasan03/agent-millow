<?php

declare(strict_types=1);

namespace App\Evaluation\Scenarios;

use App\Enums\MessageStatus;
use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Models\Conversation;
use App\Models\Message;
use App\Providers\Llm\Contracts\ChatMessage;
use App\Providers\Llm\Contracts\ChatRequest;
use App\Providers\Llm\ProviderRegistry;
use App\Services\Conversations\MessageService;

final class ProviderNormalizationScenario implements EvaluationScenario
{
    public function key(): string
    {
        return 'provider-normalization';
    }

    public function label(): string
    {
        return 'Provider response normalization';
    }

    /**
     * @return list<EvaluationCheck>
     */
    public function run(EvaluationContext $context): array
    {
        $provider = app(ProviderRegistry::class)->get('fake');
        $request = new ChatRequest(
            model: 'fake-model',
            messages: [
                new ChatMessage('system', 'Stay within the approved boundary.'),
                new ChatMessage('user', 'normalized input'),
            ],
        );

        $result = $provider->complete($request);

        $conversation = Conversation::factory()->for($context->owner)->create([
            'provider' => 'fake',
            'model' => 'fake-model',
            'system_prompt' => 'Stay within the approved boundary.',
        ]);

        Message::factory()->for($conversation)->create([
            'user_id' => $context->owner->getKey(),
            'role' => 'user',
            'content' => 'normalized input',
            'status' => MessageStatus::Complete,
        ]);

        $built = app(MessageService::class)->buildRequest($conversation);
        $builtRoles = array_map(
            static fn (ChatMessage $message): string => $message->role,
            $built->messages,
        );

        return [
            $provider->name() === 'fake'
                ? EvaluationCheck::pass('fake_adapter_selected', 'Deterministic fake adapter is active.')
                : EvaluationCheck::fail('fake_adapter_selected', 'Expected the fake adapter.'),
            $result->content === '[fake:fake-model] normalized input'
                ? EvaluationCheck::pass('normalized_content', 'Fake response content is normalized.')
                : EvaluationCheck::fail('normalized_content', 'Fake response content was not deterministic.'),
            $result->provider === 'fake' && $result->model === 'fake-model' && $result->finishReason === 'stop'
                ? EvaluationCheck::pass('normalized_metadata', 'Provider, model, and finish reason are normalized.')
                : EvaluationCheck::fail('normalized_metadata', 'Provider metadata was not normalized.'),
            $result->usage->total() > 0 && $result->usage->total() === $result->usage->promptTokens + $result->usage->completionTokens
                ? EvaluationCheck::pass('normalized_usage', 'Token usage is present and internally consistent.')
                : EvaluationCheck::fail('normalized_usage', 'Token usage is missing or inconsistent.'),
            $builtRoles === ['system', 'user'] && $built->messages[1]->content === 'normalized input'
                ? EvaluationCheck::pass('message_context_normalized', 'System and user context are ordered correctly.')
                : EvaluationCheck::fail('message_context_normalized', 'Conversation context was not normalized.'),
            $built->model === 'fake-model' && $built->stream === false
                ? EvaluationCheck::pass('chat_request_normalized', 'Model and streaming flag are normalized.')
                : EvaluationCheck::fail('chat_request_normalized', 'Chat request fields were not normalized.'),
        ];
    }
}
