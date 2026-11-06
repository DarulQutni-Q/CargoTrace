<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentException extends Model
{
    use HasFactory;

    protected $table = 'exceptions';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_INVESTIGATING = 'INVESTIGATING';
    public const STATUS_RESOLVED = 'RESOLVED';

    /** Exception types mandated by the UAS brief (slide 8). */
    public const TYPE_CUSTOMS_HOLD = 'CUSTOMS_HOLD';
    public const TYPE_WEATHER_DELAY = 'WEATHER_DELAY';
    public const TYPE_PORT_CONGESTION = 'PORT_CONGESTION';
    public const TYPE_DAMAGED = 'DAMAGED';
    public const TYPE_MISROUTED = 'MISROUTED';
    public const TYPE_DOCUMENTATION_ISSUE = 'DOCUMENTATION_ISSUE';

    public const TYPES = [
        self::TYPE_CUSTOMS_HOLD,
        self::TYPE_WEATHER_DELAY,
        self::TYPE_PORT_CONGESTION,
        self::TYPE_DAMAGED,
        self::TYPE_MISROUTED,
        self::TYPE_DOCUMENTATION_ISSUE,
    ];

    /** Title Case labels, mirroring the AI Studio reference exceptionTypeLabel. */
    public const TYPE_LABELS = [
        self::TYPE_CUSTOMS_HOLD => 'Customs hold',
        self::TYPE_WEATHER_DELAY => 'Weather delay',
        self::TYPE_PORT_CONGESTION => 'Port congestion',
        self::TYPE_DAMAGED => 'Damaged',
        self::TYPE_MISROUTED => 'Misrouted',
        self::TYPE_DOCUMENTATION_ISSUE => 'Documentation issue',
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucwords(strtolower(str_replace('_', ' ', $type)));
    }

    protected $fillable = [
        'shipment_id',
        'type',
        'severity',
        'status',
        'detected_at',
        'resolved_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}
