<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrossUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_endpoints_only_return_the_authenticated_user(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $response = $this->actingAs($alice)->getJson('/api/v1/me')->assertOk();

        $response->assertJsonPath('data.id', $alice->id)
            ->assertJsonPath('data.name', 'Alice');

        $this->assertNotSame($bob->id, $response->json('data.id'));
    }

    public function test_a_user_cannot_edit_another_users_record_via_policy(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->assertTrue($alice->can('update', $alice));
        $this->assertFalse($alice->can('update', $bob));
        $this->assertFalse($alice->can('delete', $bob));

        $this->assertTrue($alice->can('view', $alice));
        $this->assertFalse($alice->can('view', $bob));
    }

    public function test_only_admins_can_list_all_users(): void
    {
        $regular = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $this->assertFalse($regular->can('viewAny', User::class));
        $this->assertTrue($admin->can('viewAny', User::class));

        $this->assertTrue($admin->can('view', $other));
    }

    public function test_an_api_token_only_grants_access_to_its_owner(): void
    {
        $alice = User::factory()->withPassword('Str0ng-Passw0rd!')->create();
        $bob = User::factory()->create();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $alice->email,
            'password' => 'Str0ng-Passw0rd!',
            'issue_token' => true,
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $alice->id)
            ->assertJsonMissing(['id' => $bob->id]);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ng-Passw0rd!',
            'device_name' => 'first',
            'issue_token' => true,
        ])->json('token');

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ng-Passw0rd!',
            'device_name' => 'second',
            'issue_token' => true,
        ])->json('token');

        $this->assertSame(2, $user->tokens()->count());

        $this->withHeader('Authorization', 'Bearer '.$first)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $user->refresh();

        $this->assertSame(1, $user->tokens()->count(), 'Logout must revoke only the current token.');

        $this->withHeader('Authorization', 'Bearer '.$second)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
