@extends('layouts.app')

@section('title', 'Shipments')
@section('header_title', 'Shipments')
@section('header_subtitle', $shipments->count() . ' active shipments monitored')

@section('content')
<div class="grid min-h-[calc(100vh-56px)] grid-cols-1 lg:grid-cols-[440px_1fr]">
    <!-- Left Column: Tracking Feed -->
    <section class="divide-y divide-[var(--line)] border-r border-[var(--line)] bg-[var(--bg-base)]">
        @foreach ($shipments as $s)
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
        @endforeach
    </section>

    <!-- Right Column: Interactive Leaflet Map -->
    <section class="relative min-h-[380px] bg-[#E8ECEF] lg:min-h-full">
        <div id="corridor-map" class="h-[380px] w-full lg:h-full"></div>
    </section>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapEl = document.getElementById('corridor-map');
        if (!mapEl || typeof L === 'undefined') return;
        const map = L.map(mapEl, { center: [15, 65], zoom: 3, minZoom: 2 });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    });
</script>
@endpush
@endsection
