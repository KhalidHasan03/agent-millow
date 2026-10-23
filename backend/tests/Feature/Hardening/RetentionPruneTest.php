<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Models\AuditEvent;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RetentionPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_command_is_a_dry_run_by_default(): void
    {
        $this->seedRetainedRecords();

        $this->artisan('maintenance:prune')->assertSuccessful();

        $this->assertSame(2, AuditEvent::query()->count());
        $this->assertSame(2, DB::table('failed_jobs')->count());
        $this->assertSame(2, PersonalAccessToken::query()->count());
    }

    public function test_retention_command_deletes_only_expired_records_when_forced(): void
    {
        $this->seedRetainedRecords();

        $this->artisan('maintenance:prune --force')->assertSuccessful();

        $this->assertDatabaseHas('audit_events', ['event' => 'recent.event']);
        $this->assertDatabaseMissing('audit_events', ['event' => 'old.event']);
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertSame('recent-job', DB::table('failed_jobs')->value('uuid'));
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_forced_retention_is_refused_in_local_environment(): void
    {
        $this->seedRetainedRecords();

        $this->app['env'] = 'local';

        $this->artisan('maintenance:prune --force')->assertFailed();

        $this->assertSame(2, AuditEvent::query()->count());
        $this->assertSame(2, DB::table('failed_jobs')->count());
        $this->assertSame(2, PersonalAccessToken::query()->count());
    }

    public function test_scheduled_retention_is_production_only(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'maintenance:prune'));

        $this->assertNotNull($event);
        $this->assertTrue($event->runsInEnvironment('production'));
        $this->assertFalse($event->runsInEnvironment('local'));
        $this->assertFalse($event->runsInEnvironment('testing'));
    }

    private function seedRetainedRecords(): void
    {
        DB::table('audit_events')->insert([
            ['event' => 'old.event', 'created_at' => now()->subDays(400)],
            ['event' => 'recent.event', 'created_at' => now()->subDays(10)],
        ]);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => 'old-job',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Old failure',
                'failed_at' => now()->subDays(40),
            ],
            [
                'uuid' => 'recent-job',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Recent failure',
                'failed_at' => now()->subDay(),
            ],
        ]);

        $user = User::factory()->create();

        $user->tokens()->create([
            'name' => 'expired',
            'token' => hash('sha256', Str::random(40)),
            'abilities' => ['*'],
            'expires_at' => now()->subDay(),
        ]);

        $user->tokens()->create([
            'name' => 'active',
            'token' => hash('sha256', Str::random(40)),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);
    }
}
