<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\ShipmentItem;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the CargoTrace database with users, carriers, global & Indonesian 38-province locations,
     * shipments, multi-leg checkpoints, exceptions, notifications, and audit logs.
     *
     * The 14 demo shipments mirror the original app's seed plans (BASE_TIME 2026-11-04 12:00 UTC):
     * ids run 1-14 in shipment-number order, so "newest first" (id desc) reproduces the
     * original feed order. The first in-transit row is CT0011NFR (SIN -> RTM via Suez, 72%).
     */
    public function run(): void
    {
        // 1. Seed Users (Admin, Logistics Operator, Customer)
        $admin = User::updateOrCreate(
            ['email' => 'admin@cargotrace.io'],
            [
                'name' => 'Aria Wibowo',
                'password' => Hash::make('password123'),
                'role' => 'ADMIN',
            ]
        );

        $operator = User::updateOrCreate(
            ['email' => 'operator@cargotrace.io'],
            [
                'name' => 'Bimo Santoso',
                'password' => Hash::make('password123'),
                'role' => 'LOGISTICS_OPERATOR',
            ]
        );

        $customer = User::updateOrCreate(
            ['email' => 'customer@cargotrace.io'],
            [
                'name' => 'Citra Hapsari',
                'password' => Hash::make('password123'),
                'role' => 'CUSTOMER',
            ]
        );

        // Second customer so customer-scoped isolation is demonstrable in the demo.
        $customer2 = User::updateOrCreate(
            ['email' => 'dewi@cargotrace.io'],
            [
                'name' => 'Dewi Lestari',
                'password' => Hash::make('password123'),
                'role' => 'CUSTOMER',
            ]
        );

        // 2. Seed Default Scenario Rule
        DB::table('scenario_configs')->updateOrInsert(
            ['id' => 1],
            [
                'group_code' => 'DEFAULT',
                'route_corridor' => 'Southeast Asia → Europe',
                'low_threshold_hours' => 6,
                'high_threshold_hours' => 24,
                'primary_exception' => 'CUSTOMS_HOLD',
                'kpi_focus' => 'On-Time Delivery & Exception Rate',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. Seed Carriers
        $carriersData = [
            ['name' => 'Nusantara Freight', 'code' => 'NFR', 'service_type' => 'SEA', 'contact_email' => 'ops@nusantarafreight.id', 'is_active' => true],
            ['name' => 'Aurora Container Line', 'code' => 'ACL', 'service_type' => 'SEA', 'contact_email' => 'dispatch@auroracontainers.com', 'is_active' => true],
            ['name' => 'Meridian Air Cargo', 'code' => 'MAC', 'service_type' => 'AIR', 'contact_email' => 'desk@meridianair.aero', 'is_active' => true],
            ['name' => 'Java Express Haulage', 'code' => 'JVX', 'service_type' => 'ROAD', 'contact_email' => 'fleet@javaexpress.co.id', 'is_active' => true],
        ];

        $carriers = [];
        foreach ($carriersData as $c) {
            $carriers[$c['code']] = Carrier::updateOrCreate(['code' => $c['code']], $c);
        }

        // 4. Seed Core & Regional Indonesian Locations (including Aceh: Krueng Geukuh, Bireuen, Lhokseumawe, Sabang)
        $locationsData = [
            ['name' => 'Singapore Changi Air Cargo', 'code' => 'SIN', 'city' => 'Singapore', 'province' => null, 'country' => 'Singapore', 'latitude' => 1.3644, 'longitude' => 103.9915],
            ['name' => 'Tanjung Priok Port', 'code' => 'JKT', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'country' => 'Indonesia', 'latitude' => -6.1050, 'longitude' => 106.8800],
            ['name' => 'Juanda International', 'code' => 'SUB', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'country' => 'Indonesia', 'latitude' => -7.3798, 'longitude' => 112.7876],
            ['name' => 'Suvarnabhumi Cargo', 'code' => 'BKK', 'city' => 'Bangkok', 'province' => null, 'country' => 'Thailand', 'latitude' => 13.6900, 'longitude' => 100.7501],
            ['name' => 'Port of Rotterdam', 'code' => 'RTM', 'city' => 'Rotterdam', 'province' => null, 'country' => 'Netherlands', 'latitude' => 51.9496, 'longitude' => 4.1425],
            ['name' => 'Hamburg Airport Cargo', 'code' => 'HAM', 'city' => 'Hamburg', 'province' => null, 'country' => 'Germany', 'latitude' => 53.6304, 'longitude' => 9.9882],
            ['name' => 'Pelabuhan Krueng Geukuh (Pelindo)', 'code' => 'KRG', 'city' => 'Krueng Geukuh, Kab. Aceh Utara', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.2417, 'longitude' => 97.0264],
            ['name' => 'Pelabuhan Lhokseumawe & KEK Arun', 'code' => 'LSW', 'city' => 'Kota Lhokseumawe', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.1801, 'longitude' => 97.1507],
            ['name' => 'Bireuen Logistics & Cargo Terminal', 'code' => 'BIR', 'city' => 'Kab. Bireuen', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.2030, 'longitude' => 96.7009],
            ['name' => 'Pelabuhan Malahayati Krueng Raya', 'code' => 'MLH', 'city' => 'Banda Aceh / Aceh Besar', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.5967, 'longitude' => 95.5122],
            ['name' => 'Pelabuhan Bebas Sabang (CT-3 BPKS)', 'code' => 'SBG', 'city' => 'Kota Sabang (Pulau Weh)', 'province' => 'Aceh', 'country' => 'Indonesia', 'latitude' => 5.8897, 'longitude' => 95.3164],
            ['name' => 'Pelabuhan Belawan International Container Terminal', 'code' => 'BLW', 'city' => 'Kota Medan / Belawan', 'province' => 'Sumatera Utara', 'country' => 'Indonesia', 'latitude' => 3.7844, 'longitude' => 98.6836],
        ];

        $locs = [];
        foreach ($locationsData as $l) {
            $locs[$l['code']] = Location::updateOrCreate(['code' => $l['code']], $l);
        }

        // 5. Seed Representative Multi-Leg Shipments (14 Shipments across all 5 Lifecycle States)
        // Waypoints: Colombo (idx%4==0), Dubai Jebel Ali (1), Suez Canal (2), Piraeus (3).
        // Event clock per shipment: BOOKED = departure-2d 09:00, PICKED_UP = departure-4h,
        // IN_TRANSIT "Departed {city}" = departure+9h, CHECKPOINT = departure+transitDays hours,
        // DELIVERED = arrival-3h.
        $seedShipments = [
            [
                'shipment_number' => 'CT0001NFR',
                'carrier_code' => 'NFR',
                'origin_code' => 'JKT',
                'dest_code' => 'RTM',
                'status' => 'CREATED',
                'cargo_type' => 'Electronics',
                'weight_kg' => 800,
                'volume_m3' => 12,
                'expected_departure_at' => '2026-11-07 08:30:00',
                'expected_arrival_at' => '2026-11-25 08:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-02 04:00:00',
                'checkpoints' => [],
            ],
            [
                'shipment_number' => 'CT0002MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'SUB',
                'dest_code' => 'HAM',
                'status' => 'CREATED',
                'cargo_type' => 'Garments',
                'weight_kg' => 1417.5,
                'volume_m3' => 15.25,
                'expected_departure_at' => '2026-11-09 09:30:00',
                'expected_arrival_at' => '2026-11-30 09:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-02 08:00:00',
                'checkpoints' => [],
            ],
            [
                'shipment_number' => 'CT0003ACL',
                'carrier_code' => 'ACL',
                'origin_code' => 'BKK',
                'dest_code' => 'RTM',
                'status' => 'CREATED',
                'cargo_type' => 'Machinery parts',
                'weight_kg' => 2035,
                'volume_m3' => 18.5,
                'expected_departure_at' => '2026-11-11 10:30:00',
                'expected_arrival_at' => '2026-12-07 10:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-02 12:00:00',
                'checkpoints' => [],
            ],
            [
                'shipment_number' => 'CT0004MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'SIN',
                'dest_code' => 'RTM',
                'status' => 'BOOKED',
                'cargo_type' => 'Perishables',
                'weight_kg' => 2652.5,
                'volume_m3' => 21.75,
                'expected_departure_at' => '2026-11-05 11:30:00',
                'expected_arrival_at' => '2026-11-21 11:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-02 16:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-11-03 09:00:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0005ACL',
                'carrier_code' => 'ACL',
                'origin_code' => 'JKT',
                'dest_code' => 'HAM',
                'status' => 'BOOKED',
                'cargo_type' => 'Automotive components',
                'weight_kg' => 3270,
                'volume_m3' => 25,
                'expected_departure_at' => '2026-11-06 12:30:00',
                'expected_arrival_at' => '2026-11-30 12:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-02 20:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-11-04 09:00:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0006MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'SIN',
                'dest_code' => 'HAM',
                'status' => 'PICKED_UP',
                'cargo_type' => 'Consumer goods',
                'weight_kg' => 3887.5,
                'volume_m3' => 28.25,
                'expected_departure_at' => '2026-11-03 13:30:00',
                'expected_arrival_at' => '2026-11-20 13:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-03 00:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-11-01 09:00:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-11-03 09:30:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => 1.3644,
                        'longitude' => 103.9915,
                        'description' => 'Cargo picked up and sealed',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0007JVX',
                'carrier_code' => 'JVX',
                'origin_code' => 'SUB',
                'dest_code' => 'RTM',
                'status' => 'PICKED_UP',
                'cargo_type' => 'Electronics',
                'weight_kg' => 4505,
                'volume_m3' => 31.5,
                'expected_departure_at' => '2026-11-02 08:30:00',
                'expected_arrival_at' => '2026-11-30 08:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-03 04:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-31 09:00:00',
                        'location_name' => 'Juanda International',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-11-02 04:30:00',
                        'location_name' => 'Juanda International',
                        'latitude' => -7.3798,
                        'longitude' => 112.7876,
                        'description' => 'Cargo picked up and sealed',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0008MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'SIN',
                'dest_code' => 'RTM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Garments',
                'weight_kg' => 5122.5,
                'volume_m3' => 34.75,
                'expected_departure_at' => '2026-11-02 09:30:00',
                'expected_arrival_at' => '2026-11-19 09:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-03 08:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-31 09:00:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-11-02 05:30:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => 1.3644,
                        'longitude' => 103.9915,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-11-02 18:30:00',
                        'location_name' => 'Departed Singapore',
                        'latitude' => 1.3644,
                        'longitude' => 103.9915,
                        'description' => null,
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-11-03 02:30:00',
                        'location_name' => 'Piraeus Med Gateway · Greece',
                        'latitude' => 37.942,
                        'longitude' => 23.637,
                        'description' => 'Carrier telemetry check-in',
                    ],
                    [
                        'event_type' => 'EXCEPTION',
                        'occurred_at' => '2026-11-03 14:15:00',
                        'location_name' => 'Port of Rotterdam Customs Terminal',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Customs hold raised (HIGH): Container selected for physical inspection at Rotterdam customs.',
                    ],
                ],
                'exception' => [
                    'type' => 'CUSTOMS_HOLD',
                    'severity' => 'HIGH',
                    'status' => 'OPEN',
                    'detected_at' => '2026-11-03 14:15:00',
                    'description' => 'Container selected for physical inspection at Rotterdam customs.',
                ],
            ],
            [
                'shipment_number' => 'CT0009MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'BKK',
                'dest_code' => 'HAM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Machinery parts',
                'weight_kg' => 5740,
                'volume_m3' => 38,
                'expected_departure_at' => '2026-11-01 10:30:00',
                'expected_arrival_at' => '2026-11-20 10:30:00',
                'estimated_arrival_at' => '2026-11-20 18:30:00',
                'created_at' => '2026-11-03 12:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-30 09:00:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-11-01 06:30:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => 13.69,
                        'longitude' => 100.7501,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-11-01 19:30:00',
                        'location_name' => 'Departed Bangkok',
                        'latitude' => 13.69,
                        'longitude' => 100.7501,
                        'description' => null,
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-11-02 05:30:00',
                        'location_name' => 'Colombo Deepwater Hub · Sri Lanka',
                        'latitude' => 6.9497,
                        'longitude' => 79.8428,
                        'description' => 'Carrier telemetry check-in',
                    ],
                    [
                        'event_type' => 'EXCEPTION',
                        'occurred_at' => '2026-11-02 11:00:00',
                        'location_name' => 'North Sea Approach Corridor',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Weather delay raised (MEDIUM): North Sea storm window — carrier expects a one-day berth delay.',
                    ],
                ],
                'exception' => [
                    'type' => 'WEATHER_DELAY',
                    'severity' => 'MEDIUM',
                    'status' => 'INVESTIGATING',
                    'detected_at' => '2026-11-02 11:00:00',
                    'description' => 'North Sea storm window — carrier expects a one-day berth delay.',
                ],
            ],
            [
                'shipment_number' => 'CT0010ACL',
                'carrier_code' => 'ACL',
                'origin_code' => 'JKT',
                'dest_code' => 'RTM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Perishables',
                'weight_kg' => 6357.5,
                'volume_m3' => 41.25,
                'expected_departure_at' => '2026-10-29 11:30:00',
                'expected_arrival_at' => '2026-11-20 11:30:00',
                'estimated_arrival_at' => '2026-11-21 17:30:00',
                'created_at' => '2026-11-03 16:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-27 09:00:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-29 07:30:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => -6.105,
                        'longitude' => 106.88,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-29 20:30:00',
                        'location_name' => 'Departed Jakarta',
                        'latitude' => -6.105,
                        'longitude' => 106.88,
                        'description' => null,
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-30 09:30:00',
                        'location_name' => 'Dubai Jebel Ali Logistics Hub · UAE',
                        'latitude' => 25.0112,
                        'longitude' => 55.0612,
                        'description' => 'Carrier telemetry check-in',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0011NFR',
                'carrier_code' => 'NFR',
                'origin_code' => 'SIN',
                'dest_code' => 'RTM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Automotive components',
                'weight_kg' => 6975,
                'volume_m3' => 44.5,
                'expected_departure_at' => '2026-10-30 12:30:00',
                'expected_arrival_at' => '2026-11-19 12:30:00',
                'estimated_arrival_at' => '2026-11-19 08:30:00',
                'created_at' => '2026-11-03 20:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-28 09:00:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-30 08:30:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => 1.3644,
                        'longitude' => 103.9915,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-30 21:30:00',
                        'location_name' => 'Departed Singapore',
                        'latitude' => 1.3644,
                        'longitude' => 103.9915,
                        'description' => null,
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-31 08:30:00',
                        'location_name' => 'Suez Canal · Port Said Transit',
                        'latitude' => 31.2653,
                        'longitude' => 32.3019,
                        'description' => 'Carrier telemetry check-in',
                    ],
                ],
                'exception' => [
                    'type' => 'MISROUTED',
                    'severity' => 'LOW',
                    'status' => 'RESOLVED',
                    'detected_at' => '2026-11-01 08:00:00',
                    'description' => 'Container loaded on the wrong feeder — rerouted at Singapore.',
                ],
            ],
            [
                'shipment_number' => 'CT0012NFR',
                'carrier_code' => 'NFR',
                'origin_code' => 'SUB',
                'dest_code' => 'RTM',
                'status' => 'DELIVERED',
                'cargo_type' => 'Consumer goods',
                'weight_kg' => 7592.5,
                'volume_m3' => 47.75,
                'expected_departure_at' => '2026-10-15 13:30:00',
                'expected_arrival_at' => '2026-11-05 13:30:00',
                'estimated_arrival_at' => '2026-11-05 11:30:00',
                'created_at' => '2026-11-04 00:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-13 09:00:00',
                        'location_name' => 'Juanda International',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-15 09:30:00',
                        'location_name' => 'Juanda International',
                        'latitude' => -7.3798,
                        'longitude' => 112.7876,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-15 22:30:00',
                        'location_name' => 'Departed Surabaya',
                        'latitude' => -7.3798,
                        'longitude' => 112.7876,
                        'description' => null,
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-16 10:30:00',
                        'location_name' => 'Piraeus Med Gateway · Greece',
                        'latitude' => 37.942,
                        'longitude' => 23.637,
                        'description' => 'Carrier telemetry check-in',
                    ],
                    [
                        'event_type' => 'DELIVERED',
                        'occurred_at' => '2026-11-05 10:30:00',
                        'location_name' => 'Port of Rotterdam',
                        'latitude' => 51.9496,
                        'longitude' => 4.1425,
                        'description' => 'Proof of delivery signed',
                    ],
                ],
                'exception' => [
                    'type' => 'DOCUMENTATION_ISSUE',
                    'severity' => 'LOW',
                    'status' => 'RESOLVED',
                    'detected_at' => '2026-10-29 10:00:00',
                    'description' => 'Bill of lading name mismatch — corrected with the consignee.',
                ],
            ],
            [
                'shipment_number' => 'CT0013ACL',
                'carrier_code' => 'ACL',
                'origin_code' => 'BKK',
                'dest_code' => 'HAM',
                'status' => 'DELIVERED',
                'cargo_type' => 'Electronics',
                'weight_kg' => 8210,
                'volume_m3' => 51,
                'expected_departure_at' => '2026-10-10 08:30:00',
                'expected_arrival_at' => '2026-11-05 08:30:00',
                'estimated_arrival_at' => '2026-11-05 18:30:00',
                'created_at' => '2026-11-04 04:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-08 09:00:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-10 04:30:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => 13.69,
                        'longitude' => 100.7501,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-10 17:30:00',
                        'location_name' => 'Departed Bangkok',
                        'latitude' => 13.69,
                        'longitude' => 100.7501,
                        'description' => null,
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-11 10:30:00',
                        'location_name' => 'Colombo Deepwater Hub · Sri Lanka',
                        'latitude' => 6.9497,
                        'longitude' => 79.8428,
                        'description' => 'Carrier telemetry check-in',
                    ],
                    [
                        'event_type' => 'DELIVERED',
                        'occurred_at' => '2026-11-05 05:30:00',
                        'location_name' => 'Hamburg Airport Cargo',
                        'latitude' => 53.6304,
                        'longitude' => 9.9882,
                        'description' => 'Proof of delivery signed',
                    ],
                ],
                'exception' => [
                    'type' => 'PORT_CONGESTION',
                    'severity' => 'MEDIUM',
                    'status' => 'RESOLVED',
                    'detected_at' => '2026-10-28 10:00:00',
                    'description' => 'Berth congestion at Hamburg — vessel waited 14h at anchorage.',
                ],
            ],
            [
                'shipment_number' => 'CT0014MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'JKT',
                'dest_code' => 'RTM',
                'status' => 'DELIVERED',
                'cargo_type' => 'Garments',
                'weight_kg' => 8827.5,
                'volume_m3' => 54.25,
                'expected_departure_at' => '2026-10-20 09:30:00',
                'expected_arrival_at' => '2026-11-07 09:30:00',
                'estimated_arrival_at' => null,
                'created_at' => '2026-11-04 08:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-18 09:00:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => null,
                        'longitude' => null,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-20 05:30:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => -6.105,
                        'longitude' => 106.88,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-20 18:30:00',
                        'location_name' => 'Departed Jakarta',
                        'latitude' => -6.105,
                        'longitude' => 106.88,
                        'description' => null,
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-21 03:30:00',
                        'location_name' => 'Dubai Jebel Ali Logistics Hub · UAE',
                        'latitude' => 25.0112,
                        'longitude' => 55.0612,
                        'description' => 'Carrier telemetry check-in',
                    ],
                    [
                        'event_type' => 'DELIVERED',
                        'occurred_at' => '2026-11-07 06:30:00',
                        'location_name' => 'Port of Rotterdam',
                        'latitude' => 51.9496,
                        'longitude' => 4.1425,
                        'description' => 'Proof of delivery signed',
                    ],
                ],
                'exception' => [
                    'type' => 'DAMAGED',
                    'severity' => 'LOW',
                    'status' => 'RESOLVED',
                    'detected_at' => '2026-10-22 09:00:00',
                    'description' => 'Minor carton damage on one pallet — repacked at origin warehouse.',
                ],
            ],
        ];

        $customersByCode = [
            'CITRA' => $customer->id,
            'DEWI' => $customer2->id,
        ];

        foreach ($seedShipments as $index => $row) {
            $shipment = Shipment::updateOrCreate(
                ['shipment_number' => $row['shipment_number']],
                [
                    'customer_id' => $customersByCode[$row['customer_code'] ?? 'CITRA'],
                    'carrier_id' => $carriers[$row['carrier_code']]->id,
                    'origin_location_id' => $locs[$row['origin_code']]->id,
                    'destination_location_id' => $locs[$row['dest_code']]->id,
                    'current_status' => $row['status'],
                    'cargo_type' => $row['cargo_type'],
                    'weight_kg' => $row['weight_kg'],
                    'volume_m3' => $row['volume_m3'],
                    'expected_departure_at' => $row['expected_departure_at'],
                    'expected_arrival_at' => $row['expected_arrival_at'],
                    'estimated_arrival_at' => $row['estimated_arrival_at'],
                ]
            );
            // Keep the original seed's created_at clock (BASE_TIME minus (14-index)*4h).
            $shipment->forceFill(['created_at' => $row['created_at']])->save();

            // One manifest item per shipment, same formulas as the original seed.
            ShipmentItem::updateOrCreate(
                ['shipment_id' => $shipment->id, 'description' => 'Palletized ' . $row['cargo_type']],
                [
                    'quantity' => 12 + $index * 7,
                    'weight_kg' => 400 + $index * 221.25,
                    'volume_m3' => 6 + $index * 1.4,
                ]
            );

            foreach ($row['checkpoints'] as $cp) {
                TrackingEvent::updateOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'event_type' => $cp['event_type'],
                        'occurred_at' => $cp['occurred_at'],
                    ],
                    [
                        'location_name' => $cp['location_name'],
                        'latitude' => $cp['latitude'],
                        'longitude' => $cp['longitude'],
                        'description' => $cp['description'],
                        'created_by' => $operator->id,
                    ]
                );
            }

            if (! empty($row['exception'])) {
                ShipmentException::updateOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'type' => $row['exception']['type'],
                    ],
                    [
                        'severity' => $row['exception']['severity'],
                        'status' => $row['exception']['status'],
                        'detected_at' => $row['exception']['detected_at'],
                        'resolved_at' => $row['exception']['status'] === 'RESOLVED'
                            ? date('Y-m-d H:i:s', strtotime($row['exception']['detected_at']) + 86400)
                            : null,
                        'description' => $row['exception']['description'],
                    ]
                );
            }
        }

        // 6. Initial Notifications & Audit Log (same four as the original seed:
        // admin gets two unread + one read, Citra gets one unread -> bell badge "2").
        $notif = function (array $match, array $values, ?string $createdAt = null) {
            $n = Notification::updateOrCreate($match, $values);
            if ($createdAt !== null) {
                $n->forceFill(['created_at' => $createdAt])->save();
            }
        };

        $notif(
            ['user_id' => $admin->id, 'title' => 'Customs hold on CT0008MAC'],
            [
                'shipment_id' => Shipment::where('shipment_number', 'CT0008MAC')->value('id'),
                'type' => 'EXCEPTION_CREATED',
                'body' => 'Severity HIGH — Container selected for physical inspection at Rotterdam customs.',
                'read_at' => null,
            ],
            '2026-11-03 14:15:00'
        );

        $notif(
            ['user_id' => $admin->id, 'title' => 'Weather delay on CT0009MAC'],
            [
                'shipment_id' => Shipment::where('shipment_number', 'CT0009MAC')->value('id'),
                'type' => 'EXCEPTION_CREATED',
                'body' => 'Severity MEDIUM — North Sea storm window — carrier expects a one-day berth delay.',
                'read_at' => null,
            ],
            '2026-11-02 11:00:00'
        );

        $notif(
            ['user_id' => $admin->id, 'title' => 'Shipment CT0010ACL is now In transit'],
            [
                'shipment_id' => Shipment::where('shipment_number', 'CT0010ACL')->value('id'),
                'type' => 'STATUS_CHANGED',
                'body' => 'Latest position: Departed Jakarta',
                'read_at' => '2026-11-04 00:00:00',
            ],
            '2026-11-03 18:00:00'
        );

        $notif(
            ['user_id' => $customer->id, 'title' => 'Customs hold on CT0008MAC'],
            [
                'shipment_id' => Shipment::where('shipment_number', 'CT0008MAC')->value('id'),
                'type' => 'EXCEPTION_CREATED',
                'body' => 'Severity HIGH — Container selected for physical inspection at Rotterdam customs.',
                'read_at' => null,
            ],
            '2026-11-03 14:15:00'
        );

        AuditLog::create([
            'actor_id' => $operator->id,
            'action' => 'EXCEPTION_CREATED',
            'entity_type' => 'ShipmentException',
            'entity_id' => ShipmentException::where('type', 'CUSTOMS_HOLD')->value('id'),
            'changes' => [
                'type' => ['from' => null, 'to' => 'CUSTOMS_HOLD'],
                'severity' => ['from' => null, 'to' => 'HIGH'],
            ],
            'created_at' => '2026-11-03 14:15:00',
        ]);

        AuditLog::create([
            'actor_id' => $operator->id,
            'action' => 'EXCEPTION_STATUS_CHANGED',
            'entity_type' => 'ShipmentException',
            'entity_id' => ShipmentException::where('type', 'WEATHER_DELAY')->value('id'),
            'changes' => [
                'status' => ['from' => 'OPEN', 'to' => 'INVESTIGATING'],
            ],
            'created_at' => '2026-11-03 08:00:00',
        ]);

        AuditLog::create([
            'actor_id' => $operator->id,
            'action' => 'SHIPMENT_STATUS_CHANGED',
            'entity_type' => 'Shipment',
            'entity_id' => Shipment::where('shipment_number', 'CT0008MAC')->value('id'),
            'changes' => [
                'current_status' => ['from' => 'PICKED_UP', 'to' => 'IN_TRANSIT'],
            ],
            'created_at' => '2026-11-03 00:00:00',
        ]);

        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'SHIPMENT_CREATED',
            'entity_type' => 'Shipment',
            'entity_id' => Shipment::where('shipment_number', 'CT0001NFR')->value('id'),
            'changes' => [
                'shipment_number' => ['from' => null, 'to' => 'CT0001NFR'],
                'current_status' => ['from' => null, 'to' => 'CREATED'],
            ],
            'created_at' => '2026-11-02 12:00:00',
        ]);
    }
}
