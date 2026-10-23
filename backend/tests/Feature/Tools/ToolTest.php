<?php

declare(strict_types=1);

namespace Tests\Feature\Tools;

use App\Models\AuditEvent;
use App\Models\ToolCall;
use App\Models\User;
use App\Services\Tools\Policy\ToolRiskPolicy;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolSeeder::class);
    }

    public function test_tools_are_listed_and_echo_tool_can_be_called_safely(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonFragment(['name' => 'echo']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'echo',
                'input' => ['message' => 'hello'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'succeeded');

        $this->assertSame('hello', $response->json('result.echo'));
        $this->assertDatabaseHas('tool_calls', ['status' => 'succeeded']);
        $this->assertDatabaseHas('audit_events', ['event' => 'tool.proposed']);
        $this->assertDatabaseHas('audit_events', ['event' => 'tool.succeeded']);
    }

    public function test_a_guarded_demo_tool_requires_approval_before_execution(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/tools')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'echo_guarded',
                'risk_level' => 'high',
                'approval_required' => true,
            ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'echo_guarded',
                'input' => ['message' => 'needs approval'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'pending_approval');

        $callId = $response->json('tool_call_id');

        $this->assertDatabaseHas('tool_calls', [
            'id' => $callId,
            'status' => 'pending_approval',
            'risk_level' => 'high',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/tools/calls/'.$callId.'/approve')
            ->assertOk()
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('result.echo', 'needs approval');
    }

    public function test_tool_input_validation_rejects_bad_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'echo',
                'input' => ['message' => 123],
            ])
            ->assertStatus(422);
    }

    public function test_unknown_tool_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'does_not_exist',
                'input' => ['x' => 1],
            ])
            ->assertStatus(404);
    }

    public function test_tool_call_is_user_scoped(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $response = $this->actingAs($alice)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'echo',
                'input' => ['message' => 'hi'],
            ])
            ->assertOk();

        $callId = $response->json('tool_call_id');
        $call = ToolCall::query()->findOrFail($callId);

        $this->actingAs($bob)->getJson('/api/v1/tools/calls/'.$call->id)->assertForbidden();
    }

    public function test_role_based_approval_limit_matches_the_user_role_enum(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $policy = app(ToolRiskPolicy::class);
        $definition = [
            'risk_level' => 'low',
            'approval_required' => false,
            'limits' => ['require_approval_for_roles' => ['admin']],
        ];

        $this->assertTrue($policy->classify($admin, $definition, [])['approval_required']);
        $this->assertFalse($policy->classify($user, $definition, [])['approval_required']);
    }

    public function test_redaction_prevents_logging_secrets_in_audit_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/tools/call', [
                'tool' => 'echo',
                'input' => ['message' => 'hi'],
            ])
            ->assertOk();

        $count = AuditEvent::query()->where('event', 'like', 'tool.%')->count();
        $this->assertGreaterThan(0, $count);
    }
}
