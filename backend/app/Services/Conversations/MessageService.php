<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\DataTransferObjects\Conversations\SendMessageData;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProviderUsage;
use App\Models\User;
use App\Providers\Llm\Contracts\ChatMessage;
use App\Providers\Llm\Contracts\ChatRequest;
use App\Providers\Llm\Exceptions\ProviderException;
use App\Providers\Llm\ProviderRegistry;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MessageService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Message>
     */
    public function paginate(Conversation $conversation, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 30);

        return Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function appendUserMessage(Conversation $conversation, User $user, SendMessageData $data): Message
    {
        $message = DB::transaction(function () use ($conversation, $user, $data): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->getKey(),
                'user_id' => $user->getKey(),
                'role' => MessageRole::User,
                'content' => $data->content,
                'status' => MessageStatus::Complete,
                'client_message_id' => $data->clientMessageId,
                'completed_at' => now(),
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            if ($conversation->title === null) {
                $conversation->forceFill(['title' => $this->deriveTitle($data->content)])->save();
            }

            return $message;
        });

        $this->audit->record('message.sent', $user, $message);

        return $message;
    }

    /**
     * Generate an assistant reply for the conversation and persist it.
     */
    public function generateReply(Conversation $conversation, User $user): Message
    {
        $provider = $this->providers->get((string) $conversation->provider);
        $request = $this->buildRequest($conversation);

        $started = microtime(true);

        try {
            $result = $provider->complete($request, null);
        } catch (ProviderException $exception) {
            Log::warning('provider.reply_failed', [
                'provider' => (string) $conversation->provider,
                'model' => (string) $conversation->model,
                'conversation_id' => $conversation->getKey(),
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $latency = (int) round((microtime(true) - $started) * 1000);

        return DB::transaction(function () use ($conversation, $result, $latency, $user): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->getKey(),
                'user_id' => null,
                'role' => MessageRole::Assistant,
                'content' => $result->content,
                'status' => MessageStatus::Complete,
                'provider' => $result->provider,
                'model' => $result->model,
                'finish_reason' => $result->finishReason,
                'prompt_tokens' => $result->usage->promptTokens,
                'completion_tokens' => $result->usage->completionTokens,
                'completed_at' => now(),
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            ProviderUsage::query()->create([
                'user_id' => $user->getKey(),
                'conversation_id' => $conversation->getKey(),
                'message_id' => $message->getKey(),
                'provider' => $result->provider,
                'model' => $result->model,
                'prompt_tokens' => $result->usage->promptTokens,
                'completion_tokens' => $result->usage->completionTokens,
                'total_tokens' => $result->usage->total(),
                'latency_ms' => $latency,
                'status' => 'success',
            ]);

            $this->audit->record('message.generated', $user, $message, [
                'provider' => $result->provider,
                'model' => $result->model,
            ]);

            return $message;
        });
    }

    public function buildRequest(Conversation $conversation, bool $stream = false): ChatRequest
    {
        $limit = (int) config('agent_millow.conversations.max_context_messages', 50);

        $history = Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->whereIn('role', [MessageRole::User->value, MessageRole::Assistant->value])
            ->where('status', MessageStatus::Complete->value)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $messages = [];

        if ((string) $conversation->system_prompt !== '') {
            $messages[] = new ChatMessage('system', (string) $conversation->system_prompt);
        }

        foreach ($history as $message) {
            $messages[] = new ChatMessage($message->role->value, (string) $message->content);
        }

        return new ChatRequest(
            model: (string) $conversation->model,
            messages: $messages,
            stream: $stream,
        );
    }

    private function deriveTitle(string $content): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $content) ?? '');

        return mb_substr($title, 0, 60);
    }
}
