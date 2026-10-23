<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageStatus: string
{
    case Pending = 'pending';
    case Streaming = 'streaming';
    case Complete = 'complete';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Complete || $this === self::Failed;
    }
}
