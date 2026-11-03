<?php

namespace App\Enums;

enum ExceptionType: string
{
    case CustomsHold = 'CUSTOMS_HOLD';
    case WeatherDelay = 'WEATHER_DELAY';
    case PortCongestion = 'PORT_CONGESTION';
    case Damaged = 'DAMAGED';
    case Misrouted = 'MISROUTED';
    case DocumentationIssue = 'DOCUMENTATION_ISSUE';

    public function label(): string
    {
        return match ($this) {
            self::CustomsHold => 'Customs hold',
            self::WeatherDelay => 'Weather delay',
            self::PortCongestion => 'Port congestion',
            self::Damaged => 'Damaged',
            self::Misrouted => 'Misrouted',
            self::DocumentationIssue => 'Documentation issue',
        };
    }
}
