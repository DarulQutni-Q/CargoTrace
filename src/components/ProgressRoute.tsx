import React, { useMemo } from 'react';
import { Location, Shipment, ShipmentStatus } from '../domain/types';
import { computeRouteProgress, haversineKm, isShipmentDelayed } from '../domain/rules';
import { formatDateTime, useStore } from '../domain/store';

export const ProgressRoute: React.FC<{
  shipment: Shipment;
  origin: Location;
  destination: Location;
}> = ({ shipment, origin, destination }) => {
  const { state } = useStore();
  const { percent, label } = computeRouteProgress(
    shipment.expected_departure_at,
    shipment.expected_arrival_at,
  );
  const delayed = isShipmentDelayed(shipment.expected_arrival_at, shipment.estimated_arrival_at);

  // Extract chronological intermediate checkpoints (with coordinates) between Origin and Destination
  const { transitStops, latestCoordEvent } = useMemo(() => {
    const coordEvents = state.trackingEvents
      .filter(
        (e) =>
          e.shipment_id === shipment.id &&
          e.latitude !== null &&
          e.longitude !== null,
      )
      .sort((a, b) => new Date(a.occurred_at).getTime() - new Date(b.occurred_at).getTime());

    const stops: Array<{
      id: number;
      name: string;
      latitude: number;
      longitude: number;
      percentAlong: number;
    }> = [];
    const seenNames = new Set<string>();

    for (const ev of coordEvents) {
      const lat = ev.latitude!;
      const lng = ev.longitude!;
      const isAtOrigin =
        Math.abs(lat - origin.latitude) < 0.02 && Math.abs(lng - origin.longitude) < 0.02;
      const isAtDest =
        Math.abs(lat - destination.latitude) < 0.02 &&
        Math.abs(lng - destination.longitude) < 0.02;
      if (isAtOrigin || isAtDest) continue;

      const key = `${lat.toFixed(2)},${lng.toFixed(2)}`;
      if (seenNames.has(key)) continue;
      seenNames.add(key);

      const dFromOrigin = haversineKm([origin.latitude, origin.longitude], [lat, lng]);
      const dToDest = haversineKm([lat, lng], [destination.latitude, destination.longitude]);
      const total = Math.max(1, dFromOrigin + dToDest);
      const pct = Math.min(88, Math.max(14, Math.round((dFromOrigin / total) * 100)));

      stops.push({
        id: ev.id,
        name: ev.location_name,
        latitude: lat,
        longitude: lng,
        percentAlong: pct,
      });
    }

    const newest = coordEvents.length > 0 ? coordEvents[coordEvents.length - 1] : null;
    return { transitStops: stops, latestCoordEvent: newest };
  }, [state.trackingEvents, shipment.id, origin, destination]);

  const effectivePercent = useMemo(() => {
    if (shipment.current_status === ShipmentStatus.Delivered) return 100;

    // If there is a recorded checkpoint away from Origin (e.g. Port of Singapore between KRG and SUB),
    // compute geographic progress along Origin -> Current Checkpoint -> Destination
    if (latestCoordEvent && latestCoordEvent.latitude !== null && latestCoordEvent.longitude !== null) {
      const lat = latestCoordEvent.latitude;
      const lng = latestCoordEvent.longitude;
      const isAtOrigin =
        Math.abs(lat - origin.latitude) < 0.02 && Math.abs(lng - origin.longitude) < 0.02;
      if (!isAtOrigin) {
        const dFromOrigin = haversineKm([origin.latitude, origin.longitude], [lat, lng]);
        const dToDest = haversineKm([lat, lng], [destination.latitude, destination.longitude]);
        const total = Math.max(1, dFromOrigin + dToDest);
        return Math.min(92, Math.max(18, Math.round((dFromOrigin / total) * 100)));
      }
    }

    if (shipment.current_status === ShipmentStatus.Created) return 0;
    if (shipment.current_status === ShipmentStatus.Booked) return 14;
    if (shipment.current_status === ShipmentStatus.PickedUp) return 28;
    return Math.max(35, Math.min(92, percent));
  }, [shipment.current_status, latestCoordEvent, origin, destination, percent]);

  return (
    <div className="py-1">
      <div className="flex items-center justify-between text-xs">
        <span className="font-medium text-ink-2">
          Corridor progress ({effectivePercent}%)
          {transitStops.length > 0 && (
            <span className="ml-1.5 font-mono text-[11px] font-semibold text-ink">
              · {transitStops.length} transit {transitStops.length === 1 ? 'stop' : 'stops'}
            </span>
          )}
        </span>
        <span className="font-mono text-xs font-medium text-ink tabular-nums">{label}</span>
      </div>

      {/* Crisp 2px solid progress line with intermediate checkpoint nodes */}
      <div className="relative my-3 flex items-center">
        <span
          title={`Origin: ${origin.code} · ${origin.city}`}
          className="z-10 size-2.5 rounded-full border border-[#080B0C] bg-white dark:border-white dark:bg-[#080B0C]"
        />
        <div className="relative mx-1 h-[2.5px] flex-1 bg-line-strong">
          <div
            className={`absolute inset-y-0 left-0 ${
              shipment.current_status === ShipmentStatus.Delivered
                ? 'bg-emerald-600 dark:bg-emerald-400'
                : delayed
                ? 'bg-amber-500 dark:bg-amber-400'
                : 'bg-[#080B0C] dark:bg-[#C6F432]'
            }`}
            style={{ width: `${effectivePercent}%` }}
          />
          {/* Intermediate Transit Checkpoint Nodes along the corridor */}
          {transitStops.map((stop) => (
            <span
              key={stop.id}
              title={`Checkpoint: ${stop.name}`}
              className="absolute top-1/2 z-10 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#080B0C] bg-[#C6F432]"
              style={{ left: `${stop.percentAlong}%` }}
            />
          ))}
          {/* Current Position Indicator */}
          <span
            className="absolute top-1/2 z-20 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-[#080B0C] bg-[#C6F432] shadow-sm"
            style={{ left: `${effectivePercent}%` }}
          />
        </div>
        <span
          title={`Destination: ${destination.code} · ${destination.city}`}
          className="z-10 size-2.5 rounded-full bg-ink-2"
        />
      </div>

      <div className="flex items-baseline justify-between text-xs">
        <span className="font-mono font-bold text-ink">
          {origin.code} <span className="font-sans font-medium text-ink-2">· {origin.city}</span>
        </span>
        <span className="font-mono font-bold text-ink">
          <span className="font-sans font-medium text-ink-2">{destination.city} · </span>
          {destination.code}
        </span>
      </div>

      {/* Explicit Multi-Stop Route Chain: Origin -> Checkpoint(s) -> Destination */}
      {transitStops.length > 0 && (
        <div className="mt-2 rounded-md border border-line bg-surface px-2.5 py-1.5">
          <div className="text-[10px] font-semibold uppercase tracking-wider text-ink-3">
            Active Transit Route (Origin → Checkpoint → Destination)
          </div>
          <div className="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
            <span className="inline-flex items-center gap-1 rounded border border-line bg-base px-1.5 py-0.5 font-mono text-[11px] font-bold text-ink">
              {origin.code}
              <span className="font-sans font-normal text-ink-2">{origin.city}</span>
            </span>
            {transitStops.map((stop) => (
              <React.Fragment key={stop.id}>
                <span className="font-mono text-xs font-bold text-ink-2">━━▶</span>
                <span className="inline-flex items-center gap-1 rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-mono text-[11px] font-bold text-[#080B0C]">
                  ● {stop.name}
                </span>
              </React.Fragment>
            ))}
            <span className="font-mono text-xs font-bold text-ink-3">╌╌▶</span>
            <span className="inline-flex items-center gap-1 rounded border border-line bg-base px-1.5 py-0.5 font-mono text-[11px] font-bold text-ink">
              {destination.code}
              <span className="font-sans font-normal text-ink-2">{destination.city}</span>
            </span>
          </div>
        </div>
      )}

      <div className="mt-1.5 flex items-baseline justify-between font-mono text-[11px] font-medium text-ink-3 tabular-nums">
        <span>Dep {formatDateTime(shipment.expected_departure_at)}</span>
        <span className="text-right">Arr {formatDateTime(shipment.expected_arrival_at)}</span>
      </div>
    </div>
  );
};

