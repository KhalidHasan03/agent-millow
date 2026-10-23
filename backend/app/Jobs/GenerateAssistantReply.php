<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Conversations\MessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generate an assistant reply in the background.
 *
 * Idempotency: the job refuses to run if the conversation already contains an
 * assistant reply for the triggering user message, so retries and duplicate
 * dispatches do not create duplicate replies.
 */
final class GenerateAssistantReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $userId,
        public readonly int $userMessageId,
    ) {
        $this->onQueue('provider');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(MessageService $messages): void
    {
        $conversation = Conversation::query()->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        if ($this->alreadyAnswered($conversation)) {
            return;
        }

        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $messages->generateReply($conversation, $user);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('queue.job_failed_permanently', [
            'job' => self::class,
            'queue' => $this->queue,
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'request_id' => $this->requestId(),
            'attempts' => $this->attempts(),
            'exception' => $exception::class,
            'reason' => $exception->getMessage(),
        ]);
    }

    private function alreadyAnswered(Conversation $conversation): bool
    {
        return Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('role', 'assistant')
            ->where('id', '>', $this->userMessageId)
            ->exists();
    }

    private function requestId(): ?string
    {
        $id = request()?->attributes->get('request_id');

        return is_string($id) ? $id : null;
    }
}
