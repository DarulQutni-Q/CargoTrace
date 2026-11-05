@extends('layouts.app')

@section('title', 'Shipments')
@section('header_title', 'Shipments')
@section('header_subtitle', $shipments->total() . ' active shipments monitored')

@section('content')
<div class="mx-auto max-w-[1440px] space-y-4 p-3 sm:p-6">
    @if (session('status'))
        <div class="rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <!-- Search & filter (server-side) -->
    <form method="GET" action="{{ route('shipments.index') }}" class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-3 sm:p-4">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-5">
            <div class="col-span-2">
                <label for="q" class="text-xs font-semibold">Search reference, port, carrier</label>
                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="CT0001NFR, Jakarta, Electronics…"
                    class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            </div>
            <div>
                <label for="status" class="text-xs font-semibold">Status</label>
                <select id="status" name="status" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                    <option value="">All statuses</option>
                    @foreach (['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'] as $st)
                        <option value="{{ $st }}" {{ ($filters['status'] ?? '') === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="carrier_id" class="text-xs font-semibold">Carrier</label>
                <select id="carrier_id" name="carrier_id" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                    <option value="">All carriers</option>
                    @foreach ($carriers as $carrier)
                        <option value="{{ $carrier->id }}" {{ (string) ($filters['carrier_id'] ?? '') === (string) $carrier->id ? 'selected' : '' }}>{{ $carrier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sort" class="text-xs font-semibold">Sort</label>
                <select id="sort" name="sort" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                    <option value="latest" {{ ($filters['sort'] ?? 'latest') === 'latest' ? 'selected' : '' }}>Newest first</option>
                    <option value="oldest" {{ ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                    <option value="eta_asc" {{ ($filters['sort'] ?? '') === 'eta_asc' ? 'selected' : '' }}>ETA ascending</option>
                    <option value="eta_desc" {{ ($filters['sort'] ?? '') === 'eta_desc' ? 'selected' : '' }}>ETA descending</option>
                    <option value="ref_asc" {{ ($filters['sort'] ?? '') === 'ref_asc' ? 'selected' : '' }}>Reference A–Z</option>
                </select>
            </div>
            <div>
                <label for="date_from" class="text-xs font-semibold">Departed from</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            </div>
            <div>
                <label for="date_to" class="text-xs font-semibold">Departed until</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
            </div>
            <div>
                <label for="delayed" class="text-xs font-semibold">Delay status</label>
                <select id="delayed" name="delayed" class="mt-1.5 h-9 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                    <option value="">All</option>
                    <option value="1" {{ ($filters['delayed'] ?? '') === '1' ? 'selected' : '' }}>Delayed only</option>
                    <option value="0" {{ ($filters['delayed'] ?? '') === '0' ? 'selected' : '' }}>On time only</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex h-9 items-center rounded-md bg-[#C6F432] px-4 text-xs font-semibold text-[#080B0C]">Apply filters</button>
                <a href="{{ route('shipments.index') }}" class="inline-flex h-9 items-center text-xs font-medium underline">Reset</a>
            </div>
        </div>
    </form>

    <div class="grid min-h-[calc(100vh-56px)] grid-cols-1 gap-4 lg:grid-cols-[440px_1fr]">
        <!-- Left Column: Tracking Feed -->
        <section class="divide-y divide-[var(--line)] overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-base)]">
            @forelse ($shipments as $s)
                <div class="p-4 transition-colors hover:bg-[var(--bg-surface)]">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-semibold">{{ $s->shipment_number }}</span>
                            <span class="text-xs opacity-75">{{ $s->cargo_type }}</span>
                        </div>
                        <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2 py-0.5 font-mono text-[11px] font-semibold">{{ $s->current_status }}</span>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between text-xs">
                        <span><strong class="font-mono">{{ $s->origin->code }}</strong> {{ $s->origin->city }}</span>
                        <span class="font-mono text-[11px] opacity-70">{{ $s->carrier->service_type }}</span>
                        <span>{{ $s->destination->city }} <strong class="font-mono">{{ $s->destination->code }}</strong></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] opacity-75">
                        <span>{{ $s->carrier->name }} ({{ $s->carrier->code }})</span>
                        <a href="{{ route('shipments.show', $s) }}" class="font-semibold text-[var(--ink-1)] underline">Inspect ↗</a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm opacity-70">No shipments match these filters.</div>
            @endforelse
            <div class="p-4">
                {{ $shipments->links() }}
            </div>
        </section>

        <!-- Right Column: Interactive Leaflet Map (OpenStreetMap) -->
        <section class="relative h-[380px] overflow-hidden rounded-lg border border-[var(--line)] bg-[#aad3df] lg:h-[640px]">
            <div id="corridor-map" class="h-full w-full"></div>
        </section>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapEl = document.getElementById('corridor-map');
        if (!mapEl || typeof L === 'undefined') return;
        const map = L.map(mapEl, { center: [15, 65], zoom: 3, minZoom: 2 });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        const points = @json($mapPoints);
        const bounds = [];
        points.forEach(function (p) {
            const o = p.corridor.origin, d = p.corridor.destination;
            const originMarker = L.marker([o.latitude, o.longitude]).addTo(map)
                .bindPopup('<b>' + p.shipment_number + '</b><br>Origin: ' + o.location_name + ' (' + o.code + ')<br>Status: ' + p.status);
            const destMarker = L.marker([d.latitude, d.longitude]).addTo(map)
                .bindPopup('<b>' + p.shipment_number + '</b><br>Destination: ' + d.location_name + ' (' + d.code + ')<br>Status: ' + p.status);
            const latlngs = [[o.latitude, o.longitude]];
            p.corridor.intermediate_stops.forEach(function (stop) {
                L.circleMarker([stop.latitude, stop.longitude], { radius: 6, color: '#080B0C', fillColor: '#C6F432', fillOpacity: 1 }).addTo(map)
                    .bindPopup('<b>' + p.shipment_number + '</b><br>Transit stop: ' + stop.location_name + '<br>' + stop.occurred_at);
                latlngs.push([stop.latitude, stop.longitude]);
            });
            latlngs.push([d.latitude, d.longitude]);
            L.polyline(latlngs, { color: '#080B0C', weight: 2, dashArray: '6 4' }).addTo(map);
            bounds.push([o.latitude, o.longitude], [d.latitude, d.longitude]);
        });
        // The map column is lg:h-full, so its height depends on the sibling
        // list column. Re-measure after layout settles, otherwise the top
        // (or bottom) of the container stays grey with no tiles.
        map.invalidateSize();
        if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
        window.addEventListener('load', function () { map.invalidateSize(); });
    });
</script>
@endpush
@endsection
