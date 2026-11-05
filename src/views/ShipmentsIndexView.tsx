import React, { useMemo, useState } from 'react';
import { ArrowUpRight, Filter, ListFilter, Map as MapIcon, Search, Table } from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import { ProgressRoute } from '../components/ProgressRoute';
import { ShipmentCard } from '../components/ShipmentCard';
import { MapShipmentEntry, WorldMap } from '../components/WorldMap';
import {
  delayTone,
  isShipmentDelayed,
  statusLabel,
  statusTone,
} from '../domain/rules';
import { useStore } from '../domain/store';
import { ExceptionStatus, ShipmentStatus } from '../domain/types';

export const ShipmentsIndexView: React.FC = () => {
  const {
    state,
    visibleShipments,
    route,
    navigate,
    assessShipmentDelay,
    formatDateTime,
    formatShortDate,
  } = useStore();

  const q = route.query.q || '';
  const statusFilter = route.query.status || '';
  const delayedFilter = route.query.delayed === '1';
  const exceptionsFilter = route.query.exceptions === '1';
  const carrierFilter = route.query.carrier_id || '';
  const originFilter = route.query.origin_id || '';
  const destinationFilter = route.query.destination_id || '';
  const dateFrom = route.query.date_from || '';
  const dateTo = route.query.date_to || '';
  const sortBy = route.query.sort || 'latest';

  const [viewMode, setViewMode] = useState<'split' | 'table'>('split');
  const [showFilters, setShowFilters] = useState<boolean>(false);
  const [selectedId, setSelectedId] = useState<number>(0);
  const [currentPage, setCurrentPage] = useState<number>(1);
  const pageSize = 10;

  const setQueryParam = (updates: Record<string, string>) => {
    const next = { ...route.query, ...updates };
    Object.keys(next).forEach((k) => {
      if (!next[k]) delete next[k];
    });
    navigate('/shipments', next);
    setCurrentPage(1);
  };

  const filteredShipments = useMemo(() => {
    const list = visibleShipments.filter((s) => {
      const origin = state.locations.find((l) => l.id === s.origin_location_id);
      const dest = state.locations.find((l) => l.id === s.destination_location_id);
      const carrier = state.carriers.find((c) => c.id === s.carrier_id);
      const hasOpenExc = state.exceptions.some(
        (e) => e.shipment_id === s.id && e.status !== ExceptionStatus.Resolved,
      );
      const delayed = isShipmentDelayed(s.expected_arrival_at, s.estimated_arrival_at);

      if (q.trim()) {
        const needle = q.trim().toLowerCase();
        const match =
          s.shipment_number.toLowerCase().includes(needle) ||
          s.cargo_type.toLowerCase().includes(needle) ||
          (origin &&
            (origin.city.toLowerCase().includes(needle) ||
              origin.code.toLowerCase().includes(needle) ||
              origin.name.toLowerCase().includes(needle))) ||
          (dest &&
            (dest.city.toLowerCase().includes(needle) ||
              dest.code.toLowerCase().includes(needle) ||
              dest.name.toLowerCase().includes(needle))) ||
          (carrier &&
            (carrier.name.toLowerCase().includes(needle) ||
              carrier.code.toLowerCase().includes(needle)));
        if (!match) return false;
      }

      if (statusFilter && s.current_status !== statusFilter) return false;
      if (delayedFilter && !delayed) return false;
      if (exceptionsFilter && !hasOpenExc) return false;
      if (carrierFilter && s.carrier_id !== Number(carrierFilter)) return false;
      if (originFilter && s.origin_location_id !== Number(originFilter)) return false;
      if (destinationFilter && s.destination_location_id !== Number(destinationFilter)) {
        return false;
      }

      if (dateFrom) {
        const depMs = new Date(s.expected_departure_at).getTime();
        if (depMs < new Date(dateFrom).getTime()) return false;
      }
      if (dateTo) {
        const depMs = new Date(s.expected_departure_at).getTime();
        if (depMs > new Date(dateTo).getTime() + 86400000) return false;
      }

      return true;
    });

    return [...list].sort((a, b) => {
      if (sortBy === 'eta_asc') {
        return (
          new Date(a.estimated_arrival_at ?? a.expected_arrival_at).getTime() -
          new Date(b.estimated_arrival_at ?? b.expected_arrival_at).getTime()
        );
      }
      if (sortBy === 'eta_desc') {
        return (
          new Date(b.estimated_arrival_at ?? b.expected_arrival_at).getTime() -
          new Date(a.estimated_arrival_at ?? a.expected_arrival_at).getTime()
        );
      }
      if (sortBy === 'ref_asc') {
        return a.shipment_number.localeCompare(b.shipment_number);
      }
      if (sortBy === 'weight_desc') {
        return b.weight_kg - a.weight_kg;
      }
      return b.id - a.id;
    });
  }, [
    visibleShipments,
    state.locations,
    state.carriers,
    state.exceptions,
    q,
    statusFilter,
    delayedFilter,
    exceptionsFilter,
    carrierFilter,
    originFilter,
    destinationFilter,
    dateFrom,
    dateTo,
    sortBy,
  ]);

  const activeFocusId = useMemo(() => {
    if (selectedId && filteredShipments.some((s) => s.id === selectedId)) {
      return selectedId;
    }
    const inTransit = filteredShipments.find(
      (s) => s.current_status === ShipmentStatus.InTransit,
    );
    return inTransit?.id ?? filteredShipments[0]?.id ?? 0;
  }, [selectedId, filteredShipments]);

  const focusedShipment = useMemo(
    () => filteredShipments.find((s) => s.id === activeFocusId) ?? null,
    [filteredShipments, activeFocusId],
  );

  const mapEntries: MapShipmentEntry[] = useMemo(() => {
    return filteredShipments.map((s) => {
      const origin = state.locations.find((l) => l.id === s.origin_location_id);
      const dest = state.locations.find((l) => l.id === s.destination_location_id);
      const carrier = state.carriers.find((c) => c.id === s.carrier_id);
      const events = state.trackingEvents
        .filter((e) => e.shipment_id === s.id && e.latitude !== null && e.longitude !== null)
        .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());

      const transitEvents = events.filter((ev) => {
        const latVal = ev.latitude!;
        const lngVal = ev.longitude!;
        const atOrigin =
          origin &&
          Math.abs(latVal - origin.latitude) < 0.02 &&
          Math.abs(lngVal - origin.longitude) < 0.02;
        const atDest =
          dest &&
          Math.abs(latVal - dest.latitude) < 0.02 &&
          Math.abs(lngVal - dest.longitude) < 0.02;
        return !atOrigin && !atDest;
      });

      const latest =
        s.current_status !== ShipmentStatus.Delivered && transitEvents.length > 0
          ? transitEvents[0]
          : events[0];
      const previous = events[1];

      return {
        id: s.id,
        shipment_number: s.shipment_number,
        status: s.current_status,
        carrier_name: carrier?.name,
        service_type: carrier?.service_type,
        delayed: isShipmentDelayed(s.expected_arrival_at, s.estimated_arrival_at),
        origin: origin
          ? {
              latitude: origin.latitude,
              longitude: origin.longitude,
              location_name: `${origin.code} · ${origin.city}`,
              code: origin.code,
              time: `Dep: ${formatDateTime(s.expected_departure_at)}`,
            }
          : null,
        destination: dest
          ? {
              latitude: dest.latitude,
              longitude: dest.longitude,
              location_name: `${dest.code} · ${dest.city}`,
              code: dest.code,
              time: `ETA: ${formatDateTime(s.estimated_arrival_at ?? s.expected_arrival_at)}`,
            }
          : null,
        current_position: latest
          ? {
              latitude: latest.latitude!,
              longitude: latest.longitude!,
              location_name: latest.location_name,
              time: formatDateTime(latest.occurred_at),
            }
          : origin
          ? {
              latitude: origin.latitude,
              longitude: origin.longitude,
              location_name: origin.name,
            }
          : null,
        previous_position: previous
          ? { latitude: previous.latitude!, longitude: previous.longitude! }
          : origin
          ? { latitude: origin.latitude, longitude: origin.longitude }
          : null,
        checkpoints: transitEvents.map((ev) => ({
          latitude: ev.latitude!,
          longitude: ev.longitude!,
          location_name: ev.location_name,
          time: formatDateTime(ev.occurred_at),
        })),
      };
    });
  }, [filteredShipments, state.locations, state.carriers, state.trackingEvents, formatDateTime]);

  const isAllTab = !statusFilter && !delayedFilter && !exceptionsFilter;
  const totalPages = Math.max(1, Math.ceil(filteredShipments.length / pageSize));
  const paginatedShipments = filteredShipments.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize,
  );

  const tabClass = (active: boolean) =>
    `rounded px-2.5 py-1 text-xs transition-colors whitespace-nowrap cursor-pointer ${
      active
        ? 'bg-[#080B0C] text-[#FFFFFF] dark:bg-[#C6F432] dark:text-[#080B0C] font-semibold'
        : 'font-medium text-ink-2 hover:bg-tint hover:text-ink'
    }`;

  return (
    <AppLayout
      title="Shipments"
      subtitle={`${filteredShipments.length} active shipments monitored`}
    >
      <div className="flex h-[calc(100vh-56px)] flex-col overflow-hidden bg-base">
        {/* Top Filter & View Control Bar */}
        <div className="shrink-0 border-b border-line bg-base px-5 py-2.5">
          <div className="flex flex-wrap items-center justify-between gap-2.5">
            <div className="flex flex-wrap items-center gap-2">
              <div className="relative w-52 xl:w-64">
                <Search
                  className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-ink-3"
                  strokeWidth={1.75}
                />
                <input
                  type="search"
                  value={q}
                  onChange={(e) => setQueryParam({ q: e.target.value })}
                  placeholder="Search reference, port, carrier..."
                  aria-label="Search shipments"
                  className="h-8 w-full rounded-md border border-line bg-surface pl-8 pr-3 text-xs font-medium text-ink placeholder:text-ink-3 focus:border-line-strong focus:outline-none"
                />
              </div>

              {/* Segmented Filter Tabs */}
              <div className="flex items-center gap-0.5 rounded-md border border-line bg-surface p-0.5">
                <button
                  type="button"
                  onClick={() =>
                    setQueryParam({ status: '', delayed: '', exceptions: '' })
                  }
                  className={tabClass(isAllTab)}
                >
                  All{' '}
                  <span
                    className={`font-mono text-[11px] font-semibold ${
                      isAllTab
                        ? 'text-[#C6F432] dark:text-[#080B0C]'
                        : 'text-ink-3'
                    }`}
                  >
                    {visibleShipments.length}
                  </span>
                </button>
                <button
                  type="button"
                  onClick={() =>
                    setQueryParam({
                      status: ShipmentStatus.InTransit,
                      delayed: '',
                      exceptions: '',
                    })
                  }
                  className={tabClass(statusFilter === ShipmentStatus.InTransit)}
                >
                  In transit
                </button>
                <button
                  type="button"
                  onClick={() =>
                    setQueryParam({ status: '', delayed: '1', exceptions: '' })
                  }
                  className={tabClass(delayedFilter)}
                >
                  Delayed
                </button>
                <button
                  type="button"
                  onClick={() =>
                    setQueryParam({ status: '', delayed: '', exceptions: '1' })
                  }
                  className={tabClass(exceptionsFilter)}
                >
                  Exceptions
                </button>
                <button
                  type="button"
                  onClick={() =>
                    setQueryParam({
                      status: ShipmentStatus.Delivered,
                      delayed: '',
                      exceptions: '',
                    })
                  }
                  className={tabClass(statusFilter === ShipmentStatus.Delivered)}
                >
                  Delivered
                </button>
              </div>

              <button
                type="button"
                onClick={() => setShowFilters((s) => !s)}
                className={`inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs transition-colors cursor-pointer ${
                  showFilters
                    ? 'border-line-strong bg-tint-strong font-medium text-ink'
                    : 'border-line bg-surface font-normal text-ink-2 hover:text-ink'
                }`}
              >
                <Filter className="size-3.5" strokeWidth={1.5} />
                Filters
              </button>
            </div>

            {/* Right: Sort & View Mode Switcher */}
            <div className="flex items-center gap-2">
              <div className="flex items-center gap-1.5">
                <ListFilter className="size-3.5 text-ink-3" strokeWidth={1.5} />
                <select
                  aria-label="Sort shipments"
                  value={sortBy}
                  onChange={(e) => setQueryParam({ sort: e.target.value })}
                  className="h-8 rounded-md border border-line bg-surface px-2.5 text-xs text-ink focus:border-line-strong focus:outline-none"
                >
                  <option value="latest">Sort: Newest first</option>
                  <option value="eta_asc">Sort: ETA earliest</option>
                  <option value="eta_desc">Sort: ETA latest</option>
                  <option value="ref_asc">Sort: Reference A–Z</option>
                  <option value="weight_desc">Sort: Weight high–low</option>
                </select>
              </div>

              <div className="flex items-center rounded-md border border-line bg-surface p-0.5">
                <button
                  type="button"
                  onClick={() => setViewMode('split')}
                  className={`inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs transition-colors cursor-pointer ${
                    viewMode === 'split'
                      ? 'bg-[#080B0C] text-[#FFFFFF] dark:bg-[#C6F432] dark:text-[#080B0C] font-semibold'
                      : 'text-ink-2 hover:text-ink'
                  }`}
                >
                  <MapIcon className="size-3.5" strokeWidth={1.5} />
                  Split map
                </button>
                <button
                  type="button"
                  onClick={() => setViewMode('table')}
                  className={`inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs transition-colors cursor-pointer ${
                    viewMode === 'table'
                      ? 'bg-[#080B0C] text-[#FFFFFF] dark:bg-[#C6F432] dark:text-[#080B0C] font-semibold'
                      : 'text-ink-2 hover:text-ink'
                  }`}
                >
                  <Table className="size-3.5" strokeWidth={1.5} />
                  Table
                </button>
              </div>
            </div>
          </div>

          {/* Expandable Filters: Status, Carrier, Origin, Destination, Date Range */}
          {showFilters && (
            <div className="mt-2.5 grid grid-cols-2 gap-3 border-t border-line pt-2.5 sm:grid-cols-3 lg:grid-cols-6">
              <div>
                <label className="mb-1 block text-xs text-ink-3">
                  Lifecycle status
                </label>
                <select
                  value={statusFilter}
                  onChange={(e) => setQueryParam({ status: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-surface px-2 text-xs text-ink"
                >
                  <option value="">All statuses</option>
                  {Object.values(ShipmentStatus).map((st) => (
                    <option key={st} value={st}>
                      {statusLabel(st)}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1 block text-xs text-ink-3">
                  Carrier
                </label>
                <select
                  value={carrierFilter}
                  onChange={(e) => setQueryParam({ carrier_id: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-surface px-2 text-xs text-ink"
                >
                  <option value="">All carriers</option>
                  {state.carriers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} ({c.code})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1 block text-xs text-ink-3">
                  Origin port
                </label>
                <select
                  value={originFilter}
                  onChange={(e) => setQueryParam({ origin_id: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-surface px-2 text-xs text-ink"
                >
                  <option value="">All origins</option>
                  {state.locations.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.code} — {l.city}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1 block text-xs text-ink-3">
                  Destination port
                </label>
                <select
                  value={destinationFilter}
                  onChange={(e) => setQueryParam({ destination_id: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-surface px-2 text-xs text-ink"
                >
                  <option value="">All destinations</option>
                  {state.locations.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.code} — {l.city}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1 block text-xs text-ink-3">
                  Departure from
                </label>
                <input
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setQueryParam({ date_from: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-surface px-2 text-xs text-ink"
                />
              </div>

              <div>
                <label className="mb-1 block text-xs text-ink-3">
                  Departure to
                </label>
                <input
                  type="date"
                  value={dateTo}
                  onChange={(e) => setQueryParam({ date_to: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-surface px-2 text-xs text-ink"
                />
              </div>
            </div>
          )}
        </div>

        {/* Main View Area */}
        {viewMode === 'split' ? (
          <div className="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[410px_1fr]">
            {/* Left Feed: Flat Hairline-Divided Shipment List */}
            <section className="flex min-h-0 flex-col border-r border-line bg-base">
              <div className="flex items-center justify-between border-b border-line px-4 py-2 text-xs">
                <span className="font-medium text-ink-2">
                  Tracking feed <span className="font-mono text-ink-3">({filteredShipments.length})</span>
                </span>
                <span className="text-ink-3">
                  Select a row to focus corridor
                </span>
              </div>

              <div className="flex-1 overflow-y-auto">
                {filteredShipments.length === 0 ? (
                  <div className="p-8 text-center">
                    <p className="text-sm font-medium text-ink">No shipments match filters</p>
                    <button
                      type="button"
                      onClick={() => navigate('/shipments')}
                      className="mt-2 text-xs font-medium text-ink-2 underline hover:text-ink cursor-pointer"
                    >
                      Clear all filters
                    </button>
                  </div>
                ) : (
                  filteredShipments.map((shipment) => {
                    const origin = state.locations.find(
                      (l) => l.id === shipment.origin_location_id,
                    )!;
                    const destination = state.locations.find(
                      (l) => l.id === shipment.destination_location_id,
                    )!;
                    const carrier = state.carriers.find((c) => c.id === shipment.carrier_id)!;
                    const hasOpenException = state.exceptions.some(
                      (e) =>
                        e.shipment_id === shipment.id && e.status !== ExceptionStatus.Resolved,
                    );
                    const latestEvent = state.trackingEvents
                      .filter((e) => e.shipment_id === shipment.id)
                      .sort(
                        (a, b) =>
                          new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime(),
                      )[0];

                    return (
                      <ShipmentCard
                        key={shipment.id}
                        shipment={shipment}
                        origin={origin}
                        destination={destination}
                        carrier={carrier}
                        lastCheckpointName={latestEvent?.location_name ?? null}
                        lastCheckpointTime={
                          latestEvent ? formatDateTime(latestEvent.occurred_at) : null
                        }
                        selected={shipment.id === activeFocusId}
                        hasOpenException={hasOpenException}
                        onSelect={() => setSelectedId(shipment.id)}
                        onOpenDetail={() => navigate(`/shipments/${shipment.id}`)}
                      />
                    );
                  })
                )}
              </div>
            </section>

            {/* Right Map: Cartographic Canvas + Clean Single-Elevation Inspector */}
            <section className="isolate relative z-0 flex min-h-[420px] flex-col bg-map-bg">
              <div className="relative flex-1">
                <WorldMap
                  shipments={mapEntries}
                  focusId={activeFocusId}
                  onSelectShipment={(id) => setSelectedId(id)}
                />
              </div>

              {/* Quiet Map Legend */}
              <div className="pointer-events-none absolute right-4 top-4 z-[400] flex flex-wrap items-center gap-4 rounded-md border border-line-strong bg-overlay px-3 py-1.5 text-xs font-medium text-ink shadow-md">
                <span className="inline-flex items-center gap-1.5">
                  <span className="inline-block h-[2.5px] w-4 bg-[#080B0C] dark:bg-[#C6F432]" />
                  Completed leg
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <span className="inline-block h-0 w-4 border-t-2 border-dashed border-ink-2" />
                  Remaining leg
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <span className="inline-block size-2 rounded-full bg-emerald-600 dark:bg-emerald-400" />
                  Delivered
                </span>
              </div>

              {/* Single-Elevation Active Shipment Overlay */}
              {focusedShipment && (() => {
                const origin = state.locations.find(
                  (l) => l.id === focusedShipment.origin_location_id,
                )!;
                const destination = state.locations.find(
                  (l) => l.id === focusedShipment.destination_location_id,
                )!;
                const carrier = state.carriers.find(
                  (c) => c.id === focusedShipment.carrier_id,
                )!;
                const delayInfo = assessShipmentDelay(focusedShipment);
                const delayed = isShipmentDelayed(
                  focusedShipment.expected_arrival_at,
                  focusedShipment.estimated_arrival_at,
                );

                return (
                  <div className="absolute bottom-4 left-4 right-4 z-[400] rounded-lg border border-line-strong bg-overlay p-4 shadow-2xl xl:left-auto xl:w-[440px]">
                    <div className="flex items-start justify-between gap-3 border-b border-line pb-3">
                      <div>
                        <span className="text-xs font-medium text-ink-3">
                          {carrier.name} · {carrier.service_type}
                        </span>
                        <h3 className="mt-0.5 font-mono text-sm font-bold text-ink">
                          {focusedShipment.shipment_number}{' '}
                          <span className="font-sans font-medium text-ink-2">
                            · {focusedShipment.cargo_type}
                          </span>
                        </h3>
                      </div>
                      <div className="flex items-center gap-1.5">
                        {delayed && (
                          <Badge tone={delayTone(delayInfo)}>
                            +{delayInfo.delay_hours.toFixed(0)}h delay
                          </Badge>
                        )}
                        <Badge tone={statusTone(focusedShipment.current_status)}>
                          {statusLabel(focusedShipment.current_status)}
                        </Badge>
                      </div>
                    </div>

                    <div className="py-2.5">
                      <ProgressRoute
                        shipment={focusedShipment}
                        origin={origin}
                        destination={destination}
                      />
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-line pt-3">
                      <div className="font-mono text-xs font-semibold text-ink tabular-nums">
                        {focusedShipment.weight_kg.toLocaleString()} kg · {focusedShipment.volume_m3} m³
                      </div>
                      <button
                        type="button"
                        onClick={() => navigate(`/shipments/${focusedShipment.id}`)}
                        className="inline-flex h-8 items-center gap-1.5 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink transition-colors hover:bg-brand-hover cursor-pointer"
                      >
                        Inspect shipment
                        <ArrowUpRight className="size-3.5" strokeWidth={1.75} />
                      </button>
                    </div>
                  </div>
                );
              })()}
            </section>
          </div>
        ) : (
          /* Full Table View with Sorting & Pagination */
          <div className="flex-1 overflow-y-auto p-6">
            <div className="mx-auto max-w-[1440px] overflow-hidden rounded-lg border border-line bg-surface">
              <div className="overflow-x-auto">
                <table className="w-full border-collapse text-left text-sm">
                  <thead>
                    <tr className="border-b border-line bg-base text-xs font-semibold text-ink-3">
                      <th className="px-4 py-3 font-semibold">Reference</th>
                      <th className="px-4 py-3 font-semibold">Status</th>
                      <th className="px-4 py-3 font-semibold">Route</th>
                      <th className="px-4 py-3 font-semibold">Carrier</th>
                      <th className="px-4 py-3 font-semibold">Cargo</th>
                      <th className="px-4 py-3 font-semibold">Arrival</th>
                      <th className="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-line">
                    {paginatedShipments.map((s) => {
                      const origin = state.locations.find(
                        (l) => l.id === s.origin_location_id,
                      )!;
                      const dest = state.locations.find(
                        (l) => l.id === s.destination_location_id,
                      )!;
                      const carrier = state.carriers.find((c) => c.id === s.carrier_id)!;
                      const delay = assessShipmentDelay(s);
                      const delayed = isShipmentDelayed(
                        s.expected_arrival_at,
                        s.estimated_arrival_at,
                      );

                      return (
                        <tr
                          key={s.id}
                          onClick={() => navigate(`/shipments/${s.id}`)}
                          className="cursor-pointer transition-colors hover:bg-raised"
                        >
                          <td className="px-4 py-3 font-mono text-xs font-bold text-ink">
                            {s.shipment_number}
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex flex-wrap items-center gap-1.5">
                              <Badge tone={statusTone(s.current_status)}>
                                {statusLabel(s.current_status)}
                              </Badge>
                              {delayed && (
                                <Badge tone={delayTone(delay)}>
                                  +{delay.delay_hours.toFixed(0)}h
                                </Badge>
                              )}
                            </div>
                          </td>
                          <td className="px-4 py-3 text-xs text-ink">
                            <span className="mr-1 font-mono font-bold text-ink">
                              {origin.code}
                            </span>
                            <span className="font-medium text-ink-2">{origin.city}</span>
                            <span className="mx-1.5 text-ink-3">→</span>
                            <span className="mr-1 font-mono font-bold text-ink">
                              {dest.code}
                            </span>
                            <span className="font-medium text-ink-2">{dest.city}</span>
                          </td>
                          <td className="px-4 py-3 text-xs font-medium text-ink-2">
                            {carrier.name}{' '}
                            <span className="font-mono text-ink-3">
                              ({carrier.service_type})
                            </span>
                          </td>
                          <td className="px-4 py-3 text-xs font-medium text-ink-2">
                            <div>{s.cargo_type}</div>
                            <div className="font-mono text-[11px] text-ink-3 tabular-nums">
                              {s.weight_kg.toLocaleString()} kg
                            </div>
                          </td>
                          <td className="px-4 py-3 font-mono text-xs tabular-nums">
                            <div className="text-ink-3">
                              Plan {formatShortDate(s.expected_arrival_at)}
                            </div>
                            <div
                              className={
                                delayed
                                  ? 'font-semibold text-amber-700 dark:text-amber-300'
                                  : 'font-semibold text-ink'
                              }
                            >
                              Est {formatShortDate(s.estimated_arrival_at ?? s.expected_arrival_at)}
                            </div>
                          </td>
                          <td className="px-4 py-3 text-right">
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation();
                                navigate(`/shipments/${s.id}`);
                              }}
                              className="text-xs font-semibold text-ink hover:underline cursor-pointer"
                            >
                              Inspect →
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              {/* Pagination Footer */}
              <div className="flex items-center justify-between border-t border-line bg-base px-5 py-3 text-xs text-ink-2">
                <span className="tabular-nums">
                  Showing{' '}
                  <span className="font-mono font-medium text-ink">
                    {filteredShipments.length === 0 ? 0 : (currentPage - 1) * pageSize + 1}
                  </span>{' '}
                  to{' '}
                  <span className="font-mono font-medium text-ink">
                    {Math.min(currentPage * pageSize, filteredShipments.length)}
                  </span>{' '}
                  of <span className="font-mono font-medium text-ink">{filteredShipments.length}</span>
                </span>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    disabled={currentPage <= 1}
                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                    className="rounded border border-line bg-surface px-2.5 py-1 text-xs font-medium text-ink disabled:opacity-40 cursor-pointer"
                  >
                    Previous
                  </button>
                  <span className="font-mono text-xs tabular-nums">
                    {currentPage} / {totalPages}
                  </span>
                  <button
                    type="button"
                    disabled={currentPage >= totalPages}
                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                    className="rounded border border-line bg-surface px-2.5 py-1 text-xs font-medium text-ink disabled:opacity-40 cursor-pointer"
                  >
                    Next
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
};
