<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Created = 'CREATED';
    case Booked = 'BOOKED';
    case PickedUp = 'PICKED_UP';
    case InTransit = 'IN_TRANSIT';
    case Delivered = 'DELIVERED';

    /**
     * The one allowed-transition map for the whole application.
     * Controllers, services, and tests all read from here so an
     * invalid status can never slip through a different door.
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Created => [self::Booked],
            self::Booked => [self::PickedUp],
            self::PickedUp => [self::InTransit],
            self::InTransit => [self::Delivered],
            self::Delivered => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Booked => 'Booked',
            self::PickedUp => 'Picked up',
            self::InTransit => 'In transit',
            self::Delivered => 'Delivered',
        };
    }

    /**
     * Badge color per the DESIGN.md status palette.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Created => 'neutral',
            self::Booked => 'amber',
            self::PickedUp => 'teal',
            self::InTransit => 'blue',
            self::Delivered => 'green',
        };
    }
}
