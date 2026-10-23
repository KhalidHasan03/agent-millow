<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Models\User;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RateLimitHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_endpoints_use_the_dedicated_tool_limiter(): void
    {
        $this->seed(ToolSeeder::class);
        config()->set('agent_millow.rate_limits.tool_per_minute', 2);
        config()->set('agent_millow.tools.max_calls_per_hour', 0);

        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($user)
                ->postJson('/api/v1/tools/call', ['tool' => 'echo', 'input' => ['message' => 'hi']])
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson('/api/v1/tools/call', ['tool' => 'echo', 'input' => ['message' => 'hi']])
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_sensitive_operations_use_a_configurable_limiter(): void
    {
        config()->set('agent_millow.rate_limits.sensitive_per_minute', 1);

        $user = User::factory()->create();

        $payload = [
            'current_password' => 'wrong-password',
            'password' => 'New-password-123',
            'password_confirmation' => 'New-password-123',
        ];

        $this->actingAs($user)
            ->putJson('/api/v1/me/password', $payload)
            ->assertStatus(422);

        $this->actingAs($user)
            ->putJson('/api/v1/me/password', $payload)
            ->assertStatus(429);
    }
}
