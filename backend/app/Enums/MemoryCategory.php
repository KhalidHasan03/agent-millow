<?php

declare(strict_types=1);

namespace App\Enums;

enum MemoryCategory: string
{
    case Fact = 'fact';
    case Preference = 'preference';
    case Instruction = 'instruction';
    case Context = 'context';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fact => 'Fact',
            self::Preference => 'Preference',
            self::Instruction => 'Instruction',
            self::Context => 'Context',
            self::Other => 'Other',
        };
    }
}
