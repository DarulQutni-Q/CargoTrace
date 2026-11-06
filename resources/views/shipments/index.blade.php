@extends('layouts.app')

@section('title', 'Shipments')
@section('header_title', 'Shipments')
@section('header_subtitle', $filteredCount . ' active shipments monitored')

@php
    $badgeTone = function (string $tone): array {
        return match ($tone) {
            'blue' => ['dot' => 'bg-[#080B0C] dark:bg-[#C6F432]', 'text' => 'text-[var(--ink-1)]'],
            'teal' => ['dot' => 'bg-teal-600 dark:bg-teal-400', 'text' => 'text-teal-800 dark:text-teal-300'],
            'amber' => ['dot' => 'bg-amber-600 dark:bg-amber-400', 'text' => 'text-amber-800 dark:text-amber-300'],
            'orange' => ['dot' => 'bg-orange-600 dark:bg-orange-400', 'text' => 'text-orange-800 dark:text-orange-300'],
            'green' => ['dot' => 'bg-emerald-600 dark:bg-emerald-400', 'text' => 'text-emerald-800 dark:text-emerald-300'],
            'red' => ['dot' => 'bg-rose-600 dark:bg-rose-400', 'text' => 'text-rose-800 dark:text-rose-300'],
            default => ['dot' => 'bg-[var(--ink-3)]', 'text' => 'text-[var(--ink-2)]'],
        };
    };
    $statusTone = fn (string $st): string => match ($st) {
        'CREATED' => 'neutral', 'BOOKED' => 'amber', 'PICKED_UP' => 'teal',
        'IN_TRANSIT' => 'blue', 'DELIVERED' => 'green', default => 'neutral',
    };
    $delayTone = fn (string $state): string => match ($state) {
        'low' => 'amber', 'medium' => 'orange', 'high' => 'red',
        'on_time' => 'green', default => 'neutral',
    };
    $tabBase = request()->except(['status', 'delayed', 'exceptions', 'page', 'focus']);
    $isAllTab = empty($filters['status']) && empty($filters['delayed']) && empty($filters['exceptions']);
    $tabClass = fn (bool $active): string => 'rounded px-2.5 py-1 text-xs transition-colors whitespace-nowrap cursor-pointer ' . ($active
        ? 'bg-[#080B0C] text-[#FFFFFF] dark:bg-[#C6F432] dark:text-[#080B0C] font-semibold'
        : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]');
    $viewClass = fn (bool $active): string => 'inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs transition-colors cursor-pointer ' . ($active
        ? 'bg-[#080B0C] text-[#FFFFFF] dark:bg-[#C6F432] dark:text-[#080B0C] font-semibold'
        : 'text-[var(--ink-2)] hover:text-[var(--ink-1)]');
    $focusUrl = fn (int $id): string => route('shipments.index', array_merge(request()->except(['page', 'focus']), ['focus' => $id]));
@endphp

