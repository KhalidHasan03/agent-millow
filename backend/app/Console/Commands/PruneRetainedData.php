<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\PersonalAccessToken;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PruneRetainedData extends Command
{
    protected $signature = 'maintenance:prune
        {--force : Permanently delete expired records; without this flag the command is a dry run}';

    protected $description = 'Prune audit events, failed jobs, and expired tokens according to retention policy.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($force && ! app()->environment(['production', 'testing'])) {
            $this->error('Refusing to delete retained data outside production or testing environments.');

            return self::FAILURE;
        }

        $auditDays = max(1, (int) config('agent_millow.retention.audit_days', 365));
        $failedJobsDays = max(1, (int) config('agent_millow.retention.failed_jobs_days', 30));
        $pruneTokens = (bool) config('agent_millow.retention.prune_expired_tokens', true);

        $auditCutoff = Carbon::now()->subDays($auditDays);
        $failedJobsCutoff = Carbon::now()->subDays($failedJobsDays);

        $auditQuery = AuditEvent::query()->where('created_at', '<', $auditCutoff);
        $auditCount = $auditQuery->count();

        $failedJobsCount = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '<', $failedJobsCutoff)->count()
            : 0;

        $tokenQuery = PersonalAccessToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now());
        $tokenCount = $pruneTokens ? $tokenQuery->count() : 0;

        $this->info($force ? 'Retention run: DELETE' : 'Retention run: DRY RUN');
        $this->line(sprintf('Audit events older than %d days: %d', $auditDays, $auditCount));
        $this->line(sprintf('Failed jobs older than %d days: %d', $failedJobsDays, $failedJobsCount));
        $this->line(sprintf('Expired access tokens: %d', $tokenCount));
        $this->line('Conversation messages: retained indefinitely');

        if (! $force) {
            $this->comment('No records were deleted. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        if ($auditCount > 0) {
            AuditEvent::query()
                ->where('created_at', '<', $auditCutoff)
                ->chunkById(500, function (Collection $events): void {
                    foreach ($events as $event) {
                        $event->deleteForRetention();
                    }
                });
        }

        if ($failedJobsCount > 0 && Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->where('failed_at', '<', $failedJobsCutoff)->delete();
        }

        if ($tokenCount > 0 && $pruneTokens) {
            PersonalAccessToken::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', Carbon::now())
                ->delete();
        }

        $this->warn('Deletion is irreversible.');

        return self::SUCCESS;
    }
}
