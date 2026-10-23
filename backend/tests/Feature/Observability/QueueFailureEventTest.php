<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class QueueFailureEventTest extends TestCase
{
    public function test_queue_failure_is_logged_with_job_context(): void
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('provider');
        $job->shouldReceive('resolveName')->andReturn('App\Jobs\GenerateAssistantReply');

        Log::spy();

        event(new JobFailed('database', $job, new RuntimeException('Bearer sk-queue-secret')));

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'queue.job_failed'
                    && $context['connection'] === 'database'
                    && $context['queue'] === 'provider'
                    && $context['job'] === 'App\Jobs\GenerateAssistantReply'
                    && $context['exception'] === RuntimeException::class
                    && $context['reason'] === 'Bearer sk-queue-secret';
            });
    }
}
