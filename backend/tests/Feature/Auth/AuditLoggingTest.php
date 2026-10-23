<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_audited(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_events', ['event' => 'auth.registered']);
    }

    public function test_successful_login_is_audited(): void
    {
        $user = User::factory()->withPassword('Str0ng-Passw0rd!')->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Str0ng-Passw0rd!',
        ])->assertOk();

        $this->assertDatabaseHas('audit_events', ['event' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_failed_login_is_audited_without_storing_the_password(): void
    {
        User::factory()->withPassword('Str0ng-Passw0rd!')->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'Wrong-Passw0rd!',
        ])->assertStatus(422);

        $event = AuditEvent::query()->where('event', 'auth.failed')->firstOrFail();

        $this->assertSame('ada@example.com', $event->context['email']);
        $this->assertArrayNotHasKey('password', $event->context ?? []);
    }

    public function test_audit_events_carry_the_request_id(): void
    {
        $this->withHeader('X-Request-Id', 'test-request-id-1234')
            ->postJson('/api/v1/auth/register', [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'password' => 'Str0ng-Passw0rd!',
                'password_confirmation' => 'Str0ng-Passw0rd!',
            ])->assertCreated();

        $this->assertDatabaseHas('audit_events', [
            'event' => 'auth.registered',
            'request_id' => 'test-request-id-1234',
        ]);
    }

    public function test_sensitive_context_keys_are_redacted(): void
    {
        $logger = app(AuditLogger::class);

        $event = $logger->record('test.redaction', context: [
            'password' => 'super-secret',
            'token' => 'abc',
            'safe' => 'visible',
        ]);

        $this->assertSame('[redacted]', $event->context['password']);
        $this->assertSame('[redacted]', $event->context['token']);
        $this->assertSame('visible', $event->context['safe']);
    }
}
