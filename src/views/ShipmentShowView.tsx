import React, { useEffect, useMemo, useState } from 'react';
import {
  ArrowLeft,
  ArrowRight,
  Check,
  ChevronDown,
  CloudSun,
  Compass,
  Edit,
  Plus,
  Printer,
  Search,
  Trash2,
  X,
} from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import { ProgressRoute } from '../components/ProgressRoute';
import { MapShipmentEntry, WorldMap } from '../components/WorldMap';
import {
  allowedNextExceptionStatuses,
  allowedNextStatuses,
  delayTone,
  exceptionSeverityTone,
  exceptionStatusTone,
  exceptionTypeLabel,
  isShipmentDelayed,
  statusLabel,
  statusTone,
} from '../domain/rules';
import { PORT_HUB_PRESETS } from '../domain/indonesiaLocations';
import { useStore } from '../domain/store';
import {
  ExceptionSeverity,
  ExceptionStatus,
  ExceptionType,
  ShipmentStatus,
  UserRole,
} from '../domain/types';

interface WeatherReading {
  temperatureC: number;
  windSpeedKmh: number;
  conditionLabel: string;
}

interface TransitHubPreset {
  name: string;
  region: string;
  latitude: number;
  longitude: number;
}

const TRANSIT_HUB_PRESETS: TransitHubPreset[] = [
  { name: 'Port of Singapore · PSA Keppel', region: 'Singapore Strait', latitude: 1.2644, longitude: 103.8405 },
  { name: 'Colombo Transshipment Hub', region: 'Sri Lanka · Indian Ocean', latitude: 6.9531, longitude: 79.8506 },
  { name: 'Port of Tanjung Pelepas (PTP)', region: 'Johor · Malaysia', latitude: 1.3624, longitude: 103.5475 },
  { name: 'Jebel Ali Logistics Hub · Dubai', region: 'UAE · Persian Gulf', latitude: 25.0112, longitude: 55.0616 },
  { name: 'Bab el-Mandeb · Port of Aden', region: 'Yemen · Red Sea Entry', latitude: 12.7895, longitude: 44.9854 },
  { name: 'Suez Canal · Port Said Transit', region: 'Egypt · Mediterranean Entry', latitude: 31.2653, longitude: 32.3019 },
  { name: 'Port of Piraeus · Athens', region: 'Greece · Eastern Med', latitude: 37.9421, longitude: 23.6371 },
  { name: 'Strait of Gibraltar · Algeciras', region: 'Spain · Atlantic Gateway', latitude: 36.1315, longitude: -5.4375 },
  { name: 'Port of Rotterdam · Maasvlakte', region: 'Netherlands · North Sea', latitude: 51.9545, longitude: 4.1292 },
  { name: 'Port of Hamburg · Waltershof', region: 'Germany · Elbe Terminal', latitude: 53.5322, longitude: 9.9186 },
  { name: 'Port of Antwerp-Bruges', region: 'Belgium · Scheldt Hub', latitude: 51.2663, longitude: 4.3712 },
  { name: 'Port of Vladivostok (VVO)', region: 'Russia · Pacific Hub', latitude: 43.1056, longitude: 131.8735 },
  { name: 'Port of Saint Petersburg (LED)', region: 'Russia · Baltic Sea', latitude: 59.8897, longitude: 30.2144 },
  { name: 'Port of Novorossiysk (NVR)', region: 'Russia · Black Sea', latitude: 44.7239, longitude: 37.7686 },
];

function decodeWmoCode(code: number): string {
  if (code === 0) return 'Clear sky';
  if (code <= 3) return 'Partly cloudy';
  if (code <= 49) return 'Coastal mist';
  if (code <= 69) return 'Rain squalls';
  if (code <= 79) return 'Snow / sleet';
  if (code <= 84) return 'Heavy showers';
  return 'Marine gale';
}

