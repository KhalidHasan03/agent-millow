<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Jobs\GenerateAssistantReply;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Conversations\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GenerateAssistantReplyJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_job_generates_an_assistant_reply(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        $userMessage = Message::factory()->for($conversation)->create(['content' => 'Hi there', 'role' => 'user']);

        (new GenerateAssistantReply($conversation->id, $user->id, $userMessage->id))
            ->handle(app(MessageService::class));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => '[fake:fake-model] Hi there',
        ]);
    }

    public function test_the_job_is_idempotent_and_does_not_create_duplicate_replies(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();
        $userMessage = Message::factory()->for($conversation)->create(['content' => 'Once', 'role' => 'user']);

        $job = new GenerateAssistantReply($conversation->id, $user->id, $userMessage->id);

        $job->handle(app(MessageService::class));
        $job->handle(app(MessageService::class));

        $this->assertSame(
            1,
            Message::query()->where('conversation_id', $conversation->id)->where('role', 'assistant')->count(),
        );
    }

    public function test_the_job_uses_a_provider_queue_with_retry_boundaries(): void
    {
        $job = new GenerateAssistantReply(1, 2, 3);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([5, 15, 30], $job->backoff());
        $this->assertSame('provider', $job->queue);
    }
}
