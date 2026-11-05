import {
  AuditLog,
  Carrier,
  ExceptionSeverity,
  ExceptionStatus,
  ExceptionType,
  Location,
  ScenarioConfig,
  Shipment,
  ShipmentException,
  ShipmentItem,
  ShipmentNotification,
  ShipmentStatus,
  TrackingEvent,
  User,
  UserRole,
} from './types';

const BASE_TIME = new Date('2026-11-04T12:00:00.000Z');

function addDays(base: Date, days: number, hour = 8, minute = 30): Date {
  const d = new Date(base.getTime());
  d.setUTCDate(d.getUTCDate() + days);
  d.setUTCHours(hour, minute, 0, 0);
  return d;
}

function addHours(base: Date, hours: number): Date {
  return new Date(base.getTime() + hours * 3600 * 1000);
}

export interface SeedState {
  users: User[];
  carriers: Carrier[];
  locations: Location[];
  shipments: Shipment[];
  items: ShipmentItem[];
  trackingEvents: TrackingEvent[];
  exceptions: ShipmentException[];
  notifications: ShipmentNotification[];
  auditLogs: AuditLog[];
  scenarioConfig: ScenarioConfig;
}

export function buildInitialSeed(): SeedState {
  const users: User[] = [
    {
      id: 1,
      name: 'Aria Wibowo',
      email: 'admin@cargotrace.test',
      role: UserRole.Admin,
    },
    {
      id: 2,
      name: 'Bimo Santoso',
      email: 'operator@cargotrace.test',
      role: UserRole.Operator,
    },
    {
      id: 3,
      name: 'Citra Hapsari',
      email: 'customer@cargotrace.test',
      role: UserRole.Customer,
    },
  ];

  const carriers: Carrier[] = [
    {
      id: 1,
      name: 'Meridian Air Cargo',
      code: 'MAC',
      service_type: 'AIR',
      contact_email: 'ops@meridianair.example',
      is_active: true,
    },
    {
      id: 2,
      name: 'Nusantara Freight',
      code: 'NFR',
      service_type: 'SEA',
      contact_email: 'desk@nusantarafreight.example',
      is_active: true,
    },
    {
      id: 3,
      name: 'Aurora Container Line',
      code: 'ACL',
      service_type: 'SEA',
      contact_email: 'bookings@auroracl.example',
      is_active: true,
    },
    {
      id: 4,
      name: 'Java Express Haulage',
      code: 'JVX',
      service_type: 'ROAD',
      contact_email: 'control@javaexpress.example',
      is_active: true,
    },
  ];

  const locations: Location[] = [
    {
      id: 1,
      name: 'Singapore Changi Air Cargo',
      code: 'SIN',
      city: 'Singapore',
      country: 'Singapore',
      latitude: 1.3644,
      longitude: 103.9915,
    },
    {
      id: 2,
      name: 'Tanjung Priok Port',
      code: 'JKT',
      city: 'Jakarta',
      country: 'Indonesia',
      latitude: -6.105,
      longitude: 106.88,
    },
    {
      id: 3,
      name: 'Juanda International',
      code: 'SUB',
      city: 'Surabaya',
      country: 'Indonesia',
      latitude: -7.3798,
      longitude: 112.7876,
    },
    {
      id: 4,
      name: 'Suvarnabhumi Cargo',
      code: 'BKK',
      city: 'Bangkok',
      country: 'Thailand',
      latitude: 13.69,
      longitude: 100.7501,
    },
    {
      id: 5,
      name: 'Port of Rotterdam',
      code: 'RTM',
      city: 'Rotterdam',
      country: 'Netherlands',
      latitude: 51.9496,
      longitude: 4.1425,
    },
    {
      id: 6,
      name: 'Hamburg Airport Cargo',
      code: 'HAM',
      city: 'Hamburg',
      country: 'Germany',
      latitude: 53.6304,
      longitude: 9.9882,
    },
  ];

  const locationByCode = (code: string) => locations.find((l) => l.code === code)!;
  const carrierByCode = (code: string) => carriers.find((c) => c.code === code)!;

  const plans: Array<
    [
      ShipmentStatus,
      string,
      string,
      string,
      number,
      number,
      number,
      number | null,
    ]
  > = [
    [ShipmentStatus.Created, 'JKT', 'RTM', 'NFR', 3, 18, 0, null],
    [ShipmentStatus.Created, 'SUB', 'HAM', 'MAC', 5, 21, 1, null],
    [ShipmentStatus.Created, 'BKK', 'RTM', 'ACL', 7, 26, 2, null],
    [ShipmentStatus.Booked, 'SIN', 'RTM', 'MAC', 1, 16, 0, null],
    [ShipmentStatus.Booked, 'JKT', 'HAM', 'ACL', 2, 24, 1, null],
    [ShipmentStatus.PickedUp, 'SIN', 'HAM', 'MAC', -1, 17, 2, null],
    [ShipmentStatus.PickedUp, 'SUB', 'RTM', 'JVX', -2, 28, 0, null],
    [ShipmentStatus.InTransit, 'SIN', 'RTM', 'MAC', -2, 17, 0, null],
    [ShipmentStatus.InTransit, 'BKK', 'HAM', 'MAC', -3, 19, 1, 8],
    [ShipmentStatus.InTransit, 'JKT', 'RTM', 'ACL', -6, 22, 2, 30],
    [ShipmentStatus.InTransit, 'SIN', 'RTM', 'NFR', -5, 20, 0, -4],
    [ShipmentStatus.Delivered, 'SUB', 'RTM', 'NFR', -20, 21, 1, -2],
    [ShipmentStatus.Delivered, 'BKK', 'HAM', 'ACL', -25, 26, 2, 10],
    [ShipmentStatus.Delivered, 'JKT', 'RTM', 'MAC', -15, 18, 0, null],
  ];

  const cargoTypes = [
    'Electronics',
    'Garments',
    'Machinery parts',
    'Perishables',
    'Automotive components',
    'Consumer goods',
  ];

  const midRouteWaypoints: Array<{ name: string; lat: number; lng: number }> = [
    { name: 'Colombo Deepwater Hub · Sri Lanka', lat: 6.9497, lng: 79.8428 },
    { name: 'Dubai Jebel Ali Logistics Hub · UAE', lat: 25.0112, lng: 55.0612 },
    { name: 'Suez Canal · Port Said Transit', lat: 31.2653, lng: 32.3019 },
    { name: 'Piraeus Med Gateway · Greece', lat: 37.942, lng: 23.637 },
  ];

  const shipments: Shipment[] = [];
  const items: ShipmentItem[] = [];
  const trackingEvents: TrackingEvent[] = [];
  let eventIdCounter = 1;

  plans.forEach(
    ([status, originCode, destCode, carrierCode, offsetDays, transitDays, _custIdx, estimateHours], index) => {
      const id = index + 1;
      const departure = addDays(BASE_TIME, offsetDays, 8 + (index % 6), 30);
      const arrival = addDays(departure, transitDays, 8 + (index % 6), 30);
      const estimated =
        estimateHours === null ? null : addHours(arrival, estimateHours).toISOString();

      const originLoc = locationByCode(originCode);
      const destLoc = locationByCode(destCode);
      const carrier = carrierByCode(carrierCode);

      const shipment: Shipment = {
        id,
        shipment_number: `CT${String(id).padStart(4, '0')}${carrierCode}`,
        customer_id: 3,
        carrier_id: carrier.id,
        origin_location_id: originLoc.id,
        destination_location_id: destLoc.id,
        cargo_type: cargoTypes[index % cargoTypes.length],
        weight_kg: 800 + index * 617.5,
        volume_m3: 12 + index * 3.25,
        expected_departure_at: departure.toISOString(),
        expected_arrival_at: arrival.toISOString(),
        estimated_arrival_at: estimated,
        current_status: status,
        created_at: addHours(BASE_TIME, -(14 - index) * 4).toISOString(),
      };
      shipments.push(shipment);

      items.push({
        id,
        shipment_id: id,
        description: `Palletized ${cargoTypes[index % cargoTypes.length]}`,
        quantity: 12 + index * 7,
        weight_kg: 400 + index * 221.25,
        volume_m3: 6 + index * 1.4,
      });

      if (
        [
          ShipmentStatus.Booked,
          ShipmentStatus.PickedUp,
          ShipmentStatus.InTransit,
          ShipmentStatus.Delivered,
        ].includes(status)
      ) {
        trackingEvents.push({
          id: eventIdCounter++,
          shipment_id: id,
          event_type: ShipmentStatus.Booked,
          occurred_at: addDays(departure, -2, 9, 0).toISOString(),
          location_name: originLoc.name,
          latitude: null,
          longitude: null,
          description: 'Booking confirmed',
          created_by: 2,
        });
      }

      if (
        [
          ShipmentStatus.PickedUp,
          ShipmentStatus.InTransit,
          ShipmentStatus.Delivered,
        ].includes(status)
      ) {
        trackingEvents.push({
          id: eventIdCounter++,
          shipment_id: id,
          event_type: ShipmentStatus.PickedUp,
          occurred_at: addHours(departure, -4).toISOString(),
          location_name: originLoc.name,
          latitude: originLoc.latitude,
          longitude: originLoc.longitude,
          description: 'Cargo picked up and sealed',
          created_by: 2,
        });
      }

      if ([ShipmentStatus.InTransit, ShipmentStatus.Delivered].includes(status)) {
        trackingEvents.push({
          id: eventIdCounter++,
          shipment_id: id,
          event_type: ShipmentStatus.InTransit,
          occurred_at: addHours(departure, 9).toISOString(),
          location_name: `Departed ${originLoc.city}`,
          latitude: originLoc.latitude,
          longitude: originLoc.longitude,
          description: null,
          created_by: 2,
        });

        const wp = midRouteWaypoints[index % midRouteWaypoints.length];
        trackingEvents.push({
          id: eventIdCounter++,
          shipment_id: id,
          event_type: 'INFO',
          occurred_at: addHours(departure, transitDays).toISOString(),
          location_name: wp.name,
          latitude: wp.lat,
          longitude: wp.lng,
          description: 'Carrier telemetry check-in',
          created_by: 2,
        });
      }

      if (status === ShipmentStatus.Delivered) {
        trackingEvents.push({
          id: eventIdCounter++,
          shipment_id: id,
          event_type: ShipmentStatus.Delivered,
          occurred_at: addHours(arrival, -3).toISOString(),
          location_name: destLoc.name,
          latitude: destLoc.latitude,
          longitude: destLoc.longitude,
          description: 'Proof of delivery signed',
          created_by: 2,
        });
      }
    },
  );

  const inTransitShipments = shipments.filter((s) => s.current_status === ShipmentStatus.InTransit);
  const deliveredShipment = shipments.find((s) => s.current_status === ShipmentStatus.Delivered)!;

  const exceptions: ShipmentException[] = [
    {
      id: 1,
      shipment_id: inTransitShipments[0].id,
      type: ExceptionType.CustomsHold,
      severity: ExceptionSeverity.High,
      status: ExceptionStatus.Open,
      description: 'Container selected for physical inspection at Rotterdam customs.',
      detected_at: addDays(BASE_TIME, -1, 14, 15).toISOString(),
      resolved_at: null,
      resolved_by: null,
    },
    {
      id: 2,
      shipment_id: inTransitShipments[1].id,
      type: ExceptionType.WeatherDelay,
      severity: ExceptionSeverity.Medium,
      status: ExceptionStatus.Investigating,
      description: 'North Sea storm window — carrier expects a one-day berth delay.',
      detected_at: addDays(BASE_TIME, -2, 11, 0).toISOString(),
      resolved_at: null,
      resolved_by: null,
    },
    {
      id: 3,
      shipment_id: deliveredShipment.id,
      type: ExceptionType.DocumentationIssue,
      severity: ExceptionSeverity.Low,
      status: ExceptionStatus.Resolved,
      description: 'Bill of lading name mismatch — corrected with the consignee.',
      detected_at: addDays(BASE_TIME, -6, 10, 0).toISOString(),
      resolved_at: addDays(BASE_TIME, -5, 16, 30).toISOString(),
      resolved_by: 2,
    },
  ];

  trackingEvents.push(
    {
      id: eventIdCounter++,
      shipment_id: inTransitShipments[0].id,
      event_type: 'EXCEPTION',
      occurred_at: exceptions[0].detected_at,
      location_name: 'Port of Rotterdam Customs Terminal',
      latitude: null,
      longitude: null,
      description: `Customs hold raised (HIGH): ${exceptions[0].description}`,
      created_by: 2,
      exception_id: 1,
    },
    {
      id: eventIdCounter++,
      shipment_id: inTransitShipments[1].id,
      event_type: 'EXCEPTION',
      occurred_at: exceptions[1].detected_at,
      location_name: 'North Sea Approach Corridor',
      latitude: null,
      longitude: null,
      description: `Weather delay raised (MEDIUM): ${exceptions[1].description}`,
      created_by: 2,
      exception_id: 2,
    },
  );

  const notifications: ShipmentNotification[] = [
    {
      id: 1,
      user_id: 1,
      shipment_id: inTransitShipments[0].id,
      type: 'EXCEPTION_CREATED',
      title: `Customs hold on ${inTransitShipments[0].shipment_number}`,
      body: 'Severity HIGH — Container selected for physical inspection at Rotterdam customs.',
      read_at: null,
      created_at: exceptions[0].detected_at,
    },
    {
      id: 2,
      user_id: 1,
      shipment_id: inTransitShipments[1].id,
      type: 'EXCEPTION_CREATED',
      title: `Weather delay on ${inTransitShipments[1].shipment_number}`,
      body: 'Severity MEDIUM — North Sea storm window — carrier expects a one-day berth delay.',
      read_at: null,
      created_at: exceptions[1].detected_at,
    },
    {
      id: 3,
      user_id: 1,
      shipment_id: inTransitShipments[2].id,
      type: 'STATUS_CHANGED',
      title: `Shipment ${inTransitShipments[2].shipment_number} is now In transit`,
      body: 'Latest position: Departed Jakarta',
      read_at: addHours(BASE_TIME, -12).toISOString(),
      created_at: addHours(BASE_TIME, -18).toISOString(),
    },
    {
      id: 4,
      user_id: 3,
      shipment_id: inTransitShipments[0].id,
      type: 'EXCEPTION_CREATED',
      title: `Customs hold on ${inTransitShipments[0].shipment_number}`,
      body: 'Severity HIGH — Container selected for physical inspection at Rotterdam customs.',
      read_at: null,
      created_at: exceptions[0].detected_at,
    },
  ];

  const auditLogs: AuditLog[] = [
    {
      id: 1,
      actor_id: 2,
      action: 'exception.created',
      entity_type: 'ShipmentException',
      entity_id: 1,
      changes: {
        type: { from: null, to: 'CUSTOMS_HOLD' },
        severity: { from: null, to: 'HIGH' },
      },
      created_at: exceptions[0].detected_at,
    },
    {
      id: 2,
      actor_id: 2,
      action: 'exception.status_changed',
      entity_type: 'ShipmentException',
      entity_id: 2,
      changes: {
        status: { from: 'OPEN', to: 'INVESTIGATING' },
      },
      created_at: addHours(BASE_TIME, -28).toISOString(),
    },
    {
      id: 3,
      actor_id: 2,
      action: 'shipment.status_changed',
      entity_type: 'Shipment',
      entity_id: inTransitShipments[0].id,
      changes: {
        current_status: { from: 'PICKED_UP', to: 'IN_TRANSIT' },
      },
      created_at: addHours(BASE_TIME, -36).toISOString(),
    },
    {
      id: 4,
      actor_id: 1,
      action: 'shipment.created',
      entity_type: 'Shipment',
      entity_id: 1,
      changes: null,
      created_at: addHours(BASE_TIME, -48).toISOString(),
    },
  ];

  const scenarioConfig: ScenarioConfig = {
    groupCode: 'DEFAULT',
    routeCorridor: 'Southeast Asia → Europe',
    lowThresholdHours: 6,
    highThresholdHours: 24,
    primaryException: ExceptionType.CustomsHold,
    kpiFocus: 'On-Time Delivery & Exception Rate',
  };

  return {
    users,
    carriers,
    locations,
    shipments,
    items,
    trackingEvents,
    exceptions,
    notifications,
    auditLogs,
    scenarioConfig,
  };
}
