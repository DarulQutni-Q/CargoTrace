import {
  BadgeTone,
  DelayAssessment,
  ExceptionSeverity,
  ExceptionStatus,
  ExceptionType,
  ShipmentStatus,
  UserRole,
} from './types';

export const DEFAULT_DELAY_LOW_HOURS = 6;
export const DEFAULT_DELAY_HIGH_HOURS = 24;

export function roleLabel(role: UserRole): string {
  switch (role) {
    case UserRole.Admin:
      return 'Administrator';
    case UserRole.Operator:
      return 'Logistics Operator';
    case UserRole.Customer:
      return 'Customer';
  }
}

export function statusLabel(status: ShipmentStatus): string {
  switch (status) {
    case ShipmentStatus.Created:
      return 'Created';
    case ShipmentStatus.Booked:
      return 'Booked';
    case ShipmentStatus.PickedUp:
      return 'Picked up';
    case ShipmentStatus.InTransit:
      return 'In transit';
    case ShipmentStatus.Delivered:
      return 'Delivered';
  }
}

export function statusTone(status: ShipmentStatus): BadgeTone {
  switch (status) {
    case ShipmentStatus.Created:
      return 'neutral';
    case ShipmentStatus.Booked:
      return 'amber';
    case ShipmentStatus.PickedUp:
      return 'teal';
    case ShipmentStatus.InTransit:
      return 'blue';
    case ShipmentStatus.Delivered:
      return 'green';
  }
}

export function allowedNextStatuses(status: ShipmentStatus): ShipmentStatus[] {
  switch (status) {
    case ShipmentStatus.Created:
      return [ShipmentStatus.Booked];
    case ShipmentStatus.Booked:
      return [ShipmentStatus.PickedUp];
    case ShipmentStatus.PickedUp:
      return [ShipmentStatus.InTransit];
    case ShipmentStatus.InTransit:
      return [ShipmentStatus.Delivered];
    case ShipmentStatus.Delivered:
      return [];
  }
}

export function canTransitionStatus(
  fromStatus: ShipmentStatus,
  toStatus: ShipmentStatus,
): boolean {
  return allowedNextStatuses(fromStatus).includes(toStatus);
}

export function exceptionTypeLabel(type: ExceptionType): string {
  switch (type) {
    case ExceptionType.CustomsHold:
      return 'Customs hold';
    case ExceptionType.WeatherDelay:
      return 'Weather delay';
    case ExceptionType.PortCongestion:
      return 'Port congestion';
    case ExceptionType.Damaged:
      return 'Damaged';
    case ExceptionType.Misrouted:
      return 'Misrouted';
    case ExceptionType.DocumentationIssue:
      return 'Documentation issue';
  }
}

export function exceptionSeverityTone(severity: ExceptionSeverity): BadgeTone {
  switch (severity) {
    case ExceptionSeverity.Low:
      return 'amber';
    case ExceptionSeverity.Medium:
      return 'orange';
    case ExceptionSeverity.High:
      return 'red';
  }
}

export function exceptionStatusTone(status: ExceptionStatus): BadgeTone {
  switch (status) {
    case ExceptionStatus.Open:
      return 'red';
    case ExceptionStatus.Investigating:
      return 'amber';
    case ExceptionStatus.Resolved:
      return 'green';
  }
}

export function allowedNextExceptionStatuses(status: ExceptionStatus): ExceptionStatus[] {
  switch (status) {
    case ExceptionStatus.Open:
      return [ExceptionStatus.Investigating, ExceptionStatus.Resolved];
    case ExceptionStatus.Investigating:
      return [ExceptionStatus.Resolved];
    case ExceptionStatus.Resolved:
      return [];
  }
}

/**
 * Deterministic delay rule from EtaService.php:
 * Compares estimated_arrival_at against expected_arrival_at and classifies
 * the gap using configurable low and high thresholds.
 */