function toInputDateTimeUtc(iso: string | null | undefined, addHours = 0): string {
  const baseMs = iso ? new Date(iso).getTime() : Date.now();
  const safeMs = Number.isNaN(baseMs) ? Date.now() : baseMs + addHours * 3600 * 1000;
  const d = new Date(safeMs);
  const yyyy = d.getUTCFullYear();
  const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(d.getUTCDate()).padStart(2, '0');
  const hh = String(d.getUTCHours()).padStart(2, '0');
  const min = String(d.getUTCMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
}

export const ShipmentShowView: React.FC<{ shipmentId: number }> = ({ shipmentId }) => {
  const {
    state,
    currentUser,
    navigate,
    assessShipmentDelay,
    addTrackingEvent,
    createException,
    updateExceptionStatus,
    archiveShipment,
    formatDateTime,
  } = useStore();

  const shipment = state.shipments.find((s) => s.id === shipmentId && !s.archived_at);
  const canOperate =
    currentUser?.role === UserRole.Admin || currentUser?.role === UserRole.Operator;

  const [showEventForm, setShowEventForm] = useState<boolean>(false);
  const [showExceptionForm, setShowExceptionForm] = useState<boolean>(false);

  const [eventType, setEventType] = useState<string>('CHECKPOINT');
  const [occurredAt, setOccurredAt] = useState<string>('2026-11-05T10:00');
  const [locationName, setLocationName] = useState<string>('');
  const [lat, setLat] = useState<string>('');
  const [lng, setLng] = useState<string>('');
  const [eventDesc, setEventDesc] = useState<string>('');
  const [eventEta, setEventEta] = useState<string>('');
  const [eventErrors, setEventErrors] = useState<Record<string, string>>({});
  const [hubMenuOpen, setHubMenuOpen] = useState<boolean>(false);
  const [geocodeStatus, setGeocodeStatus] = useState<{
    kind: 'idle' | 'loading' | 'ok' | 'empty' | 'error';
    message: string;
  }>({ kind: 'idle', message: '' });

  const [excType, setExcType] = useState<ExceptionType>(ExceptionType.CustomsHold);
  const [excSeverity, setExcSeverity] = useState<ExceptionSeverity>(ExceptionSeverity.Medium);
  const [excDetectedAt, setExcDetectedAt] = useState<string>('2026-11-05T11:00');
  const [excLocation, setExcLocation] = useState<string>('');
  const [excDesc, setExcDesc] = useState<string>('');
  const [excEta, setExcEta] = useState<string>('');
  const [excErrors, setExcErrors] = useState<Record<string, string>>({});
  const [confirmDelete, setConfirmDelete] = useState<boolean>(false);
  const [showBillModal, setShowBillModal] = useState<boolean>(false);

  // External API #2: Open-Meteo Live Weather Telemetry
  const [weather, setWeather] = useState<{
    origin?: WeatherReading;
    current?: WeatherReading;
    destination?: WeatherReading;
    source: 'live' | 'fallback';
    loading: boolean;
  }>({ source: 'live', loading: true });

  const events = useMemo(() => {
    if (!shipment) return [];
    return state.trackingEvents
      .filter((e) => e.shipment_id === shipment.id)
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());
  }, [shipment?.id, state.trackingEvents]);

  // Combine system port locations, Indonesian Kabupaten/Provinsi/Port catalog, and maritime/air transit hubs
  const allHubSuggestions = useMemo(() => {
    const portItems: TransitHubPreset[] = state.locations.map((loc) => ({
      name: `${loc.name} (${loc.code})`,
      region: `${loc.city}, ${loc.country}`,
      latitude: loc.latitude,
      longitude: loc.longitude,
    }));
    const catalogItems: TransitHubPreset[] = PORT_HUB_PRESETS.map((p) => ({
      name: `${p.name} (${p.code})`,
      region: `${p.city}, ${p.country}`,
      latitude: p.latitude,
      longitude: p.longitude,
    }));
    const seen = new Set<string>();
    const combined: TransitHubPreset[] = [];
    for (const item of [...TRANSIT_HUB_PRESETS, ...portItems, ...catalogItems]) {
      const key = item.name.toLowerCase();
      if (!seen.has(key)) {
        seen.add(key);
        combined.push(item);
      }
    }
    return combined;
  }, [state.locations]);

  const filteredHubSuggestions = useMemo(() => {
    const q = locationName.trim().toLowerCase();
    if (!q) return allHubSuggestions.slice(0, 14);
    return allHubSuggestions
      .filter(
        (h) =>
          h.name.toLowerCase().includes(q) || h.region.toLowerCase().includes(q),
      )
      .slice(0, 14);
  }, [locationName, allHubSuggestions]);

  useEffect(() => {
    if (!shipment) return;
    let cancelled = false;

    const origin = state.locations.find((l) => l.id === shipment.origin_location_id);
    const dest = state.locations.find((l) => l.id === shipment.destination_location_id);
    const eventsWithCoords = events.filter(
      (e) => e.latitude !== null && e.longitude !== null,
    );

    const curLat = eventsWithCoords[0]?.latitude ?? origin?.latitude ?? 0;
    const curLng = eventsWithCoords[0]?.longitude ?? origin?.longitude ?? 0;

    async function fetchPoint(latVal: number, lngVal: number): Promise<WeatherReading> {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), 4000);
      try {
        const res = await fetch(
          `https://api.open-meteo.com/v1/forecast?latitude=${latVal}&longitude=${lngVal}&current_weather=true`,
          { signal: ctrl.signal },
        );
        clearTimeout(timer);
        if (!res.ok) throw new Error('HTTP error');
        const json = await res.json();
        const cw = json.current_weather;
        return {
          temperatureC: cw.temperature,
          windSpeedKmh: cw.windspeed,
          conditionLabel: decodeWmoCode(cw.weathercode),
        };
      } finally {
        clearTimeout(timer);
      }
    }

    if (origin && dest) {
      Promise.all([
        fetchPoint(origin.latitude, origin.longitude),
        fetchPoint(curLat, curLng),
        fetchPoint(dest.latitude, dest.longitude),
      ])
        .then(([o, c, d]) => {
          if (cancelled) return;
          setWeather({ origin: o, current: c, destination: d, source: 'live', loading: false });
        })
        .catch(() => {
          if (cancelled) return;
          setWeather({
            origin: { temperatureC: 29.2, windSpeedKmh: 14.5, conditionLabel: 'Partly cloudy' },
            current: { temperatureC: 24.6, windSpeedKmh: 21.0, conditionLabel: 'Clear sky' },
            destination: { temperatureC: 11.4, windSpeedKmh: 18.2, conditionLabel: 'Coastal mist' },
            source: 'fallback',
            loading: false,
          });
        });
    }

    return () => {
      cancelled = true;
    };
  }, [shipment?.id, events.length, state.locations]);

  if (!shipment) {
    return (
      <AppLayout title="Shipment not found">
        <div className="p-6">
          <div className="rounded-lg border border-line bg-surface p-6 text-center">
            <p className="text-sm text-ink-2">Shipment not found or archived.</p>
            <button
              type="button"
              onClick={() => navigate('/shipments')}
              className="mt-3 text-xs font-medium text-ink hover:underline cursor-pointer"
            >
              ← Back to shipments
            </button>
          </div>
        </div>
      </AppLayout>
    );
  }

  if (currentUser?.role === UserRole.Customer && shipment.customer_id !== currentUser.id) {
    return (
      <AppLayout title="403 Forbidden" subtitle="Customer shipment ownership policy">
        <div className="mx-auto max-w-lg p-8">
          <div className="rounded-lg border border-rose-500/30 bg-surface p-6 text-center space-y-3">
            <h2 className="text-base font-semibold text-ink">
              403 Forbidden — Shipment Access Restricted
            </h2>
            <p className="text-xs leading-relaxed text-ink-2">
              Customer accounts may only inspect shipments assigned to their own account.
            </p>
            <button
              type="button"
              onClick={() => navigate('/shipments')}
              className="mt-2 h-8 rounded-md bg-brand px-4 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
            >
              ← Back to your shipments
            </button>
          </div>
        </div>
      </AppLayout>
    );
  }

  const origin = state.locations.find((l) => l.id === shipment.origin_location_id)!;
  const destination = state.locations.find((l) => l.id === shipment.destination_location_id)!;
  const carrier = state.carriers.find((c) => c.id === shipment.carrier_id)!;
  const customer = state.users.find((u) => u.id === shipment.customer_id);
  const items = state.items.filter((i) => i.shipment_id === shipment.id);
  const exceptions = state.exceptions
    .filter((e) => e.shipment_id === shipment.id)
    .sort((a, b) => new Date(b.detected_at).getTime() - new Date(a.detected_at).getTime());

  const assessment = assessShipmentDelay(shipment);
  const delayed = isShipmentDelayed(shipment.expected_arrival_at, shipment.estimated_arrival_at);

  const eventsWithCoords = events.filter((e) => e.latitude !== null && e.longitude !== null);
  const latestCoordEvent = eventsWithCoords[0];
  const prevCoordEvent = eventsWithCoords[1];

  // Chronological unique intermediate transit stops strictly between Origin and Destination
  const transitStops = useMemo(() => {
    const chronological = [...eventsWithCoords].reverse();
    const stops: typeof eventsWithCoords = [];
    for (const ev of chronological) {
      const latVal = ev.latitude!;
      const lngVal = ev.longitude!;
      const isAtOrigin =
        Math.abs(latVal - origin.latitude) < 0.02 && Math.abs(lngVal - origin.longitude) < 0.02;
      const isAtDest =
        Math.abs(latVal - destination.latitude) < 0.02 &&
        Math.abs(lngVal - destination.longitude) < 0.02;
      if (isAtOrigin || isAtDest) continue;
      const exists = stops.some(
        (s) =>
          Math.abs(s.latitude! - latVal) < 0.02 && Math.abs(s.longitude! - lngVal) < 0.02,
      );
      if (!exists) stops.push(ev);
    }
    return stops;
  }, [eventsWithCoords, origin.latitude, origin.longitude, destination.latitude, destination.longitude]);

  const activePositionEvent =
    shipment.current_status !== ShipmentStatus.Delivered && transitStops.length > 0
      ? transitStops[transitStops.length - 1]
      : latestCoordEvent;

  const mapEntry: MapShipmentEntry = {
    id: shipment.id,
    shipment_number: shipment.shipment_number,
    status: shipment.current_status,
    carrier_name: carrier.name,
    service_type: carrier.service_type,
    delayed,
    origin: {
      latitude: origin.latitude,
      longitude: origin.longitude,
      location_name: `${origin.code} · ${origin.city}`,
      code: origin.code,
      time: `Dep: ${formatDateTime(shipment.expected_departure_at)}`,
    },
    destination: {
      latitude: destination.latitude,
      longitude: destination.longitude,
      location_name: `${destination.code} · ${destination.city}`,
      code: destination.code,
      time: `ETA: ${formatDateTime(shipment.estimated_arrival_at ?? shipment.expected_arrival_at)}`,
    },
    current_position: activePositionEvent
      ? {
          latitude: activePositionEvent.latitude!,
          longitude: activePositionEvent.longitude!,
          location_name: activePositionEvent.location_name,
          time: formatDateTime(activePositionEvent.occurred_at),
        }
      : {
          latitude: origin.latitude,
          longitude: origin.longitude,
          location_name: origin.name,
        },
    previous_position: prevCoordEvent
      ? { latitude: prevCoordEvent.latitude!, longitude: prevCoordEvent.longitude! }
      : { latitude: origin.latitude, longitude: origin.longitude },
    checkpoints: transitStops
      .slice()
      .reverse()
      .map((ev) => ({
        latitude: ev.latitude!,
        longitude: ev.longitude!,
        location_name: ev.location_name,
        time: formatDateTime(ev.occurred_at),
      })),
  };

  const lifecycleSteps: ShipmentStatus[] = [
    ShipmentStatus.Created,
    ShipmentStatus.Booked,
    ShipmentStatus.PickedUp,
    ShipmentStatus.InTransit,
    ShipmentStatus.Delivered,
  ];
  const currentStepIdx = lifecycleSteps.indexOf(shipment.current_status);
  const nextAllowed = allowedNextStatuses(shipment.current_status);

  const handleQuickAdvanceStatus = () => {
    if (nextAllowed.length === 0) return;
    const targetStatus = nextAllowed[0];
    const latestMs =
      events.length > 0
        ? Math.max(...events.map((ev) => new Date(ev.occurred_at).getTime()))
        : new Date(shipment.expected_departure_at).getTime();
    const nextIso = new Date(Math.max(Date.now(), latestMs + 2 * 3600 * 1000)).toISOString();

    let locName = `${origin.name} (${origin.code})`;
    let locLat = origin.latitude;
    let locLng = origin.longitude;
    let desc = `Lifecycle advanced to ${statusLabel(targetStatus)}.`;

    const latestTransit = transitStops.length > 0 ? transitStops[transitStops.length - 1] : null;

    if (targetStatus === ShipmentStatus.Delivered) {
      locName = `${destination.name} (${destination.code})`;
      locLat = destination.latitude;
      locLng = destination.longitude;
      desc = 'Cargo delivered and POD confirmed at destination port.';
    } else if (targetStatus === ShipmentStatus.InTransit && latestTransit) {
      locName = latestTransit.location_name;
      locLat = latestTransit.latitude!;
      locLng = latestTransit.longitude!;
      desc = `Vessel in transit via ${latestTransit.location_name}.`;
    } else if (targetStatus === ShipmentStatus.InTransit && latestCoordEvent) {
      locName = latestCoordEvent.location_name;
      locLat = latestCoordEvent.latitude!;
      locLng = latestCoordEvent.longitude!;
      desc = 'Vessel departed on main corridor leg.';
    }

    addTrackingEvent(shipment.id, {
      event_type: targetStatus,
      occurred_at: nextIso,
      location_name: locName,
      latitude: locLat,
      longitude: locLng,
      description: desc,
    });
  };

  const openCheckpointForm = () => {
    const nextState = !showEventForm;
    setShowEventForm(nextState);
    setShowExceptionForm(false);
    if (nextState) {
      const latestTime = events[0]?.occurred_at ?? shipment.expected_departure_at;
      setOccurredAt(toInputDateTimeUtc(latestTime, 4));
      setEventEta(
        toInputDateTimeUtc(shipment.estimated_arrival_at ?? shipment.expected_arrival_at, 0),
      );
      setGeocodeStatus({ kind: 'idle', message: '' });
    }
  };

  const openRaiseExceptionForm = () => {
    const nextState = !showExceptionForm;
    setShowExceptionForm(nextState);
    setShowEventForm(false);
    if (nextState) {
      const latestTime = events[0]?.occurred_at ?? shipment.expected_departure_at;
      setExcDetectedAt(toInputDateTimeUtc(latestTime, 2));
      setExcLocation(events[0]?.location_name ?? origin.name);
      setExcEta(
        toInputDateTimeUtc(shipment.estimated_arrival_at ?? shipment.expected_arrival_at, 12),
      );
    }
  };

  const handleGeocodeWaypoint = async () => {
    if (!locationName.trim()) {
      setGeocodeStatus({
        kind: 'empty',
        message: 'Enter a port or city name before geocoding.',
      });
      return;
    }
    setGeocodeStatus({
      kind: 'loading',
      message: 'Querying OpenStreetMap Nominatim...',
    });

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 5000);
    try {
      const res = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(
          locationName.trim(),
        )}`,
        {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        },
      );
      clearTimeout(timer);
      if (!res.ok) {
        setGeocodeStatus({
          kind: 'error',
          message: `Geocoding service returned HTTP ${res.status}. Enter coordinates manually.`,
        });
        return;
      }
      const hits = await res.json();
      if (Array.isArray(hits) && hits.length > 0) {
        const foundLat = Number(hits[0].lat).toFixed(4);
        const foundLng = Number(hits[0].lon).toFixed(4);
        setLat(foundLat);
        setLng(foundLng);
        setGeocodeStatus({
          kind: 'ok',
          message: `Found coordinates (${foundLat}, ${foundLng}) — ${hits[0].display_name}`,
        });
      } else {
        setGeocodeStatus({
          kind: 'empty',
          message: 'No matching location found in Nominatim. Pick a hub preset or enter coordinates manually.',
        });
      }
    } catch (err: unknown) {
      clearTimeout(timer);
      const isAbort = err instanceof Error && err.name === 'AbortError';
      setGeocodeStatus({
        kind: 'error',
        message: isAbort
          ? 'Nominatim request timed out after 5 seconds. Pick a hub preset or enter coordinates manually.'
          : 'Could not reach Nominatim geocoding service. Enter coordinates manually.',
      });
    }
  };

  const handleAddEvent = (e: React.FormEvent) => {
    e.preventDefault();
    const res = addTrackingEvent(shipment.id, {
      event_type: eventType,
      occurred_at: `${occurredAt}:00.000Z`,
      location_name: locationName,
      latitude: lat.trim() ? Number(lat) : null,
      longitude: lng.trim() ? Number(lng) : null,
      description: eventDesc || null,
      estimated_arrival_at: eventEta ? `${eventEta}:00.000Z` : undefined,
    });
    if (!res.ok) {
      setEventErrors(res.errors ?? {});
    } else {
      setEventErrors({});
      setLocationName('');
      setLat('');
      setLng('');
      setEventDesc('');
      setGeocodeStatus({ kind: 'idle', message: '' });
      setShowEventForm(false);
    }
  };

  const handleCreateException = (e: React.FormEvent) => {
    e.preventDefault();
    const res = createException(shipment.id, {
      type: excType,
      severity: excSeverity,
      detected_at: `${excDetectedAt}:00.000Z`,
      location_name: excLocation,
      description: excDesc,
      estimated_arrival_at: excEta ? `${excEta}:00.000Z` : undefined,
    });
    if (!res.ok) {
      setExcErrors(res.errors ?? {});
    } else {
      setExcErrors({});
      setExcLocation('');
      setExcDesc('');
      setShowExceptionForm(false);
    }
  };

  const openExceptionsCount = exceptions.filter(
    (e) => e.status !== ExceptionStatus.Resolved,
  ).length;

  return (
    <AppLayout
      title={shipment.shipment_number}
      subtitle={`${origin.code} (${origin.city}) → ${destination.code} (${destination.city})`}
    >
      <div className="flex min-h-[calc(100vh-56px)] flex-col bg-base lg:h-[calc(100vh-56px)] lg:overflow-hidden">
        {/* Top Control & Lifecycle Bar */}
        <div className="shrink-0 border-b border-line bg-base px-3 py-2.5 sm:px-5">
          <div className="flex flex-wrap items-center justify-between gap-2.5 sm:gap-3">
            {/* Left: Back link, Shipment ID, Status, Delay */}
            <div className="flex flex-wrap items-center gap-2.5">
              <button
                type="button"
                onClick={() => navigate('/shipments')}
                className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-surface px-2.5 text-xs text-ink-2 transition-colors hover:border-line-strong hover:text-ink cursor-pointer"
              >
                <ArrowLeft className="size-3.5" strokeWidth={1.5} />
                Shipments
              </button>

              <span className="h-4 w-[1px] bg-line" />

              <span className="font-mono text-sm font-semibold text-ink">
                {shipment.shipment_number}
              </span>
              <span className="text-xs text-ink-3">· {shipment.cargo_type}</span>

              <Badge tone={statusTone(shipment.current_status)}>
                {statusLabel(shipment.current_status)}
              </Badge>

              <Badge tone={delayTone(assessment)}>
                {assessment.state === 'on_time'
                  ? 'On schedule'
                  : assessment.state === 'pending'
                  ? 'ETA pending'
                  : `+${assessment.delay_hours}h delay (${assessment.state})`}
              </Badge>
            </div>

            {/* Center: Clean Inline 5-Stage Lifecycle Pipeline */}
            <div className="hidden xl:flex items-center gap-2 text-xs">
              {lifecycleSteps.map((st, idx) => {
                const done = idx < currentStepIdx;
                const active = idx === currentStepIdx;
                return (
                  <React.Fragment key={st}>
                    <span
                      className={`inline-flex items-center gap-1.5 ${
                        active
                          ? 'font-semibold text-ink'
                          : done
                          ? 'text-ink-2'
                          : 'text-ink-3'
                      }`}
                    >
                      {done ? (
                        <Check className="size-3 text-emerald-500 dark:text-emerald-400" strokeWidth={2} />
                      ) : active ? (
                        <span className="size-2 rounded-full border border-[#080B0C] bg-[#C6F432]" />
                      ) : (
                        <span className="size-1.5 rounded-full bg-line-strong" />
                      )}
                      <span>{statusLabel(st)}</span>
                    </span>
                    {idx < lifecycleSteps.length - 1 && (
                      <span className="h-[1px] w-4 bg-line-strong" />
                    )}
                  </React.Fragment>
                );
              })}
            </div>

            {/* Right: Document & Operator Actions */}
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => setShowBillModal(true)}
                className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-surface px-2.5 text-xs font-medium text-ink-2 transition-colors hover:border-line-strong hover:text-ink cursor-pointer"
              >
                <Printer className="size-3.5" strokeWidth={1.5} />
                Print B/L
              </button>

              {canOperate && nextAllowed.length > 0 && (
                <button
                  type="button"
                  onClick={handleQuickAdvanceStatus}
                  className="inline-flex h-8 items-center gap-1.5 rounded-md border border-[#080B0C] bg-[#C6F432] px-2.5 text-xs font-semibold text-[#080B0C] transition-colors hover:bg-brand-hover cursor-pointer"
                >
                  <span>Advance → {statusLabel(nextAllowed[0])}</span>
                  <ArrowRight className="size-3.5" strokeWidth={1.75} />
                </button>
              )}

              {canOperate && (
                <>
                  <button
                    type="button"
                    onClick={openCheckpointForm}
                    className={`inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs transition-colors cursor-pointer ${
                      showEventForm
                        ? 'border-[#080B0C] bg-brand font-semibold text-brand-ink'
                        : 'border-line bg-surface text-ink-2 hover:text-ink'
                    }`}
                  >
                    <Plus className="size-3.5" strokeWidth={1.75} />
                    Add checkpoint
                  </button>

                  <button
                    type="button"
                    onClick={openRaiseExceptionForm}
                    className={`inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs transition-colors cursor-pointer ${
                      showExceptionForm
                        ? 'border-[#080B0C] bg-brand font-semibold text-brand-ink'
                        : 'border-line bg-surface text-ink-2 hover:text-ink'
                    }`}
                  >
                    <Plus className="size-3.5" strokeWidth={1.75} />
                    Raise exception
                  </button>

                  <button
                    type="button"
                    onClick={() => navigate(`/shipments/${shipment.id}/edit`)}
                    className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-surface px-2.5 text-xs text-ink-2 transition-colors hover:border-line-strong hover:text-ink cursor-pointer"
                  >
                    <Edit className="size-3.5" strokeWidth={1.5} />
                    Edit
                  </button>

                  {!confirmDelete ? (
                    <button
                      type="button"
                      onClick={() => setConfirmDelete(true)}
                      className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-surface px-2.5 text-xs text-ink-3 transition-colors hover:border-rose-500/40 hover:text-rose-500 cursor-pointer"
                    >
                      <Trash2 className="size-3.5" strokeWidth={1.5} />
                      Archive
                    </button>
                  ) : (
                    <div className="flex items-center gap-1.5 rounded-md border border-rose-500/40 bg-surface px-2.5 py-1 text-xs">
                      <span className="text-rose-600 dark:text-rose-300">Archive?</span>
                      <button
                        type="button"
                        onClick={() => {
                          archiveShipment(shipment.id);
                          navigate('/shipments');
                        }}
                        className="rounded bg-rose-500 px-2 py-0.5 font-medium text-white cursor-pointer"
                      >
                        Yes
                      </button>
                      <button
                        type="button"
                        onClick={() => setConfirmDelete(false)}
                        className="text-ink-2 cursor-pointer"
                      >
                        No
                      </button>
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>

        {/* Main Split Workspace — Left Dossier & Timeline Rail | Right Full-Height Map */}
        <div className="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[460px_1fr]">
          {/* Left Column: Flush Hairline-Divided Dossier & Chronological Timeline */}
          <section className="flex min-h-0 flex-col border-b border-line bg-base lg:overflow-y-auto lg:border-b-0 lg:border-r">
            {/* Collapsible Add Checkpoint Form */}
            {canOperate && showEventForm && (
              <form
                onSubmit={handleAddEvent}
                className="border-b border-line bg-surface px-3.5 py-4 sm:px-5 space-y-3"
              >
                <div className="flex items-center justify-between">
                  <div>
                    <span className="text-xs font-bold text-ink">
                      Append transit checkpoint (Mid-route stop)
                    </span>
                    <p className="text-[11px] text-ink-3">
                      Inserts an intermediate waypoint between {origin.code} ({origin.city}) and{' '}
                      {destination.code} ({destination.city})
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => setShowEventForm(false)}
                    className="text-ink-3 hover:text-ink cursor-pointer"
                  >
                    <X className="size-3.5" strokeWidth={1.5} />
                  </button>
                </div>

                {/* Live Corridor Route Chain Preview */}
                <div className="rounded-md border border-line bg-base px-2.5 py-2 text-xs">
                  <div className="text-[10px] font-semibold uppercase tracking-wider text-ink-3">
                    Corridor Trajectory Preview
                  </div>
                  <div className="mt-1 flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                    <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-bold text-ink">
                      {origin.code} · {origin.city}
                    </span>
                    <span className="font-bold text-ink-2">━━▶</span>
                    <span className="rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-bold text-[#080B0C]">
                      ● {locationName.trim() || 'Select transit stop (e.g. Port of Singapore)'}
                    </span>
                    <span className="font-bold text-ink-3">╌╌▶</span>
                    <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-bold text-ink">
                      {destination.code} · {destination.city}
                    </span>
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">
                      Event / status transition
                    </label>
                    <select
                      value={eventType}
                      onChange={(e) => setEventType(e.target.value)}
                      className="h-8 w-full rounded-md border border-line bg-base px-2 text-xs text-ink"
                    >
                      <option value="CHECKPOINT">CHECKPOINT (Position scan only)</option>
                      {Object.values(ShipmentStatus).map((st) => {
                        const isNext = nextAllowed.includes(st);
                        return (
                          <option key={st} value={st}>
                            {isNext ? `★ Transition → ${statusLabel(st)} (Allowed next)` : `Transition → ${statusLabel(st)}`}
                          </option>
                        );
                      })}
                    </select>
                  </div>

                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">
                      Occurred at (UTC) *
                    </label>
                    <input
                      type="datetime-local"
                      value={occurredAt}
                      onChange={(e) => setOccurredAt(e.target.value)}
                      className="h-8 w-full rounded-md border border-line bg-base px-2 text-xs text-ink"
                    />
                  </div>
                </div>
                {eventErrors.event_type && (
                  <p className="text-xs text-rose-500">{eventErrors.event_type}</p>
                )}
                {eventErrors.occurred_at && (
                  <p className="text-xs text-rose-500">{eventErrors.occurred_at}</p>
                )}

                {/* Searchable Transit Hub Autocomplete + Nominatim Geocode */}
                <div className="relative">
                  <label className="mb-1 block text-[11px] text-ink-3">
                    Location / transit hub * (type or pick preset)
                  </label>
                  <div className="flex gap-2">
                    <div className="relative flex-1">
                      <input
                        type="text"
                        value={locationName}
                        onFocus={() => setHubMenuOpen(true)}
                        onBlur={() => setTimeout(() => setHubMenuOpen(false), 180)}
                        onChange={(e) => {
                          setLocationName(e.target.value);
                          setHubMenuOpen(true);
                        }}
                        placeholder="Type hub (e.g. Suez Canal, Colombo, Singapore...)"
                        className="h-8 w-full rounded-md border border-line bg-base pl-2.5 pr-7 text-xs text-ink placeholder:text-ink-3"
                      />
                      <button
                        type="button"
                        tabIndex={-1}
                        aria-label="Toggle transit hub presets"
                        onClick={() => setHubMenuOpen((o) => !o)}
                        className="absolute right-2 top-1/2 -translate-y-1/2 text-ink-3 hover:text-ink cursor-pointer"
                      >
                        <ChevronDown className="size-3.5" strokeWidth={1.5} />
                      </button>
                    </div>
                    <button
                      type="button"
                      disabled={geocodeStatus.kind === 'loading'}
                      onClick={handleGeocodeWaypoint}
                      className="inline-flex h-8 items-center gap-1 rounded-md border border-line bg-raised px-2.5 text-xs text-ink hover:border-line-strong cursor-pointer"
                    >
                      <Search className="size-3" strokeWidth={1.5} />
                      {geocodeStatus.kind === 'loading' ? '...' : 'Geocode'}
                    </button>
                  </div>

                  {hubMenuOpen && filteredHubSuggestions.length > 0 && (
                    <div className="absolute left-0 right-0 z-30 mt-1 max-h-48 overflow-y-auto rounded-md border border-line-strong bg-overlay p-1 shadow-xl">
                      {filteredHubSuggestions.map((hub) => (
                        <button
                          key={`${hub.name}-${hub.latitude}`}
                          type="button"
                          onMouseDown={(ev) => {
                            ev.preventDefault();
                            setLocationName(hub.name);
                            setLat(hub.latitude.toFixed(4));
                            setLng(hub.longitude.toFixed(4));
                            setHubMenuOpen(false);
                            setGeocodeStatus({
                              kind: 'ok',
                              message: `Selected preset coordinates (${hub.latitude.toFixed(4)}, ${hub.longitude.toFixed(4)})`,
                            });
                          }}
                          className="flex w-full items-center justify-between gap-2 rounded px-2.5 py-1.5 text-left text-xs transition-colors hover:bg-surface cursor-pointer"
                        >
                          <div className="min-w-0">
                            <div className="truncate font-medium text-ink">{hub.name}</div>
                            <div className="truncate text-[10px] text-ink-3">{hub.region}</div>
                          </div>
                          <span className="shrink-0 font-mono text-[10px] text-ink-2 tabular-nums">
                            {hub.latitude.toFixed(2)}°, {hub.longitude.toFixed(2)}°
                          </span>
                        </button>
                      ))}
                    </div>
                  )}

                  {eventErrors.location_name && (
                    <p className="mt-1 text-xs text-rose-500">{eventErrors.location_name}</p>
                  )}
                  {geocodeStatus.kind !== 'idle' && (
                    <p
                      className={`mt-1 text-[11px] ${
                        geocodeStatus.kind === 'ok'
                          ? 'text-emerald-600 dark:text-emerald-400'
                          : geocodeStatus.kind === 'error'
                          ? 'text-rose-500'
                          : 'text-ink-2'
                      }`}
                    >
                      {geocodeStatus.message}
                    </p>
                  )}
                </div>

                <div className="grid grid-cols-2 gap-2.5">
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">Latitude</label>
                    <input
                      type="number"
                      step="any"
                      value={lat}
                      onChange={(e) => setLat(e.target.value)}
                      placeholder="e.g. 6.9531"
                      className="h-8 w-full rounded-md border border-line bg-base px-2.5 font-mono text-xs text-ink"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">Longitude</label>
                    <input
                      type="number"
                      step="any"
                      value={lng}
                      onChange={(e) => setLng(e.target.value)}
                      placeholder="e.g. 79.8506"
                      className="h-8 w-full rounded-md border border-line bg-base px-2.5 font-mono text-xs text-ink"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">
                      Updated Estimated Arrival (ETA)
                    </label>
                    <input
                      type="datetime-local"
                      value={eventEta}
                      onChange={(e) => setEventEta(e.target.value)}
                      className="h-8 w-full rounded-md border border-line bg-base px-2 text-xs text-ink"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">
                      Checkpoint notes
                    </label>
                    <input
                      type="text"
                      value={eventDesc}
                      onChange={(e) => setEventDesc(e.target.value)}
                      placeholder="Vessel transit remarks..."
                      className="h-8 w-full rounded-md border border-line bg-base px-2.5 text-xs text-ink"
                    />
                  </div>
                </div>

                <div className="flex items-center justify-between pt-1">
                  <span className="font-mono text-[11px] text-ink-3">
                    {nextAllowed.length > 0
                      ? `Allowed next: ${nextAllowed.join(', ')}`
                      : 'Terminal state (DELIVERED)'}
                  </span>
                  <button
                    type="submit"
                    className="h-8 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
                  >
                    Save checkpoint
                  </button>
                </div>
              </form>
            )}

            {/* Collapsible Raise Exception Form */}
            {canOperate && showExceptionForm && (
              <form
                onSubmit={handleCreateException}
                className="border-b border-line bg-surface px-3.5 py-4 sm:px-5 space-y-3"
              >
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-ink">
                    Raise operational exception
                  </span>
                  <button
                    type="button"
                    onClick={() => setShowExceptionForm(false)}
                    className="text-ink-3 hover:text-ink cursor-pointer"
                  >
                    <X className="size-3.5" strokeWidth={1.5} />
                  </button>
                </div>

                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">Exception type *</label>
                    <select
                      value={excType}
                      onChange={(e) => setExcType(e.target.value as ExceptionType)}
                      className="h-8 w-full rounded-md border border-line bg-base px-2 text-xs text-ink"
                    >
                      {Object.values(ExceptionType).map((t) => (
                        <option key={t} value={t}>
                          {exceptionTypeLabel(t)}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">Severity *</label>
                    <select
                      value={excSeverity}
                      onChange={(e) => setExcSeverity(e.target.value as ExceptionSeverity)}
                      className="h-8 w-full rounded-md border border-line bg-base px-2 text-xs text-ink"
                    >
                      {Object.values(ExceptionSeverity).map((s) => (
                        <option key={s} value={s}>
                          {s}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">Detected at (UTC) *</label>
                    <input
                      type="datetime-local"
                      value={excDetectedAt}
                      onChange={(e) => setExcDetectedAt(e.target.value)}
                      className="h-8 w-full rounded-md border border-line bg-base px-2 text-xs text-ink"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-[11px] text-ink-3">
                      Updated Estimated Arrival (ETA)
                    </label>
                    <input
                      type="datetime-local"
                      value={excEta}
                      onChange={(e) => setExcEta(e.target.value)}
                      className="h-8 w-full rounded-md border border-line bg-base px-2 text-xs text-ink"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                  <input
                    type="text"
                    value={excLocation}
                    onChange={(e) => setExcLocation(e.target.value)}
                    placeholder="Hub / port detected..."
                    className="h-8 w-full rounded-md border border-line bg-base px-2.5 text-xs text-ink"
                  />
                  <input
                    type="text"
                    value={excDesc}
                    onChange={(e) => setExcDesc(e.target.value)}
                    placeholder="Exception description / hold reason..."
                    className="h-8 w-full rounded-md border border-line bg-base px-2.5 text-xs text-ink"
                  />
                </div>
                {excErrors.detected_at && (
                  <p className="text-xs text-rose-500">{excErrors.detected_at}</p>
                )}

                <div className="flex justify-end pt-1">
                  <button
                    type="submit"
                    className="h-8 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
                  >
                    Submit exception
                  </button>
                </div>
              </form>
            )}

            {/* Section 1: Corridor Progress & Schedule Dossier */}
            <div className="border-b border-line px-5 py-4">
              <ProgressRoute shipment={shipment} origin={origin} destination={destination} />

              <div className="mt-4 divide-y divide-line border-t border-line text-xs">
                <div className="flex items-center justify-between py-2">
                  <span className="text-ink-3">Carrier &amp; mode</span>
                  <span className="text-ink">
                    {carrier.name}{' '}
                    <span className="font-mono text-ink-2">
                      ({carrier.code} · {carrier.service_type})
                    </span>
                  </span>
                </div>
                <div className="flex items-center justify-between py-2">
                  <span className="text-ink-3">Consignee</span>
                  <span className="text-ink">{customer?.name ?? '—'}</span>
                </div>
                <div className="flex items-center justify-between py-2">
                  <span className="text-ink-3">Total load</span>
                  <span className="font-mono text-ink tabular-nums">
                    {shipment.weight_kg.toLocaleString()} kg · {shipment.volume_m3} m³
                  </span>
                </div>
                <div className="flex items-center justify-between py-2">
                  <span className="text-ink-3">Planned arrival</span>
                  <span className="font-mono text-ink tabular-nums">
                    {formatDateTime(shipment.expected_arrival_at, true)}
                  </span>
                </div>
                <div className="flex items-center justify-between py-2">
                  <span className="text-ink-3">Estimated arrival</span>
                  <span
                    className={`font-mono font-medium tabular-nums ${
                      delayed ? 'text-amber-600 dark:text-amber-300' : 'text-ink'
                    }`}
                  >
                    {formatDateTime(
                      shipment.estimated_arrival_at ?? shipment.expected_arrival_at,
                      true,
                    )}
                  </span>
                </div>
                <div className="py-2">
                  <div className="flex items-center justify-between">
                    <span className="text-ink-3">Delay rule ({state.scenarioConfig.groupCode})</span>
                    <span className="font-mono text-[11px] text-ink-2 tabular-nums">
                      Low ≤{assessment.threshold_low}h · Med ≤{assessment.threshold_high}h (
                      {delayed ? `+${assessment.delay_hours}h` : '0h'})
                    </span>
                  </div>
                  <p className="mt-1 text-[11px] text-ink-3">{assessment.reason}</p>
                </div>
              </div>
            </div>

            {/* Section 2: Exceptions */}
            <div className="border-b border-line px-5 py-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <h3 className="text-xs font-medium text-ink-2">Exceptions</h3>
                  <span className="font-mono text-xs text-ink-3">({exceptions.length})</span>
                </div>
                {openExceptionsCount > 0 ? (
                  <span className="font-mono text-[11px] font-medium text-rose-600 dark:text-rose-400">
                    {openExceptionsCount} open
                  </span>
                ) : (
                  <span className="text-[11px] text-ink-3">None active</span>
                )}
              </div>

              {exceptions.length === 0 ? (
                <p className="mt-2 text-xs text-ink-3">
                  No operational exceptions recorded for this shipment.
                </p>
              ) : (
                <div className="mt-2.5 divide-y divide-line border-t border-line">
                  {exceptions.map((ex) => {
                    const nextStates = allowedNextExceptionStatuses(ex.status);
                    return (
                      <div key={ex.id} className="py-3 first:pt-2.5 last:pb-0">
                        <div className="flex items-center justify-between gap-2">
                          <span className="text-xs font-medium text-ink">
                            {exceptionTypeLabel(ex.type)}
                          </span>
                          <div className="flex items-center gap-1.5">
                            <Badge tone={exceptionSeverityTone(ex.severity)}>{ex.severity}</Badge>
                            <Badge tone={exceptionStatusTone(ex.status)}>{ex.status}</Badge>
                          </div>
                        </div>
                        {ex.description && (
                          <p className="mt-1 text-xs leading-relaxed text-ink-2">
                            {ex.description}
                          </p>
                        )}
                        <div className="mt-1.5 flex items-center justify-between gap-2">
                          <span className="font-mono text-[11px] text-ink-3 tabular-nums">
                            Detected {formatDateTime(ex.detected_at, true)}
                            {ex.resolved_at ? ` · Resolved ${formatDateTime(ex.resolved_at, true)}` : ''}
                          </span>
                          {canOperate && nextStates.length > 0 && (
                            <div className="flex items-center gap-1.5">
                              {nextStates.map((target) => (
                                <button
                                  key={target}
                                  type="button"
                                  onClick={() => updateExceptionStatus(ex.id, target)}
                                  className="rounded border border-line bg-surface px-2 py-0.5 text-[11px] font-medium text-ink hover:border-line-strong cursor-pointer"
                                >
                                  Mark {target.toLowerCase()}
                                </button>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Section 3: Append-Only Tracking Timeline */}
            <div className="border-b border-line px-5 py-4">
              <div className="mb-3.5 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <h3 className="text-xs font-semibold text-ink-2">Tracking timeline</h3>
                  <span className="font-mono text-xs text-ink-3">({events.length})</span>
                </div>
                <span className="text-[11px] font-medium text-ink-3">
                  {origin.code} → {eventsWithCoords.length > 0 ? 'Checkpoints → ' : ''}
                  {destination.code}
                </span>
              </div>

              <ol className="relative ml-1.5 space-y-4 border-l border-line-strong">
                {/* Upcoming Destination Target Node (when shipment is not yet DELIVERED) */}
                {shipment.current_status !== ShipmentStatus.Delivered && (
                  <li className="ml-4">
                    <span className="absolute -left-[5px] mt-1.5 size-2.5 rounded-full border-2 border-dashed border-ink-2 bg-base" />
                    <div className="border-b border-dashed border-line pb-3">
                      <div className="flex items-baseline justify-between gap-2">
                        <div className="flex items-center gap-2 min-w-0">
                          <span className="truncate text-xs font-semibold text-ink-2">
                            {destination.code} · {destination.name} ({destination.city})
                          </span>
                          <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink-3">
                            TARGET DESTINATION
                          </span>
                        </div>
                        <time className="shrink-0 font-mono text-[11px] text-ink-3 tabular-nums">
                          ETA {formatDateTime(shipment.estimated_arrival_at ?? shipment.expected_arrival_at)}
                        </time>
                      </div>
                      <div className="mt-1 flex items-center justify-between text-[10px] text-ink-3">
                        <span className="inline-flex items-center gap-1 font-mono tabular-nums">
                          <Compass className="size-3" strokeWidth={1.5} />
                          <span>
                            {destination.latitude.toFixed(4)}°, {destination.longitude.toFixed(4)}°
                          </span>
                        </span>
                        <span>Remaining leg to {destination.code}</span>
                      </div>
                    </div>
                  </li>
                )}

                {events.map((ev, idx) => {
                  const creator = state.users.find((u) => u.id === ev.created_by);
                  return (
                    <li key={ev.id} className="ml-4">
                      <span
                        className={`absolute -left-[4.5px] mt-1.5 size-2 rounded-full ${
                          ev.event_type === 'EXCEPTION'
                            ? 'bg-rose-500'
                            : idx === 0
                            ? 'border border-[#080B0C] bg-[#C6F432]'
                            : 'bg-ink-3'
                        }`}
                      />
                      <div className="border-b border-line pb-3.5 last:border-b-0 last:pb-0">
                        <div className="flex items-baseline justify-between gap-2">
                          <div className="flex items-center gap-2 min-w-0">
                            <span className="truncate text-xs font-semibold text-ink">
                              {ev.location_name}
                            </span>
                            <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink-2">
                              {ev.event_type}
                            </span>
                          </div>
                          <time className="shrink-0 font-mono text-[11px] text-ink-3 tabular-nums">
                            {formatDateTime(ev.occurred_at)}
                          </time>
                        </div>
                        {ev.description && (
                          <p className="mt-1 text-xs leading-relaxed text-ink-2">
                            {ev.description}
                          </p>
                        )}
                        <div className="mt-1 flex flex-wrap items-center justify-between gap-2 text-[10px] text-ink-3">
                          {ev.latitude !== null && ev.longitude !== null ? (
                            <span className="inline-flex items-center gap-1 font-mono tabular-nums">
                              <Compass className="size-3" strokeWidth={1.5} />
                              <span>
                                {ev.latitude.toFixed(4)}°, {ev.longitude.toFixed(4)}°
                              </span>
                            </span>
                          ) : (
                            <span />
                          )}
                          <span>Recorded by {creator?.name ?? 'System'}</span>
                        </div>
                      </div>
                    </li>
                  );
                })}

                {/* Origin Departure Anchor Node (always ensures timeline clearly starts from Origin port e.g. KRG) */}
                {!events.some(
                  (ev) =>
                    ev.latitude !== null &&
                    ev.longitude !== null &&
                    Math.abs(ev.latitude - origin.latitude) < 0.02 &&
                    Math.abs(ev.longitude - origin.longitude) < 0.02,
                ) && (
                  <li className="ml-4">
                    <span className="absolute -left-[5px] mt-1.5 size-2.5 rounded-full border-2 border-[#080B0C] bg-white dark:border-white dark:bg-[#080B0C]" />
                    <div className="pt-0.5">
                      <div className="flex items-baseline justify-between gap-2">
                        <div className="flex items-center gap-2 min-w-0">
                          <span className="truncate text-xs font-semibold text-ink">
                            {origin.code} · {origin.name} ({origin.city})
                          </span>
                          <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink-2">
                            ORIGIN PORT
                          </span>
                        </div>
                        <time className="shrink-0 font-mono text-[11px] text-ink-3 tabular-nums">
                          Dep {formatDateTime(shipment.expected_departure_at)}
                        </time>
                      </div>
                      <div className="mt-1 flex items-center justify-between text-[10px] text-ink-3">
                        <span className="inline-flex items-center gap-1 font-mono tabular-nums">
                          <Compass className="size-3" strokeWidth={1.5} />
                          <span>
                            {origin.latitude.toFixed(4)}°, {origin.longitude.toFixed(4)}°
                          </span>
                        </span>
                        <span>Corridor origin anchor</span>
                      </div>
                    </div>
                  </li>
                )}
              </ol>
            </div>

            {/* Section 4: Cargo Manifest & Corridor Weather */}
            <div className="px-5 py-4 space-y-4">
              <div>
                <h4 className="mb-2 text-xs font-medium text-ink-2">
                  Cargo manifest ({items.length})
                </h4>
                <div className="divide-y divide-line border-t border-line text-xs">
                  {items.map((item) => (
                    <div key={item.id} className="flex items-center justify-between py-2">
                      <div>
                        <span className="text-ink">{item.description}</span>
                        <span className="ml-2 font-mono text-[11px] text-ink-3">
                          ×{item.quantity}
                        </span>
                      </div>
                      <span className="font-mono text-ink-2 tabular-nums">
                        {item.weight_kg.toLocaleString()} kg · {item.volume_m3} m³
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="border-t border-line pt-3.5">
                <div className="mb-2 flex items-center justify-between text-xs">
                  <span className="inline-flex items-center gap-1.5 text-ink-2">
                    <CloudSun className="size-3.5 text-ink-3" strokeWidth={1.5} />
                    <span>Corridor weather</span>
                  </span>
                  <span className="text-[11px] text-ink-3">
                    Open-Meteo ({weather.source === 'live' ? 'live' : 'cached'} · informational)
                  </span>
                </div>
                <div className="grid grid-cols-3 divide-x divide-line rounded-md border border-line bg-surface text-xs">
                  <div className="p-2.5">
                    <div className="text-[11px] text-ink-3">{origin.code}</div>
                    <div className="mt-0.5 font-mono text-xs font-medium text-ink tabular-nums">
                      {weather.loading ? '—' : `${weather.origin?.temperatureC}°C`}
                    </div>
                    <div className="truncate text-[10px] text-ink-3">
                      {weather.origin?.conditionLabel ?? ''}
                    </div>
                  </div>
                  <div className="p-2.5">
                    <div className="text-[11px] text-ink-3">Current</div>
                    <div className="mt-0.5 font-mono text-xs font-medium text-ink tabular-nums">
                      {weather.loading ? '—' : `${weather.current?.temperatureC}°C`}
                    </div>
                    <div className="truncate text-[10px] text-ink-3">
                      {weather.current?.conditionLabel ?? ''}
                    </div>
                  </div>
                  <div className="p-2.5">
                    <div className="text-[11px] text-ink-3">{destination.code}</div>
                    <div className="mt-0.5 font-mono text-xs font-medium text-ink tabular-nums">
                      {weather.loading ? '—' : `${weather.destination?.temperatureC}°C`}
                    </div>
                    <div className="truncate text-[10px] text-ink-3">
                      {weather.destination?.conditionLabel ?? ''}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          {/* Right Column: Full-Height Cartographic Map */}
          <section className="isolate relative z-0 flex flex-col bg-map-bg lg:min-h-[420px]">
            <div className="relative h-[360px] w-full sm:h-[420px] lg:h-auto lg:flex-1">
              <WorldMap shipments={[mapEntry]} focusId={shipment.id} />

              {/* Quiet Map Legend */}
              <div className="pointer-events-none absolute right-2.5 top-2.5 z-[400] flex max-w-[calc(100%-1.25rem)] flex-wrap items-center gap-2.5 rounded-md border border-line-strong bg-overlay px-2.5 py-1.5 text-[11px] font-medium text-ink shadow-md sm:right-4 sm:top-4 sm:gap-4 sm:px-3 sm:text-xs">
                <span className="inline-flex items-center gap-1.5">
                  <span className="inline-block h-[2.5px] w-3.5 bg-[#080B0C] dark:bg-[#C6F432] sm:w-4" />
                  Completed leg
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <span className="inline-block h-0 w-3.5 border-t-2 border-dashed border-ink-2 sm:w-4" />
                  Remaining leg
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <span className="inline-block size-2 rounded-full border border-[#080B0C] bg-[#C6F432]" />
                  Transit stop ({transitStops.length})
                </span>
              </div>
            </div>

            {/* Compact Current Position Telemetry Overlay (stacked cleanly below map on mobile, floating on desktop) */}
            {mapEntry.current_position && (
              <div className="border-t border-line bg-overlay px-3.5 py-2.5 text-xs md:pointer-events-none md:absolute md:bottom-4 md:left-4 md:z-[400] md:max-w-md md:rounded-md md:border md:border-line-strong md:shadow-lg">
                <div className="flex flex-wrap items-center gap-1.5 font-mono text-[10px] font-bold text-ink-2">
                  <span className="rounded border border-line bg-surface px-1.5 py-0.5 text-ink">
                    1. {origin.code}
                  </span>
                  {transitStops.map((stop, i) => (
                    <React.Fragment key={stop.id}>
                      <span>━━▶</span>
                      <span className="rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 text-[#080B0C]">
                        {i + 2}. {stop.location_name}
                      </span>
                    </React.Fragment>
                  ))}
                  <span>{shipment.current_status === ShipmentStatus.Delivered ? '━━▶' : '╌╌▶'}</span>
                  <span
                    className={`rounded px-1.5 py-0.5 ${
                      shipment.current_status === ShipmentStatus.Delivered
                        ? 'border border-[#080B0C] bg-[#C6F432] text-[#080B0C]'
                        : 'border border-line bg-surface text-ink'
                    }`}
                  >
                    {transitStops.length + 2}. {destination.code}
                  </span>
                </div>
                <div className="mt-1 font-semibold text-ink">
                  Latest telemetry: {mapEntry.current_position.location_name}
                </div>
                <div className="mt-0.5 font-mono text-[11px] font-medium text-ink-2 tabular-nums">
                  {mapEntry.current_position.latitude.toFixed(4)}°,{' '}
                  {mapEntry.current_position.longitude.toFixed(4)}°
                  {mapEntry.current_position.time ? ` · ${mapEntry.current_position.time}` : ''}
                </div>
              </div>
            )}
          </section>
        </div>

        {/* Printable Official Bill of Lading (e-B/L) & Cargo Manifest Modal */}
        {showBillModal && (
          <div className="fixed inset-0 z-[1500] flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
            <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg border-2 border-[#080B0C] bg-white p-6 text-[#080B0C] shadow-2xl">
              <div className="flex flex-wrap items-start justify-between gap-3 border-b-2 border-[#080B0C] pb-4">
                <div>
                  <div className="inline-flex items-center gap-2 rounded bg-[#C6F432] px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-[#080B0C]">
                    Official Electronic Bill of Lading (e-B/L)
                  </div>
                  <h2 className="mt-1.5 font-mono text-xl font-bold tracking-tight text-[#080B0C]">
                    WAYBILL #{shipment.shipment_number}
                  </h2>
                  <p className="text-xs font-medium text-[#2A3338]">
                    CargoTrace Supply Chain Corridor Telemetry · Issued{' '}
                    {formatDateTime(shipment.created_at, true)}
                  </p>
                </div>
                <div className="flex items-center gap-2 print:hidden">
                  <button
                    type="button"
                    onClick={() => window.print()}
                    className="inline-flex h-8 items-center gap-1.5 rounded-md border border-[#080B0C] bg-[#C6F432] px-3 text-xs font-bold text-[#080B0C] hover:bg-[#b5e422] cursor-pointer"
                  >
                    <Printer className="size-3.5" strokeWidth={1.75} />
                    Print / Save PDF
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowBillModal(false)}
                    className="inline-flex h-8 items-center gap-1 rounded-md border border-[#CBD5E1] bg-white px-2.5 text-xs font-semibold text-[#080B0C] hover:bg-[#F3F5F6] cursor-pointer"
                  >
                    <X className="size-3.5" strokeWidth={1.75} />
                    Close
                  </button>
                </div>
              </div>

              {/* Parties & Carrier Metadata */}
              <div className="mt-4 grid gap-4 border-b border-[#D0D7DE] pb-4 sm:grid-cols-3 text-xs">
                <div>
                  <div className="font-mono text-[10px] font-bold uppercase text-[#526068]">
                    Consignee / Customer
                  </div>
                  <div className="mt-1 font-bold text-[#080B0C]">
                    {customer?.name ?? 'Assigned Consignee'}
                  </div>
                  <div className="text-[11px] text-[#2A3338]">{customer?.email ?? '—'}</div>
                </div>
                <div>
                  <div className="font-mono text-[10px] font-bold uppercase text-[#526068]">
                    Assigned Carrier &amp; Mode
                  </div>
                  <div className="mt-1 font-bold text-[#080B0C]">{carrier.name}</div>
                  <div className="font-mono text-[11px] text-[#2A3338]">
                    Code: {carrier.code} · Mode: {carrier.service_type}
                  </div>
                </div>
                <div>
                  <div className="font-mono text-[10px] font-bold uppercase text-[#526068]">
                    Lifecycle &amp; Cargo Class
                  </div>
                  <div className="mt-1 font-bold text-[#080B0C]">
                    {statusLabel(shipment.current_status).toUpperCase()}
                  </div>
                  <div className="text-[11px] text-[#2A3338]">
                    Commodity: {shipment.cargo_type}
                  </div>
                </div>
              </div>

              {/* Multi-Leg Route Chain (Origin -> Checkpoints -> Destination) */}
              <div className="mt-4 border-b border-[#D0D7DE] pb-4 text-xs">
                <div className="font-mono text-[10px] font-bold uppercase text-[#526068]">
                  Corridor Routing &amp; Schedule
                </div>
                <div className="mt-2 grid gap-3 sm:grid-cols-3">
                  <div className="rounded border border-[#D0D7DE] bg-[#F8FAFC] p-3">
                    <div className="font-mono text-[10px] font-bold text-[#526068]">
                      1. PORT OF LOADING (ORIGIN)
                    </div>
                    <div className="mt-1 font-bold text-[#080B0C]">
                      {origin.code} — {origin.name}
                    </div>
                    <div className="text-[11px] text-[#2A3338]">
                      {origin.city}, {origin.country}
                    </div>
                    <div className="mt-1.5 font-mono text-[11px] text-[#080B0C]">
                      Dep: {formatDateTime(shipment.expected_departure_at, true)}
                    </div>
                  </div>

                  <div className="rounded border border-[#D0D7DE] bg-[#F8FAFC] p-3">
                    <div className="font-mono text-[10px] font-bold text-[#526068]">
                      2. TRANSIT CHECKPOINTS ({events.filter((e) => e.event_type === 'CHECKPOINT').length})
                    </div>
                    {events.filter((e) => e.event_type === 'CHECKPOINT').length === 0 ? (
                      <div className="mt-1 text-[11px] text-[#526068]">
                        Direct corridor routing (no intermediate stops recorded)
                      </div>
                    ) : (
                      <div className="mt-1 space-y-1">
                        {events
                          .filter((e) => e.event_type === 'CHECKPOINT')
                          .map((cp) => (
                            <div key={cp.id} className="text-[11px] font-semibold text-[#080B0C]">
                              ● {cp.location_name}{' '}
                              <span className="font-mono text-[10px] font-normal text-[#526068]">
                                ({formatDateTime(cp.occurred_at)})
                              </span>
                            </div>
                          ))}
                      </div>
                    )}
                  </div>

                  <div className="rounded border border-[#D0D7DE] bg-[#F8FAFC] p-3">
                    <div className="font-mono text-[10px] font-bold text-[#526068]">
                      3. PORT OF DISCHARGE (DESTINATION)
                    </div>
                    <div className="mt-1 font-bold text-[#080B0C]">
                      {destination.code} — {destination.name}
                    </div>
                    <div className="text-[11px] text-[#2A3338]">
                      {destination.city}, {destination.country}
                    </div>
                    <div className="mt-1.5 font-mono text-[11px] text-[#080B0C]">
                      ETA:{' '}
                      {formatDateTime(
                        shipment.estimated_arrival_at ?? shipment.expected_arrival_at,
                        true,
                      )}
                    </div>
                  </div>
                </div>
              </div>

              {/* Cargo Manifest Table */}
              <div className="mt-4">
                <div className="mb-2 flex items-center justify-between">
                  <span className="font-mono text-[10px] font-bold uppercase text-[#526068]">
                    Itemized Cargo Manifest ({items.length} line items)
                  </span>
                  <span className="font-mono text-xs font-bold text-[#080B0C]">
                    Declared Total: {shipment.weight_kg.toLocaleString()} kg · {shipment.volume_m3}{' '}
                    m³
                  </span>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full border-collapse border border-[#D0D7DE] text-left text-xs">
                    <thead>
                      <tr className="border-b border-[#D0D7DE] bg-[#F3F5F6] font-mono text-[11px] text-[#2A3338]">
                        <th className="px-3 py-2">#</th>
                        <th className="px-3 py-2">Description of Goods</th>
                        <th className="px-3 py-2 text-right">Qty</th>
                        <th className="px-3 py-2 text-right">Gross Weight (kg)</th>
                        <th className="px-3 py-2 text-right">Volume (m³)</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[#D0D7DE]">
                      {items.map((item, idx) => (
                        <tr key={item.id}>
                          <td className="px-3 py-2 font-mono text-[#526068]">{idx + 1}</td>
                          <td className="px-3 py-2 font-semibold text-[#080B0C]">
                            {item.description}
                          </td>
                          <td className="px-3 py-2 text-right font-mono text-[#080B0C]">
                            {item.quantity}
                          </td>
                          <td className="px-3 py-2 text-right font-mono text-[#080B0C]">
                            {item.weight_kg.toLocaleString()}
                          </td>
                          <td className="px-3 py-2 text-right font-mono text-[#080B0C]">
                            {item.volume_m3}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="mt-5 flex items-center justify-between border-t border-[#D0D7DE] pt-3 text-[11px] text-[#526068]">
                <span>
                  Verified by CargoTrace Corridor Control · Delay Rule: {state.scenarioConfig.groupCode}
                </span>
                <span className="font-mono font-bold text-[#080B0C]">
                  AUTHENTICATED WAYBILL #{shipment.shipment_number}
                </span>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
};
