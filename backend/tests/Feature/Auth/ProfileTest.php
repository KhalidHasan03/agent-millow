<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_view_their_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_a_user_can_update_their_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_updating_email_resets_verification(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.email_verified_at', null);
    }

    public function test_a_user_cannot_take_another_users_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/me', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_a_user_can_change_their_password(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $this->actingAs($user)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'Str0ng-Passw0rd!',
                'password' => 'N3w-Passw0rd!x',
                'password_confirmation' => 'N3w-Passw0rd!x',
            ])
            ->assertNoContent();

        $this->assertTrue(
            Hash::check('N3w-Passw0rd!x', $user->refresh()->password)
        );
    }

    public function test_changing_password_requires_the_current_password(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $this->actingAs($user)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'Wrong-Passw0rd!',
                'password' => 'N3w-Passw0rd!x',
                'password_confirmation' => 'N3w-Passw0rd!x',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_a_user_can_delete_their_own_account(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $this->actingAs($user)
            ->deleteJson('/api/v1/me', ['password' => 'Str0ng-Passw0rd!'])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_deleting_an_account_requires_the_password(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $this->actingAs($user)
            ->deleteJson('/api/v1/me', ['password' => 'Wrong-Passw0rd!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
