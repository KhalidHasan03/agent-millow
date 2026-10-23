<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class AuthFailureEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_account_login_failure_is_logged_without_the_email(): void
    {
        Log::spy();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'auth.login_failed'
                    && $context['reason'] === 'unknown_account'
                    && is_string($context['email_hash'])
                    && $context['user_id'] === null
                    && ! array_key_exists('email', $context)
                    && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'ghost@example.com');
            });
    }

    public function test_invalid_password_login_failure_is_logged_with_the_user_id(): void
    {
        $user = User::factory()->create(['email' => 'known@example.com']);

        Log::spy();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'known@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($user): bool {
                return $message === 'auth.login_failed'
                    && $context['reason'] === 'invalid_password'
                    && $context['user_id'] === $user->id
                    && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'known@example.com');
            });
    }
}
