<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Models\Tool;
use App\Models\ToolVersion;
use App\Models\User;
use App\Services\Tools\Registry\ToolRegistry;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ToolQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_hourly_tool_call_quota_is_enforced_per_user(): void
    {
        $this->seed(ToolSeeder::class);
        config()->set('agent_millow.rate_limits.tool_per_minute', 100);
        config()->set('agent_millow.tools.max_calls_per_hour', 1);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)
            ->postJson('/api/v1/tools/call', ['tool' => 'echo', 'input' => ['message' => 'one']])
            ->assertOk();

        $this->actingAs($alice)
            ->postJson('/api/v1/tools/call', ['tool' => 'echo', 'input' => ['message' => 'two']])
            ->assertStatus(429);

        $this->actingAs($bob)
            ->postJson('/api/v1/tools/call', ['tool' => 'echo', 'input' => ['message' => 'bob']])
            ->assertOk();
    }

    public function test_pending_approval_quota_blocks_new_high_risk_calls(): void
    {
        config()->set('agent_millow.rate_limits.tool_per_minute', 100);
        config()->set('agent_millow.tools.max_calls_per_hour', 0);
        config()->set('agent_millow.tools.max_pending_approvals', 1);

        $tool = Tool::factory()->create(['name' => 'danger', 'enabled' => true]);
        ToolVersion::factory()->highRisk()->create([
            'tool_id' => $tool->id,
            'version' => 1,
            'input_schema' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]],
        ]);

        app(ToolRegistry::class)->clearCache();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/tools/call', ['tool' => 'danger', 'input' => ['x' => 'one']])
            ->assertOk()
            ->assertJsonPath('status', 'pending_approval');

        $this->actingAs($user)
            ->postJson('/api/v1/tools/call', ['tool' => 'danger', 'input' => ['x' => 'two']])
            ->assertStatus(429);
    }
}
