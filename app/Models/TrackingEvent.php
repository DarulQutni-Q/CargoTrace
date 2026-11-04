<?php

namespace App\Models;

use Database\Factories\TrackingEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical, append-only rows. Nothing ever updates or deletes them —
 * the timeline is the legal record of the journey.
 */
class TrackingEvent extends Model
{
    /** @use HasFactory<TrackingEventFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'event_type', 'occurred_at', 'location_name',
        'latitude', 'longitude', 'description', 'created_by', 'exception_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exception(): BelongsTo
    {
        return $this->belongsTo(ShipmentException::class);
    }

    /**
     * Status-mapped events drive the shipment lifecycle; anything else
     * is an informational observation on the timeline.
     */
    public function isStatusEvent(): bool
    {
        return ShipmentStatus::tryFrom($this->event_type) !== null;
    }

    public function isExceptionEvent(): bool
    {
        return $this->exception_id !== null;
    }
}
