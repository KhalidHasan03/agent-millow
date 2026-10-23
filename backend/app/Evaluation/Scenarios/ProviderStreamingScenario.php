<?php

declare(strict_types=1);

namespace App\Evaluation\Scenarios;

use App\Evaluation\Contracts\EvaluationScenario;
use App\Evaluation\EvaluationCheck;
use App\Evaluation\EvaluationContext;
use App\Providers\Llm\Contracts\ChatMessage;
use App\Providers\Llm\Contracts\ChatRequest;
use App\Providers\Llm\Contracts\StreamEvent;
use App\Providers\Llm\ProviderRegistry;

final class ProviderStreamingScenario implements EvaluationScenario
{
    public function key(): string
    {
        return 'provider-streaming';
    }

    public function label(): string
    {
        return 'Provider streaming normalization';
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
                new ChatMessage('user', 'A longer streaming response should arrive in multiple normalized chunks.'),
            ],
            stream: true,
        );

        $content = '';
        $deltaCount = 0;
        $done = null;
        $error = null;

        foreach ($provider->stream($request) as $event) {
            if ($event->type === StreamEvent::TYPE_DELTA) {
                $content .= $event->text;
                $deltaCount++;
            } elseif ($event->type === StreamEvent::TYPE_DONE) {
                $done = $event;
            } elseif ($event->type === StreamEvent::TYPE_ERROR) {
                $error = $event;
            }
        }

        return [
            $deltaCount > 1
                ? EvaluationCheck::pass('stream_chunks_split', sprintf('Received %d delta chunks.', $deltaCount))
                : EvaluationCheck::fail('stream_chunks_split', 'Expected more than one delta chunk.'),
            $content === '[fake:fake-model] A longer streaming response should arrive in multiple normalized chunks.'
                ? EvaluationCheck::pass('stream_content_normalized', 'Delta chunks concatenate to the complete response.')
                : EvaluationCheck::fail('stream_content_normalized', 'Streamed content did not match the complete response.'),
            $done instanceof StreamEvent && $done->type === StreamEvent::TYPE_DONE
                ? EvaluationCheck::pass('stream_done_event', 'Stream terminated with a normalized done event.')
                : EvaluationCheck::fail('stream_done_event', 'Stream did not terminate with a done event.'),
            $done instanceof StreamEvent && $done->usage !== null && $done->usage->total() > 0
                ? EvaluationCheck::pass('stream_usage', 'Done event includes token usage.')
                : EvaluationCheck::fail('stream_usage', 'Done event is missing token usage.'),
            $done instanceof StreamEvent && $done->finishReason === 'stop'
                ? EvaluationCheck::pass('stream_finish_reason', 'Done event includes the finish reason.')
                : EvaluationCheck::fail('stream_finish_reason', 'Done event is missing the finish reason.'),
            $error === null
                ? EvaluationCheck::pass('stream_no_error', 'Stream produced no error events.')
                : EvaluationCheck::fail('stream_no_error', 'Stream unexpectedly produced an error event.'),
        ];
    }
}
