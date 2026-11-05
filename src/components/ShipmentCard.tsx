import React from 'react';
import { ArrowUpRight } from 'lucide-react';
import { Carrier, Location, Shipment, ShipmentStatus } from '../domain/types';
import {
  computeRouteProgress,
  isShipmentDelayed,
  plannedDurationHours,
  statusLabel,
} from '../domain/rules';
import { formatShortDate, useStore } from '../domain/store';

function statusDotColor(status: ShipmentStatus, delayed: boolean): string {
  if (status === ShipmentStatus.Delivered) return 'bg-emerald-600 dark:bg-emerald-400';
  if (delayed) return 'bg-amber-600 dark:bg-amber-400';
  if (status === ShipmentStatus.InTransit) return 'bg-[#080B0C] dark:bg-[#C6F432]';
  if (status === ShipmentStatus.PickedUp) return 'bg-teal-600 dark:bg-teal-400';
  return 'bg-ink-3';
}

function modeText(type: Carrier['service_type']): string {
  if (type === 'SEA') return 'Sea';
  if (type === 'ROAD') return 'Road';
  return 'Air';
}

export const ShipmentCard: React.FC<{
  shipment: Shipment;
  origin: Location;
  destination: Location;
  carrier: Carrier;
  lastCheckpointName?: string | null;
  lastCheckpointTime?: string | null;
  selected?: boolean;
  hasOpenException?: boolean;
  onSelect: () => void;
  onOpenDetail?: () => void;
}> = ({
  shipment,
  origin,
  destination,
  carrier,
  lastCheckpointName,
  lastCheckpointTime,
  selected = false,
  hasOpenException = false,
  onSelect,
  onOpenDetail,
}) => {
  const { assessShipmentDelay } = useStore();
  const delayed = isShipmentDelayed(shipment.expected_arrival_at, shipment.estimated_arrival_at);
  const assessment = assessShipmentDelay(shipment);
  const durationHours = plannedDurationHours(
    shipment.expected_departure_at,
    shipment.expected_arrival_at,
  );
  const { percent } = computeRouteProgress(
    shipment.expected_departure_at,
    shipment.expected_arrival_at,
  );

  const hasTransitCheckpoint =
    Boolean(lastCheckpointName) &&
    !lastCheckpointName!.toLowerCase().includes(origin.city.toLowerCase()) &&
    !lastCheckpointName!.toLowerCase().includes(origin.name.toLowerCase()) &&
    !lastCheckpointName!.toLowerCase().includes(destination.city.toLowerCase());

  const effectivePercent =
    shipment.current_status === ShipmentStatus.Delivered
      ? 100
      : hasTransitCheckpoint
      ? Math.max(42, Math.min(85, percent || 45))
      : shipment.current_status === ShipmentStatus.Created
      ? 0
      : shipment.current_status === ShipmentStatus.Booked
      ? 14
      : shipment.current_status === ShipmentStatus.PickedUp
      ? 28
      : Math.max(35, Math.min(92, percent));

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={onSelect}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onSelect();
        }
      }}
      className={`group relative cursor-pointer border-b border-line px-4 py-3.5 transition-colors ${
        selected
          ? 'bg-[#F4FCE3] dark:bg-surface'
          : 'bg-base hover:bg-surface'
      }`}
    >
      {/* Left active indicator bar */}
      {selected && (
        <span className="absolute inset-y-0 left-0 w-1.5 bg-[#080B0C] dark:bg-[#C6F432]" />
      )}

      {/* Row 1: Reference & Cargo on left; Status dot + plain text on right */}
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-baseline gap-2 min-w-0">
          <span className="font-mono text-[13px] font-bold text-ink">
            {shipment.shipment_number}
          </span>
          <span className="truncate text-xs font-medium text-ink-2">
            {shipment.cargo_type}
          </span>
        </div>

        <div className="flex items-center gap-2 shrink-0 text-xs">
          {hasOpenException && (
            <span className="font-mono text-[11px] font-semibold text-rose-700 dark:text-rose-400">
              Exception
            </span>
          )}
          {delayed && (
            <span className="font-mono text-[11px] font-semibold text-amber-700 dark:text-amber-400 tabular-nums">
              +{assessment.delay_hours.toFixed(0)}h
            </span>
          )}
          <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-ink">
            <span
              className={`size-2 rounded-full ${statusDotColor(
                shipment.current_status,
                delayed,
              )}`}
            />
            <span>{statusLabel(shipment.current_status)}</span>
          </span>
        </div>
      </div>

      {/* Row 2: Flat Route Strip */}
      <div className="mt-3 grid grid-cols-[minmax(0,1fr)_110px_minmax(0,1fr)] items-center gap-3">
        <div className="min-w-0">
          <div className="flex items-baseline gap-1.5">
            <span className="font-mono text-xs font-bold text-ink">{origin.code}</span>
            <span className="truncate text-xs font-medium text-ink-2">{origin.city}</span>
          </div>
        </div>

        {/* Clean 2px Hairline Route Connector */}
        <div className="flex flex-col items-center">
          <div className="mb-1 flex items-center gap-1 font-mono text-[10px] font-medium text-ink-3 tabular-nums">
            <span>{modeText(carrier.service_type)}</span>
            <span>·</span>
            <span>{durationHours}h</span>
          </div>
          <div className="relative flex w-full items-center">
            <span className="size-1.5 shrink-0 rounded-full bg-ink-2" />
            <div className="relative mx-1 h-[2px] flex-1 bg-line-strong">
              <div
                className={`h-full ${
                  shipment.current_status === ShipmentStatus.Delivered
                    ? 'bg-emerald-600 dark:bg-emerald-400'
                    : delayed
                    ? 'bg-amber-600 dark:bg-amber-400'
                    : 'bg-[#080B0C] dark:bg-[#C6F432]'
                }`}
                style={{ width: `${effectivePercent}%` }}
              />
              {hasTransitCheckpoint && shipment.current_status !== ShipmentStatus.Delivered && (
                <span
                  className="absolute top-1/2 z-10 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#080B0C] bg-[#C6F432]"
                  style={{ left: `${effectivePercent}%` }}
                />
              )}
            </div>
            <span
              className={`size-1.5 shrink-0 rounded-full ${
                shipment.current_status === ShipmentStatus.Delivered
                  ? 'bg-emerald-600 dark:bg-emerald-400'
                  : 'bg-ink-2'
              }`}
            />
          </div>
        </div>

        <div className="min-w-0 text-right">
          <div className="flex items-baseline justify-end gap-1.5">
            <span className="truncate text-xs font-medium text-ink-2">{destination.city}</span>
            <span className="font-mono text-xs font-bold text-ink">{destination.code}</span>
          </div>
        </div>
      </div>

      {/* Row 3: Last Recorded Checkpoint & Multi-Stop Route Chain */}
      {lastCheckpointName && (
        <div className="mt-2 flex items-center justify-between gap-2 text-[11px] text-ink-3">
          <span className="truncate">
            {hasTransitCheckpoint ? (
              <>
                Route:{' '}
                <span className="font-mono font-bold text-ink">{origin.code}</span>
                <span className="mx-1 text-ink-2">→</span>
                <span className="rounded bg-[#C6F432]/70 px-1 py-0.5 font-semibold text-[#080B0C]">
                  {lastCheckpointName}
                </span>
                <span className="mx-1 text-ink-2">→</span>
                <span className="font-mono font-bold text-ink">{destination.code}</span>
              </>
            ) : (
              <>
                Last checkpoint: <span className="font-semibold text-ink">{lastCheckpointName}</span>
              </>
            )}
          </span>
          {lastCheckpointTime && (
            <span className="font-mono text-[10px] font-medium text-ink-2 shrink-0 tabular-nums">
              {lastCheckpointTime}
            </span>
          )}
        </div>
      )}

      <div className="mt-2 flex items-center justify-between gap-2 text-xs text-ink-2">
        <span className="truncate font-medium">
          {carrier.name} <span className="font-mono text-[11px] text-ink-3">({carrier.code})</span>
        </span>

        <div className="flex items-center gap-3 shrink-0">
          <span className="font-mono text-[11px] font-semibold text-ink tabular-nums">
            ETA {formatShortDate(shipment.estimated_arrival_at ?? shipment.expected_arrival_at)}
          </span>
          {onOpenDetail && (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                onOpenDetail();
              }}
              aria-label={`Open detail for ${shipment.shipment_number}`}
              className="inline-flex items-center gap-0.5 text-xs font-semibold text-ink transition-colors hover:underline cursor-pointer"
            >
              <span>Inspect</span>
              <ArrowUpRight className="size-3.5" strokeWidth={1.75} />
            </button>
          )}
        </div>
      </div>
    </div>
  );
};