@section('content')
<div class="flex min-h-[calc(100vh-56px)] flex-col bg-[var(--bg-base)] lg:h-[calc(100vh-56px)] lg:overflow-hidden">
    @if (session('status'))
        <div class="shrink-0 border-b border-[var(--line)] bg-[var(--bg-base)] px-3 py-2 sm:px-5">
            <div class="rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm">{{ session('status') }}</div>
        </div>
    @endif

    <!-- Top Filter & View Control Bar -->
    <div class="shrink-0 border-b border-[var(--line)] bg-[var(--bg-base)] px-3 py-2.5 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex w-full flex-wrap items-center gap-2 lg:w-auto">
                <form method="GET" action="{{ route('shipments.index') }}" class="relative w-full sm:w-52 xl:w-64">
                    @foreach (['status', 'delayed', 'exceptions', 'carrier_id', 'origin_location_id', 'destination_location_id', 'date_from', 'date_to', 'sort', 'view'] as $k)
                        @if (!empty($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}" />@endif
                    @endforeach
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-[var(--ink-3)]"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search reference, port, carrier..." aria-label="Search shipments"
                        class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-surface)] pl-8 pr-3 text-xs font-medium text-[var(--ink-1)] placeholder:text-[var(--ink-3)] focus:border-[var(--line-strong)] focus:outline-none" />
                </form>

                <!-- Segmented Filter Tabs -->
                <div class="flex max-w-full items-center gap-0.5 overflow-x-auto rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-0.5">
                    <a href="{{ route('shipments.index', $tabBase) }}" class="{{ $tabClass($isAllTab) }}">All
                        <span class="font-mono text-[11px] font-semibold {{ $isAllTab ? 'text-[#C6F432] dark:text-[#080B0C]' : 'text-[var(--ink-3)]' }}">{{ $visibleCount }}</span>
                    </a>
                    <a href="{{ route('shipments.index', array_merge($tabBase, ['status' => 'IN_TRANSIT'])) }}" class="{{ $tabClass(($filters['status'] ?? '') === 'IN_TRANSIT') }}">In transit</a>
                    <a href="{{ route('shipments.index', array_merge($tabBase, ['delayed' => '1'])) }}" class="{{ $tabClass(($filters['delayed'] ?? '') === '1') }}">Delayed</a>
                    <a href="{{ route('shipments.index', array_merge($tabBase, ['exceptions' => '1'])) }}" class="{{ $tabClass(($filters['exceptions'] ?? '') === '1') }}">Exceptions</a>
                    <a href="{{ route('shipments.index', array_merge($tabBase, ['status' => 'DELIVERED'])) }}" class="{{ $tabClass(($filters['status'] ?? '') === 'DELIVERED') }}">Delivered</a>
                </div>

                <button type="button" onclick="toggleShipFilters()" id="filters-toggle" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs font-normal text-[var(--ink-2)] transition-colors hover:text-[var(--ink-1)]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filters
                </button>
            </div>

            <!-- Right: Sort & View Mode Switcher -->
            <div class="flex w-full items-center justify-between gap-2 sm:w-auto sm:justify-end">
                <form method="GET" action="{{ route('shipments.index') }}" class="flex items-center gap-1.5">
                    @foreach (['q', 'status', 'delayed', 'exceptions', 'carrier_id', 'origin_location_id', 'destination_location_id', 'date_from', 'date_to', 'view'] as $k)
                        @if (!empty($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}" />@endif
                    @endforeach
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[var(--ink-3)]"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>
                    <select name="sort" aria-label="Sort shipments" onchange="this.form.submit()" class="h-8 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs text-[var(--ink-1)] focus:border-[var(--line-strong)] focus:outline-none">
                        <option value="latest" {{ ($filters['sort'] ?? 'latest') === 'latest' ? 'selected' : '' }}>Sort: Newest first</option>
                        <option value="eta_asc" {{ ($filters['sort'] ?? '') === 'eta_asc' ? 'selected' : '' }}>Sort: ETA earliest</option>
                        <option value="eta_desc" {{ ($filters['sort'] ?? '') === 'eta_desc' ? 'selected' : '' }}>Sort: ETA latest</option>
                        <option value="ref_asc" {{ ($filters['sort'] ?? '') === 'ref_asc' ? 'selected' : '' }}>Sort: Reference A–Z</option>
                        <option value="weight_desc" {{ ($filters['sort'] ?? '') === 'weight_desc' ? 'selected' : '' }}>Sort: Weight high–low</option>
                    </select>
                </form>

                <div class="flex items-center rounded-md border border-[var(--line)] bg-[var(--bg-surface)] p-0.5">
                    <a href="{{ route('shipments.index', array_merge(request()->except(['page', 'view']), ['view' => 'split'])) }}" class="{{ $viewClass($view === 'split') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/></svg>
                        Split map
                    </a>
                    <a href="{{ route('shipments.index', array_merge(request()->except(['page', 'view']), ['view' => 'table'])) }}" class="{{ $viewClass($view === 'table') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M12 3v18"/><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/></svg>
                        Table
                    </a>
                </div>
            </div>
        </div>

        <!-- Expandable Filters: Status, Carrier, Origin, Destination, Date Range -->
        <form method="GET" action="{{ route('shipments.index') }}" id="filters-panel" class="mt-2.5 hidden grid-cols-1 gap-3 border-t border-[var(--line)] pt-2.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
            @foreach (['q', 'sort', 'view', 'delayed', 'exceptions'] as $k)
                @if (!empty($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}" />@endif
            @endforeach
            <div>
                <label class="mb-1 block text-xs text-[var(--ink-3)]">Lifecycle status</label>
                <select name="status" onchange="this.form.submit()" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2 text-xs text-[var(--ink-1)]">
                    <option value="">All statuses</option>
                    @foreach (['CREATED' => 'Created', 'BOOKED' => 'Booked', 'PICKED_UP' => 'Picked up', 'IN_TRANSIT' => 'In transit', 'DELIVERED' => 'Delivered'] as $val => $label)
                        <option value="{{ $val }}" {{ ($filters['status'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-[var(--ink-3)]">Carrier</label>
                <select name="carrier_id" onchange="this.form.submit()" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2 text-xs text-[var(--ink-1)]">
                    <option value="">All carriers</option>
                    @foreach ($carriers as $carrier)
                        <option value="{{ $carrier->id }}" {{ (string) ($filters['carrier_id'] ?? '') === (string) $carrier->id ? 'selected' : '' }}>{{ $carrier->name }} ({{ $carrier->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-[var(--ink-3)]">Origin port</label>
                <select name="origin_location_id" onchange="this.form.submit()" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2 text-xs text-[var(--ink-1)]">
                    <option value="">All origins</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string) ($filters['origin_location_id'] ?? '') === (string) $loc->id ? 'selected' : '' }}>{{ $loc->code }} — {{ $loc->city }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-[var(--ink-3)]">Destination port</label>
                <select name="destination_location_id" onchange="this.form.submit()" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2 text-xs text-[var(--ink-1)]">
                    <option value="">All destinations</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string) ($filters['destination_location_id'] ?? '') === (string) $loc->id ? 'selected' : '' }}>{{ $loc->code }} — {{ $loc->city }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-[var(--ink-3)]">Departure from</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" onchange="this.form.submit()" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2 text-xs text-[var(--ink-1)]" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-[var(--ink-3)]">Departure to</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" onchange="this.form.submit()" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2 text-xs text-[var(--ink-1)]" />
            </div>
        </form>
    </div>

    @if ($view === 'split')
    <!-- Main View Area: Split Map -->
    <div class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[410px_1fr]">
        <!-- Left Feed: Flat Hairline-Divided Shipment List -->
        <section class="flex max-h-[480px] min-h-0 flex-col border-b border-[var(--line)] bg-[var(--bg-base)] lg:max-h-none lg:border-b-0 lg:border-r">
            <div class="flex items-center justify-between border-b border-[var(--line)] px-4 py-2 text-xs">
                <span class="font-medium text-[var(--ink-2)]">Tracking feed <span class="font-mono text-[var(--ink-3)]">({{ $filteredCount }})</span></span>
                <span class="text-[var(--ink-3)]">Select a row to focus corridor</span>
            </div>

            <div class="flex-1 overflow-y-auto">
                @forelse ($cards as $c)
                    @php
                        $dotClass = $c['current_status'] === 'DELIVERED' ? 'bg-emerald-600 dark:bg-emerald-400'
                            : ($c['delayed'] ? 'bg-amber-600 dark:bg-amber-400'
                            : ($c['current_status'] === 'IN_TRANSIT' ? 'bg-[#080B0C] dark:bg-[#C6F432]'
                            : ($c['current_status'] === 'PICKED_UP' ? 'bg-teal-600 dark:bg-teal-400' : 'bg-[var(--ink-3)]')));
                        $barClass = $c['current_status'] === 'DELIVERED' ? 'bg-emerald-600 dark:bg-emerald-400'
                            : ($c['delayed'] ? 'bg-amber-600 dark:bg-amber-400' : 'bg-[#080B0C] dark:bg-[#C6F432]');
                    @endphp
                    <div role="button" tabindex="0" onclick="window.location.href='{{ $focusUrl($c['id']) }}'" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href='{{ $focusUrl($c['id']) }}'; }"
                        class="group relative cursor-pointer border-b border-[var(--line)] px-4 py-3.5 transition-colors {{ $c['id'] === $focusId ? 'bg-[#F4FCE3] dark:bg-[var(--bg-surface)]' : 'bg-[var(--bg-base)] hover:bg-[var(--bg-surface)]' }}">
                        @if ($c['id'] === $focusId)
                            <span class="absolute inset-y-0 left-0 w-1.5 bg-[#080B0C] dark:bg-[#C6F432]"></span>
                        @endif

                        <!-- Row 1: Reference & Cargo; Status dot + plain text -->
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-baseline gap-2">
                                <span class="font-mono text-[13px] font-bold text-[var(--ink-1)]">{{ $c['shipment_number'] }}</span>
                                <span class="truncate text-xs font-medium text-[var(--ink-2)]">{{ $c['cargo_type'] }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2 text-xs">
                                @if ($c['has_open_exception'])
                                    <span class="font-mono text-[11px] font-semibold text-rose-700 dark:text-rose-400">Exception</span>
                                @endif
                                @if ($c['delayed'])
                                    <span class="font-mono text-[11px] font-semibold tabular-nums text-amber-700 dark:text-amber-400">+{{ number_format($c['delay_hours'], 0) }}h</span>
                                @endif
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ink-1)]">
                                    <span class="size-2 rounded-full {{ $dotClass }}"></span>
                                    <span>{{ $c['status_label'] }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Row 2: Flat Route Strip -->
                        <div class="mt-3 grid grid-cols-[minmax(0,1fr)_110px_minmax(0,1fr)] items-center gap-3">
                            <div class="min-w-0">
                                <div class="flex items-baseline gap-1.5">
                                    <span class="font-mono text-xs font-bold text-[var(--ink-1)]">{{ $c['origin_code'] }}</span>
                                    <span class="truncate text-xs font-medium text-[var(--ink-2)]">{{ $c['origin_city'] }}</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-center">
                                <div class="mb-1 flex items-center gap-1 font-mono text-[10px] font-medium tabular-nums text-[var(--ink-3)]">
                                    <span>{{ $c['service_label'] }}</span><span>·</span><span>{{ $c['duration_hours'] }}h</span>
                                </div>
                                <div class="relative flex w-full items-center">
                                    <span class="size-1.5 shrink-0 rounded-full bg-[var(--ink-2)]"></span>
                                    <div class="relative mx-1 h-[2px] flex-1 bg-[var(--line-strong)]">
                                        <div class="h-full {{ $barClass }}" style="width: {{ $c['card_percent'] }}%"></div>
                                        @if ($c['has_transit_checkpoint'] && $c['current_status'] !== 'DELIVERED')
                                            <span class="absolute top-1/2 z-10 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#080B0C] bg-[#C6F432]" style="left: {{ $c['card_percent'] }}%"></span>
                                        @endif
                                    </div>
                                    <span class="size-1.5 shrink-0 rounded-full {{ $c['current_status'] === 'DELIVERED' ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-[var(--ink-2)]' }}"></span>
                                </div>
                            </div>
                            <div class="min-w-0 text-right">
                                <div class="flex items-baseline justify-end gap-1.5">
                                    <span class="truncate text-xs font-medium text-[var(--ink-2)]">{{ $c['destination_city'] }}</span>
                                    <span class="font-mono text-xs font-bold text-[var(--ink-1)]">{{ $c['destination_code'] }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Last Recorded Checkpoint & Multi-Stop Route Chain -->
                        @if ($c['latest_checkpoint_name'])
                            <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-[var(--ink-3)]">
                                <span class="truncate">
                                    @if ($c['has_transit_checkpoint'])
                                        Route:
                                        <span class="font-mono font-bold text-[var(--ink-1)]">{{ $c['origin_code'] }}</span>
                                        <span class="mx-1 text-[var(--ink-2)]">→</span>
                                        <span class="rounded bg-[#C6F432]/70 px-1 py-0.5 font-semibold text-[#080B0C]">{{ $c['latest_checkpoint_name'] }}</span>
                                        <span class="mx-1 text-[var(--ink-2)]">→</span>
                                        <span class="font-mono font-bold text-[var(--ink-1)]">{{ $c['destination_code'] }}</span>
                                    @else
                                        Last checkpoint: <span class="font-semibold text-[var(--ink-1)]">{{ $c['latest_checkpoint_name'] }}</span>
                                    @endif
                                </span>
                                @if ($c['latest_checkpoint_time'])
                                    <span class="shrink-0 font-mono text-[10px] font-medium tabular-nums text-[var(--ink-2)]">{{ $c['latest_checkpoint_time'] }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="mt-2 flex items-center justify-between gap-2 text-xs text-[var(--ink-2)]">
                            <span class="truncate font-medium">{{ $c['carrier_name'] }} <span class="font-mono text-[11px] text-[var(--ink-3)]">({{ $c['carrier_code'] }})</span></span>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="font-mono text-[11px] font-semibold tabular-nums text-[var(--ink-1)]">ETA {{ $c['eta_short'] }}</span>
                                <a href="{{ route('shipments.show', $c['id']) }}" onclick="event.stopPropagation()" aria-label="Open detail for {{ $c['shipment_number'] }}" class="inline-flex items-center gap-0.5 text-xs font-semibold text-[var(--ink-1)] transition-colors hover:underline">
                                    <span>Inspect</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <p class="text-sm font-medium text-[var(--ink-1)]">No shipments match filters</p>
                        <a href="{{ route('shipments.index') }}" class="mt-2 text-xs font-medium text-[var(--ink-2)] underline hover:text-[var(--ink-1)]">Clear all filters</a>
                    </div>
                @endforelse
            </div>
        </section>

        <!-- Right Map: Cartographic Canvas + Inspector -->
        <section class="isolate relative z-0 flex flex-col bg-[var(--bg-map)] lg:min-h-[540px]">
            <div class="relative h-[340px] w-full sm:h-[420px] lg:h-auto lg:flex-1">
                <div id="world-map" data-testid="world-map" class="isolate relative z-0 h-full w-full bg-[var(--bg-map)]"></div>
                <div id="world-globe" data-testid="globe-3d-container" class="absolute inset-0 z-10 hidden h-full w-full select-none overflow-hidden bg-[var(--bg-map)]"></div>
                @include('partials.map-projection-toggle', ['prefix' => 'shipments'])

                <!-- Quiet Map Legend -->
                <div class="pointer-events-none absolute right-3 top-12 z-[400] flex flex-wrap items-center gap-2.5 rounded-md border border-[var(--line-strong)] bg-[var(--bg-overlay)] px-2.5 py-1 text-[11px] font-medium text-[var(--ink-1)] shadow-md sm:right-4 sm:top-4 sm:gap-4 sm:px-3 sm:py-1.5 sm:text-xs">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-[2.5px] w-3.5 bg-[#080B0C] dark:bg-[#C6F432]"></span>
                        Completed leg
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-0 w-3.5 border-t-2 border-dashed border-[var(--ink-2)]"></span>
                        Remaining leg
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block size-2 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
                        Delivered
                    </span>
                </div>
            </div>

            <!-- Single-Elevation Active Shipment Inspector -->
            @if ($focused)
                @php
                    $fBarClass = $focused['current_status'] === 'DELIVERED' ? 'bg-emerald-600 dark:bg-emerald-400'
                        : ($focused['delayed'] ? 'bg-amber-500 dark:bg-amber-400' : 'bg-[#080B0C] dark:bg-[#C6F432]');
                    $fStatusTone = $badgeTone($statusTone($focused['current_status']));
                    $fDelayTone = $badgeTone($delayTone($focused['delay_state']));
                    $latestStop = end($focused['transit_stops']);
                @endphp
                <div class="border-t border-[var(--line)] bg-[var(--bg-overlay)] p-4 md:absolute md:bottom-4 md:left-4 md:right-4 md:z-[400] md:rounded-lg md:border md:border-[var(--line-strong)] md:shadow-2xl xl:left-auto xl:w-[440px]">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-[var(--line)] pb-3">
                        <div class="min-w-0">
                            <span class="text-xs font-medium text-[var(--ink-3)]">{{ $focused['carrier_name'] }} · {{ $focused['service_type'] }}</span>
                            <h3 class="mt-0.5 font-mono text-sm font-bold text-[var(--ink-1)]">{{ $focused['shipment_number'] }}
                                <span class="font-sans font-medium text-[var(--ink-2)]">· {{ $focused['cargo_type'] }}</span>
                            </h3>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if ($focused['delayed'])
                                <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded border border-[var(--line)] bg-[var(--tint)] px-2 py-0.5 text-xs font-semibold {{ $fDelayTone['text'] }}">
                                    <span class="size-1.5 shrink-0 rounded-full {{ $fDelayTone['dot'] }}"></span>
                                    <span>+{{ number_format($focused['delay_hours'], 0) }}h delay</span>
                                </span>
                            @endif
                            <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded border border-[var(--line)] bg-[var(--tint)] px-2 py-0.5 text-xs font-semibold {{ $fStatusTone['text'] }}">
                                <span class="size-1.5 shrink-0 rounded-full {{ $fStatusTone['dot'] }}"></span>
                                <span>{{ $focused['status_label'] }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="py-2.5">
                        <div class="py-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-[var(--ink-2)]">Corridor progress ({{ $focused['inspector_percent'] }}%)
                                    @if ($focused['transit_stop_count'] > 0)
                                        <span class="ml-1.5 font-mono text-[11px] font-semibold text-[var(--ink-1)]">· {{ $focused['transit_stop_count'] }} transit {{ $focused['transit_stop_count'] === 1 ? 'stop' : 'stops' }}</span>
                                    @endif
                                </span>
                                <span class="font-mono text-xs font-medium tabular-nums text-[var(--ink-1)]">{{ $focused['progress_label'] }}</span>
                            </div>

                            <div class="relative my-3 flex items-center">
                                <span title="Origin: {{ $focused['origin_code'] }} · {{ $focused['origin_city'] }}" class="z-10 size-2.5 rounded-full border border-[#080B0C] bg-white dark:border-white dark:bg-[#080B0C]"></span>
                                <div class="relative mx-1 h-[2.5px] flex-1 bg-[var(--line-strong)]">
                                    <div class="absolute inset-y-0 left-0 {{ $fBarClass }}" style="width: {{ $focused['inspector_percent'] }}%"></div>
                                    @foreach ($focused['transit_stops'] as $stop)
                                        <span title="Checkpoint: {{ $stop['name'] }}" class="absolute top-1/2 z-10 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#080B0C] bg-[#C6F432]" style="left: {{ $stop['percentAlong'] }}%"></span>
                                    @endforeach
                                    <span class="absolute top-1/2 z-20 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-[#080B0C] bg-[#C6F432] shadow-sm" style="left: {{ $focused['inspector_percent'] }}%"></span>
                                </div>
                                <span title="Destination: {{ $focused['destination_code'] }} · {{ $focused['destination_city'] }}" class="z-10 size-2.5 rounded-full bg-[var(--ink-2)]"></span>
                            </div>

                            <div class="flex items-baseline justify-between text-xs">
                                <span class="font-mono font-bold text-[var(--ink-1)]">{{ $focused['origin_code'] }} <span class="font-sans font-medium text-[var(--ink-2)]">· {{ $focused['origin_city'] }}</span></span>
                                <span class="font-mono font-bold text-[var(--ink-1)]"><span class="font-sans font-medium text-[var(--ink-2)]">{{ $focused['destination_city'] }} · </span>{{ $focused['destination_code'] }}</span>
                            </div>

                            @if ($focused['transit_stop_count'] > 0)
                                <div class="mt-2 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 py-1.5">
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ink-3)]">Active Transit Route (Origin → Checkpoint → Destination)</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="inline-flex items-center gap-1 rounded border border-[var(--line)] bg-[var(--bg-base)] px-1.5 py-0.5 font-mono text-[11px] font-bold text-[var(--ink-1)]">{{ $focused['origin_code'] }} <span class="font-sans font-normal text-[var(--ink-2)]">{{ $focused['origin_city'] }}</span></span>
                                        @foreach ($focused['transit_stops'] as $stop)
                                            <span class="font-mono text-xs font-bold text-[var(--ink-2)]">━━▶</span>
                                            <span class="inline-flex items-center gap-1 rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-mono text-[11px] font-bold text-[#080B0C]">● {{ $stop['name'] }}</span>
                                        @endforeach
                                        <span class="font-mono text-xs font-bold text-[var(--ink-3)]">╌╌▶</span>
                                        <span class="inline-flex items-center gap-1 rounded border border-[var(--line)] bg-[var(--bg-base)] px-1.5 py-0.5 font-mono text-[11px] font-bold text-[var(--ink-1)]">{{ $focused['destination_code'] }} <span class="font-sans font-normal text-[var(--ink-2)]">{{ $focused['destination_city'] }}</span></span>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-1.5 flex items-baseline justify-between font-mono text-[11px] font-medium tabular-nums text-[var(--ink-3)]">
                                <span>Dep {{ $focused['dep_datetime'] }}</span>
                                <span class="text-right">Arr {{ $focused['arr_expected_datetime'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-[var(--line)] pt-3">
                        <div class="font-mono text-xs font-semibold tabular-nums text-[var(--ink-1)]">{{ number_format($focused['weight_kg']) }} kg · {{ $focused['volume_m3'] }} m³</div>
                        <a href="{{ route('shipments.show', $focused['id']) }}" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md bg-[var(--brand)] px-3 text-xs font-semibold text-[var(--brand-ink)] transition-colors hover:bg-[var(--brand-hover)]">
                            Inspect shipment
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>
                        </a>
                    </div>
                </div>
            @endif
        </section>
    </div>
    @else
    <!-- Full Table View with Sorting & Pagination -->
    <div class="flex-1 overflow-y-auto p-6">
        <div class="mx-auto max-w-[1440px] overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs font-semibold text-[var(--ink-3)]">
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Route</th>
                            <th class="px-4 py-3 font-semibold">Carrier</th>
                            <th class="px-4 py-3 font-semibold">Cargo</th>
                            <th class="px-4 py-3 font-semibold">Arrival</th>
                            <th class="px-4 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--line)]">
                        @forelse ($cards as $c)
                            @php
                                $tTone = $badgeTone($statusTone($c['current_status']));
                                $dTone = $badgeTone($delayTone($c['delay_state']));
                            @endphp
                            <tr onclick="window.location.href='{{ route('shipments.show', $c['id']) }}'" class="cursor-pointer transition-colors hover:bg-[var(--bg-raised)]">
                                <td class="px-4 py-3 font-mono text-xs font-bold text-[var(--ink-1)]">{{ $c['shipment_number'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded border border-[var(--line)] bg-[var(--tint)] px-2 py-0.5 text-xs font-semibold {{ $tTone['text'] }}">
                                            <span class="size-1.5 shrink-0 rounded-full {{ $tTone['dot'] }}"></span>
                                            <span>{{ $c['status_label'] }}</span>
                                        </span>
                                        @if ($c['delayed'])
                                            <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded border border-[var(--line)] bg-[var(--tint)] px-2 py-0.5 text-xs font-semibold {{ $dTone['text'] }}">
                                                <span class="size-1.5 shrink-0 rounded-full {{ $dTone['dot'] }}"></span>
                                                <span>+{{ number_format($c['delay_hours'], 0) }}h</span>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-[var(--ink-1)]">
                                    <span class="mr-1 font-mono font-bold text-[var(--ink-1)]">{{ $c['origin_code'] }}</span>
                                    <span class="font-medium text-[var(--ink-2)]">{{ $c['origin_city'] }}</span>
                                    <span class="mx-1.5 text-[var(--ink-3)]">→</span>
                                    <span class="mr-1 font-mono font-bold text-[var(--ink-1)]">{{ $c['destination_code'] }}</span>
                                    <span class="font-medium text-[var(--ink-2)]">{{ $c['destination_city'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs font-medium text-[var(--ink-2)]">{{ $c['carrier_name'] }} <span class="font-mono text-[var(--ink-3)]">({{ $c['service_type'] }})</span></td>
                                <td class="px-4 py-3 text-xs font-medium text-[var(--ink-2)]">
                                    <div>{{ $c['cargo_type'] }}</div>
                                    <div class="font-mono text-[11px] tabular-nums text-[var(--ink-3)]">{{ number_format($c['weight_kg']) }} kg</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs tabular-nums">
                                    <div class="text-[var(--ink-3)]">Plan {{ $c['plan_short'] }}</div>
                                    <div class="{{ $c['delayed'] ? 'font-semibold text-amber-700 dark:text-amber-300' : 'font-semibold text-[var(--ink-1)]' }}">Est {{ $c['eta_short'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('shipments.show', $c['id']) }}" onclick="event.stopPropagation()" class="text-xs font-semibold text-[var(--ink-1)] hover:underline">Inspect →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-[var(--ink-3)]">No shipments match filters. <a href="{{ route('shipments.index') }}" class="font-medium underline">Clear all filters</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($page)
            <div class="flex items-center justify-between border-t border-[var(--line)] bg-[var(--bg-base)] px-5 py-3 text-xs text-[var(--ink-2)]">
                <span class="tabular-nums">Showing
                    <span class="font-mono font-medium text-[var(--ink-1)]">{{ $page->firstItem() ?? 0 }}</span> to
                    <span class="font-mono font-medium text-[var(--ink-1)]">{{ $page->lastItem() ?? 0 }}</span> of
                    <span class="font-mono font-medium text-[var(--ink-1)]">{{ $page->total() }}</span>
                </span>
                <div class="flex items-center gap-2">
                    @if ($page->previousPageUrl())
                        <a href="{{ $page->previousPageUrl() }}" class="cursor-pointer rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 py-1 text-xs font-medium text-[var(--ink-1)]">Previous</a>
                    @else
                        <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 py-1 text-xs font-medium text-[var(--ink-1)] opacity-40">Previous</span>
                    @endif
                    <span class="font-mono text-xs tabular-nums">{{ $page->currentPage() }} / {{ $page->lastPage() }}</span>
                    @if ($page->nextPageUrl())
                        <a href="{{ $page->nextPageUrl() }}" class="cursor-pointer rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 py-1 text-xs font-medium text-[var(--ink-1)]">Next</a>
                    @else
                        <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 py-1 text-xs font-medium text-[var(--ink-1)] opacity-40">Next</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="{{ asset('js/globe3d.js') }}"></script>
@endpush

@push('scripts')
<script>
    function toggleShipFilters() {
        const panel = document.getElementById('filters-panel');
        const btn = document.getElementById('filters-toggle');
        if (!panel || !btn) return;
        const show = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !show);
        panel.classList.toggle('grid', show);
        btn.classList.toggle('border-[var(--line-strong)]', show);
        btn.classList.toggle('bg-[var(--tint-strong)]', show);
        btn.classList.toggle('font-medium', show);
        btn.classList.toggle('text-[var(--ink-1)]', show);
    }
</script>
@if ($view === 'split')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mapEl = document.getElementById('world-map');
        if (!mapEl || typeof L === 'undefined') return;
        const entries = @json($mapEntries);
        const focusId = {{ $focusId }};
        const focusUrl = function (id) {
            const u = new URL(window.location.href);
            u.searchParams.set('focus', id);
            return u.toString();
        };

        // Real OSM tiles, desaturated to the pale canvas look via CSS
        // (CARTO basemaps now need an API key, and Esri canvas is not OSM data).
        const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
        const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

        const map = L.map(mapEl, {
            worldCopyJump: true,
            attributionControl: true,
            zoomControl: false,
            minZoom: 2,
            maxZoom: 10,
            maxBounds: [[-72, -180], [78, 180]],
            maxBoundsViscosity: 1.0
        }).setView([20, 55], 3);
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        const tileLayer = L.tileLayer(TILES, {
            attribution: ATTRIBUTION, maxZoom: 19, minZoom: 2
        }).addTo(map);
        const group = L.layerGroup().addTo(map);

        function toRad(d) { return d * Math.PI / 180; }
        function toDeg(r) { return r * 180 / Math.PI; }
        function greatCirclePoints(a, b, n) {
            const lat1 = toRad(a[0]), lng1 = toRad(a[1]);
            const lat2 = toRad(b[0]), lng2 = toRad(b[1]);
            const d = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin((lat2 - lat1) / 2), 2) + Math.cos(lat1) * Math.cos(lat2) * Math.pow(Math.sin((lng2 - lng1) / 2), 2)));
            const pts = [];
            for (let i = 0; i <= n; i++) {
                const f = n === 0 ? 0 : i / n;
                const A = Math.sin((1 - f) * d) / Math.sin(d);
                const B = Math.sin(f * d) / Math.sin(d);
                const x = A * Math.cos(lat1) * Math.cos(lng1) + B * Math.cos(lat2) * Math.cos(lng2);
                const y = A * Math.cos(lat1) * Math.sin(lng1) + B * Math.cos(lat2) * Math.sin(lng2);
                const z = A * Math.sin(lat1) + B * Math.sin(lat2);
                pts.push([toDeg(Math.atan2(z, Math.sqrt(x * x + y * y))), toDeg(Math.atan2(y, x))]);
            }
            return pts;
        }
        function bearingDeg(a, b) {
            const y = Math.sin(toRad(b[1] - a[1])) * Math.cos(toRad(b[0]));
            const x = Math.cos(toRad(a[0])) * Math.sin(toRad(b[0])) - Math.sin(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.cos(toRad(b[1] - a[1]));
            return (toDeg(Math.atan2(y, x)) + 360) % 360;
        }
        function pointPopup(s, kind, p) {
            return '<div class="min-w-48 font-sans" style="color:var(--ink-1)">'
                + '<div class="flex items-center justify-between gap-2 pb-1.5" style="border-bottom:1px solid var(--line)">'
                + '<span class="font-mono text-xs font-bold">' + s.shipment_number + '</span>'
                + '<span class="font-mono text-[10px] font-semibold" style="color:var(--ink-2)">' + s.status + '</span></div>'
                + '<p class="mt-2 text-xs font-semibold">' + kind + ': ' + p.location_name + '</p>'
                + '<p class="mt-0.5 font-mono text-[10px]" style="color:var(--ink-3)">' + Number(p.latitude).toFixed(4) + '°, ' + Number(p.longitude).toFixed(4) + '°</p>'
                + (p.time ? '<p class="mt-1 font-mono text-[10px] font-medium" style="color:var(--ink-2)">' + p.time + '</p>' : '')
                + '</div>';
        }

        function render() {
            const isDark = document.documentElement.classList.contains('dark');
            group.clearLayers();
            const bounds = [];
            const renderedPorts = new Set();
            entries.forEach(function (s) {
                const isFocused = focusId !== 0 && s.id === focusId;
                const isDimmed = focusId !== 0 && s.id !== focusId;
                const activeLineColor = s.status === 'DELIVERED' ? (isDark ? '#10B981' : '#047857')
                    : s.delayed ? (isDark ? '#F59E0B' : '#D97706')
                    : (isDark ? '#C6F432' : '#080B0C');
                const secondaryLineColor = isDark ? '#525E64' : '#64748B';
                const near = function (a, b, eps) {
                    eps = eps || 0.02;
                    return Math.abs(a[0] - b[0]) < eps && Math.abs(a[1] - b[1]) < eps;
                };

                if (s.origin && s.destination) {
                    const o = [s.origin.latitude, s.origin.longitude];
                    const d = [s.destination.latitude, s.destination.longitude];
                    const raw = [];
                    (s.checkpoints || []).slice().reverse().forEach(function (cp) { raw.push(cp); });
                    if (s.current_position) raw.push(s.current_position);
                    const stops = [];
                    raw.forEach(function (pt) {
                        const c = [pt.latitude, pt.longitude];
                        if (near(c, o) || near(c, d)) return;
                        if (stops.some(function (e) { return near([e.latitude, e.longitude], c); })) return;
                        stops.push(pt);
                    });
                    const waypoints = [o].concat(stops.map(function (pt) { return [pt.latitude, pt.longitude]; }));
                    const hasMoved = waypoints.length >= 2;

                    if (s.status === 'DELIVERED') {
                        waypoints.push(d);
                        let arc = [];
                        for (let i = 0; i < waypoints.length - 1; i++) {
                            const seg = greatCirclePoints(waypoints[i], waypoints[i + 1], 28);
                            if (i > 0) seg.shift();
                            arc = arc.concat(seg);
                        }
                        L.polyline(arc, { color: isFocused ? activeLineColor : secondaryLineColor, weight: isFocused ? 3 : 1.5, opacity: isDimmed ? 0.28 : 0.98, lineCap: 'round' }).addTo(group);
                    } else if (hasMoved) {
                        let completed = [];
                        for (let i = 0; i < waypoints.length - 1; i++) {
                            const seg = greatCirclePoints(waypoints[i], waypoints[i + 1], 24);
                            if (i > 0) seg.shift();
                            completed = completed.concat(seg);
                        }
                        const latestStop = waypoints[waypoints.length - 1];
                        const remaining = greatCirclePoints(latestStop, d, 36);
                        if (completed.length >= 2) {
                            L.polyline(completed, { color: isFocused ? activeLineColor : secondaryLineColor, weight: isFocused ? 3.2 : 1.5, opacity: isDimmed ? 0.28 : 0.98, lineCap: 'round' }).addTo(group);
                        }
                        L.polyline(remaining, { color: isFocused ? (isDark ? '#B0BEC4' : '#3D4850') : secondaryLineColor, weight: isFocused ? 2.1 : 1.25, dashArray: '5 6', opacity: isDimmed ? 0.25 : 0.9 }).addTo(group);
                    } else {
                        L.polyline(greatCirclePoints(o, d, 48), { color: isFocused ? activeLineColor : secondaryLineColor, weight: isFocused ? 2.8 : 1.3, dashArray: '4 6', opacity: isDimmed ? 0.25 : (isFocused ? 0.96 : 0.5) }).addTo(group);
                    }

                    if (isFocused && stops.length > 0) {
                        stops.forEach(function (cp, idx) {
                            L.circleMarker([cp.latitude, cp.longitude], { radius: 5.5, color: '#080B0C', weight: 2, fillColor: '#C6F432', fillOpacity: 1 })
                                .addTo(group)
                                .bindPopup(pointPopup(s, 'Transit Stop #' + (idx + 1), cp))
                                .bindTooltip('Transit #' + (idx + 1) + ': ' + String(cp.location_name).split('·')[0].trim(), { permanent: isFocused, direction: 'top', offset: [0, -6], className: 'ct-tooltip' });
                        });
                    }

                    const oKey = o[0].toFixed(3) + ',' + o[1].toFixed(3);
                    if (!renderedPorts.has(oKey) || isFocused) {
                        renderedPorts.add(oKey);
                        L.circleMarker(o, { radius: isFocused ? 6 : 4, color: isDark ? '#FFFFFF' : '#080B0C', weight: 2.2, fillColor: isDark ? '#080B0C' : '#FFFFFF', fillOpacity: 1, opacity: isDimmed ? 0.4 : 0.98 })
                            .addTo(group)
                            .bindPopup(pointPopup(s, 'Origin port', s.origin))
                            .bindTooltip(isFocused && s.origin.code ? 'Origin: ' + s.origin.code : s.origin.location_name, { permanent: isFocused, direction: 'left', offset: [-8, 0], className: 'ct-tooltip' });
                    }
                    const dKey = 'dest:' + d[0].toFixed(3) + ',' + d[1].toFixed(3);
                    if (!renderedPorts.has(dKey) || isFocused) {
                        renderedPorts.add(dKey);
                        L.circleMarker(d, { radius: isFocused ? 6.2 : 4.2, color: isFocused ? activeLineColor : (isDark ? '#B0BEC4' : '#1A2125'), weight: 2.2, fillColor: isFocused ? activeLineColor : (isDark ? '#172024' : '#FFFFFF'), fillOpacity: 0.98, opacity: isDimmed ? 0.4 : 0.98 })
                            .addTo(group)
                            .bindPopup(pointPopup(s, 'Destination port', s.destination))
                            .bindTooltip(isFocused && s.destination.code ? 'Dest: ' + s.destination.code : s.destination.location_name, { permanent: isFocused, direction: 'bottom', offset: [0, 8], className: 'ct-tooltip' });
                    }
                    bounds.push(o, d);
                }

                if (s.current_position) {
                    const pos = [s.current_position.latitude, s.current_position.longitude];
                    let heading = 45;
                    if (s.destination && s.status !== 'DELIVERED' && (Math.abs(pos[0] - s.destination.latitude) > 0.02 || Math.abs(pos[1] - s.destination.longitude) > 0.02)) {
                        heading = bearingDeg(pos, [s.destination.latitude, s.destination.longitude]);
                    } else if (s.previous_position) {
                        heading = bearingDeg([s.previous_position.latitude, s.previous_position.longitude], pos);
                    }
                    const badgeBg = isFocused ? '#C6F432' : '#080B0C';
                    const badgeBorder = isFocused ? '#080B0C' : (s.delayed ? '#F59E0B' : '#C6F432');
                    const arrowFill = isFocused ? '#080B0C' : (s.delayed ? '#F59E0B' : '#C6F432');
                    const icon = L.divIcon({
                        className: '',
                        html: '<div class="relative flex items-center justify-center ' + (isDimmed ? 'opacity-40' : 'opacity-100') + '">'
                            + '<div class="rounded-full p-1 shadow-md" style="background:' + badgeBg + ';border:1.5px solid ' + badgeBorder + ';transform:rotate(' + heading + 'deg)">'
                            + '<svg width="13" height="13" viewBox="0 0 24 24" fill="' + arrowFill + '"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/></svg></div>'
                            + (isFocused ? '<span class="ct-port-label absolute bottom-7 left-1/2 -translate-x-1/2 whitespace-nowrap">' + s.shipment_number + '</span>' : '')
                            + '</div>',
                        iconSize: [24, 24], iconAnchor: [12, 12]
                    });
                    const marker = L.marker(pos, { icon: icon, zIndexOffset: isFocused ? 1000 : 500 })
                        .addTo(group)
                        .bindPopup(pointPopup(s, 'Current position', s.current_position))
                        .bindTooltip(s.shipment_number + ' · ' + s.current_position.location_name, { direction: 'top', className: 'ct-tooltip' });
                    marker.on('click', function () { window.location.href = focusUrl(s.id); });
                    bounds.push(pos);
                }
            });

            if (focusId !== 0 && bounds.length > 0) {
                const focused = entries.find(function (e) { return e.id === focusId; });
                const fb = [];
                if (focused) {
                    if (focused.origin) fb.push([focused.origin.latitude, focused.origin.longitude]);
                    (focused.checkpoints || []).forEach(function (cp) { fb.push([cp.latitude, cp.longitude]); });
                    if (focused.destination) fb.push([focused.destination.latitude, focused.destination.longitude]);
                    if (focused.current_position) fb.push([focused.current_position.latitude, focused.current_position.longitude]);
                }
                map.fitBounds(fb.length > 0 ? fb : bounds, { padding: [70, 70], maxZoom: 5 });
            } else if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [55, 55], maxZoom: 4 });
            }
            map.invalidateSize();
        }

        render();
        window.addEventListener('ct:theme', render);
        window.addEventListener('load', function () { map.invalidateSize(); });

        // 3D globe projection (vanilla canvas port of Globe3DCanvas.tsx).
        // Same entries as the Leaflet map; click a vessel marker to focus it.
        var globeEl = document.getElementById('world-globe');
        if (globeEl && window.CargoTraceGlobe) {
            var globe = window.CargoTraceGlobe.mount(globeEl, entries, focusId, function (id) {
                window.location.href = focusUrl(id);
            });
            window.CargoTraceGlobe.initProjection({
                btn2d: document.getElementById('shipments-proj-2d'),
                btn3d: document.getElementById('shipments-proj-3d'),
                mapEl: mapEl,
                globeEl: globeEl,
                onShow2D: function () { map.invalidateSize(); },
                onShow3D: function () { globe.render(); }
            });
        }
    });
</script>
@endif
@endpush
@endsection
