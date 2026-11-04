<?php

namespace Database\Seeders;

use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data along a Southeast Asia → Europe corridor: real city/port
 * names, fictional carriers, believable schedules, and every status
 * represented so the timeline, delay rule, and dashboard show
 * non-trivial numbers [S4].
 */
class OpsSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::query()->where('role', 'CUSTOMER')->orderBy('id')->get();

        $carriers = collect([
            ['name' => 'Meridian Air Cargo', 'code' => 'MAC', 'service_type' => 'AIR', 'contact_email' => 'ops@meridianair.example'],
            ['name' => 'Nusantara Freight', 'code' => 'NFR', 'service_type' => 'SEA', 'contact_email' => 'desk@nusantarafreight.example'],
            ['name' => 'Aurora Container Line', 'code' => 'ACL', 'service_type' => 'SEA', 'contact_email' => 'bookings@auroracl.example'],
            ['name' => 'Java Express Haulage', 'code' => 'JVX', 'service_type' => 'ROAD', 'contact_email' => 'control@javaexpress.example'],
        ])->map(fn (array $row) => Carrier::create($row));

        $locations = collect([
            ['name' => 'Singapore Changi Air Cargo', 'code' => 'SIN', 'city' => 'Singapore', 'country' => 'Singapore', 'latitude' => 1.364400, 'longitude' => 103.991500],
            ['name' => 'Tanjung Priok Port', 'code' => 'JKT', 'city' => 'Jakarta', 'country' => 'Indonesia', 'latitude' => -6.105000, 'longitude' => 106.880000],
            ['name' => 'Juanda International', 'code' => 'SUB', 'city' => 'Surabaya', 'country' => 'Indonesia', 'latitude' => -7.379800, 'longitude' => 112.787600],
            ['name' => 'Suvarnabhumi Cargo', 'code' => 'BKK', 'city' => 'Bangkok', 'country' => 'Thailand', 'latitude' => 13.690000, 'longitude' => 100.750100],
            ['name' => 'Port of Rotterdam', 'code' => 'RTM', 'city' => 'Rotterdam', 'country' => 'Netherlands', 'latitude' => 51.949600, 'longitude' => 4.142500],
            ['name' => 'Hamburg Airport Cargo', 'code' => 'HAM', 'city' => 'Hamburg', 'country' => 'Germany', 'latitude' => 53.630400, 'longitude' => 9.988200],
        ])->map(fn (array $row) => Location::create($row));

        $locationId = fn (string $code) => $locations->firstWhere('code', $code)->id;
        $carrierId = fn (string $code) => $carriers->firstWhere('code', $code)->id;

        // status, origin, destination, carrier, departure offset, planned
        // transit days, customer index, estimated-arrival offset in hours
        // (null = no estimate yet, negative = running early).
        $plans = [
            [ShipmentStatus::Created, 'JKT', 'RTM', 'NFR', '+3 days', 18, 0, null],
            [ShipmentStatus::Created, 'SUB', 'HAM', 'MAC', '+5 days', 21, 1, null],
            [ShipmentStatus::Created, 'BKK', 'RTM', 'ACL', '+7 days', 26, 2, null],
            [ShipmentStatus::Booked, 'SIN', 'RTM', 'MAC', '+1 day', 16, 0, null],
            [ShipmentStatus::Booked, 'JKT', 'HAM', 'ACL', '+2 days', 24, 1, null],
            [ShipmentStatus::PickedUp, 'SIN', 'HAM', 'MAC', '-1 day', 17, 2, null],
            [ShipmentStatus::PickedUp, 'SUB', 'RTM', 'JVX', '-2 days', 28, 0, null],
            [ShipmentStatus::InTransit, 'SIN', 'RTM', 'MAC', '-2 days', 17, 0, null],
            [ShipmentStatus::InTransit, 'BKK', 'HAM', 'MAC', '-3 days', 19, 1, 8],
            [ShipmentStatus::InTransit, 'JKT', 'RTM', 'ACL', '-6 days', 22, 2, 30],
            [ShipmentStatus::InTransit, 'SIN', 'RTM', 'NFR', '-5 days', 20, 0, -4],
            [ShipmentStatus::Delivered, 'SUB', 'RTM', 'NFR', '-20 days', 21, 1, -2],
            [ShipmentStatus::Delivered, 'BKK', 'HAM', 'ACL', '-25 days', 26, 2, 10],
            [ShipmentStatus::Delivered, 'JKT', 'RTM', 'MAC', '-15 days', 18, 0, null],
        ];

        $cargo = ['Electronics', 'Garments', 'Machinery parts', 'Perishables', 'Automotive components', 'Consumer goods'];

        foreach ($plans as $index => [$status, $origin, $destination, $carrier, $offset, $transitDays, $customerIndex, $estimateHours]) {
            $departure = now()->modify($offset)->setTime(8 + $index % 6, 30);
            $arrival = (clone $departure)->modify("+{$transitDays} days");

            $shipment = Shipment::create([
                'shipment_number' => 'CT'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).$carrier,
                'customer_id' => $customers[$customerIndex % $customers->count()]->id,
                'carrier_id' => $carrierId($carrier),
                'origin_location_id' => $locationId($origin),
                'destination_location_id' => $locationId($destination),
                'cargo_type' => $cargo[$index % count($cargo)],
                'weight_kg' => 800 + $index * 617.5,
                'volume_m3' => 12 + $index * 3.25,
                'expected_departure_at' => $departure,
                'expected_arrival_at' => $arrival,
                'estimated_arrival_at' => $estimateHours === null
                    ? null
                    : (clone $arrival)->modify(($estimateHours >= 0 ? '+' : '').$estimateHours.' hours'),
                'current_status' => $status->value,
            ]);

            ShipmentItem::create([
                'shipment_id' => $shipment->id,
                'description' => 'Palletized '.$cargo[$index % count($cargo)],
                'quantity' => 12 + $index * 7,
                'weight_kg' => 400 + $index * 221.25,
                'volume_m3' => 6 + $index * 1.4,
            ]);

            // A believable journey: each status beyond CREATED has the
            // events that would have produced it, in chronological order.
            $events = [];

            if (in_array($status, [ShipmentStatus::Booked, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::Delivered], true)) {
                $events[] = [ShipmentStatus::Booked->value, (clone $departure)->modify('-2 days'), $locations->firstWhere('code', $origin)->name, null, null, 'Booking confirmed'];
            }
            if (in_array($status, [ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::Delivered], true)) {
                $originLocation = $locations->firstWhere('code', $origin);
                $events[] = [ShipmentStatus::PickedUp->value, (clone $departure)->modify('-4 hours'), $originLocation->name, (string) $originLocation->latitude, (string) $originLocation->longitude, 'Cargo picked up and sealed'];
            }
            if (in_array($status, [ShipmentStatus::InTransit, ShipmentStatus::Delivered], true)) {
                $events[] = [ShipmentStatus::InTransit->value, (clone $departure)->modify('+9 hours'), "Departed {$locations->firstWhere('code', $origin)->city}", null, null, null];
                $events[] = ['INFO', (clone $departure)->modify("+{$transitDays} hours"), 'Mid-route position report', null, null, 'Carrier telemetry check-in'];
            }
            if ($status === ShipmentStatus::Delivered) {
                $destinationLocation = $locations->firstWhere('code', $destination);
                $events[] = [ShipmentStatus::Delivered->value, (clone $arrival)->modify('-3 hours'), $destinationLocation->name, (string) $destinationLocation->latitude, (string) $destinationLocation->longitude, 'Proof of delivery signed'];
            }

            foreach ($events as [$type, $at, $location, $lat, $lng, $description]) {
                $shipment->trackingEvents()->create([
                    'event_type' => $type,
                    'occurred_at' => $at,
                    'location_name' => $location,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'description' => $description,
                    'created_by' => $customers->first()->id,
                ]);
            }
        }
    }
}
