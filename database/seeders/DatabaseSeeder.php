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
            ['name' => 'Pelabuhan Belawan International Container Terminal', 'code' => 'BLW', 'city' => 'Kota Medan / Belawan', 'province' => 'Sumatera Utara', 'province' => 'Sumatera Utara', 'country' => 'Indonesia', 'latitude' => 3.7844, 'longitude' => 98.6836],
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
        ];

        foreach ($seedShipments as $row) {
            $shipment = Shipment::updateOrCreate(
                ['shipment_number' => $row['shipment_number']],
                [
                    'customer_id' => $customer->id,
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

        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'SEED_INITIALIZED',
            'entity_type' => 'ScenarioConfig',
            'entity_id' => 1,
            'changes' => [
                'group_code' => ['from' => null, 'to' => 'DEFAULT'],
            ],
        ]);
    }
}
