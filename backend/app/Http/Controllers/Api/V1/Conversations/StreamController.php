<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Conversations;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Providers\Llm\Contracts\StreamEvent;
use App\Providers\Llm\Exceptions\ProviderException;
use App\Providers\Llm\ProviderRegistry;
use App\Services\Audit\AuditLogger;
use App\Services\Conversations\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Server-sent-events streaming endpoint.
 *
 * Authorization is enforced (by route middleware and the policy) before any
 * bytes of the stream are written, so an unauthorized client never receives a
 * partial stream. The provider is called through its adapter, which normalizes
 * events; only normalized data is forwarded to the client.
 */
final class StreamController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly MessageService $messages,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(Request $request, Conversation $conversation): StreamedResponse
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $providerName = (string) $conversation->provider;

        $adapter = $this->providers->getReady($providerName);

        $chatRequest = $this->messages->buildRequest($conversation, stream: true);

        return response()->stream(function () use ($adapter, $chatRequest, $conversation, $user, $providerName): void {
            $buffer = '';
            $finishReason = null;
            $usage = null;
            $started = microtime(true);

            $this->emit('start', ['conversation_id' => $conversation->getKey()]);

            try {
                foreach ($adapter->stream($chatRequest, null) as $event) {
                    if ($event->type === StreamEvent::TYPE_DELTA) {
                        $buffer .= $event->text;
                        $this->emit('delta', ['text' => $event->text]);
                    } elseif ($event->type === StreamEvent::TYPE_DONE) {
                        $usage = $event->usage;
                        $finishReason = $event->finishReason;
                    } elseif ($event->type === StreamEvent::TYPE_ERROR) {
                        Log::warning('provider.stream_failed', [
                            'provider' => $providerName,
                            'model' => (string) $conversation->model,
                            'conversation_id' => $conversation->getKey(),
                            'user_id' => $user->getKey(),
                            'reason' => $event->text,
                        ]);

                        $this->emit('error', ['message' => 'The provider request failed.']);

                        return;
                    }
                }
            } catch (ProviderException $exception) {
                Log::warning('provider.stream_failed', [
                    'provider' => $providerName,
                    'model' => (string) $conversation->model,
                    'conversation_id' => $conversation->getKey(),
                    'user_id' => $user->getKey(),
                    'exception' => $exception::class,
                    'reason' => $exception->getMessage(),
                ]);

                $this->emit('error', ['message' => 'The provider request failed.']);

                return;
            } catch (Throwable $exception) {
                report($exception);
                $this->emit('error', ['message' => 'The response could not be completed.']);

                return;
            }

            $message = $this->persistAssistantMessage(
                $conversation,
                $user,
                $buffer,
                $finishReason,
                $usage?->promptTokens,
                $usage?->completionTokens,
                (int) round((microtime(true) - $started) * 1000),
            );

            $this->emit('done', [
                'message_id' => $message->getKey(),
                'finish_reason' => $finishReason,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(string $event, array $payload): void
    {
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($payload, JSON_THROW_ON_ERROR)."\n\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    private function persistAssistantMessage(
        Conversation $conversation,
        User $user,
        string $buffer,
        ?string $finishReason,
        ?int $promptTokens,
        ?int $completionTokens,
        int $latencyMs,
    ): Message {
        $message = Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'user_id' => null,
            'role' => MessageRole::Assistant,
            'content' => $buffer,
            'status' => MessageStatus::Complete,
            'provider' => $conversation->provider,
            'model' => $conversation->model,
            'finish_reason' => $finishReason,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'completed_at' => now(),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        ProviderUsage::query()->create([
            'user_id' => $user->getKey(),
            'conversation_id' => $conversation->getKey(),
            'message_id' => $message->getKey(),
            'provider' => (string) $conversation->provider,
            'model' => $conversation->model,
            'prompt_tokens' => $promptTokens ?? 0,
            'completion_tokens' => $completionTokens ?? 0,
            'total_tokens' => ($promptTokens ?? 0) + ($completionTokens ?? 0),
            'latency_ms' => $latencyMs,
            'status' => 'success',
        ]);

        $this->audit->record('message.generated', $user, $message, [
            'provider' => $conversation->provider,
            'streamed' => true,
        ]);

        return $message;
    }
}
