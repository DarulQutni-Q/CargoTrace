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
            ['name' => 'Java Express Haulage', 'code' => 'JEH', 'service_type' => 'ROAD', 'contact_email' => 'fleet@javaexpress.co.id', 'is_active' => false],
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
                'checkpoints' => [],
            ],
            [
                'shipment_number' => 'CT0010ACL',
                'carrier_code' => 'ACL',
                'origin_code' => 'JKT',
                'dest_code' => 'RTM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Perishables',
                'weight_kg' => 6340,
                'volume_m3' => 41,
                'expected_departure_at' => '2026-10-29 09:30:00',
                'expected_arrival_at' => '2026-11-20 09:30:00',
                'estimated_arrival_at' => '2026-11-21 15:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-30 09:30:00',
                        'location_name' => 'Dubai Jebel Ali Logistics Hub · UAE',
                        'latitude' => 25.0112,
                        'longitude' => 55.0612,
                        'description' => 'Intermediate transshipment scan at Jebel Ali hub',
                    ],
                ],
                'exception' => [
                    'type' => 'CUSTOMS_HOLD',
                    'severity' => 'HIGH',
                    'status' => 'OPEN',
                    'detected_at' => '2026-10-30 12:00:00',
                    'description' => 'Customs hold raised during phytosanitary certificate verification (+30h ETA drift).',
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
                'estimated_arrival_at' => '2026-11-19 12:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-31 08:30:00',
                        'location_name' => 'Suez Canal · Port Said Transit',
                        'latitude' => 31.2653,
                        'longitude' => 32.3019,
                        'description' => 'Northbound convoy transit cleared',
                    ],
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
                'estimated_arrival_at' => '2026-11-07 06:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-18 09:00:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => -6.1050,
                        'longitude' => 106.8800,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-20 05:30:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => -6.1050,
                        'longitude' => 106.8800,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-20 18:30:00',
                        'location_name' => 'Departed Jakarta',
                        'latitude' => -6.1050,
                        'longitude' => 106.8800,
                        'description' => 'Main corridor departure',
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
            ],
            [
                'shipment_number' => 'CT0002NFR',
                'customer_code' => 'DEWI',
                'carrier_code' => 'NFR',
                'origin_code' => 'JKT',
                'dest_code' => 'RTM',
                'status' => 'BOOKED',
                'cargo_type' => 'Machinery parts',
                'weight_kg' => 1200,
                'volume_m3' => 18,
                'expected_departure_at' => '2026-11-08 08:30:00',
                'expected_arrival_at' => '2026-11-26 08:30:00',
                'estimated_arrival_at' => null,
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-11-06 09:00:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => -6.1050,
                        'longitude' => 106.8800,
                        'description' => 'Booking confirmed',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0003ACL',
                'customer_code' => 'DEWI',
                'carrier_code' => 'ACL',
                'origin_code' => 'SUB',
                'dest_code' => 'HAM',
                'status' => 'PICKED_UP',
                'cargo_type' => 'Consumer goods',
                'weight_kg' => 2400,
                'volume_m3' => 30,
                'expected_departure_at' => '2026-11-02 09:30:00',
                'expected_arrival_at' => '2026-11-22 09:30:00',
                'estimated_arrival_at' => '2026-11-22 09:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-11-01 09:00:00',
                        'location_name' => 'Juanda International',
                        'latitude' => -7.3798,
                        'longitude' => 112.7876,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-11-02 05:30:00',
                        'location_name' => 'Juanda International',
                        'latitude' => -7.3798,
                        'longitude' => 112.7876,
                        'description' => 'Cargo picked up and sealed',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0004NFR',
                'carrier_code' => 'NFR',
                'origin_code' => 'KRG',
                'dest_code' => 'RTM',
                'status' => 'BOOKED',
                'cargo_type' => 'Palm oil derivatives',
                'weight_kg' => 5000,
                'volume_m3' => 40,
                'expected_departure_at' => '2026-11-09 08:30:00',
                'expected_arrival_at' => '2026-11-27 08:30:00',
                'estimated_arrival_at' => null,
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-11-05 09:00:00',
                        'location_name' => 'Pelabuhan Krueng Geukuh (Pelindo)',
                        'latitude' => 5.2417,
                        'longitude' => 97.0264,
                        'description' => 'Booking confirmed at Aceh hub',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0005ACL',
                'carrier_code' => 'ACL',
                'origin_code' => 'SUB',
                'dest_code' => 'HAM',
                'status' => 'PICKED_UP',
                'cargo_type' => 'Textiles',
                'weight_kg' => 1800,
                'volume_m3' => 22,
                'expected_departure_at' => '2026-11-03 09:30:00',
                'expected_arrival_at' => '2026-11-23 09:30:00',
                'estimated_arrival_at' => '2026-11-23 19:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-11-02 09:00:00',
                        'location_name' => 'Juanda International',
                        'latitude' => -7.3798,
                        'longitude' => 112.7876,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-11-03 05:30:00',
                        'location_name' => 'Juanda International',
                        'latitude' => -7.3798,
                        'longitude' => 112.7876,
                        'description' => 'Cargo picked up and sealed',
                    ],
                ],
                'exception' => [
                    'type' => 'WEATHER_DELAY',
                    'severity' => 'HIGH',
                    'status' => 'OPEN',
                    'detected_at' => '2026-11-04 12:00:00',
                    'description' => 'North Sea storm window — carrier expects a one-day berth delay.',
                ],
            ],
            [
                'shipment_number' => 'CT0006MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'BKK',
                'dest_code' => 'HAM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Electronics',
                'weight_kg' => 950,
                'volume_m3' => 14,
                'expected_departure_at' => '2026-10-28 09:30:00',
                'expected_arrival_at' => '2026-11-18 09:30:00',
                'estimated_arrival_at' => '2026-11-19 21:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-26 09:00:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => 13.6900,
                        'longitude' => 100.7501,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-28 05:30:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => 13.6900,
                        'longitude' => 100.7501,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-29 18:30:00',
                        'location_name' => 'Departed Bangkok',
                        'latitude' => 13.6900,
                        'longitude' => 100.7501,
                        'description' => 'Main corridor departure',
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
                'exception' => [
                    'type' => 'PORT_CONGESTION',
                    'severity' => 'MEDIUM',
                    'status' => 'INVESTIGATING',
                    'detected_at' => '2026-11-02 08:00:00',
                    'description' => 'Berth congestion at Hamburg — vessel queued at anchorage.',
                ],
            ],
            [
                'shipment_number' => 'CT0007NFR',
                'carrier_code' => 'NFR',
                'origin_code' => 'JKT',
                'dest_code' => 'RTM',
                'status' => 'DELIVERED',
                'cargo_type' => 'Coffee beans',
                'weight_kg' => 3200,
                'volume_m3' => 28,
                'expected_departure_at' => '2026-10-15 09:30:00',
                'expected_arrival_at' => '2026-11-05 09:30:00',
                'estimated_arrival_at' => '2026-11-05 06:00:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-13 09:00:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => -6.1050,
                        'longitude' => 106.8800,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-15 05:30:00',
                        'location_name' => 'Tanjung Priok Port',
                        'latitude' => -6.1050,
                        'longitude' => 106.8800,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-16 18:30:00',
                        'location_name' => 'Departed Jakarta',
                        'latitude' => -6.1050,
                        'longitude' => 106.8800,
                        'description' => 'Main corridor departure',
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-18 09:30:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => 1.3644,
                        'longitude' => 103.9915,
                        'description' => 'Transshipment scan',
                    ],
                    [
                        'event_type' => 'DELIVERED',
                        'occurred_at' => '2026-11-05 06:00:00',
                        'location_name' => 'Port of Rotterdam',
                        'latitude' => 51.9496,
                        'longitude' => 4.1425,
                        'description' => 'Proof of delivery signed',
                    ],
                ],
            ],
            [
                'shipment_number' => 'CT0008ACL',
                'carrier_code' => 'ACL',
                'origin_code' => 'BKK',
                'dest_code' => 'RTM',
                'status' => 'DELIVERED',
                'cargo_type' => 'Frozen seafood',
                'weight_kg' => 4100,
                'volume_m3' => 35,
                'expected_departure_at' => '2026-10-18 09:30:00',
                'expected_arrival_at' => '2026-11-06 09:30:00',
                'estimated_arrival_at' => '2026-11-06 19:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-16 09:00:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => 13.6900,
                        'longitude' => 100.7501,
                        'description' => 'Booking confirmed',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-18 05:30:00',
                        'location_name' => 'Suvarnabhumi Cargo',
                        'latitude' => 13.6900,
                        'longitude' => 100.7501,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-19 18:30:00',
                        'location_name' => 'Departed Bangkok',
                        'latitude' => 13.6900,
                        'longitude' => 100.7501,
                        'description' => 'Main corridor departure',
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-10-20 09:30:00',
                        'location_name' => 'Dubai Jebel Ali Logistics Hub · UAE',
                        'latitude' => 25.0112,
                        'longitude' => 55.0612,
                        'description' => 'Carrier telemetry check-in',
                    ],
                    [
                        'event_type' => 'DELIVERED',
                        'occurred_at' => '2026-11-06 19:30:00',
                        'location_name' => 'Port of Rotterdam',
                        'latitude' => 51.9496,
                        'longitude' => 4.1425,
                        'description' => 'Proof of delivery signed (late)',
                    ],
                ],
                'exception' => [
                    'type' => 'DOCUMENTATION_ISSUE',
                    'severity' => 'MEDIUM',
                    'status' => 'RESOLVED',
                    'detected_at' => '2026-10-25 10:00:00',
                    'description' => 'Missing phytosanitary certificate — reissued by exporter.',
                ],
            ],
            [
                'shipment_number' => 'CT0009NFR',
                'carrier_code' => 'NFR',
                'origin_code' => 'KRG',
                'dest_code' => 'HAM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Furniture',
                'weight_kg' => 2600,
                'volume_m3' => 33,
                'expected_departure_at' => '2026-10-31 09:30:00',
                'expected_arrival_at' => '2026-11-20 09:30:00',
                'estimated_arrival_at' => '2026-11-20 09:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-29 09:00:00',
                        'location_name' => 'Pelabuhan Krueng Geukuh (Pelindo)',
                        'latitude' => 5.2417,
                        'longitude' => 97.0264,
                        'description' => 'Booking confirmed at Aceh hub',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-31 05:30:00',
                        'location_name' => 'Pelabuhan Krueng Geukuh (Pelindo)',
                        'latitude' => 5.2417,
                        'longitude' => 97.0264,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-11-01 18:30:00',
                        'location_name' => 'Departed Krueng Geukuh',
                        'latitude' => 5.2417,
                        'longitude' => 97.0264,
                        'description' => 'Main corridor departure',
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
                'shipment_number' => 'CT0012MAC',
                'carrier_code' => 'MAC',
                'origin_code' => 'JKT',
                'dest_code' => 'HAM',
                'status' => 'CREATED',
                'cargo_type' => 'Garments',
                'weight_kg' => 1500,
                'volume_m3' => 20,
                'expected_departure_at' => '2026-11-10 08:30:00',
                'expected_arrival_at' => '2026-11-28 08:30:00',
                'estimated_arrival_at' => null,
                'checkpoints' => [],
            ],
            [
                'shipment_number' => 'CT0013NFR',
                'carrier_code' => 'NFR',
                'origin_code' => 'LSW',
                'dest_code' => 'RTM',
                'status' => 'IN_TRANSIT',
                'cargo_type' => 'Rubber products',
                'weight_kg' => 3800,
                'volume_m3' => 31,
                'expected_departure_at' => '2026-10-30 09:30:00',
                'expected_arrival_at' => '2026-11-19 09:30:00',
                'estimated_arrival_at' => '2026-11-19 13:30:00',
                'checkpoints' => [
                    [
                        'event_type' => 'BOOKED',
                        'occurred_at' => '2026-10-28 09:00:00',
                        'location_name' => 'Pelabuhan Lhokseumawe & KEK Arun',
                        'latitude' => 5.1801,
                        'longitude' => 97.1507,
                        'description' => 'Booking confirmed at Aceh hub',
                    ],
                    [
                        'event_type' => 'PICKED_UP',
                        'occurred_at' => '2026-10-30 05:30:00',
                        'location_name' => 'Pelabuhan Lhokseumawe & KEK Arun',
                        'latitude' => 5.1801,
                        'longitude' => 97.1507,
                        'description' => 'Cargo picked up and sealed',
                    ],
                    [
                        'event_type' => 'IN_TRANSIT',
                        'occurred_at' => '2026-10-31 18:30:00',
                        'location_name' => 'Departed Lhokseumawe',
                        'latitude' => 5.1801,
                        'longitude' => 97.1507,
                        'description' => 'Main corridor departure',
                    ],
                    [
                        'event_type' => 'CHECKPOINT',
                        'occurred_at' => '2026-11-01 09:30:00',
                        'location_name' => 'Singapore Changi Air Cargo',
                        'latitude' => 1.3644,
                        'longitude' => 103.9915,
                        'description' => 'Transshipment scan',
                    ],
                ],
                'exception' => [
                    'type' => 'DAMAGED',
                    'severity' => 'MEDIUM',
                    'status' => 'OPEN',
                    'detected_at' => '2026-11-03 10:00:00',
                    'description' => 'Water ingress in one container — cargo survey scheduled.',
                ],
            ],
        ];

        $customersByCode = [
            'CITRA' => $customer->id,
            'DEWI' => $customer2->id,
        ];

        foreach ($seedShipments as $row) {
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

            ShipmentItem::updateOrCreate(
                ['shipment_id' => $shipment->id, 'description' => 'Palletized ' . $row['cargo_type']],
                [
                    'quantity' => 24,
                    'weight_kg' => $row['weight_kg'],
                    'volume_m3' => $row['volume_m3'],
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

        // 6. Initial Notifications & Audit Log
        Notification::updateOrCreate(
            ['user_id' => $admin->id, 'title' => 'Customs hold raised on CT0010ACL'],
            [
                'type' => 'EXCEPTION_RAISED',
                'body' => 'High-severity CUSTOMS_HOLD detected at Dubai Jebel Ali Logistics Hub.',
            ]
        );

        Notification::updateOrCreate(
            ['user_id' => $customer->id, 'title' => 'Shipment CT0011NFR is now In transit'],
            [
                'type' => 'STATUS_IN_TRANSIT',
                'body' => 'Latest position: Suez Canal · Port Said Transit.',
                'shipment_id' => Shipment::where('shipment_number', 'CT0011NFR')->value('id'),
            ]
        );

        Notification::updateOrCreate(
            ['user_id' => $customer->id, 'title' => 'Shipment CT0014MAC delivered'],
            [
                'type' => 'STATUS_DELIVERED',
                'body' => 'Proof of delivery signed at Port of Rotterdam.',
                'shipment_id' => Shipment::where('shipment_number', 'CT0014MAC')->value('id'),
            ]
        );

        Notification::updateOrCreate(
            ['user_id' => $customer->id, 'title' => 'Weather delay on CT0005ACL'],
            [
                'type' => 'EXCEPTION_RAISED',
                'body' => 'High-severity WEATHER_DELAY — North Sea storm window.',
                'shipment_id' => Shipment::where('shipment_number', 'CT0005ACL')->value('id'),
            ]
        );

        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'SEED_INITIALIZED',
            'entity_type' => 'ScenarioConfig',
            'entity_id' => 1,
            'changes' => [
                'group_code' => ['from' => null, 'to' => 'DEFAULT'],
            ],
        ]);

        AuditLog::create([
            'actor_id' => $operator->id,
            'action' => 'SHIPMENT_CREATED',
            'entity_type' => 'Shipment',
            'entity_id' => Shipment::where('shipment_number', 'CT0014MAC')->value('id'),
            'changes' => [
                'shipment_number' => ['from' => null, 'to' => 'CT0014MAC'],
                'current_status' => ['from' => null, 'to' => 'DELIVERED'],
            ],
        ]);
    }
}
