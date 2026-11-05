import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Check, ChevronDown, MapPin, Plus, Search, Sparkles } from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import {
  COUNTRIES,
  CountryOption,
  INDONESIAN_PROVINCES,
  PORT_HUB_PRESETS,
  PortHubPreset,
} from '../domain/indonesiaLocations';
import { useStore } from '../domain/store';

function highlightMatch(text: string, query: string): React.ReactNode {
  const q = query.trim();
  if (!q) return text;
  const idx = text.toLowerCase().indexOf(q.toLowerCase());
  if (idx === -1) return text;
  const before = text.slice(0, idx);
  const match = text.slice(idx, idx + q.length);
  const after = text.slice(idx + q.length);
  return (
    <>
      {before}
      <span className="rounded bg-[#C6F432]/45 px-0.5 font-bold text-ink underline decoration-[#080B0C] dark:decoration-[#C6F432] underline-offset-2">
        {match}
      </span>
      {after}
    </>
  );
}

export const LocationsListView: React.FC = () => {
  const { state, navigate, ensureLocationPreset } = useStore();
  const [searchQuery, setSearchQuery] = useState('');
  const [regionFilter, setRegionFilter] = useState<string>('ALL');
  const [quickAddFeedback, setQuickAddFeedback] = useState<string | null>(null);

  const filteredLocations = useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    return state.locations.filter((loc) => {
      if (regionFilter === 'ID_ALL' && loc.country.toLowerCase() !== 'indonesia') {
        return false;
      }
      if (regionFilter === 'GLOBAL' && loc.country.toLowerCase() === 'indonesia') {
        return false;
      }
      if (
        regionFilter !== 'ALL' &&
        regionFilter !== 'ID_ALL' &&
        regionFilter !== 'GLOBAL'
      ) {
        const provLower = regionFilter.toLowerCase();
        const matchesProv =
          loc.city.toLowerCase().includes(provLower) ||
          PORT_HUB_PRESETS.some(
            (p) =>
              p.code.toUpperCase() === loc.code.toUpperCase() &&
              p.province?.toLowerCase() === provLower,
          );
        if (!matchesProv) return false;
      }

      if (!q) return true;
      return (
        loc.name.toLowerCase().includes(q) ||
        loc.code.toLowerCase().includes(q) ||
        loc.city.toLowerCase().includes(q) ||
        loc.country.toLowerCase().includes(q)
      );
    });
  }, [state.locations, searchQuery, regionFilter]);

  // Suggest matching presets from the 38-Province Indonesian & Global catalog that aren't in state.locations yet
  const catalogMatches = useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    const existingCodes = new Set(state.locations.map((l) => l.code.toUpperCase()));
    let available = PORT_HUB_PRESETS.filter((p) => !existingCodes.has(p.code.toUpperCase()));

    if (regionFilter === 'ID_ALL') {
      available = available.filter((p) => p.country.toLowerCase() === 'indonesia');
    } else if (regionFilter === 'GLOBAL') {
      available = available.filter((p) => p.country.toLowerCase() !== 'indonesia');
    } else if (regionFilter !== 'ALL') {
      available = available.filter(
        (p) => p.province?.toLowerCase() === regionFilter.toLowerCase(),
      );
    }

    if (!q) {
      if (regionFilter !== 'ALL') return available.slice(0, 12);
      return available.filter((p) => p.province === 'Aceh' || p.country === 'Indonesia').slice(0, 6);
    }
    return available
      .filter(
        (p) =>
          p.name.toLowerCase().includes(q) ||
          p.city.toLowerCase().includes(q) ||
          p.code.toLowerCase().includes(q) ||
          (p.province && p.province.toLowerCase().includes(q)) ||
          p.country.toLowerCase().includes(q),
      )
      .slice(0, 12);
  }, [searchQuery, regionFilter, state.locations]);

  const handleQuickImport = (preset: PortHubPreset) => {
    ensureLocationPreset({
      name: preset.name,
      code: preset.code,
      city: preset.province ? `${preset.city} (${preset.province})` : preset.city,
      country: preset.country,
      latitude: preset.latitude,
      longitude: preset.longitude,
    });
    setQuickAddFeedback(
      `Added ${preset.name} (${preset.code} · ${preset.city}${
        preset.province ? `, ${preset.province}` : ''
      }) to active port registry.`,
    );
  };

  return (
    <AppLayout
      title="Port locations"
      subtitle={`${state.locations.length} active ports & regional hubs (${PORT_HUB_PRESETS.length}+ searchable catalog presets)`}
    >
      <div className="mx-auto max-w-[1440px] space-y-5 p-3 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line bg-surface px-4 py-4 sm:px-5">
          <div>
            <h2 className="text-base font-semibold text-ink">
              Port, Kabupaten/Kota &amp; Terminal Registry
            </h2>
            <p className="mt-0.5 text-xs font-medium text-ink-3">
              Search active locations or 38 Indonesian provinces (Aceh: Bireuen, Krueng Geukuh, Lhokseumawe, Sabang, Malahayati, etc.)
            </p>
          </div>
          <div className="flex w-full flex-wrap items-center gap-2.5 sm:w-auto">
            <div className="relative w-full sm:w-80">
              <Search
                className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-ink-3"
                strokeWidth={1.75}
              />
              <input
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search port, code, Bireuen, Krueng Geukuh..."
                aria-label="Search port locations"
                className="h-8 w-full rounded-md border border-line bg-base pl-8 pr-3 text-xs font-medium text-ink placeholder:text-ink-3 focus:border-line-strong focus:outline-none"
              />
            </div>

            <select
              value={regionFilter}
              onChange={(e) => setRegionFilter(e.target.value)}
              aria-label="Filter by region or Indonesian province"
              className="h-8 w-full rounded-md border border-line bg-base px-2.5 text-xs font-medium text-ink focus:border-line-strong focus:outline-none sm:w-auto"
            >
              <option value="ALL">All Countries &amp; Provinces ({state.locations.length})</option>
              <option value="ID_ALL">Indonesia — All 38 Provinces</option>
              <option value="GLOBAL">International / Global Ports</option>
              <optgroup label="Filter by Provinsi Indonesia (38 Provinsi)">
                {INDONESIAN_PROVINCES.map((prov) => (
                  <option key={prov} value={prov}>
                    Provinsi {prov}
                  </option>
                ))}
              </optgroup>
            </select>

            {(searchQuery.trim() || regionFilter !== 'ALL') && (
              <button
                type="button"
                onClick={() => {
                  setSearchQuery('');
                  setRegionFilter('ALL');
                }}
                className="h-8 rounded-md border border-line bg-raised px-2.5 text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
              >
                Reset
              </button>
            )}

            <button
              type="button"
              onClick={() => navigate('/locations/create')}
              className="inline-flex h-8 items-center gap-1.5 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink transition-colors hover:bg-brand-hover cursor-pointer"
            >
              <Plus className="size-3.5" strokeWidth={2} />
              Add location
            </button>
          </div>
        </div>

        {quickAddFeedback && (
          <div className="flex items-center justify-between rounded-md border border-line-strong bg-[#F4FCE3] dark:bg-surface px-4 py-2.5 text-xs font-medium text-ink">
            <span>{quickAddFeedback}</span>
            <button
              type="button"
              onClick={() => setQuickAddFeedback(null)}
              className="font-mono text-xs font-semibold text-ink-2 hover:text-ink cursor-pointer"
            >
              Dismiss
            </button>
          </div>
        )}

        {/* Quick-Add Regional & Indonesian Kabupaten/Provinsi Catalog Strip */}
        {catalogMatches.length > 0 && (
          <div className="rounded-lg border border-line bg-surface p-4">
            <div className="mb-2.5 flex items-center justify-between gap-2">
              <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-ink">
                <Sparkles className="size-3.5 text-ink-2" strokeWidth={1.75} />
                {searchQuery.trim()
                  ? `Matching catalog hubs for "${searchQuery.trim()}" (1-click add to active registry)`
                  : regionFilter !== 'ALL' && regionFilter !== 'ID_ALL' && regionFilter !== 'GLOBAL'
                  ? `Available catalog hubs in Provinsi ${regionFilter} (1-click add)`
                  : 'Quick-add Indonesian Regional & Aceh Hubs (Bireuen, Krueng Geukuh, Lhokseumawe, Sabang)'}
              </span>
              <span className="font-mono text-[11px] font-medium text-ink-3">
                {PORT_HUB_PRESETS.length} total presets in catalog
              </span>
            </div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {catalogMatches.map((preset) => (
                <div
                  key={`${preset.code}-${preset.name}`}
                  className="flex items-center justify-between gap-2 rounded-md border border-line bg-base px-3 py-2 text-xs"
                >
                  <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                      <span className="rounded bg-tint-strong px-1.5 py-0.5 font-mono text-[10px] font-bold text-ink">
                        {highlightMatch(preset.code, searchQuery)}
                      </span>
                      <span className="truncate font-semibold text-ink">
                        {highlightMatch(preset.name, searchQuery)}
                      </span>
                    </div>
                    <p className="mt-0.5 truncate text-[11px] font-medium text-ink-3">
                      {highlightMatch(preset.city, searchQuery)}
                      {preset.province ? ` · Prov. ${preset.province}` : ` · ${preset.country}`}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => handleQuickImport(preset)}
                    className="shrink-0 rounded border border-line-strong bg-surface px-2.5 py-1 text-[11px] font-semibold text-ink transition-colors hover:bg-brand hover:text-brand-ink hover:border-transparent cursor-pointer"
                  >
                    + Add
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="overflow-hidden rounded-lg border border-line bg-surface">
          <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-base px-4 py-2.5 text-xs sm:px-5">
            <span className="font-semibold text-ink">
              Active Port Locations ({filteredLocations.length} of {state.locations.length})
            </span>
            {(searchQuery.trim() || regionFilter !== 'ALL') && (
              <span className="font-mono text-[11px] text-ink-3">
                Filtered view · {catalogMatches.length} additional catalog matches above
              </span>
            )}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line bg-base text-left text-xs font-semibold text-ink-3">
                <th className="px-5 py-3 font-semibold">Location name</th>
                <th className="px-5 py-3 font-semibold">UN/LOCODE</th>
                <th className="px-5 py-3 font-semibold">City / Kabupaten &amp; Provinsi</th>
                <th className="px-5 py-3 font-semibold">Country</th>
                <th className="px-5 py-3 font-semibold">WGS84 coordinates</th>
                <th className="px-5 py-3 text-right font-semibold">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {filteredLocations.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-8 text-center text-xs text-ink-3">
                    No active registered locations match your filter.{' '}
                    {catalogMatches.length > 0
                      ? 'Click "+ Add" on one of the matching catalog hubs above to activate it immediately.'
                      : 'Click "Add location" to register a custom port or terminal.'}
                  </td>
                </tr>
              ) : (
                filteredLocations.map((loc) => (
                  <tr key={loc.id} className="hover:bg-raised">
                    <td className="px-5 py-3 text-xs font-semibold text-ink">
                      {highlightMatch(loc.name, searchQuery)}
                    </td>
                    <td className="px-5 py-3 font-mono text-xs font-bold text-ink">
                      {highlightMatch(loc.code, searchQuery)}
                    </td>
                    <td className="px-5 py-3 text-xs font-medium text-ink-2">
                      {highlightMatch(loc.city, searchQuery)}
                    </td>
                    <td className="px-5 py-3 text-xs font-medium text-ink-2">
                      {highlightMatch(loc.country, searchQuery)}
                    </td>
                    <td className="px-5 py-3 font-mono text-xs font-medium text-ink-2 tabular-nums">
                      {loc.latitude.toFixed(4)}, {loc.longitude.toFixed(4)}
                    </td>
                    <td className="px-5 py-3 text-right">
                      <button
                        type="button"
                        onClick={() => navigate(`/locations/${loc.id}/edit`)}
                        className="text-xs font-semibold text-ink hover:underline cursor-pointer"
                      >
                        Edit
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

export const LocationFormView: React.FC<{ editId?: number }> = ({ editId }) => {
  const { state, navigate, saveLocation } = useStore();
  const existing = editId ? state.locations.find((l) => l.id === editId) : undefined;

  const [name, setName] = useState(existing?.name ?? '');
  const [code, setCode] = useState(existing?.code ?? '');
  const [city, setCity] = useState(existing?.city ?? '');
  const [provinceFilter, setProvinceFilter] = useState('');
  const [country, setCountry] = useState(existing?.country ?? 'Indonesia');
  const [latitude, setLatitude] = useState(existing ? String(existing.latitude) : '');
  const [longitude, setLongitude] = useState(existing ? String(existing.longitude) : '');
  const [geocodeStatus, setGeocodeStatus] = useState('');
  const [geocoding, setGeocoding] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Autocomplete open states & keyboard indices
  const [countryDropdownOpen, setCountryDropdownOpen] = useState(false);
  const [countryActiveIdx, setCountryActiveIdx] = useState(0);

  const [cityDropdownOpen, setCityDropdownOpen] = useState(false);
  const [cityActiveIdx, setCityActiveIdx] = useState(0);

  const [portDropdownOpen, setPortDropdownOpen] = useState(false);
  const [portActiveIdx, setPortActiveIdx] = useState(0);

  const countryRef = useRef<HTMLDivElement>(null);
  const cityRef = useRef<HTMLDivElement>(null);
  const portRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (countryRef.current && !countryRef.current.contains(e.target as Node)) {
        setCountryDropdownOpen(false);
      }
      if (cityRef.current && !cityRef.current.contains(e.target as Node)) {
        setCityDropdownOpen(false);
      }
      if (portRef.current && !portRef.current.contains(e.target as Node)) {
        setPortDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const isIndonesiaSelected =
    !country.trim() || country.trim().toLowerCase() === 'indonesia';

  // Filtered Countries: prefix matches first, then substring matches
  const filteredCountries = useMemo(() => {
    const q = country.trim().toLowerCase();
    if (!q) return COUNTRIES.slice(0, 14);
    const startsWith = COUNTRIES.filter(
      (c) =>
        c.name.toLowerCase().startsWith(q) ||
        c.iso.toLowerCase().startsWith(q),
    );
    const includes = COUNTRIES.filter(
      (c) =>
        !startsWith.includes(c) &&
        (c.name.toLowerCase().includes(q) || c.region.toLowerCase().includes(q)),
    );
    return [...startsWith, ...includes].slice(0, 12);
  }, [country]);

  // Filtered City / Kabupaten / Provinsi options
  const filteredCities = useMemo(() => {
    const q = city.trim().toLowerCase();
    const countryFilter = country.trim().toLowerCase();
    let pool = countryFilter
      ? PORT_HUB_PRESETS.filter((p) => p.country.toLowerCase().includes(countryFilter))
      : PORT_HUB_PRESETS;
    if (pool.length === 0) pool = PORT_HUB_PRESETS;

    if (provinceFilter) {
      const byProv = pool.filter(
        (p) => p.province?.toLowerCase() === provinceFilter.toLowerCase(),
      );
      if (byProv.length > 0) pool = byProv;
    }

    if (!q) return pool.slice(0, 18);
    const starts = pool.filter(
      (p) =>
        p.city.toLowerCase().startsWith(q) ||
        p.code.toLowerCase().startsWith(q) ||
        (p.province && p.province.toLowerCase().startsWith(q)),
    );
    const contains = pool.filter(
      (p) =>
        !starts.includes(p) &&
        (p.city.toLowerCase().includes(q) ||
          p.name.toLowerCase().includes(q) ||
          (p.province && p.province.toLowerCase().includes(q)) ||
          p.country.toLowerCase().includes(q)),
    );
    const combined = [...starts, ...contains];
    if (combined.length > 0) return combined.slice(0, 18);

    // Fallback search across all presets if province/country filter hid a match
    return PORT_HUB_PRESETS.filter(
      (p) =>
        p.city.toLowerCase().includes(q) ||
        p.name.toLowerCase().includes(q) ||
        p.code.toLowerCase().includes(q) ||
        (p.province && p.province.toLowerCase().includes(q)),
    ).slice(0, 18);
  }, [city, country, provinceFilter]);

  // Filtered Port / Hub presets for the Port name input
  const filteredPorts = useMemo(() => {
    const q = name.trim().toLowerCase();
    const countryFilter = country.trim().toLowerCase();
    let pool = countryFilter
      ? PORT_HUB_PRESETS.filter((p) => p.country.toLowerCase().includes(countryFilter))
      : PORT_HUB_PRESETS;
    if (pool.length === 0) pool = PORT_HUB_PRESETS;

    if (provinceFilter) {
      const byProv = pool.filter(
        (p) => p.province?.toLowerCase() === provinceFilter.toLowerCase(),
      );
      if (byProv.length > 0) pool = byProv;
    }

    if (!q) return pool.slice(0, 18);
    const starts = pool.filter(
      (p) =>
        p.name.toLowerCase().startsWith(q) ||
        p.city.toLowerCase().startsWith(q) ||
        p.code.toLowerCase().startsWith(q) ||
        (p.province && p.province.toLowerCase().startsWith(q)),
    );
    const contains = pool.filter(
      (p) =>
        !starts.includes(p) &&
        (p.name.toLowerCase().includes(q) ||
          p.city.toLowerCase().includes(q) ||
          (p.province && p.province.toLowerCase().includes(q)) ||
          p.country.toLowerCase().includes(q)),
    );
    const combined = [...starts, ...contains];
    if (combined.length > 0) return combined.slice(0, 18);

    return PORT_HUB_PRESETS.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        p.city.toLowerCase().includes(q) ||
        p.code.toLowerCase().includes(q) ||
        (p.province && p.province.toLowerCase().includes(q)),
    ).slice(0, 18);
  }, [name, country, provinceFilter]);

  const selectCountryOption = (option: CountryOption) => {
    setCountry(option.name);
    if (option.name.toLowerCase() !== 'indonesia') {
      setProvinceFilter('');
    }
    const countryPresets = PORT_HUB_PRESETS.filter(
      (p) => p.country.toLowerCase() === option.name.toLowerCase(),
    );
    const currentCityPreset = PORT_HUB_PRESETS.find(
      (p) => p.city.toLowerCase() === city.trim().toLowerCase(),
    );
    if (
      countryPresets.length > 0 &&
      (!city.trim() ||
        (currentCityPreset &&
          currentCityPreset.country.toLowerCase() !== option.name.toLowerCase()))
    ) {
      const primary = countryPresets[0];
      setCity(primary.province ? `${primary.city}, ${primary.province}` : primary.city);
      setCode(primary.code);
      setName(primary.name);
      setLatitude(String(primary.latitude));
      setLongitude(String(primary.longitude));
      setGeocodeStatus(
        `Loaded primary port hub in ${option.name}: ${primary.name} (${primary.code}).`,
      );
    }
    setCountryDropdownOpen(false);
    setErrors((prev) => ({ ...prev, country: '' }));
  };

  const selectCityPreset = (preset: PortHubPreset) => {
    setCity(preset.province ? `${preset.city}, ${preset.province}` : preset.city);
    setCountry(preset.country);
    if (preset.province) setProvinceFilter(preset.province);
    setCode(preset.code);
    setName(preset.name);
    setLatitude(String(preset.latitude));
    setLongitude(String(preset.longitude));
    setCityDropdownOpen(false);
    setGeocodeStatus(
      `Auto-filled ${preset.name} (${preset.code}) in ${preset.city}${
        preset.province ? `, Prov. ${preset.province}` : ''
      }.`,
    );
    setErrors({});
  };

  const selectPortPreset = (preset: PortHubPreset) => {
    setName(preset.name);
    setCode(preset.code);
    setCity(preset.province ? `${preset.city}, ${preset.province}` : preset.city);
    setCountry(preset.country);
    if (preset.province) setProvinceFilter(preset.province);
    setLatitude(String(preset.latitude));
    setLongitude(String(preset.longitude));
    setPortDropdownOpen(false);
    setGeocodeStatus(
      `Auto-filled WGS84 coordinates for ${preset.name} (${preset.code} · ${preset.city}).`,
    );
    setErrors({});
  };

  const handleGeocode = async () => {
    const query = [name.trim(), city.trim(), provinceFilter.trim(), country.trim()]
      .filter(Boolean)
      .join(', ');
    if (!query) {
      setGeocodeStatus('Enter a location name, Kabupaten/Kota, or country first.');
      return;
    }
    setGeocoding(true);
    setGeocodeStatus('Querying OpenStreetMap Nominatim API...');
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 5000);
    try {
      const res = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(
          query,
        )}`,
        { headers: { Accept: 'application/json' }, signal: controller.signal },
      );
      window.clearTimeout(timer);
      if (!res.ok) {
        setGeocodeStatus(`Nominatim returned HTTP ${res.status} — enter coordinates manually.`);
        return;
      }
      const hits = await res.json();
      if (Array.isArray(hits) && hits.length > 0) {
        setLatitude(Number(hits[0].lat).toFixed(6));
        setLongitude(Number(hits[0].lon).toFixed(6));
        setGeocodeStatus(`Resolved: ${hits[0].display_name}`);
      } else {
        setGeocodeStatus('No matching coordinates — enter manually.');
      }
    } catch (err) {
      window.clearTimeout(timer);
      const isAbort = err instanceof DOMException && err.name === 'AbortError';
      setGeocodeStatus(
        isAbort
          ? 'Nominatim request timed out (5s) — enter coordinates manually.'
          : 'Nominatim service unreachable — enter coordinates manually.',
      );
    } finally {
      setGeocoding(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const res = saveLocation({
      id: existing?.id,
      name,
      code,
      city,
      country,
      latitude: latitude.trim() === '' ? Number.NaN : Number(latitude),
      longitude: longitude.trim() === '' ? Number.NaN : Number(longitude),
    });
    if (!res.ok) {
      setErrors(res.errors ?? {});
    } else {
      navigate('/locations');
    }
  };

  return (
    <AppLayout title={existing ? 'Edit port location' : 'Add port location'}>
      <div className="mx-auto max-w-xl p-3 sm:p-6">
        <form
          onSubmit={handleSubmit}
          className="space-y-4 rounded-lg border border-line bg-surface p-4 sm:p-6"
        >
          {/* Optional Indonesian Province Filter (38 Provinsi) */}
          {isIndonesiaSelected && (
            <div className="rounded-md border border-line bg-base p-3">
              <div className="flex items-center justify-between gap-2">
                <label
                  htmlFor="loc-province-filter"
                  className="text-xs font-semibold text-ink"
                >
                  Filter Provinsi Indonesia (38 Provinsi)
                </label>
                {provinceFilter && (
                  <button
                    type="button"
                    onClick={() => setProvinceFilter('')}
                    className="font-mono text-[11px] font-semibold text-ink-2 hover:text-ink cursor-pointer"
                  >
                    Reset provinsi
                  </button>
                )}
              </div>
              <select
                id="loc-province-filter"
                value={provinceFilter}
                onChange={(e) => {
                  const prov = e.target.value;
                  setProvinceFilter(prov);
                  setCountry('Indonesia');
                  if (prov) {
                    const firstInProv = PORT_HUB_PRESETS.find(
                      (p) => p.province?.toLowerCase() === prov.toLowerCase(),
                    );
                    if (firstInProv) {
                      setCity(`${firstInProv.city}, ${firstInProv.province}`);
                      setCode(firstInProv.code);
                      setName(firstInProv.name);
                      setLatitude(String(firstInProv.latitude));
                      setLongitude(String(firstInProv.longitude));
                      setGeocodeStatus(
                        `Filtered to Provinsi ${prov} — loaded ${firstInProv.name} (${firstInProv.code}).`,
                      );
                    }
                  }
                }}
                className="mt-1.5 h-9 w-full rounded-md border border-line bg-surface px-3 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
              >
                <option value="">Semua Provinsi Indonesia (Aceh s/d Papua)</option>
                {INDONESIAN_PROVINCES.map((prov) => (
                  <option key={prov} value={prov}>
                    Provinsi {prov}
                  </option>
                ))}
              </select>
            </div>
          )}

          {/* Port / Hub Name with Interactive Autocomplete */}
          <div ref={portRef} className="relative">
            <label htmlFor="loc-name" className="mb-1.5 block text-xs font-medium text-ink-2">
              Port / hub name * (search Pelabuhan, Bandara, Krueng Geukuh, Bireuen, Lhokseumawe...)
            </label>
            <div className="relative">
              <input
                id="loc-name"
                type="text"
                autoComplete="off"
                value={name}
                onFocus={() => {
                  setPortDropdownOpen(true);
                  setPortActiveIdx(0);
                }}
                onChange={(e) => {
                  setName(e.target.value);
                  setPortDropdownOpen(true);
                  setPortActiveIdx(0);
                }}
                onKeyDown={(e) => {
                  if (!portDropdownOpen || filteredPorts.length === 0) return;
                  if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    setPortActiveIdx((i) => (i + 1) % filteredPorts.length);
                  } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    setPortActiveIdx((i) => (i - 1 + filteredPorts.length) % filteredPorts.length);
                  } else if (e.key === 'Enter' && portDropdownOpen) {
                    e.preventDefault();
                    selectPortPreset(filteredPorts[portActiveIdx] ?? filteredPorts[0]);
                  } else if (e.key === 'Escape') {
                    setPortDropdownOpen(false);
                  }
                }}
                placeholder="e.g. Pelabuhan Krueng Geukuh, Bireuen Logistics Terminal..."
                className="h-9 w-full rounded-md border border-line bg-base pl-3 pr-8 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
              />
              <button
                type="button"
                tabIndex={-1}
                aria-label="Toggle port presets"
                onClick={() => setPortDropdownOpen((o) => !o)}
                className="absolute inset-y-0 right-0 flex items-center pr-2.5 text-ink-3 hover:text-ink cursor-pointer"
              >
                <ChevronDown className="size-3.5" strokeWidth={1.5} />
              </button>
            </div>
            {errors.name && (
              <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                {errors.name}
              </p>
            )}

            {portDropdownOpen && (
              <div
                role="listbox"
                aria-label="Port hub suggestions"
                className="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-md border border-line-strong bg-overlay py-1 shadow-2xl"
              >
                <div className="border-b border-line px-3 py-1 text-[10px] font-medium text-ink-3">
                  Select a port, airport, or regional hub to auto-fill Kabupaten/Provinsi &amp; coordinates
                </div>
                {filteredPorts.map((preset, idx) => {
                  const isActive = idx === portActiveIdx;
                  return (
                    <button
                      key={`${preset.code}-${preset.name}`}
                      type="button"
                      role="option"
                      aria-selected={isActive}
                      onMouseEnter={() => setPortActiveIdx(idx)}
                      onClick={() => selectPortPreset(preset)}
                      className={`flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-xs transition-colors cursor-pointer ${
                        isActive ? 'bg-tint-strong font-medium text-ink' : 'text-ink-2 hover:bg-tint'
                      }`}
                    >
                      <div className="flex min-w-0 items-center gap-2">
                        <MapPin className="size-3.5 shrink-0 text-ink-3" strokeWidth={1.5} />
                        <span className="truncate font-medium">
                          {highlightMatch(preset.name, name)}
                        </span>
                      </div>
                      <span className="shrink-0 font-mono text-[11px] font-medium text-ink-3">
                        {preset.code} · {preset.city}
                        {preset.province ? `, ${preset.province}` : `, ${preset.country}`}
                      </span>
                    </button>
                  );
                })}
                {name.trim() && (
                  <button
                    type="button"
                    onClick={() => setPortDropdownOpen(false)}
                    className="flex w-full items-center justify-between border-t border-line px-3 py-2 text-left text-xs font-semibold text-ink hover:bg-tint cursor-pointer"
                  >
                    <span>Use custom port name: &ldquo;{name.trim()}&rdquo;</span>
                    <span className="font-mono text-[10px] text-ink-3">Custom</span>
                  </button>
                )}
              </div>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="loc-code" className="mb-1.5 block text-xs font-medium text-ink-2">
                Code *
              </label>
              <input
                id="loc-code"
                type="text"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder="KRG"
                className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs font-semibold uppercase text-ink focus:border-line-strong focus:outline-none"
              />
              {errors.code && (
                <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                  {errors.code}
                </p>
              )}
            </div>

            {/* City / Kabupaten / Provinsi with Interactive Autocomplete */}
            <div ref={cityRef} className="relative">
              <label htmlFor="loc-city" className="mb-1.5 block text-xs font-medium text-ink-2">
                City / Kabupaten / Provinsi *
              </label>
              <div className="relative">
                <input
                  id="loc-city"
                  type="text"
                  autoComplete="off"
                  value={city}
                  onFocus={() => {
                    setCityDropdownOpen(true);
                    setCityActiveIdx(0);
                  }}
                  onChange={(e) => {
                    setCity(e.target.value);
                    setCityDropdownOpen(true);
                    setCityActiveIdx(0);
                  }}
                  onKeyDown={(e) => {
                    if (!cityDropdownOpen || filteredCities.length === 0) return;
                    if (e.key === 'ArrowDown') {
                      e.preventDefault();
                      setCityActiveIdx((i) => (i + 1) % filteredCities.length);
                    } else if (e.key === 'ArrowUp') {
                      e.preventDefault();
                      setCityActiveIdx(
                        (i) => (i - 1 + filteredCities.length) % filteredCities.length,
                      );
                    } else if (e.key === 'Enter' && cityDropdownOpen) {
                      e.preventDefault();
                      selectCityPreset(filteredCities[cityActiveIdx] ?? filteredCities[0]);
                    } else if (e.key === 'Escape') {
                      setCityDropdownOpen(false);
                    }
                  }}
                  placeholder="Bireuen, Lhokseumawe, Krueng Geukuh, Aceh..."
                  className="h-9 w-full rounded-md border border-line bg-base pl-3 pr-8 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
                />
                <button
                  type="button"
                  tabIndex={-1}
                  aria-label="Toggle city suggestions"
                  onClick={() => setCityDropdownOpen((o) => !o)}
                  className="absolute inset-y-0 right-0 flex items-center pr-2.5 text-ink-3 hover:text-ink cursor-pointer"
                >
                  <ChevronDown className="size-3.5" strokeWidth={1.5} />
                </button>
              </div>
              {errors.city && (
                <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                  {errors.city}
                </p>
              )}

              {cityDropdownOpen && (
                <div
                  role="listbox"
                  aria-label="City and Kabupaten suggestions"
                  className="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-md border border-line-strong bg-overlay py-1 shadow-2xl"
                >
                  {filteredCities.map((preset, idx) => {
                    const isActive = idx === cityActiveIdx;
                    const label = preset.province
                      ? `${preset.city} · Prov. ${preset.province}`
                      : preset.city;
                    return (
                      <button
                        key={`${preset.city}-${preset.code}`}
                        type="button"
                        role="option"
                        aria-selected={isActive}
                        onMouseEnter={() => setCityActiveIdx(idx)}
                        onClick={() => selectCityPreset(preset)}
                        className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs transition-colors cursor-pointer ${
                          isActive ? 'bg-tint-strong font-medium text-ink' : 'text-ink-2 hover:bg-tint'
                        }`}
                      >
                        <div className="min-w-0">
                          <div className="truncate font-semibold text-ink">
                            {highlightMatch(label, city)}
                          </div>
                          <div className="truncate text-[11px] text-ink-3">
                            {highlightMatch(preset.name, city)}
                          </div>
                        </div>
                        <span className="shrink-0 font-mono text-[10px] font-semibold text-ink-3">
                          {preset.code}
                        </span>
                      </button>
                    );
                  })}
                  {city.trim() && (
                    <button
                      type="button"
                      onClick={() => setCityDropdownOpen(false)}
                      className="flex w-full items-center justify-between border-t border-line px-3 py-2 text-left text-xs font-semibold text-ink hover:bg-tint cursor-pointer"
                    >
                      <span>Use custom Kabupaten / Kota: &ldquo;{city.trim()}&rdquo;</span>
                      <span className="font-mono text-[10px] text-ink-3">Custom</span>
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Country with Interactive Autocomplete Dropdown (195+ Countries) */}
          <div ref={countryRef} className="relative">
            <label htmlFor="loc-country" className="mb-1.5 block text-xs font-medium text-ink-2">
              Country *
            </label>
            <div className="relative">
              <input
                id="loc-country"
                type="text"
                autoComplete="off"
                value={country}
                placeholder="Type to search country (e.g. Indonesia, India, Russia, Netherlands...)"
                onFocus={() => {
                  setCountryDropdownOpen(true);
                  setCountryActiveIdx(0);
                }}
                onChange={(e) => {
                  setCountry(e.target.value);
                  setCountryDropdownOpen(true);
                  setCountryActiveIdx(0);
                }}
                onKeyDown={(e) => {
                  if (!countryDropdownOpen) return;
                  if (e.key === 'ArrowDown' && filteredCountries.length > 0) {
                    e.preventDefault();
                    setCountryActiveIdx((i) => (i + 1) % filteredCountries.length);
                  } else if (e.key === 'ArrowUp' && filteredCountries.length > 0) {
                    e.preventDefault();
                    setCountryActiveIdx(
                      (i) => (i - 1 + filteredCountries.length) % filteredCountries.length,
                    );
                  } else if (e.key === 'Enter' && countryDropdownOpen) {
                    e.preventDefault();
                    if (filteredCountries[countryActiveIdx]) {
                      selectCountryOption(filteredCountries[countryActiveIdx]);
                    } else {
                      setCountryDropdownOpen(false);
                    }
                  } else if (e.key === 'Escape') {
                    setCountryDropdownOpen(false);
                  }
                }}
                className="h-9 w-full rounded-md border border-line bg-base pl-3 pr-8 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
              />
              <button
                type="button"
                tabIndex={-1}
                aria-label="Toggle country suggestions"
                onClick={() => setCountryDropdownOpen((o) => !o)}
                className="absolute inset-y-0 right-0 flex items-center pr-2.5 text-ink-3 hover:text-ink cursor-pointer"
              >
                <ChevronDown className="size-3.5" strokeWidth={1.5} />
              </button>
            </div>
            {errors.country && (
              <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                {errors.country}
              </p>
            )}

            {countryDropdownOpen && (
              <div
                role="listbox"
                aria-label="Country suggestions"
                className="absolute left-0 right-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-md border border-line-strong bg-overlay py-1 shadow-2xl"
              >
                {filteredCountries.map((option, idx) => {
                  const isSelected = option.name.toLowerCase() === country.trim().toLowerCase();
                  const isActive = idx === countryActiveIdx;
                  return (
                    <button
                      key={option.iso}
                      type="button"
                      role="option"
                      aria-selected={isSelected}
                      onMouseEnter={() => setCountryActiveIdx(idx)}
                      onClick={() => selectCountryOption(option)}
                      className={`flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-xs transition-colors cursor-pointer ${
                        isActive ? 'bg-tint-strong font-medium text-ink' : 'text-ink-2 hover:bg-tint'
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-[10px] text-ink-3">{option.iso}</span>
                        <span className="font-medium">
                          {highlightMatch(option.name, country)}
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className="text-[11px] text-ink-3">{option.region}</span>
                        {isSelected && (
                          <Check className="size-3.5 text-emerald-600 dark:text-emerald-400" strokeWidth={2} />
                        )}
                      </div>
                    </button>
                  );
                })}
                {country.trim() &&
                  !filteredCountries.some(
                    (c) => c.name.toLowerCase() === country.trim().toLowerCase(),
                  ) && (
                    <button
                      type="button"
                      onClick={() => setCountryDropdownOpen(false)}
                      className="flex w-full items-center justify-between border-t border-line px-3 py-2 text-left text-xs font-semibold text-ink hover:bg-tint cursor-pointer"
                    >
                      <span>Add custom country: &ldquo;{country.trim()}&rdquo;</span>
                      <span className="font-mono text-[10px] text-ink-3">Custom</span>
                    </button>
                  )}
              </div>
            )}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label htmlFor="loc-lat" className="mb-1.5 block text-xs font-medium text-ink-2">
                Latitude *
              </label>
              <input
                id="loc-lat"
                type="number"
                step="any"
                value={latitude}
                onChange={(e) => setLatitude(e.target.value)}
                placeholder="5.2419"
                className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs text-ink focus:border-line-strong focus:outline-none"
              />
              {errors.latitude && (
                <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                  {errors.latitude}
                </p>
              )}
            </div>
            <div>
              <label htmlFor="loc-lng" className="mb-1.5 block text-xs font-medium text-ink-2">
                Longitude *
              </label>
              <input
                id="loc-lng"
                type="number"
                step="any"
                value={longitude}
                onChange={(e) => setLongitude(e.target.value)}
                placeholder="97.0275"
                className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs text-ink focus:border-line-strong focus:outline-none"
              />
              {errors.longitude && (
                <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                  {errors.longitude}
                </p>
              )}
            </div>
          </div>

          <div className="space-y-2">
            <button
              type="button"
              disabled={geocoding}
              onClick={handleGeocode}
              className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-raised px-3 text-xs font-medium text-ink hover:border-line-strong cursor-pointer"
            >
              <Search className="size-3.5" strokeWidth={1.5} />
              {geocoding ? 'Looking up...' : 'Lookup coordinates via OpenStreetMap Nominatim'}
            </button>
            {geocodeStatus && <p className="text-xs font-medium text-ink-2">{geocodeStatus}</p>}
          </div>

          <div className="flex items-center gap-3 pt-2">
            <button
              type="submit"
              className="h-9 rounded-md bg-brand px-4 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
            >
              Save location
            </button>
            <button
              type="button"
              onClick={() => navigate('/locations')}
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
