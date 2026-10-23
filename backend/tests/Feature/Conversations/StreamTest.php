<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProviderUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_streaming_returns_sse_events_and_persists_the_assistant_reply(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        Message::factory()->for($conversation)->create(['content' => 'Tell me a joke', 'role' => 'user']);

        $response = $this->actingAs($user)
            ->get('/api/v1/conversations/'.$conversation->id.'/stream');

        $response->assertOk();

        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/event-stream', $contentType);

        $content = $response->streamedContent();

        $this->assertStringContainsString('event: start', $content);
        $this->assertStringContainsString('event: delta', $content);
        $this->assertStringContainsString('event: done', $content);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => '[fake:fake-model] Tell me a joke',
        ]);

        $this->assertSame(1, ProviderUsage::query()->where('status', 'success')->count());
    }

    public function test_streaming_requires_authentication(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->getJson('/api/v1/conversations/'.$conversation->id.'/stream')->assertUnauthorized();
    }

    public function test_a_user_cannot_stream_another_users_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = Conversation::factory()->for($bob)->create();

        $this->actingAs($alice)
            ->getJson('/api/v1/conversations/'.$conversation->id.'/stream')
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }
}
