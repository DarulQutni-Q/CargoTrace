<?php

namespace App\Models;

use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use Database\Factories\ShipmentExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentException extends Model
{
    /** @use HasFactory<ShipmentExceptionFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id', 'type', 'severity', 'status', 'description',
        'detected_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExceptionType::class,
            'severity' => ExceptionSeverity::class,
            'status' => ExceptionStatus::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    public function isOpen(): bool
    {
        return $this->status !== ExceptionStatus::Resolved;
    }
}
