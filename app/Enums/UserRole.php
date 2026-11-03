<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Operator = 'OPERATOR';
    case Customer = 'CUSTOMER';

    /**
     * Human-readable label used across the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Operator => 'Logistics Operator',
            self::Customer => 'Customer',
        };
    }
}