export function assessDelay(
  expectedArrivalIso: string,
  estimatedArrivalIso: string | null,
  thresholdLow: number = DEFAULT_DELAY_LOW_HOURS,
  thresholdHigh: number = DEFAULT_DELAY_HIGH_HOURS,
): DelayAssessment {
  if (!estimatedArrivalIso) {
    return {
      state: 'pending',
      delay_hours: 0,
      threshold_low: thresholdLow,
      threshold_high: thresholdHigh,
      reason: 'Pending carrier telemetry — no estimated arrival timestamp recorded yet.',
    };
  }

  const expectedMs = new Date(expectedArrivalIso).getTime();
  const estimatedMs = new Date(estimatedArrivalIso).getTime();
  const rawDiffHours = (estimatedMs - expectedMs) / (1000 * 60 * 60);
  const delayHours = Math.max(0, rawDiffHours);
  const roundedHours = Math.round(delayHours * 100) / 100;

  let state: DelayAssessment['state'];
  let reason: string;
  if (delayHours <= 0) {
    state = 'on_time';
    reason = 'Estimated arrival matches or beats planned arrival schedule (0h delay).';
  } else if (delayHours <= thresholdLow) {
    state = 'low';
    reason = `Delay +${roundedHours}h is within LOW threshold (≤ ${thresholdLow}h).`;
  } else if (delayHours <= thresholdHigh) {
    state = 'medium';
    reason = `Delay +${roundedHours}h exceeds ${thresholdLow}h and is within MEDIUM threshold (≤ ${thresholdHigh}h).`;
  } else {
    state = 'high';
    reason = `Delay +${roundedHours}h exceeds HIGH threshold (> ${thresholdHigh}h).`;
  }

  return {
    state,
    delay_hours: roundedHours,
    threshold_low: thresholdLow,
    threshold_high: thresholdHigh,
    reason,
  };
}

export function delayTone(assessment: DelayAssessment): BadgeTone {
  switch (assessment.state) {
    case 'low':
      return 'amber';
    case 'medium':
      return 'orange';
    case 'high':
      return 'red';
    case 'on_time':
      return 'outline-green';
    default:
      return 'neutral';
  }
}

export function isShipmentDelayed(
  expectedArrivalIso: string,
  estimatedArrivalIso: string | null,
): boolean {
  if (!estimatedArrivalIso) return false;
  return new Date(estimatedArrivalIso).getTime() > new Date(expectedArrivalIso).getTime();
}

export function plannedDurationHours(
  expectedDepartureIso: string,
  expectedArrivalIso: string,
): number {
  const diffMs =
    new Date(expectedArrivalIso).getTime() - new Date(expectedDepartureIso).getTime();
  return Math.max(0, Math.round(diffMs / (1000 * 60 * 60)));
}

/**
 * Validates status transition and chronological order per TrackingService.php.
 */
export function validateTrackingEvent(params: {
  currentStatus: ShipmentStatus;
  eventType: string;
  occurredAtIso: string;
  lastEventOccurredAtIso: string | null;
  formatLastTime?: (iso: string) => string;
}): { valid: boolean; errors: Record<string, string> } {
  const errors: Record<string, string> = {};
  const { currentStatus, eventType, occurredAtIso, lastEventOccurredAtIso } = params;

  const statusValues = Object.values(ShipmentStatus) as string[];
  const targetStatus = statusValues.includes(eventType)
    ? (eventType as ShipmentStatus)
    : null;

  if (targetStatus !== null && targetStatus !== currentStatus) {
    const allowed = allowedNextStatuses(currentStatus);
    if (!allowed.includes(targetStatus)) {
      errors.event_type =
        allowed.length === 0
          ? `${currentStatus} is terminal — the shipment cannot return to an earlier status without a special procedure.`
          : `A shipment in ${currentStatus} can only move to: ${allowed.join(', ')}.`;
    }
  }

  if (!occurredAtIso || Number.isNaN(new Date(occurredAtIso).getTime())) {
    errors.occurred_at = 'A valid timestamp is required.';
  } else if (lastEventOccurredAtIso) {
    const occurredMs = new Date(occurredAtIso).getTime();
    const lastMs = new Date(lastEventOccurredAtIso).getTime();
    if (occurredMs < lastMs) {
      const formatted = params.formatLastTime
        ? params.formatLastTime(lastEventOccurredAtIso)
        : lastEventOccurredAtIso;
      errors.occurred_at = `Events are historical — this timestamp is earlier than the last recorded event (${formatted}).`;
    }
  }

  return {
    valid: Object.keys(errors).length === 0,
    errors,
  };
}

/**
 * Validates exception lifecycle transitions per ExceptionService.php.
 */
