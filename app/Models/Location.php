<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'city', 'country', 'latitude', 'longitude'];

    /**
     * WGS84 decimal degrees everywhere, as the map integration expects.
     */
    public function shipmentsFromOrigin(): HasMany
    {
        return $this->hasMany(Shipment::class, 'origin_location_id');
    }

    public function shipmentsToDestination(): HasMany
    {
        return $this->hasMany(Shipment::class, 'destination_location_id');
    }
}
