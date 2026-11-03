<?php

namespace App\Enums;

enum ExceptionSeverity: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'amber',
            self::Medium => 'orange',
            self::High => 'red',
        };
    }
}
