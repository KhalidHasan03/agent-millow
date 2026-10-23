<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_endpoints_are_rate_limited_per_user(): void
    {
        config()->set('agent_millow.rate_limits.api_per_minute', 3);

        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($user)->getJson('/api/v1/me')->assertOk();
        }

        $this->actingAs($user)->getJson('/api/v1/me')->assertStatus(429);
    }

    public function test_one_users_rate_limit_does_not_affect_another(): void
    {
        config()->set('agent_millow.rate_limits.api_per_minute', 2);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->getJson('/api/v1/me')->assertOk();
        $this->actingAs($alice)->getJson('/api/v1/me')->assertOk();
        $this->actingAs($alice)->getJson('/api/v1/me')->assertStatus(429);

        $this->actingAs($bob)->getJson('/api/v1/me')->assertOk();
    }

    public function test_rate_limited_responses_are_json(): void
    {
        config()->set('agent_millow.rate_limits.api_per_minute', 1);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/me')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/me')->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    public function test_admin_role_enum_is_cast_on_the_model(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse(User::factory()->create()->isAdmin());
    }
}
