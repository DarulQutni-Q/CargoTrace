import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  allowedNextExceptionStatuses,
  allowedNextStatuses,
  assessDelay,
  canTransitionStatus,
  isShipmentDelayed,
  validateExceptionTransition,
  validateTrackingEvent,
} from './rules';
import { ExceptionStatus, ShipmentStatus } from './types';

describe('CargoTrace Business Rules & UAS Acceptance Suite', () => {
  describe('1. Shipment Lifecycle Transitions (Slide 6)', () => {
    it('allows sequential forward transitions from CREATED to DELIVERED', () => {
      assert.equal(canTransitionStatus(ShipmentStatus.Created, ShipmentStatus.Booked), true);
      assert.equal(canTransitionStatus(ShipmentStatus.Booked, ShipmentStatus.PickedUp), true);
      assert.equal(canTransitionStatus(ShipmentStatus.PickedUp, ShipmentStatus.InTransit), true);
      assert.equal(canTransitionStatus(ShipmentStatus.InTransit, ShipmentStatus.Delivered), true);
    });

    it('enforces DELIVERED as a terminal state and blocks backward transitions', () => {
      assert.deepEqual(allowedNextStatuses(ShipmentStatus.Delivered), []);
      assert.equal(canTransitionStatus(ShipmentStatus.Delivered, ShipmentStatus.InTransit), false);
      assert.equal(canTransitionStatus(ShipmentStatus.Delivered, ShipmentStatus.Created), false);
    });

    it('blocks skipping mandatory lifecycle stages (e.g. CREATED directly to DELIVERED)', () => {
      assert.equal(canTransitionStatus(ShipmentStatus.Created, ShipmentStatus.Delivered), false);
    });
  });

  describe('2. Append-Only Tracking Timeline & Chronology Validation (Slide 7)', () => {
    it('accepts chronological tracking checkpoints and valid coordinate bounds', () => {
      const result = validateTrackingEvent({
        currentStatus: ShipmentStatus.PickedUp,
        eventType: ShipmentStatus.InTransit,
        occurredAtIso: '2026-11-05T12:00:00.000Z',
        lastEventOccurredAtIso: '2026-11-05T08:00:00.000Z',
        formatLastTime: (iso) => iso,
      });
      assert.equal(result.valid, true);
    });

    it('rejects out-of-order tracking events earlier than the latest checkpoint', () => {
      const result = validateTrackingEvent({
        currentStatus: ShipmentStatus.InTransit,
        eventType: 'CHECKPOINT',
        occurredAtIso: '2026-11-04T06:00:00.000Z',
        lastEventOccurredAtIso: '2026-11-05T08:00:00.000Z',
        formatLastTime: (iso) => iso,
      });
      assert.equal(result.valid, false);
      assert.ok(result.errors.occurred_at);
    });

    it('rejects invalid lifecycle transitions submitted through tracking events', () => {
      const result = validateTrackingEvent({
        currentStatus: ShipmentStatus.Delivered,
        eventType: ShipmentStatus.InTransit,
        occurredAtIso: '2026-11-06T12:00:00.000Z',
        lastEventOccurredAtIso: '2026-11-05T08:00:00.000Z',
        formatLastTime: (iso) => iso,
      });
      assert.equal(result.valid, false);
      assert.ok(result.errors.event_type);
    });
  });

  describe('3. Deterministic ETA & Delay Classification (Slide 8 & Slide 23)', () => {
    it('classifies on-time shipments when ETA <= expected arrival', () => {
      const expected = '2026-11-10T10:00:00.000Z';
      const estimated = '2026-11-10T09:00:00.000Z';
      assert.equal(isShipmentDelayed(expected, estimated), false);
      const res = assessDelay(expected, estimated, 6, 24);
      assert.equal(res.state, 'on_time');
      assert.equal(res.delay_hours, 0);
      assert.ok(res.reason);
    });

    it('classifies LOW, MEDIUM, and HIGH delays deterministically using group thresholds', () => {
      const expected = '2026-11-10T00:00:00.000Z';
      const lowEta = '2026-11-10T04:00:00.000Z'; // +4h (<= 6h -> LOW)
      const medEta = '2026-11-10T14:00:00.000Z'; // +14h (6h < d <= 24h -> MEDIUM)
      const highEta = '2026-11-11T08:00:00.000Z'; // +32h (> 24h -> HIGH)

      const lowRes = assessDelay(expected, lowEta, 6, 24);
      assert.equal(lowRes.state, 'low');
      assert.equal(lowRes.delay_hours, 4);

      const medRes = assessDelay(expected, medEta, 6, 24);
      assert.equal(medRes.state, 'medium');
      assert.equal(medRes.delay_hours, 14);

      const highRes = assessDelay(expected, highEta, 6, 24);
      assert.equal(highRes.state, 'high');
      assert.equal(highRes.delay_hours, 32);
    });

    it('recalculates classifications when group scenario thresholds change (e.g. Group B 18h/36h)', () => {
      const expected = '2026-11-10T00:00:00.000Z';
      const eta = '2026-11-10T14:00:00.000Z'; // +14h
      // Under Group B (low <= 18h, med <= 36h), +14h is LOW instead of MEDIUM
      const groupBRes = assessDelay(expected, eta, 18, 36);
      assert.equal(groupBRes.state, 'low');
    });
  });

  describe('4. Exception Management Lifecycle (Slide 8)', () => {
    it('allows OPEN -> INVESTIGATING -> RESOLVED and blocks reopening RESOLVED exceptions', () => {
      assert.deepEqual(allowedNextExceptionStatuses(ExceptionStatus.Open), [
        ExceptionStatus.Investigating,
        ExceptionStatus.Resolved,
      ]);
      assert.equal(
        validateExceptionTransition(ExceptionStatus.Open, ExceptionStatus.Investigating).valid,
        true,
      );
      assert.equal(
        validateExceptionTransition(ExceptionStatus.Investigating, ExceptionStatus.Resolved).valid,
        true,
      );
      assert.equal(
        validateExceptionTransition(ExceptionStatus.Resolved, ExceptionStatus.Open).valid,
        false,
      );
    });
  });
});