export function validateExceptionTransition(
  currentStatus: ExceptionStatus,
  targetStatus: ExceptionStatus,
): { valid: boolean; error?: string } {
  const allowed = allowedNextExceptionStatuses(currentStatus);
  if (!allowed.includes(targetStatus)) {
    const list = allowed.join(', ') || 'nothing — it is resolved.';
    return {
      valid: false,
      error: `An exception in ${currentStatus} can only move to: ${list}`,
    };
  }
  return { valid: true };
}

/**
 * Computes route progress bar percentage and label per progress-route.blade.php.
 */
export function computeRouteProgress(
  expectedDepartureIso: string,
  expectedArrivalIso: string,
  nowIso: string = new Date().toISOString(),
): { percent: number; label: string } {
  const startMs = new Date(expectedDepartureIso).getTime();
  const endMs = new Date(expectedArrivalIso).getTime();
  const nowMs = new Date(nowIso).getTime();
  const totalMinutes = Math.max(1, (endMs - startMs) / (1000 * 60));

  if (nowMs < startMs) {
    const remainingHours = Math.ceil((startMs - nowMs) / (1000 * 60 * 60));
    const days = Math.floor(remainingHours / 24);
    const hours = remainingHours % 24;
    return {
      percent: 0,
      label: `Departs in ${days}D ${hours}H`,
    };
  }

  if (nowMs > endMs) {
    return {
      percent: 100,
      label: 'Planned arrival passed',
    };
  }

  const elapsedMinutes = (nowMs - startMs) / (1000 * 60);
  const percent = Math.round(Math.min(100, Math.max(0, (elapsedMinutes / totalMinutes) * 100)));
  const remainingHours = Math.ceil((endMs - nowMs) / (1000 * 60 * 60));
  const days = Math.floor(remainingHours / 24);
  const hours = remainingHours % 24;

  return {
    percent,
    label: `On the way — ETA in ${days}D ${hours}H`,
  };
}

/**
 * Great-circle interpolation and bearing math from resources/js/map.js.
 */
export function greatCirclePoints(
  from: [number, number],
  to: [number, number],
  steps = 48,
): [number, number][] {
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const toDeg = (rad: number) => (rad * 180) / Math.PI;

  const [lat1, lon1] = [toRad(from[0]), toRad(from[1])];
  const [lat2, lon2] = [toRad(to[0]), toRad(to[1])];

  const d =
    2 *
    Math.asin(
      Math.sqrt(
        Math.sin((lat2 - lat1) / 2) ** 2 +
          Math.cos(lat1) * Math.cos(lat2) * Math.sin((lon2 - lon1) / 2) ** 2,
      ),
    );

  if (d === 0 || Number.isNaN(d)) {
    return [from, to];
  }

  const points: [number, number][] = [];
  for (let i = 0; i <= steps; i += 1) {
    const f = i / steps;
    const a = Math.sin((1 - f) * d) / Math.sin(d);
    const b = Math.sin(f * d) / Math.sin(d);
    const x = a * Math.cos(lat1) * Math.cos(lon1) + b * Math.cos(lat2) * Math.cos(lon2);
    const y = a * Math.cos(lat1) * Math.sin(lon1) + b * Math.cos(lat2) * Math.sin(lon2);
    const z = a * Math.sin(lat1) + b * Math.sin(lat2);
    points.push([toDeg(Math.atan2(z, Math.sqrt(x * x + y * y))), toDeg(Math.atan2(y, x))]);
  }

  return points;
}

export function bearingDeg(from: [number, number], to: [number, number]): number {
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const [lat1, lat2] = [toRad(from[0]), toRad(to[0])];
  const dLon = toRad(to[1] - from[1]);
  const y = Math.sin(dLon) * Math.cos(lat2);
  const x =
    Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);

  return (Math.atan2(y, x) * 180) / Math.PI;
}

export function haversineKm(from: [number, number], to: [number, number]): number {
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const R = 6371; // Earth mean radius in km
  const dLat = toRad(to[0] - from[0]);
  const dLon = toRad(to[1] - from[1]);
  const lat1 = toRad(from[0]);
  const lat2 = toRad(to[0]);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return Math.round(R * c);
}

export function carrierHue(code: string): number {
  let hash = 0;
  for (let i = 0; i < code.length; i++) {
    hash = (hash * 31 + code.charCodeAt(i)) | 0;
  }
  return Math.abs(hash) % 360;
}

