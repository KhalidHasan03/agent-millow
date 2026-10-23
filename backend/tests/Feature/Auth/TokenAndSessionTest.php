<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TokenAndSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stateful_frontend_request_receives_a_session_and_can_access_me(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'Origin' => 'http://localhost:5173',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ng-Passw0rd!',
        ])->assertOk();

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'Origin' => 'http://localhost:5173',
        ])->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_a_token_authenticated_guest_route_is_protected_without_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_api_requests_without_json_accept_header_still_return_json_errors(): void
    {
        $this->get('/api/v1/me', ['Accept' => 'text/html'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('expired', ['*'], now()->subMinute());
        $plain = $token->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_deleting_a_user_cascades_token_removal(): void
    {
        $user = User::factory()->create();
        $user->createToken('one', ['*'], now()->addHour());
        $user->createToken('two', ['*'], now()->addHour());

        $this->assertSame(2, PersonalAccessToken::query()->count());

        $user->delete();

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_sanctum_acting_as_uses_the_configured_token_model(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }
}
