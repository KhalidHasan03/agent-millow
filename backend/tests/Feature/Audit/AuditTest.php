<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_requires_authentication(): void
    {
        $this->getJson('/api/v1/audit')->assertUnauthorized();
    }

    public function test_audit_lists_only_the_authenticated_users_events(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        AuditEvent::query()->create(['user_id' => $alice->id, 'event' => 'conversation.created']);
        AuditEvent::query()->create(['user_id' => $alice->id, 'event' => 'memory.created']);
        AuditEvent::query()->create(['user_id' => $bob->id, 'event' => 'tool.succeeded']);

        $response = $this->actingAs($alice)
            ->getJson('/api/v1/audit')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $events = $response->json('data');
        $this->assertIsArray($events);
        $this->assertSame('memory.created', $events[0]['event']);
        $this->assertSame('conversation.created', $events[1]['event']);
    }

    public function test_audit_can_be_filtered_by_event(): void
    {
        $user = User::factory()->create();
        AuditEvent::query()->create(['user_id' => $user->id, 'event' => 'conversation.created']);
        AuditEvent::query()->create(['user_id' => $user->id, 'event' => 'memory.created']);

        $this->actingAs($user)
            ->getJson('/api/v1/audit?event=memory.created')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'memory.created');
    }
}
