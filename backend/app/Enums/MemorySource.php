<?php

declare(strict_types=1);

namespace App\Enums;

enum MemorySource: string
{
    case User = 'user';
    case Model = 'model';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Model => 'Model',
        };
    }
}
