<?php

declare(strict_types=1);

namespace App\Enums;

enum ToolCallStatus: string
{
    case Pending = 'pending';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
}
