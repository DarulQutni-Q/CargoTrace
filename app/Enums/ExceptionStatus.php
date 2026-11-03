<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case Open = 'OPEN';
    case Investigating = 'INVESTIGATING';
    case Resolved = 'RESOLVED';

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'red',
            self::Investigating => 'amber',
            self::Resolved => 'green',
        };
    }

    /**
     * Lifecycle flows forward only: OPEN → INVESTIGATING → RESOLVED.
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Open => [self::Investigating, self::Resolved],
            self::Investigating => [self::Resolved],
            self::Resolved => [],
        };
    }
}
