<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create([
            'email' => 'ada@example.com',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'Str0ng-Passw0rd!',
        ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('token', null);
    }

    public function test_login_records_last_login_metadata(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ng-Passw0rd!',
        ])->assertOk();

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_login_with_invalid_password_fails_generically(): void
    {
        User::factory()->withPassword('Str0ng-Passw0rd!')->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'Wrong-Passw0rd!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_with_unknown_email_fails_generically(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@example.com',
            'password' => 'Str0ng-Passw0rd!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_can_issue_an_api_token_on_request(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ng-Passw0rd!',
            'device_name' => 'cli',
            'issue_token' => true,
        ])->assertOk();

        $token = $response->json('token');

        $this->assertIsString($token);
        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'cli', 'tokenable_id' => $user->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->withPassword('Str0ng-Passw0rd!')->create(['email' => 'ada@example.com']);

        config()->set('agent_millow.rate_limits.login_per_minute', 3);
        RateLimiter::clear('ada@example.com|127.0.0.1');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'ada@example.com',
                'password' => 'Wrong-Passw0rd!',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'Wrong-Passw0rd!',
        ])->assertStatus(429);
    }

    public function test_a_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();
    }
}
