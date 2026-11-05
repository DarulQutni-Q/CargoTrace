import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ChevronDown, Compass, MapPin, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import { PORT_HUB_PRESETS, PortHubPreset } from '../domain/indonesiaLocations';
import { haversineKm } from '../domain/rules';
import { useStore } from '../domain/store';
import { UserRole } from '../domain/types';

function toLocalDateTime(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const yyyy = d.getUTCFullYear();
  const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(d.getUTCDate()).padStart(2, '0');
  const hh = String(d.getUTCHours()).padStart(2, '0');
  const min = String(d.getUTCMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
}

interface UnifiedPortOption {
  locationId: number | null;
  name: string;
  code: string;
  city: string;
  province?: string;
  country: string;
  latitude: number;
  longitude: number;
  isRegistered: boolean;
}

export const ShipmentFormView: React.FC<{ editId?: number }> = ({ editId }) => {
  const { state, currentUser, navigate, createShipment, updateShipment, ensureLocationPreset } =
    useStore();
  const existing = editId ? state.shipments.find((s) => s.id === editId) : undefined;
  const existingItems = editId ? state.items.filter((i) => i.shipment_id === editId) : [];

  const isCustomerRole = currentUser?.role === UserRole.Customer;
  const customers = state.users.filter((u) => u.role === UserRole.Customer);
  const activeCarriers = state.carriers.filter((c) => c.is_active);
  const locations = state.locations;

  const [customerId, setCustomerId] = useState<string>(
    existing
      ? String(existing.customer_id)
      : isCustomerRole && currentUser
      ? String(currentUser.id)
      : String(customers[0]?.id ?? ''),
  );
  const [carrierId, setCarrierId] = useState<string>(
    existing ? String(existing.carrier_id) : String(activeCarriers[0]?.id ?? ''),
  );
  const [originId, setOriginId] = useState<string>(
    existing ? String(existing.origin_location_id) : String(locations[0]?.id ?? ''),
  );
  const [destinationId, setDestinationId] = useState<string>(
    existing ? String(existing.destination_location_id) : String(locations[4]?.id ?? ''),
  );

  // Existing intermediate transit stop if editing a shipment that already has a CHECKPOINT
  const existingCheckpoint = useMemo(() => {
    if (!editId) return null;
    const cp = state.trackingEvents
      .filter(
        (e) =>
          e.shipment_id === editId &&
          e.event_type === 'CHECKPOINT' &&
          e.latitude !== null &&
          e.longitude !== null,
      )
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime())[0];
    if (!cp || cp.latitude === null || cp.longitude === null) return null;
    return {
      location_name: cp.location_name,
      latitude: cp.latitude,
      longitude: cp.longitude,
    };
  }, [editId, state.trackingEvents]);

  // Searchable Origin, Transit Stop & Destination combobox states
  const [originQuery, setOriginQuery] = useState<string>('');
  const [originOpen, setOriginOpen] = useState<boolean>(false);
  const [destQuery, setDestQuery] = useState<string>('');
  const [destOpen, setDestOpen] = useState<boolean>(false);
  const [transitStop, setTransitStop] = useState<{
    location_name: string;
    latitude: number;
    longitude: number;
  } | null>(existingCheckpoint);
  const [transitQuery, setTransitQuery] = useState<string>('');
  const [transitOpen, setTransitOpen] = useState<boolean>(false);

  const originRef = useRef<HTMLDivElement>(null);
  const destRef = useRef<HTMLDivElement>(null);
  const transitRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onClickOutside = (e: MouseEvent) => {
      if (originRef.current && !originRef.current.contains(e.target as Node)) {
        setOriginOpen(false);
      }
      if (destRef.current && !destRef.current.contains(e.target as Node)) {
        setDestOpen(false);
      }
      if (transitRef.current && !transitRef.current.contains(e.target as Node)) {
        setTransitOpen(false);
      }
    };
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, []);

  const [cargoType, setCargoType] = useState<string>(existing?.cargo_type ?? '');
  const [weightKg, setWeightKg] = useState<string>(existing ? String(existing.weight_kg) : '500');
  const [volumeM3, setVolumeM3] = useState<string>(existing ? String(existing.volume_m3) : '8');
  const [expectedDep, setExpectedDep] = useState<string>(
    existing ? toLocalDateTime(existing.expected_departure_at) : '2026-11-06T09:00',
  );
  const [expectedArr, setExpectedArr] = useState<string>(
    existing ? toLocalDateTime(existing.expected_arrival_at) : '2026-11-22T14:00',
  );
  const [estimatedArr, setEstimatedArr] = useState<string>(
    existing ? toLocalDateTime(existing.estimated_arrival_at) : '',
  );

  const [items, setItems] = useState<
    Array<{ description: string; quantity: string; weight_kg: string; volume_m3: string }>
  >(
    existingItems.length > 0
      ? existingItems.map((i) => ({
          description: i.description,
          quantity: String(i.quantity),
          weight_kg: String(i.weight_kg),
          volume_m3: String(i.volume_m3),
        }))
      : [{ description: '', quantity: '10', weight_kg: '500', volume_m3: '8' }],
  );

  const [errors, setErrors] = useState<Record<string, string>>({});

  // Build unified list of active state.locations + all Indonesian Kabupaten/Provinsi & Global presets
  const allPortOptions: UnifiedPortOption[] = useMemo(() => {
    const byCode = new Map<string, UnifiedPortOption>();
    locations.forEach((loc) => {
      byCode.set(loc.code.toUpperCase(), {
        locationId: loc.id,
        name: loc.name,
        code: loc.code,
        city: loc.city,
        country: loc.country,
        latitude: loc.latitude,
        longitude: loc.longitude,
        isRegistered: true,
      });
    });
    PORT_HUB_PRESETS.forEach((preset: PortHubPreset) => {
      const code = preset.code.toUpperCase();
      if (!byCode.has(code)) {
        byCode.set(code, {
          locationId: null,
          name: preset.name,
          code: preset.code,
          city: preset.city,
          province: preset.province,
          country: preset.country,
          latitude: preset.latitude,
          longitude: preset.longitude,
          isRegistered: false,
        });
      }
    });
    return Array.from(byCode.values());
  }, [locations]);

  const filterPortOptions = (query: string): UnifiedPortOption[] => {
    const q = query.trim().toLowerCase();
    if (!q) return allPortOptions.slice(0, 24);
    const starts = allPortOptions.filter(
      (opt) =>
        opt.city.toLowerCase().startsWith(q) ||
        opt.code.toLowerCase().startsWith(q) ||
        opt.name.toLowerCase().startsWith(q) ||
        (opt.province && opt.province.toLowerCase().startsWith(q)),
    );
    const contains = allPortOptions.filter(
      (opt) =>
        !starts.includes(opt) &&
        (opt.city.toLowerCase().includes(q) ||
          opt.name.toLowerCase().includes(q) ||
          opt.code.toLowerCase().includes(q) ||
          (opt.province && opt.province.toLowerCase().includes(q)) ||
          opt.country.toLowerCase().includes(q)),
    );
    return [...starts, ...contains].slice(0, 24);
  };

  const selectedOrigin = useMemo(
    () => locations.find((l) => String(l.id) === originId) ?? null,
    [locations, originId],
  );
  const selectedDestination = useMemo(
    () => locations.find((l) => String(l.id) === destinationId) ?? null,
    [locations, destinationId],
  );
  const selectedCarrier = useMemo(
    () => activeCarriers.find((c) => String(c.id) === carrierId) ?? null,
    [activeCarriers, carrierId],
  );

  // Great-circle corridor distance & estimated transit duration (including optional transit stop)
  const corridorStats = useMemo(() => {
    if (!selectedOrigin || !selectedDestination) return null;
    const km = transitStop
      ? haversineKm(
          [selectedOrigin.latitude, selectedOrigin.longitude],
          [transitStop.latitude, transitStop.longitude],
        ) +
        haversineKm(
          [transitStop.latitude, transitStop.longitude],
          [selectedDestination.latitude, selectedDestination.longitude],
        )
      : haversineKm(
          [selectedOrigin.latitude, selectedOrigin.longitude],
          [selectedDestination.latitude, selectedDestination.longitude],
        );
    const nm = Math.round(km * 0.539957);
    const mode = selectedCarrier?.service_type ?? 'SEA';
    const stopBufferHours = transitStop ? 12 : 0;
    const transitHours =
      (mode === 'AIR'
        ? Math.max(6, Math.round(km / 650 + 10))
        : mode === 'ROAD'
        ? Math.max(8, Math.round(km / 52 + 8))
        : Math.max(24, Math.round((km * 1.25) / 32 + 24))) + stopBufferHours;
    return {
      km: Math.round(km),
      nm,
      mode,
      transitHours,
    };
  }, [selectedOrigin, selectedDestination, selectedCarrier, transitStop]);

  const applySuggestedArrival = () => {
    if (!corridorStats || !expectedDep) return;
    const depMs = new Date(`${expectedDep}:00.000Z`).getTime();
    if (Number.isNaN(depMs)) return;
    const suggestedIso = new Date(depMs + corridorStats.transitHours * 3600000).toISOString();
    setExpectedArr(toLocalDateTime(suggestedIso));
  };

  const handleSelectPortOption = (
    opt: UnifiedPortOption,
    target: 'origin' | 'destination' | 'transit',
  ) => {
    if (target === 'transit') {
      setTransitStop({
        location_name: `${opt.name} (${opt.code})`,
        latitude: opt.latitude,
        longitude: opt.longitude,
      });
      setTransitQuery('');
      setTransitOpen(false);
      return;
    }
    let resolvedId = opt.locationId;
    if (resolvedId === null) {
      resolvedId = ensureLocationPreset({
        name: opt.name,
        code: opt.code,
        city: opt.province ? `${opt.city}, ${opt.province}` : opt.city,
        country: opt.country,
        latitude: opt.latitude,
        longitude: opt.longitude,
      });
    }
    if (target === 'origin') {
      setOriginId(String(resolvedId));
      setOriginQuery('');
      setOriginOpen(false);
    } else {
      setDestinationId(String(resolvedId));
      setDestQuery('');
      setDestOpen(false);
    }
  };

  const manifestTotals = useMemo(() => {
    const sumWeight = items.reduce((acc, row) => acc + (Number(row.weight_kg) || 0), 0);
    const sumVolume = items.reduce((acc, row) => acc + (Number(row.volume_m3) || 0), 0);
    return {
      weight: Math.round(sumWeight * 100) / 100,
      volume: Math.round(sumVolume * 100) / 100,
    };
  }, [items]);

  const syncTotalsFromItems = (
    nextRows: Array<{ description: string; quantity: string; weight_kg: string; volume_m3: string }>,
  ) => {
    const sumWeight = nextRows.reduce((acc, row) => acc + (Number(row.weight_kg) || 0), 0);
    const sumVolume = nextRows.reduce((acc, row) => acc + (Number(row.volume_m3) || 0), 0);
    if (sumWeight > 0) setWeightKg(String(Math.round(sumWeight * 100) / 100));
    if (sumVolume > 0) setVolumeM3(String(Math.round(sumVolume * 100) / 100));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const nextErrors: Record<string, string> = {};
    if (!customerId) nextErrors.customer_id = 'Select a customer.';
    if (!carrierId) nextErrors.carrier_id = 'Select a carrier.';
    if (!originId) nextErrors.origin_location_id = 'Select an origin.';
    if (!destinationId) nextErrors.destination_location_id = 'Select a destination.';
    if (originId && destinationId && originId === destinationId) {
      nextErrors.destination_location_id = 'Destination must differ from origin.';
    }
    if (!cargoType.trim()) nextErrors.cargo_type = 'Cargo type is required.';
    if (!weightKg || Number(weightKg) <= 0) nextErrors.weight_kg = 'Positive weight is required.';
    if (!volumeM3 || Number(volumeM3) <= 0) nextErrors.volume_m3 = 'Positive volume is required.';
    if (!expectedDep) nextErrors.expected_departure_at = 'Expected departure is required.';
    if (!expectedArr) nextErrors.expected_arrival_at = 'Expected arrival is required.';
    if (items.some((i) => !i.description.trim())) {
      nextErrors.items = 'Every item needs a description.';
    }

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    const payload = {
      customer_id: Number(customerId),
      carrier_id: Number(carrierId),
      origin_location_id: Number(originId),
      destination_location_id: Number(destinationId),
      cargo_type: cargoType.trim(),
      weight_kg: Number(weightKg),
      volume_m3: Number(volumeM3),
      expected_departure_at: `${expectedDep}:00.000Z`,
      expected_arrival_at: `${expectedArr}:00.000Z`,
      estimated_arrival_at: estimatedArr ? `${estimatedArr}:00.000Z` : null,
      transit_stop: transitStop,
    };

    const mappedItems = items.map((i) => ({
      description: i.description.trim(),
      quantity: Number(i.quantity) || 1,
      weight_kg: Number(i.weight_kg) || 0,
      volume_m3: Number(i.volume_m3),
    }));

    if (existing) {
      updateShipment(existing.id, payload, mappedItems);
      navigate(`/shipments/${existing.id}`);
    } else {
      const created = createShipment(payload, mappedItems);
      navigate(`/shipments/${created.id}`);
    }
  };

  return (
    <AppLayout
      title={
        existing
          ? `Edit ${existing.shipment_number}`
          : isCustomerRole
          ? 'Request shipment booking'
          : 'New shipment'
      }
      subtitle={
        isCustomerRole
          ? 'Submit a new cargo order under your Customer account (initial status: CREATED)'
          : undefined
      }
    >
      <div className="mx-auto max-w-3xl p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <section className="rounded-lg border border-line bg-surface p-6">
            <h2 className="text-sm font-semibold text-ink">
              1. Corridor route &amp; parties
            </h2>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Customer / consignee
                </label>
                {isCustomerRole && currentUser ? (
                  <div className="flex h-9 w-full items-center justify-between rounded-md border border-line bg-raised px-3 text-xs font-semibold text-ink">
                    <span className="truncate">
                      {currentUser.name} ({currentUser.email})
                    </span>
                    <span className="ml-2 shrink-0 rounded bg-[#C6F432] px-1.5 py-0.5 font-mono text-[10px] font-bold text-[#080B0C]">
                      YOUR ACCOUNT
                    </span>
                  </div>
                ) : (
                  <select
                    value={customerId}
                    onChange={(e) => setCustomerId(e.target.value)}
                    className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs font-medium text-ink"
                  >
                    <option value="">Select customer</option>
                    {customers.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.email})
                      </option>
                    ))}
                  </select>
                )}
                {errors.customer_id && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {errors.customer_id}
                  </p>
                )}
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">Carrier</label>
                <select
                  value={carrierId}
                  onChange={(e) => setCarrierId(e.target.value)}
                  className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs font-medium text-ink"
                >
                  <option value="">Select carrier</option>
                  {activeCarriers.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} ({c.code} · {c.service_type})
                    </option>
                  ))}
                </select>
                {errors.carrier_id && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {errors.carrier_id}
                  </p>
                )}
              </div>

              {/* Searchable Origin Port / Kabupaten / Provinsi Combobox */}
              <div ref={originRef} className="relative">
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Origin port / Kabupaten / Hub *
                </label>
                <div className="relative">
                  <input
                    type="text"
                    value={
                      originOpen
                        ? originQuery
                        : selectedOrigin
                        ? `${selectedOrigin.code} — ${selectedOrigin.city}, ${selectedOrigin.country}`
                        : originQuery
                    }
                    onFocus={() => {
                      setOriginQuery('');
                      setOriginOpen(true);
                    }}
                    onChange={(e) => {
                      setOriginQuery(e.target.value);
                      setOriginOpen(true);
                    }}
                    placeholder="Search Bireuen, Krueng Geukuh, Lhokseumawe, Jakarta..."
                    aria-label="Origin port or hub"
                    className="h-9 w-full rounded-md border border-line bg-base pl-3 pr-8 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
                  />
                  <button
                    type="button"
                    tabIndex={-1}
                    onClick={() => setOriginOpen((o) => !o)}
                    className="absolute inset-y-0 right-0 flex items-center pr-2.5 text-ink-3 hover:text-ink cursor-pointer"
                  >
                    <ChevronDown className="size-3.5" strokeWidth={1.5} />
                  </button>
                </div>
                {errors.origin_location_id && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {errors.origin_location_id}
                  </p>
                )}

                {originOpen && (
                  <div className="absolute left-0 right-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-md border border-line-strong bg-overlay py-1 shadow-2xl">
                    <div className="border-b border-line px-3 py-1 text-[10px] font-medium text-ink-3">
                      Search 38 Indonesian provinces (Bireuen, Krueng Geukuh, Lhokseumawe...) &amp; global ports
                    </div>
                    {filterPortOptions(originQuery).map((opt) => (
                      <button
                        key={`orig-${opt.code}-${opt.name}`}
                        type="button"
                        onClick={() => handleSelectPortOption(opt, 'origin')}
                        className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors hover:bg-tint cursor-pointer"
                      >
                        <div className="flex min-w-0 items-center gap-2">
                          <MapPin className="size-3.5 shrink-0 text-ink-3" strokeWidth={1.5} />
                          <div className="min-w-0">
                            <div className="truncate font-semibold text-ink">
                              {opt.code} — {opt.city}
                              {opt.province ? `, ${opt.province}` : ''}
                            </div>
                            <div className="truncate text-[11px] text-ink-3">{opt.name}</div>
                          </div>
                        </div>
                        <span className="shrink-0 font-mono text-[10px] font-medium text-ink-3">
                          {opt.country}
                        </span>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* Searchable Destination Port / Kabupaten / Provinsi Combobox */}
              <div ref={destRef} className="relative">
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Destination port / Kabupaten / Hub *
                </label>
                <div className="relative">
                  <input
                    type="text"
                    value={
                      destOpen
                        ? destQuery
                        : selectedDestination
                        ? `${selectedDestination.code} — ${selectedDestination.city}, ${selectedDestination.country}`
                        : destQuery
                    }
                    onFocus={() => {
                      setDestQuery('');
                      setDestOpen(true);
                    }}
                    onChange={(e) => {
                      setDestQuery(e.target.value);
                      setDestOpen(true);
                    }}
                    placeholder="Search Surabaya, Rotterdam, Bireuen, Lhokseumawe..."
                    aria-label="Destination port or hub"
                    className="h-9 w-full rounded-md border border-line bg-base pl-3 pr-8 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
                  />
                  <button
                    type="button"
                    tabIndex={-1}
                    onClick={() => setDestOpen((o) => !o)}
                    className="absolute inset-y-0 right-0 flex items-center pr-2.5 text-ink-3 hover:text-ink cursor-pointer"
                  >
                    <ChevronDown className="size-3.5" strokeWidth={1.5} />
                  </button>
                </div>
                {errors.destination_location_id && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {errors.destination_location_id}
                  </p>
                )}

                {destOpen && (
                  <div className="absolute left-0 right-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-md border border-line-strong bg-overlay py-1 shadow-2xl">
                    <div className="border-b border-line px-3 py-1 text-[10px] font-medium text-ink-3">
                      Search 38 Indonesian provinces (Bireuen, Krueng Geukuh, Lhokseumawe...) &amp; global ports
                    </div>
                    {filterPortOptions(destQuery).map((opt) => (
                      <button
                        key={`dest-${opt.code}-${opt.name}`}
                        type="button"
                        onClick={() => handleSelectPortOption(opt, 'destination')}
                        className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors hover:bg-tint cursor-pointer"
                      >
                        <div className="flex min-w-0 items-center gap-2">
                          <MapPin className="size-3.5 shrink-0 text-ink-3" strokeWidth={1.5} />
                          <div className="min-w-0">
                            <div className="truncate font-semibold text-ink">
                              {opt.code} — {opt.city}
                              {opt.province ? `, ${opt.province}` : ''}
                            </div>
                            <div className="truncate text-[11px] text-ink-3">{opt.name}</div>
                          </div>
                        </div>
                        <span className="shrink-0 font-mono text-[10px] font-medium text-ink-3">
                          {opt.country}
                        </span>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* Optional Planned Transit Stop / Via Hub Combobox (e.g. KRG -> Port of Singapore -> SUB) */}
              <div ref={transitRef} className="relative sm:col-span-2">
                <div className="mb-1.5 flex items-center justify-between">
                  <label className="block text-xs font-medium text-ink-2">
                    Intermediate transit stop / Via hub (optional — e.g. Port of Singapore, Sabang, Tanjung Priok)
                  </label>
                  {transitStop && (
                    <button
                      type="button"
                      onClick={() => {
                        setTransitStop(null);
                        setTransitQuery('');
                      }}
                      className="text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:underline cursor-pointer"
                    >
                      × Clear transit stop
                    </button>
                  )}
                </div>
                <div className="relative">
                  <input
                    type="text"
                    value={
                      transitOpen
                        ? transitQuery
                        : transitStop
                        ? `${transitStop.location_name} (${transitStop.latitude.toFixed(2)}°, ${transitStop.longitude.toFixed(2)}°)`
                        : transitQuery
                    }
                    onFocus={() => {
                      setTransitQuery('');
                      setTransitOpen(true);
                    }}
                    onChange={(e) => {
                      setTransitQuery(e.target.value);
                      setTransitOpen(true);
                    }}
                    placeholder="Optional mid-route checkpoint (search Singapore, Sabang, Belawan, Batam, Colombo...)"
                    aria-label="Intermediate transit stop or via hub"
                    className="h-9 w-full rounded-md border border-line bg-base pl-3 pr-8 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
                  />
                  <button
                    type="button"
                    tabIndex={-1}
                    onClick={() => setTransitOpen((o) => !o)}
                    className="absolute inset-y-0 right-0 flex items-center pr-2.5 text-ink-3 hover:text-ink cursor-pointer"
                  >
                    <ChevronDown className="size-3.5" strokeWidth={1.5} />
                  </button>
                </div>

                {transitOpen && (
                  <div className="absolute left-0 right-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-md border border-line-strong bg-overlay py-1 shadow-2xl">
                    <div className="border-b border-line px-3 py-1 text-[10px] font-medium text-ink-3">
                      Select an intermediate transshipment hub between Origin and Destination
                    </div>
                    {filterPortOptions(transitQuery).map((opt) => (
                      <button
                        key={`transit-${opt.code}-${opt.name}`}
                        type="button"
                        onClick={() => handleSelectPortOption(opt, 'transit')}
                        className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors hover:bg-tint cursor-pointer"
                      >
                        <div className="flex min-w-0 items-center gap-2">
                          <MapPin className="size-3.5 shrink-0 text-ink-3" strokeWidth={1.5} />
                          <div className="min-w-0">
                            <div className="truncate font-semibold text-ink">
                              {opt.code} — {opt.city}
                              {opt.province ? `, ${opt.province}` : ''}
                            </div>
                            <div className="truncate text-[11px] text-ink-3">{opt.name}</div>
                          </div>
                        </div>
                        <span className="shrink-0 font-mono text-[10px] font-medium text-ink-3">
                          {opt.country}
                        </span>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </div>

            {/* Live Route Trajectory & Great-Circle Corridor Telemetry Strip */}
            {corridorStats && selectedOrigin && selectedDestination && (
              <div className="mt-4 space-y-2 rounded-md border border-line bg-base px-3.5 py-2.5 text-xs">
                <div className="flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                  <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-bold text-ink">
                    {selectedOrigin.code} · {selectedOrigin.city}
                  </span>
                  {transitStop ? (
                    <>
                      <span className="font-bold text-ink-2">━━▶</span>
                      <span className="rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-bold text-[#080B0C]">
                        ● {transitStop.location_name}
                      </span>
                      <span className="font-bold text-ink-3">╌╌▶</span>
                    </>
                  ) : (
                    <span className="font-bold text-ink-2">━━▶</span>
                  )}
                  <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-bold text-ink">
                    {selectedDestination.code} · {selectedDestination.city}
                  </span>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2">
                  <div className="flex items-center gap-2 text-ink-2">
                    <Compass className="size-4 text-ink-3" strokeWidth={1.75} />
                    <span>
                      Corridor distance:{' '}
                      <span className="font-mono font-bold text-ink">
                        {corridorStats.km.toLocaleString()} km
                      </span>{' '}
                      ({corridorStats.nm.toLocaleString()} NM) · Est. {corridorStats.mode} transit:{' '}
                      <span className="font-mono font-semibold text-ink">
                        ~{corridorStats.transitHours}h
                      </span>
                    </span>
                  </div>
                  <button
                    type="button"
                    onClick={applySuggestedArrival}
                    className="font-mono text-[11px] font-semibold text-ink underline hover:opacity-80 cursor-pointer"
                  >
                    Apply ~{corridorStats.transitHours}h to expected arrival
                  </button>
                </div>
              </div>
            )}
          </section>

          <section className="rounded-lg border border-line bg-surface p-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-sm font-semibold text-ink">
                2. Cargo load &amp; schedule
              </h2>
              {(Number(weightKg) !== manifestTotals.weight ||
                Number(volumeM3) !== manifestTotals.volume) && (
                <button
                  type="button"
                  onClick={() => syncTotalsFromItems(items)}
                  className="inline-flex h-7 items-center gap-1.5 rounded-md border border-[#080B0C] bg-[#C6F432] px-2.5 text-[11px] font-semibold text-[#080B0C] hover:bg-brand-hover cursor-pointer"
                >
                  <RefreshCw className="size-3" strokeWidth={1.75} />
                  Sync with manifest ({manifestTotals.weight.toLocaleString()} kg · {manifestTotals.volume} m³)
                </button>
              )}
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">Cargo type</label>
                <input
                  type="text"
                  value={cargoType}
                  onChange={(e) => setCargoType(e.target.value)}
                  placeholder="Industrial Electronics"
                  className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs font-medium text-ink"
                />
                {errors.cargo_type && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {errors.cargo_type}
                  </p>
                )}
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Total weight (kg)
                </label>
                <input
                  type="number"
                  step="any"
                  value={weightKg}
                  onChange={(e) => setWeightKg(e.target.value)}
                  className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs font-medium text-ink"
                />
                {errors.weight_kg && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {errors.weight_kg}
                  </p>
                )}
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Total volume (m³)
                </label>
                <input
                  type="number"
                  step="any"
                  value={volumeM3}
                  onChange={(e) => setVolumeM3(e.target.value)}
                  className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs font-medium text-ink"
                />
                {errors.volume_m3 && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {errors.volume_m3}
                  </p>
                )}
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Expected departure
                </label>
                <input
                  type="datetime-local"
                  value={expectedDep}
                  onChange={(e) => setExpectedDep(e.target.value)}
                  className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs font-medium text-ink"
                />
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Expected arrival (baseline)
                </label>
                <input
                  type="datetime-local"
                  value={expectedArr}
                  onChange={(e) => setExpectedArr(e.target.value)}
                  className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs font-medium text-ink"
                />
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-ink-2">
                  Estimated arrival (live ETA)
                </label>
                <input
                  type="datetime-local"
                  value={estimatedArr}
                  onChange={(e) => setEstimatedArr(e.target.value)}
                  className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs font-medium text-ink"
                />
              </div>
            </div>
          </section>

          <section className="rounded-lg border border-line bg-surface p-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 className="text-sm font-semibold text-ink">
                  3. Cargo manifest items
                </h2>
                <p className="mt-0.5 font-mono text-[11px] font-medium text-ink-3 tabular-nums">
                  Manifest sum: {manifestTotals.weight.toLocaleString()} kg · {manifestTotals.volume} m³
                </p>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => syncTotalsFromItems(items)}
                  className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-base px-2.5 text-xs font-medium text-ink-2 hover:border-line-strong hover:text-ink cursor-pointer"
                >
                  <RefreshCw className="size-3" strokeWidth={1.5} />
                  Sync totals from items
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const next = [
                      ...items,
                      { description: '', quantity: '1', weight_kg: '100', volume_m3: '2' },
                    ];
                    setItems(next);
                    syncTotalsFromItems(next);
                  }}
                  className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-raised px-3 text-xs font-medium text-ink hover:border-line-strong cursor-pointer"
                >
                  <Plus className="size-3.5" strokeWidth={1.5} />
                  Add item
                </button>
              </div>
            </div>

            <div className="mt-4 space-y-3">
              {items.map((item, idx) => (
                <div
                  key={idx}
                  className="grid grid-cols-[1fr_5rem_6rem_6rem_2.25rem] items-end gap-2"
                >
                  <div>
                    <label className="mb-1 block text-xs font-medium text-ink-2">
                      Description
                    </label>
                    <input
                      type="text"
                      value={item.description}
                      onChange={(e) =>
                        setItems((prev) =>
                          prev.map((row, i) =>
                            i === idx ? { ...row, description: e.target.value } : row,
                          ),
                        )
                      }
                      placeholder="Pallet of automation modules"
                      className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs font-medium text-ink"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-medium text-ink-2">Qty</label>
                    <input
                      type="number"
                      min="1"
                      value={item.quantity}
                      onChange={(e) =>
                        setItems((prev) =>
                          prev.map((row, i) =>
                            i === idx ? { ...row, quantity: e.target.value } : row,
                          ),
                        )
                      }
                      className="h-9 w-full rounded-md border border-line bg-base px-2.5 font-mono text-xs font-medium text-ink"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-medium text-ink-2">Kg</label>
                    <input
                      type="number"
                      step="any"
                      value={item.weight_kg}
                      onChange={(e) => {
                        const next = items.map((row, i) =>
                          i === idx ? { ...row, weight_kg: e.target.value } : row,
                        );
                        setItems(next);
                        syncTotalsFromItems(next);
                      }}
                      className="h-9 w-full rounded-md border border-line bg-base px-2.5 font-mono text-xs font-medium text-ink"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-medium text-ink-2">m³</label>
                    <input
                      type="number"
                      step="any"
                      value={item.volume_m3}
                      onChange={(e) => {
                        const next = items.map((row, i) =>
                          i === idx ? { ...row, volume_m3: e.target.value } : row,
                        );
                        setItems(next);
                        syncTotalsFromItems(next);
                      }}
                      className="h-9 w-full rounded-md border border-line bg-base px-2.5 font-mono text-xs font-medium text-ink"
                    />
                  </div>
                  <button
                    type="button"
                    disabled={items.length <= 1}
                    onClick={() => {
                      const next = items.length > 1 ? items.filter((_, i) => i !== idx) : items;
                      setItems(next);
                      syncTotalsFromItems(next);
                    }}
                    className="flex size-9 items-center justify-center rounded-md border border-line bg-base text-ink-3 hover:text-rose-500 disabled:opacity-30 cursor-pointer"
                  >
                    <Trash2 className="size-4" strokeWidth={1.5} />
                  </button>
                </div>
              ))}
            </div>
            {errors.items && (
              <p className="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">
                {errors.items}
              </p>
            )}
          </section>

          <div className="flex items-center gap-3">
            <button
              type="submit"
              className="h-9 rounded-md bg-brand px-4 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
            >
              {existing ? 'Save changes' : 'Create shipment'}
            </button>
            <button
              type="button"
              onClick={() =>
                existing ? navigate(`/shipments/${existing.id}`) : navigate('/shipments')
              }
              className="text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};
