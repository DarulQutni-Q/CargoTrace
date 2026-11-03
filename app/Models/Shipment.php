<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Services\EtaService;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shipment_number', 'customer_id', 'carrier_id', 'origin_location_id', 'destination_location_id',
        'cargo_type', 'weight_kg', 'volume_m3', 'expected_departure_at', 'expected_arrival_at',
        'estimated_arrival_at', 'current_status',
    ];

    protected function casts(): array
    {
        return [
            'current_status' => ShipmentStatus::class,
            'expected_departure_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'volume_m3' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }

    /**
     * Customers only ever see their own shipments; staff see everything.
     * Applied on every list and single-record lookup so an unauthorized
     * id resolves to a 404 rather than revealing the record exists.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user !== null && $user->isCustomer()) {
            return $query->where('customer_id', $user->id);
        }

        return $query;
    }

    /**
     * Planned transit duration, rendered as compact hours like "17H".
     */
    public function plannedDurationHours(): int
    {
        return (int) round($this->expected_departure_at->diffInHours($this->expected_arrival_at));
    }

    /**
     * Delay state straight from the deterministic ETA rule [S8].
     *
     * @return array{state: string, delay_hours: float, threshold_low: int, threshold_high: int}
     */
    public function delayAssessment(): array
    {
        return app(EtaService::class)->assess($this);
    }

    /**
     * Badge tone for the delay chip: green when on time, then the
     * severity ramp. Shipments without an estimate are simply "Pending".
     */
    public function delayTone(): string
    {
        if ($this->estimated_arrival_at === null) {
            return 'neutral';
        }

        return match ($this->delayAssessment()['state']) {
            'on_time' => 'green',
            'low' => 'amber',
            'medium' => 'orange',
            'high' => 'red',
        };
    }
}
