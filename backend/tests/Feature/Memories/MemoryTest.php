<?php

declare(strict_types=1);

namespace Tests\Feature\Memories;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_memory(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/memories', [
                'title' => 'Preferred editor',
                'content' => 'I prefer vim.',
                'category' => 'preference',
                'key' => 'editor',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Preferred editor')
            ->assertJsonPath('data.category', 'preference')
            ->assertJsonPath('data.source', 'user');

        $this->assertDatabaseHas('memories', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'key' => 'editor',
        ]);

        $this->assertDatabaseHas('memory_events', [
            'user_id' => $user->id,
            'action' => 'created',
            'memory_id' => $response->json('data.id'),
        ]);
    }

    public function test_a_user_can_list_inspect_update_and_delete_their_memory(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->for($user)->create(['title' => 'Old']);

        $this->actingAs($user)
            ->getJson('/api/v1/memories')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Old']);

        $this->actingAs($user)
            ->getJson('/api/v1/memories/'.$memory->id)
            ->assertOk()
            ->assertJsonPath('data.id', $memory->id);

        $this->assertDatabaseHas('memory_events', ['action' => 'inspected', 'memory_id' => $memory->id]);

        $this->actingAs($user)
            ->patchJson('/api/v1/memories/'.$memory->id, ['title' => 'New', 'pinned' => true])
            ->assertOk()
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.pinned', true);

        $this->actingAs($user)
            ->deleteJson('/api/v1/memories/'.$memory->id)
            ->assertNoContent();

        $this->assertSoftDeleted('memories', ['id' => $memory->id]);
        $this->assertDatabaseHas('memory_events', ['action' => 'deleted', 'memory_id' => $memory->id]);
    }

    public function test_a_user_cannot_access_another_users_memory(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $memory = Memory::factory()->for($bob)->create();

        $this->actingAs($alice)->getJson('/api/v1/memories/'.$memory->id)->assertForbidden();
        $this->actingAs($alice)->patchJson('/api/v1/memories/'.$memory->id, ['title' => 'x'])->assertForbidden();
        $this->actingAs($alice)->deleteJson('/api/v1/memories/'.$memory->id)->assertForbidden();
    }

    public function test_memories_are_filtered_and_paginated(): void
    {
        $user = User::factory()->create();
        Memory::factory()->for($user)->create(['category' => 'preference', 'title' => 'Editor prefs']);
        Memory::factory()->for($user)->create(['category' => 'fact', 'title' => 'Location']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/memories?category=preference&per_page=1')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('preference', $response->json('data.0.category'));

        $this->actingAs($user)
            ->getJson('/api/v1/memories?q=Location')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Location']);
    }

    public function test_model_proposed_memories_can_be_stored_with_lower_confidence(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/memories', [
                'title' => 'Suggested',
                'content' => 'Maybe likes coffee.',
                'source' => 'model',
                'confidence' => 65,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'model')
            ->assertJsonPath('data.confidence', 65);
    }

    public function test_invalid_memory_data_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/memories', ['title' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');

        $this->actingAs($user)
            ->postJson('/api/v1/memories', ['title' => 'x', 'content' => 'y', 'confidence' => 150])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confidence');
    }

    public function test_memory_operations_are_audited_without_logging_full_content(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->for($user)->create(['title' => 'Secret', 'content' => 'do-not-leak']);

        $this->actingAs($user)->getJson('/api/v1/memories/'.$memory->id)->assertOk();

        $this->assertDatabaseMissing('memory_events', ['context' => ['content' => 'do-not-leak']]);
        $this->assertDatabaseHas('memory_events', ['action' => 'inspected', 'memory_id' => $memory->id]);
    }
}
