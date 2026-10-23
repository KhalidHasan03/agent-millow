<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('token', null)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role'], 'meta' => ['active_tokens']]);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'role' => 'user']);
    }

    public function test_registration_normalizes_email_case(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ADA@Example.COM',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'taken@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_a_strong_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Different-Passw0rd!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_registration_fails_validation_without_required_fields(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password'])
            ->assertJsonStructure(['message', 'errors', 'request_id']);
    }

    public function test_registration_is_rejected_when_disabled(): void
    {
        config()->set('agent_millow.registration.enabled', false);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_is_limited_to_the_private_beta_allow_list(): void
    {
        config()->set('agent_millow.private_beta.enabled', true);
        config()->set('agent_millow.private_beta.allowed_emails', ['invited@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'outsider@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Invited',
            'email' => 'invited@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
    }
}
