<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

final class QueueFailureLogger
{
    public function handle(JobFailed $event): void
    {
        Log::error('queue.job_failed', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job' => $event->job->resolveName(),
            'exception' => $event->exception::class,
            'reason' => $event->exception->getMessage(),
        ]);
    }
}
