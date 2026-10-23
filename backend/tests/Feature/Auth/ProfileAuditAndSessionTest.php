<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Auth\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileAuditAndSessionTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'Str0ng-Passw0rd!';

    private const NEW_PASSWORD = 'N3w-Passw0rd!x';

    public function test_changing_password_audits_success_and_invalidates_other_sessions(): void
    {
        $user = User::factory()->withPassword(self::OLD_PASSWORD)->create();
        $other = User::factory()->create();
        $currentSessionId = str_repeat('a', 40);

        $this->actingAs($user)
            ->withCredentials()
            ->withHeader('Referer', 'http://localhost:5173')
            ->withCookie((string) config('session.cookie'), $currentSessionId);

        $this->createSession($user, $currentSessionId);
        $this->createSession($user, 'other-session');
        $this->createSession($other, 'other-user-session');

        $this->putJson('/api/v1/me/password', [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertNoContent();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);

        $event = AuditEvent::query()->where('event', 'auth.password_changed')->sole();

        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(User::class, $event->subject_type);
        $this->assertSame((string) $user->id, $event->subject_id);
        $this->assertNull($event->context);
    }

    public function test_failed_password_change_is_not_audited_and_keeps_sessions(): void
    {
        $user = User::factory()->withPassword(self::OLD_PASSWORD)->create();
        $this->createSession($user, 'existing-session');

        $this->actingAs($user)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'Wrong-Passw0rd!',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->refresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => 'existing-session']);
        $this->assertDatabaseMissing('audit_events', ['event' => 'auth.password_changed']);
    }

    public function test_password_change_requires_authentication_and_has_no_side_effects(): void
    {
        $user = User::factory()->withPassword(self::OLD_PASSWORD)->create();
        $this->createSession($user, 'guest-target-session');

        $this->putJson('/api/v1/me/password', [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertUnauthorized();

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->refresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => 'guest-target-session']);
        $this->assertDatabaseMissing('audit_events', ['event' => 'auth.password_changed']);
    }

    public function test_deleting_account_audits_success_and_preserves_other_accounts(): void
    {
        $user = User::factory()->withPassword(self::OLD_PASSWORD)->create();
        $other = User::factory()->create();
        $this->createSession($user, 'deleted-user-session');
        $this->createSession($other, 'other-user-session');
        $user->createToken('api');

        $this->actingAs($user)
            ->deleteJson('/api/v1/me', ['password' => self::OLD_PASSWORD])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);

        $event = AuditEvent::query()->where('event', 'auth.deleted')->sole();

        $this->assertNull($event->user_id);
        $this->assertSame(User::class, $event->subject_type);
        $this->assertSame((string) $user->id, $event->subject_id);
        $this->assertNull($event->context);
    }

    public function test_failed_account_deletion_is_not_audited_and_keeps_the_account(): void
    {
        $user = User::factory()->withPassword(self::OLD_PASSWORD)->create();
        $this->createSession($user, 'existing-session');

        $this->actingAs($user)
            ->deleteJson('/api/v1/me', ['password' => 'Wrong-Passw0rd!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'existing-session']);
        $this->assertDatabaseMissing('audit_events', ['event' => 'auth.deleted']);
    }

    public function test_account_deletion_requires_authentication_and_has_no_side_effects(): void
    {
        $user = User::factory()->withPassword(self::OLD_PASSWORD)->create();
        $this->createSession($user, 'guest-target-session');

        $this->deleteJson('/api/v1/me', ['password' => self::OLD_PASSWORD])
            ->assertUnauthorized();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'guest-target-session']);
        $this->assertDatabaseMissing('audit_events', ['event' => 'auth.deleted']);
    }

    public function test_session_service_preserves_a_named_session_within_the_users_scope(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->createSession($user, 'keep-session');
        $this->createSession($user, 'remove-session');
        $this->createSession($other, 'other-user-session');

        $deleted = app(SessionService::class)->logoutEverywhere($user, 'keep-session');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('sessions', ['id' => 'keep-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'remove-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
    }

    private function createSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
