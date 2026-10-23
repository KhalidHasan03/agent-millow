<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_conversation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/conversations', [
                'title' => 'First chat',
                'system_prompt' => 'Be concise.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'First chat')
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonPath('data.model', 'fake-model')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('conversations', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event' => 'conversation.created',
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_can_list_only_their_own_conversations(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Conversation::factory()->count(2)->for($alice)->create();
        Conversation::factory()->for($bob)->create();

        $response = $this->actingAs($alice)
            ->getJson('/api/v1/conversations')
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertCount(2, $ids);
        $this->assertSame(
            [],
            Conversation::query()->whereIn('id', $ids)->where('user_id', '!=', $alice->id)->pluck('id')->all(),
        );
    }

    public function test_a_user_can_show_update_and_delete_their_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/conversations/'.$conversation->id)
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id);

        $this->actingAs($user)
            ->patchJson('/api/v1/conversations/'.$conversation->id, ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');

        $this->actingAs($user)
            ->deleteJson('/api/v1/conversations/'.$conversation->id)
            ->assertNoContent();

        $this->assertSoftDeleted('conversations', ['id' => $conversation->id]);
    }

    public function test_creating_a_conversation_requires_authentication(): void
    {
        $this->postJson('/api/v1/conversations', ['title' => 'Nope'])->assertUnauthorized();
    }

    public function test_a_disabled_provider_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/conversations', ['provider' => 'does-not-exist'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('provider');
    }

    public function test_the_providers_endpoint_lists_enabled_providers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/providers')
            ->assertOk()
            ->assertJsonPath('meta.default', 'fake');

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertSame('fake', $data[0]['name']);
        $this->assertArrayHasKey('enabled', $data[0]);
        $this->assertArrayHasKey('ready', $data[0]);
        $this->assertArrayHasKey('supports_streaming', $data[0]);
    }
}
