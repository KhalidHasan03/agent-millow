<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProviderUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_a_message_creates_the_user_message_and_an_assistant_reply(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'content' => 'What is 2+2?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_message.role', 'user')
            ->assertJsonPath('data.user_message.content', 'What is 2+2?')
            ->assertJsonPath('data.assistant_message.role', 'assistant')
            ->assertJsonPath('data.assistant_message.content', '[fake:fake-model] What is 2+2?');

        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseHas('provider_usage', [
            'conversation_id' => $conversation->id,
            'provider' => 'fake',
            'status' => 'success',
        ]);

        $this->assertNotNull($response->json('data.assistant_message.id'));
    }

    public function test_generate_false_stores_only_the_user_message(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'content' => 'Just store this.',
                'generate' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.assistant_message', null);

        $this->assertDatabaseCount('messages', 1);
        $this->assertSame(0, ProviderUsage::query()->count());
    }

    public function test_the_first_user_message_derives_a_conversation_title(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create(['title' => null]);

        $this->actingAs($user)
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'content' => 'Plan my week',
                'generate' => false,
            ])
            ->assertCreated();

        $this->assertSame('Plan my week', $conversation->refresh()->title);
    }

    public function test_messages_can_be_listed_in_order(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        Message::factory()->for($conversation)->create(['content' => 'first', 'role' => 'user']);
        Message::factory()->for($conversation)->assistant()->create(['content' => 'second']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/conversations/'.$conversation->id.'/messages')
            ->assertOk();

        $this->assertSame(['first', 'second'], array_column($response->json('data'), 'content'));
    }

    public function test_message_content_is_validated(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_a_user_cannot_post_to_another_users_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = Conversation::factory()->for($bob)->create();

        $this->actingAs($alice)
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['content' => 'hi'])
            ->assertForbidden();
    }
}
