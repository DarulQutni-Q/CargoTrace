export enum UserRole {
  Admin = 'ADMIN',
  Operator = 'OPERATOR',
  Customer = 'CUSTOMER',
}

export enum ShipmentStatus {
  Created = 'CREATED',
  Booked = 'BOOKED',
  PickedUp = 'PICKED_UP',
  InTransit = 'IN_TRANSIT',
  Delivered = 'DELIVERED',
}

export enum ExceptionType {
  CustomsHold = 'CUSTOMS_HOLD',
  WeatherDelay = 'WEATHER_DELAY',
  PortCongestion = 'PORT_CONGESTION',
  Damaged = 'DAMAGED',
  Misrouted = 'MISROUTED',
  DocumentationIssue = 'DOCUMENTATION_ISSUE',
}

export enum ExceptionSeverity {
  Low = 'LOW',
  Medium = 'MEDIUM',
  High = 'HIGH',
}

export enum ExceptionStatus {
  Open = 'OPEN',
  Investigating = 'INVESTIGATING',
  Resolved = 'RESOLVED',
}

export type BadgeTone =
  | 'neutral'
  | 'blue'
  | 'teal'
  | 'amber'
  | 'orange'
  | 'green'
  | 'red'
  | 'live'
  | 'outline-green';

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
}

export interface Carrier {
  id: number;
  name: string;
  code: string;
  service_type: 'AIR' | 'SEA' | 'ROAD' | '';
  contact_email: string;
  is_active: boolean;
}

export interface Location {
  id: number;
  name: string;
  code: string;
  city: string;
  province?: string | null;
  country: string;
  latitude: number;
  longitude: number;
}

export interface ShipmentItem {
  id: number;
  shipment_id: number;
  description: string;
  quantity: number;
  weight_kg: number;
  volume_m3: number;
}

export interface TrackingEvent {
  id: number;
  shipment_id: number;
  event_type: string;
  occurred_at: string; // ISO string
  location_name: string;
  latitude: number | null;
  longitude: number | null;
  description: string | null;
  created_by: number;
  exception_id?: number | null;
}

export interface ShipmentException {
  id: number;
  shipment_id: number;
  type: ExceptionType;
  severity: ExceptionSeverity;
  status: ExceptionStatus;
  description: string | null;
  detected_at: string; // ISO string
  resolved_at: string | null;
  resolved_by: number | null;
}

export interface Shipment {
  id: number;
  shipment_number: string;
  customer_id: number;
  carrier_id: number;
  origin_location_id: number;
  destination_location_id: number;
  cargo_type: string;
  weight_kg: number;
  volume_m3: number;
  expected_departure_at: string; // ISO string
  expected_arrival_at: string; // ISO string
  estimated_arrival_at: string | null; // ISO string
  current_status: ShipmentStatus;
  created_at: string;
  archived_at?: string | null;
}

export interface ShipmentNotification {
  id: number;
  user_id: number;
  shipment_id: number | null;
  type: string;
  title: string;
  body: string;
  read_at: string | null;
  created_at: string;
}

export interface AuditLog {
  id: number;
  actor_id: number | null;
  action: string;
  entity_type: string;
  entity_id: number;
  changes: Record<string, { from: unknown; to: unknown }> | null;
  created_at: string;
}

export interface DelayAssessment {
  state: 'pending' | 'on_time' | 'low' | 'medium' | 'high';
  delay_hours: number;
  threshold_low: number;
  threshold_high: number;
  reason?: string;
}

export interface ScenarioConfig {
  groupCode: 'DEFAULT' | 'GROUP_A' | 'GROUP_B' | 'GROUP_C' | 'CUSTOM';
  routeCorridor: string;
  lowThresholdHours: number;
  highThresholdHours: number;
  primaryException: ExceptionType;
  kpiFocus: string;
}
