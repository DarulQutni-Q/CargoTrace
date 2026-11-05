@extends('layouts.app')

@section('title', $shipment->shipment_number)
@section('header_title', $shipment->shipment_number)
@section('header_subtitle', $shipment->origin->code . ' (' . $shipment->origin->city . ') → ' . $shipment->destination->code . ' (' . $shipment->destination->city . ')')

@section('content')
<div class="flex min-h-[calc(100vh-56px)] flex-col bg-[var(--bg-base)]">
    <!-- Top Control & Lifecycle Bar -->
    <div class="border-b border-[var(--line)] px-3 py-2.5 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('shipments.index') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs">← Shipments</a>
                <span class="font-mono text-sm font-semibold">{{ $shipment->shipment_number }}</span>
                <span class="text-xs opacity-75">· {{ $shipment->cargo_type }}</span>
                <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2 py-0.5 font-mono text-xs font-semibold">{{ $shipment->current_status }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('shipments.bol', $shipment) }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs font-medium">Print B/L</a>
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
                        <span class="opacity-70">Delay assessment</span>
                        <span class="font-mono">{{ $delay['reason'] }}</span>
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
            </div>
        </section>

        <!-- Right Map Column -->
        <section class="relative flex flex-col bg-[#E8ECEF]">
            <div id="shipment-detail-map" class="h-[360px] w-full sm:h-[420px] lg:h-full lg:flex-1"></div>
        </section>
    </div>
</div>
@endsection
