@extends('layouts.app')

@section('title', $shipment->shipment_number)
@section('header_title', $shipment->shipment_number)
@section('header_subtitle', $shipment->origin->code . ' (' . $shipment->origin->city . ') → ' . $shipment->destination->code . ' (' . $shipment->destination->city . ')')

@section('content')
<div class="flex min-h-[calc(100vh-56px)] flex-col bg-[var(--bg-base)]">
    @if (session('status'))
        <div class="mx-3 mt-3 rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm sm:mx-5">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mx-3 mt-3 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 sm:mx-5">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Top Control & Lifecycle Bar -->
    <div class="border-b border-[var(--line)] px-3 py-2.5 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('shipments.index') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs">← Shipments</a>
                <span class="font-mono text-sm font-semibold">{{ $shipment->shipment_number }}</span>
                <span class="text-xs opacity-75">· {{ $shipment->cargo_type }}</span>
                <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2 py-0.5 font-mono text-xs font-semibold">{{ $shipment->current_status }}</span>
                <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2 py-0.5 font-mono text-xs">ETA {{ $delay['state'] === 'pending' ? 'pending' : strtoupper($delay['state']) }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('shipments.bol', $shipment) }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs font-medium">Print B/L</a>
                @if (auth()->user()->canOperateCorridor() && ! empty($allowedNext))
                    <form method="POST" action="{{ route('shipments.advance', $shipment) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">Advance → {{ $allowedNext[0] }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Split Workspace: Dossier & Timeline | Multi-Leg Map -->
    <div class="grid flex-1 grid-cols-1 lg:grid-cols-[460px_1fr]">
        <section class="divide-y divide-[var(--line)] border-b border-[var(--line)] lg:border-b-0 lg:border-r">
            <!-- Active Multi-Leg Corridor Route Chain -->
            <div class="p-4 sm:p-5">
                <div class="rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-3 text-xs">
                    <div class="text-[10px] font-semibold uppercase tracking-wider opacity-70">Active Transit Route (Origin → Checkpoint → Destination)</div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                        <span class="rounded border border-[var(--line)] bg-[var(--bg-base)] px-1.5 py-0.5 font-bold">1. {{ $corridor['origin']['code'] }} · {{ $corridor['origin']['city'] }}</span>
                        @foreach ($corridor['intermediate_stops'] as $idx => $stop)
                            <span>━━▶</span>
                            <span class="rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-bold text-[#080B0C]">{{ $idx + 2 }}. {{ $stop['location_name'] }}</span>
                        @endforeach
                        <span>{{ $shipment->current_status === 'DELIVERED' ? '━━▶' : '╌╌▶' }}</span>
                        <span class="rounded border border-[var(--line)] bg-[var(--bg-base)] px-1.5 py-0.5 font-bold">{{ count($corridor['intermediate_stops']) + 2 }}. {{ $corridor['destination']['code'] }} · {{ $corridor['destination']['city'] }}</span>
                    </div>
                </div>

                <div class="mt-4 divide-y divide-[var(--line)] border-t border-[var(--line)] text-xs">
                    <div class="flex justify-between py-2">
                        <span class="opacity-70">Carrier &amp; mode</span>
                        <span>{{ $shipment->carrier->name }} ({{ $shipment->carrier->code }} · {{ $shipment->carrier->service_type }})</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="opacity-70">Consignee</span>
                        <span>{{ $shipment->customer->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="opacity-70">Total load</span>
                        <span class="font-mono">{{ number_format($shipment->weight_kg) }} kg · {{ $shipment->volume_m3 }} m³</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="opacity-70">Planned arrival</span>
                        <span class="font-mono">{{ $shipment->expected_arrival_at?->format('M j, Y H:i') ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="opacity-70">Estimated arrival</span>
                        <span class="font-mono">{{ $shipment->estimated_arrival_at?->format('M j, Y H:i') ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <span class="shrink-0 opacity-70">Delay assessment</span>
                        <span class="text-right font-mono">{{ $delay['reason'] }}</span>
                    </div>
                </div>

                <!-- Corridor weather (informational only, never used as delay evidence) -->
                <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-3">
                        <div class="font-semibold">Origin weather</div>
                        <div class="mt-1 font-mono">{{ $weather['origin']['temperature_c'] }}°C · {{ $weather['origin']['label'] }}</div>
                        <div class="mt-0.5 opacity-70">Open-Meteo ({{ $weather['origin']['source'] === 'live' ? 'live' : 'cached' }} · informational)</div>
                    </div>
                    <div class="rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-3">
                        <div class="font-semibold">Destination weather</div>
                        <div class="mt-1 font-mono">{{ $weather['destination']['temperature_c'] }}°C · {{ $weather['destination']['label'] }}</div>
                        <div class="mt-0.5 opacity-70">Open-Meteo ({{ $weather['destination']['source'] === 'live' ? 'live' : 'cached' }} · informational)</div>
                    </div>
                </div>
            </div>

            <!-- Chronological Tracking Timeline -->
            <div class="p-4 sm:p-5">
                <h3 class="mb-3 text-xs font-semibold">Tracking timeline ({{ $shipment->trackingEvents->count() }})</h3>
                <ol class="relative ml-1.5 space-y-3 border-l border-[var(--line)]">
                    @foreach ($shipment->trackingEvents as $ev)
                        <li class="ml-4 text-xs">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-semibold">{{ $ev->location_name }} <span class="rounded border border-[var(--line)] px-1.5 py-0.5 font-mono text-[10px]">{{ $ev->event_type }}</span></span>
                                <time class="font-mono text-[11px] opacity-70">{{ $ev->occurred_at->format('M j, Y H:i') }}</time>
                            </div>
                            @if ($ev->description)
                                <p class="mt-0.5 opacity-80">{{ $ev->description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if (auth()->user()->canOperateCorridor())
                    <form method="POST" action="{{ route('shipments.tracking-events.store', $shipment) }}" class="mt-5 space-y-3 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-3">
                        @csrf
                        <h4 class="text-xs font-semibold">Add tracking event</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="event_type" class="text-xs font-semibold">Event type</label>
                                <select id="event_type" name="event_type" required class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                                    <option value="CHECKPOINT">CHECKPOINT</option>
                                    <option value="BOOKED">BOOKED</option>
                                    <option value="PICKED_UP">PICKED_UP</option>
                                    <option value="IN_TRANSIT">IN_TRANSIT</option>
                                    <option value="DELIVERED">DELIVERED</option>
                                    <option value="INFO">INFO</option>
                                </select>
                            </div>
                            <div>
                                <label for="occurred_at" class="text-xs font-semibold">Occurred at</label>
                                <input id="occurred_at" name="occurred_at" type="datetime-local" required class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                            </div>
                        </div>
                        <div>
                            <label for="location_name" class="text-xs font-semibold">Location name</label>
                            <input id="location_name" name="location_name" type="text" required maxlength="180" class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" placeholder="Port of Singapore" />
                        </div>
                        <div>
                            <label for="description" class="text-xs font-semibold">Description (optional)</label>
                            <input id="description" name="description" type="text" maxlength="1000" class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                        </div>
                        <button type="submit" class="inline-flex h-8 items-center rounded-md bg-[#080B0C] px-3 text-xs font-semibold text-white">Record event</button>
                    </form>
                @endif
            </div>

            <!-- Exceptions -->
            <div class="p-4 sm:p-5">
                <h3 class="mb-3 text-xs font-semibold">Exceptions ({{ $shipment->exceptions->count() }})</h3>
                @forelse ($shipment->exceptions as $exc)
                    <div class="mb-3 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-3 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-mono font-semibold">{{ $exc->type }} · {{ $exc->severity }}</span>
                            <span class="rounded border border-[var(--line)] bg-[var(--bg-base)] px-2 py-0.5 font-mono font-semibold">{{ $exc->status }}</span>
                        </div>
                        <p class="mt-1.5 opacity-80">{{ $exc->description ?? '—' }}</p>
                        <p class="mt-1 font-mono text-[11px] opacity-70">Detected {{ $exc->detected_at->format('M j, Y H:i') }}@if ($exc->resolved_at) · Resolved {{ $exc->resolved_at->format('M j, Y H:i') }} @endif</p>
                        @if (auth()->user()->canOperateCorridor() && $exc->status !== 'RESOLVED')
                            <div class="mt-2 flex gap-2">
                                @if ($exc->status === 'OPEN')
                                    <form method="POST" action="{{ route('exceptions.update', $exc) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="INVESTIGATING" />
                                        <button type="submit" class="rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 py-1 text-[11px] font-medium">Start investigating</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('exceptions.update', $exc) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="RESOLVED" />
                                    <button type="submit" class="rounded-md bg-[#C6F432] px-2.5 py-1 text-[11px] font-semibold text-[#080B0C]">Mark resolved</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-xs opacity-70">No operational exceptions recorded for this shipment.</p>
                @endforelse

                @if (auth()->user()->canOperateCorridor())
                    <form method="POST" action="{{ route('shipments.exceptions.store', $shipment) }}" class="mt-4 space-y-3 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-3">
                        @csrf
                        <h4 class="text-xs font-semibold">Raise exception</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="exc_type" class="text-xs font-semibold">Type</label>
                                <select id="exc_type" name="type" required class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                                    @foreach (\App\Models\ShipmentException::TYPES as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="exc_severity" class="text-xs font-semibold">Severity</label>
                                <select id="exc_severity" name="severity" required class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                                    <option value="LOW">LOW</option>
                                    <option value="MEDIUM" selected>MEDIUM</option>
                                    <option value="HIGH">HIGH</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="detected_at" class="text-xs font-semibold">Detected at</label>
                            <input id="detected_at" name="detected_at" type="datetime-local" required class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                        </div>
                        <div>
                            <label for="exc_description" class="text-xs font-semibold">Description (optional)</label>
                            <input id="exc_description" name="description" type="text" maxlength="1000" class="mt-1 h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                        </div>
                        <button type="submit" class="inline-flex h-8 items-center rounded-md bg-[#080B0C] px-3 text-xs font-semibold text-white">Raise exception</button>
                    </form>
                @endif
            </div>

            <!-- Cargo Manifest -->
            <div class="p-4 sm:p-5">
                <h3 class="mb-3 text-xs font-semibold">Cargo manifest ({{ $shipment->items->count() }})</h3>
                @foreach ($shipment->items as $item)
                    <div class="flex justify-between py-1.5 text-xs">
                        <span>{{ $item->description }} ×{{ $item->quantity }}</span>
                        <span class="font-mono">{{ number_format($item->weight_kg) }} kg · {{ $item->volume_m3 }} m³</span>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Right Map Column -->
        <section class="relative flex flex-col bg-[#aad3df]">
            <div id="shipment-detail-map" class="h-[360px] w-full sm:h-[420px] lg:h-full lg:flex-1"></div>
        </section>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapEl = document.getElementById('shipment-detail-map');
        if (!mapEl || typeof L === 'undefined') return;
        const corridor = @json($corridor);
        const map = L.map(mapEl, { center: [corridor.origin.latitude, corridor.origin.longitude], zoom: 3, minZoom: 2 });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        const o = corridor.origin, d = corridor.destination;
        L.marker([o.latitude, o.longitude]).addTo(map)
            .bindPopup('<b>Origin: ' + o.location_name + ' (' + o.code + ')</b><br>' + o.city);
        L.marker([d.latitude, d.longitude]).addTo(map)
            .bindPopup('<b>Destination: ' + d.location_name + ' (' + d.code + ')</b><br>' + d.city);
        const latlngs = [[o.latitude, o.longitude]];
        corridor.intermediate_stops.forEach(function (stop) {
            L.circleMarker([stop.latitude, stop.longitude], { radius: 7, color: '#080B0C', fillColor: '#C6F432', fillOpacity: 1 }).addTo(map)
                .bindPopup('<b>Transit stop</b><br>' + stop.location_name + '<br>' + stop.event_type + ' · ' + stop.occurred_at);
            latlngs.push([stop.latitude, stop.longitude]);
        });
        latlngs.push([d.latitude, d.longitude]);
        const route = L.polyline(latlngs, { color: '#080B0C', weight: 2.5, dashArray: '7 5' }).addTo(map);
        // The map column is lg:h-full, so its height depends on the sibling
        // content column. Re-measure after layout settles, otherwise part
        // of the container stays grey with no tiles.
        map.invalidateSize();
        map.fitBounds(route.getBounds(), { padding: [30, 30] });
        window.addEventListener('load', function () { map.invalidateSize(); });
    });
</script>
@endpush
@endsection
