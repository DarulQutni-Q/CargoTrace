@extends('layouts.app')

@section('title', $shipment->shipment_number)
@section('header_title', $shipment->shipment_number)
@section('header_subtitle', $shipment->origin->code . ' (' . $shipment->origin->city . ') → ' . $shipment->destination->code . ' (' . $shipment->destination->city . ')')

@php
    $statusLabel = ['CREATED' => 'Created', 'BOOKED' => 'Booked', 'PICKED_UP' => 'Picked up', 'IN_TRANSIT' => 'In transit', 'DELIVERED' => 'Delivered'];
    $statusTone = fn (string $st): string => match ($st) {
        'CREATED' => 'neutral', 'BOOKED' => 'amber', 'PICKED_UP' => 'teal',
        'IN_TRANSIT' => 'blue', 'DELIVERED' => 'green', default => 'neutral',
    };
    $delayTone = fn (string $state): string => match ($state) {
        'low' => 'amber', 'medium' => 'orange', 'high' => 'red',
        'on_time' => 'green', default => 'neutral',
    };
    $excSeverityTone = fn (string $sev): string => match ($sev) {
        'LOW' => 'amber', 'MEDIUM' => 'orange', 'HIGH' => 'red', default => 'neutral',
    };
    $excStatusTone = fn (string $st): string => match ($st) {
        'OPEN' => 'red', 'INVESTIGATING' => 'amber', 'RESOLVED' => 'green', default => 'neutral',
    };
    $lifecycleSteps = ['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'];
    $currentStepIdx = array_search($shipment->current_status, $lifecycleSteps);
    $events = $shipment->trackingEvents->sortByDesc('occurred_at')->values();
    $exceptions = $shipment->exceptions->sortByDesc('detected_at')->values();
    $openCount = $exceptions->where('status', '!=', 'RESOLVED')->count();
    $hasOriginEvent = $events->contains(function ($ev) use ($shipment) {
        return $ev->latitude !== null && $ev->longitude !== null
            && abs($ev->latitude - $shipment->origin->latitude) < 0.02
            && abs($ev->longitude - $shipment->origin->longitude) < 0.02;
    });
    $latestEvent = $events->first();
    $defaultOccurred = ($latestEvent?->occurred_at ?? $shipment->expected_departure_at)?->copy()->addHours(4)->format('Y-m-d\TH:i');
    $defaultEta = ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->format('Y-m-d\TH:i');
    $defaultDetected = ($latestEvent?->occurred_at ?? $shipment->expected_departure_at)?->copy()->addHours(2)->format('Y-m-d\TH:i');
    $defaultExcEta = ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->copy()->addHours(12)->format('Y-m-d\TH:i');
    $checkpointEvents = $events->where('event_type', 'CHECKPOINT');
    $canOperate = auth()->user()->canOperateCorridor();
@endphp

@section('content')
<div class="flex min-h-[calc(100vh-56px)] flex-col bg-[var(--bg-base)] lg:h-[calc(100vh-56px)] lg:overflow-hidden">
    @if (session('status'))
        <div class="mx-3 mt-3 shrink-0 rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm sm:mx-5">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mx-3 mt-3 shrink-0 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 sm:mx-5">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Top Control & Lifecycle Bar -->
    <div class="shrink-0 border-b border-[var(--line)] bg-[var(--bg-base)] px-3 py-2.5 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-2.5 sm:gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('shipments.index') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs text-[var(--ink-2)] transition-colors hover:border-[var(--line-strong)] hover:text-[var(--ink-1)]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    Shipments
                </a>
                <span class="h-4 w-[1px] bg-[var(--line)]"></span>
                <span class="font-mono text-sm font-semibold">{{ $shipment->shipment_number }}</span>
                <span class="text-xs text-[var(--ink-3)]">· {{ $shipment->cargo_type }}</span>
                @include('partials.badge', ['tone' => $statusTone($shipment->current_status), 'label' => $statusLabel[$shipment->current_status] ?? $shipment->current_status])
                @include('partials.badge', ['tone' => $delayTone($delay['state'] ?? 'pending'), 'label' => ($delay['state'] ?? 'pending') === 'on_time' ? 'On schedule' : (($delay['state'] ?? 'pending') === 'pending' ? 'ETA pending' : '+' . $delay['delay_hours'] . 'h delay (' . ($delay['state'] ?? '') . ')')])
            </div>

            <div class="hidden items-center gap-2 text-xs xl:flex">
                @foreach ($lifecycleSteps as $idx => $st)
                    @php $done = $idx < $currentStepIdx; $active = $idx === $currentStepIdx; @endphp
                    <span class="inline-flex items-center gap-1.5 {{ $active ? 'font-semibold text-[var(--ink-1)]' : ($done ? 'text-[var(--ink-2)]' : 'text-[var(--ink-3)]') }}">
                        @if ($done)
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3 text-emerald-500 dark:text-emerald-400"><path d="M20 6 9 17l-5-5"/></svg>
                        @elseif ($active)
                            <span class="size-2 rounded-full border border-[#080B0C] bg-[#C6F432]"></span>
                        @else
                            <span class="size-1.5 rounded-full bg-[var(--line-strong)]"></span>
                        @endif
                        <span>{{ $statusLabel[$st] }}</span>
                    </span>
                    @if ($idx < count($lifecycleSteps) - 1)
                        <span class="h-[1px] w-4 bg-[var(--line-strong)]"></span>
                    @endif
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="toggleBillModal(true)" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs font-medium text-[var(--ink-2)] transition-colors hover:border-[var(--line-strong)] hover:text-[var(--ink-1)]">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print B/L
                </button>
                @if ($canOperate && ! empty($allowedNext))
                    <form method="POST" action="{{ route('shipments.advance', $shipment) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[#080B0C] bg-[#C6F432] px-2.5 text-xs font-semibold text-[#080B0C] transition-colors hover:bg-[#b5e422]">
                            <span>Advance → {{ $statusLabel[$allowedNext[0]] ?? $allowedNext[0] }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </button>
                    </form>
                @endif
                @if ($canOperate)
                    <button type="button" id="btn-checkpoint" onclick="toggleDetailForm('checkpoint')" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs text-[var(--ink-2)] transition-colors hover:text-[var(--ink-1)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        Add checkpoint
                    </button>
                    <button type="button" id="btn-exception" onclick="toggleDetailForm('exception')" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs text-[var(--ink-2)] transition-colors hover:text-[var(--ink-1)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        Raise exception
                    </button>
                    <a href="{{ route('shipments.edit', $shipment) }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs text-[var(--ink-2)] transition-colors hover:border-[var(--line-strong)] hover:text-[var(--ink-1)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                        Edit
                    </a>
                    <span id="archive-idle">
                        <button type="button" onclick="toggleArchiveConfirm(true)" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs text-[var(--ink-3)] transition-colors hover:border-rose-500/40 hover:text-rose-500">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                            Archive
                        </button>
                    </span>
                    <span id="archive-confirm" class="hidden items-center gap-1.5 rounded-md border border-rose-500/40 bg-[var(--bg-surface)] px-2.5 py-1 text-xs">
                        <span class="text-rose-600 dark:text-rose-300">Archive?</span>
                        <form method="POST" action="{{ route('shipments.archive', $shipment) }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded bg-rose-500 px-2 py-0.5 font-medium text-white">Yes</button>
                        </form>
                        <button type="button" onclick="toggleArchiveConfirm(false)" class="text-[var(--ink-2)]">No</button>
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Main Split Workspace -->
    <div class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[460px_1fr]">
        <section class="flex min-h-0 flex-col border-b border-[var(--line)] bg-[var(--bg-base)] lg:overflow-y-auto lg:border-b-0 lg:border-r">
            @if ($canOperate)
                <!-- Collapsible Add Checkpoint Form -->
                <form id="form-checkpoint" method="POST" action="{{ route('shipments.tracking-events.store', $shipment) }}" class="hidden space-y-3 border-b border-[var(--line)] bg-[var(--bg-surface)] px-3.5 py-4 sm:px-5">
                    @csrf
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold">Append transit checkpoint (Mid-route stop)</span>
                            <p class="text-[11px] text-[var(--ink-3)]">Inserts an intermediate waypoint between {{ $shipment->origin->code }} ({{ $shipment->origin->city }}) and {{ $shipment->destination->code }} ({{ $shipment->destination->city }})</p>
                        </div>
                        <button type="button" onclick="toggleDetailForm('checkpoint')" class="text-[var(--ink-3)] hover:text-[var(--ink-1)]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <div class="rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 py-2 text-xs">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ink-3)]">Corridor Trajectory Preview</div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 font-mono text-[11px]">
                            <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 font-bold">{{ $shipment->origin->code }} · {{ $shipment->origin->city }}</span>
                            <span class="font-bold text-[var(--ink-2)]">━━▶</span>
                            <span class="rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-bold text-[#080B0C]">● <span id="checkpoint-preview-name">Select transit stop (e.g. Port of Singapore)</span></span>
                            <span class="font-bold text-[var(--ink-3)]">╌╌▶</span>
                            <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 font-bold">{{ $shipment->destination->code }} · {{ $shipment->destination->city }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div>
                            <label for="event_type" class="mb-1 block text-[11px] text-[var(--ink-3)]">Event / status transition</label>
                            <select id="event_type" name="event_type" required class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                                <option value="CHECKPOINT">CHECKPOINT (Position scan only)</option>
                                @foreach (['CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED'] as $st)
                                    <option value="{{ $st }}">{{ in_array($st, $allowedNext) ? '★ Transition → ' . ($statusLabel[$st] ?? $st) . ' (Allowed next)' : 'Transition → ' . ($statusLabel[$st] ?? $st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="occurred_at" class="mb-1 block text-[11px] text-[var(--ink-3)]">Occurred at (UTC) *</label>
                            <input id="occurred_at" name="occurred_at" type="datetime-local" required value="{{ $defaultOccurred }}" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                        </div>
                    </div>
                    <div class="relative">
                        <label for="location_name" class="mb-1 block text-[11px] text-[var(--ink-3)]">Location / transit hub * (type or pick preset)</label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input id="location_name" name="location_name" type="text" required placeholder="Type hub (e.g. Suez Canal, Colombo, Singapore...)" list="hub-suggestions" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] pl-2.5 pr-2.5 text-xs placeholder:text-[var(--ink-3)]" />
                                <datalist id="hub-suggestions">
                                    @foreach ($hubSuggestions as $hub)
                                        <option value="{{ $hub['name'] }}">{{ $hub['region'] }} · {{ number_format($hub['latitude'], 4) }}°, {{ number_format($hub['longitude'], 4) }}°</option>
                                    @endforeach
                                </datalist>
                            </div>
                            <button type="button" id="btn-geocode" onclick="geocodeWaypoint()" class="inline-flex h-8 items-center gap-1 rounded-md border border-[var(--line)] bg-[var(--bg-raised)] px-2.5 text-xs hover:border-[var(--line-strong)]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                                Geocode
                            </button>
                        </div>
                        <p id="geocode-status" class="mt-1 hidden text-[11px]"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label for="ev_lat" class="mb-1 block text-[11px] text-[var(--ink-3)]">Latitude</label>
                            <input id="ev_lat" name="latitude" type="number" step="any" placeholder="e.g. 6.9531" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
                        </div>
                        <div>
                            <label for="ev_lng" class="mb-1 block text-[11px] text-[var(--ink-3)]">Longitude</label>
                            <input id="ev_lng" name="longitude" type="number" step="any" placeholder="e.g. 79.8506" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 font-mono text-xs" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div>
                            <label for="event_eta" class="mb-1 block text-[11px] text-[var(--ink-3)]">Updated Estimated Arrival (ETA)</label>
                            <input id="event_eta" name="estimated_arrival_at" type="datetime-local" value="{{ $defaultEta }}" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                        </div>
                        <div>
                            <label for="event_desc" class="mb-1 block text-[11px] text-[var(--ink-3)]">Checkpoint notes</label>
                            <input id="event_desc" name="description" type="text" placeholder="Vessel transit remarks..." class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="font-mono text-[11px] text-[var(--ink-3)]">{{ ! empty($allowedNext) ? 'Allowed next: ' . implode(', ', $allowedNext) : 'Terminal state (DELIVERED)' }}</span>
                        <button type="submit" class="h-8 rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C] hover:bg-[#b5e422]">Save checkpoint</button>
                    </div>
                </form>

                <!-- Collapsible Raise Exception Form -->
                <form id="form-exception" method="POST" action="{{ route('shipments.exceptions.store', $shipment) }}" class="hidden space-y-3 border-b border-[var(--line)] bg-[var(--bg-surface)] px-3.5 py-4 sm:px-5">
                    @csrf
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold">Raise operational exception</span>
                        <button type="button" onclick="toggleDetailForm('exception')" class="text-[var(--ink-3)] hover:text-[var(--ink-1)]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div>
                            <label for="exc_type" class="mb-1 block text-[11px] text-[var(--ink-3)]">Exception type *</label>
                            <select id="exc_type" name="type" required class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                                @foreach (\App\Models\ShipmentException::TYPES as $type)
                                    <option value="{{ $type }}" {{ $type === 'CUSTOMS_HOLD' ? 'selected' : '' }}>{{ \App\Models\ShipmentException::typeLabel($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="exc_severity" class="mb-1 block text-[11px] text-[var(--ink-3)]">Severity *</label>
                            <select id="exc_severity" name="severity" required class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs">
                                <option value="LOW">LOW</option>
                                <option value="MEDIUM" selected>MEDIUM</option>
                                <option value="HIGH">HIGH</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div>
                            <label for="exc_detected" class="mb-1 block text-[11px] text-[var(--ink-3)]">Detected at (UTC) *</label>
                            <input id="exc_detected" name="detected_at" type="datetime-local" required value="{{ $defaultDetected }}" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                        </div>
                        <div>
                            <label for="exc_eta" class="mb-1 block text-[11px] text-[var(--ink-3)]">Updated Estimated Arrival (ETA)</label>
                            <input id="exc_eta" name="estimated_arrival_at" type="datetime-local" value="{{ $defaultExcEta }}" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 text-xs" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <input type="text" name="location_name" placeholder="Hub / port detected..." value="{{ $latestEvent?->location_name ?? $shipment->origin->name }}" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
                        <input type="text" name="description" placeholder="Exception description / hold reason..." class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2.5 text-xs" />
                    </div>
                    <div class="flex justify-end pt-1">
                        <button type="submit" class="h-8 rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C] hover:bg-[#b5e422]">Submit exception</button>
                    </div>
                </form>
            @endif

            <!-- Section 1: Corridor Progress & Schedule Dossier -->
            <div class="border-b border-[var(--line)] px-5 py-4">
                <div class="py-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-[var(--ink-2)]">
                            Corridor progress ({{ $card['inspector_percent'] }}%)
                            @if ($card['transit_stop_count'] > 0)
                                <span class="ml-1.5 font-mono text-[11px] font-semibold text-[var(--ink-1)]">· {{ $card['transit_stop_count'] }} transit {{ $card['transit_stop_count'] === 1 ? 'stop' : 'stops' }}</span>
                            @endif
                        </span>
                        <span class="font-mono text-xs font-medium tabular-nums">{{ $card['progress_label'] }}</span>
                    </div>
                    <div class="relative my-3 flex items-center">
                        <span title="Origin: {{ $shipment->origin->code }} · {{ $shipment->origin->city }}" class="z-10 size-2.5 rounded-full border border-[#080B0C] bg-white dark:border-white dark:bg-[#080B0C]"></span>
                        <div class="relative mx-1 h-[2.5px] flex-1 bg-[var(--line-strong)]">
                            <div class="absolute inset-y-0 left-0 {{ $shipment->current_status === 'DELIVERED' ? 'bg-emerald-600 dark:bg-emerald-400' : ($card['delayed'] ? 'bg-amber-500 dark:bg-amber-400' : 'bg-[#080B0C] dark:bg-[#C6F432]') }}" style="width: {{ $card['inspector_percent'] }}%"></div>
                            @foreach ($card['transit_stops'] as $stop)
                                <span title="Checkpoint: {{ $stop['name'] }}" class="absolute top-1/2 z-10 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#080B0C] bg-[#C6F432]" style="left: {{ $stop['percentAlong'] }}%"></span>
                            @endforeach
                            <span class="absolute top-1/2 z-20 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-[#080B0C] bg-[#C6F432] shadow-sm" style="left: {{ $card['inspector_percent'] }}%"></span>
                        </div>
                        <span title="Destination: {{ $shipment->destination->code }} · {{ $shipment->destination->city }}" class="z-10 size-2.5 rounded-full bg-[var(--ink-2)]"></span>
                    </div>
                    <div class="flex items-baseline justify-between text-xs">
                        <span class="font-mono font-bold">{{ $shipment->origin->code }} <span class="font-sans font-medium text-[var(--ink-2)]">· {{ $shipment->origin->city }}</span></span>
                        <span class="font-mono font-bold"><span class="font-sans font-medium text-[var(--ink-2)]">{{ $shipment->destination->city }} · </span>{{ $shipment->destination->code }}</span>
                    </div>
                    @if ($card['transit_stop_count'] > 0)
                        <div class="mt-2 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 py-1.5">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ink-3)]">Active Transit Route (Origin → Checkpoint → Destination)</div>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                <span class="inline-flex items-center gap-1 rounded border border-[var(--line)] bg-[var(--bg-base)] px-1.5 py-0.5 font-mono text-[11px] font-bold">{{ $shipment->origin->code }} <span class="font-sans font-normal text-[var(--ink-2)]">{{ $shipment->origin->city }}</span></span>
                                @foreach ($card['transit_stops'] as $stop)
                                    <span class="font-mono text-xs font-bold text-[var(--ink-2)]">━━▶</span>
                                    <span class="inline-flex items-center gap-1 rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 font-mono text-[11px] font-bold text-[#080B0C]">● {{ $stop['name'] }}</span>
                                @endforeach
                                <span class="font-mono text-xs font-bold text-[var(--ink-3)]">╌╌▶</span>
                                <span class="inline-flex items-center gap-1 rounded border border-[var(--line)] bg-[var(--bg-base)] px-1.5 py-0.5 font-mono text-[11px] font-bold">{{ $shipment->destination->code }} <span class="font-sans font-normal text-[var(--ink-2)]">{{ $shipment->destination->city }}</span></span>
                            </div>
                        </div>
                    @endif
                    <div class="mt-1.5 flex items-baseline justify-between font-mono text-[11px] font-medium tabular-nums text-[var(--ink-3)]">
                        <span>Dep {{ $shipment->expected_departure_at?->format('M j · H:i') }}</span>
                        <span class="text-right">Arr {{ $shipment->expected_arrival_at?->format('M j · H:i') }}</span>
                    </div>
                </div>

                <div class="mt-4 divide-y divide-[var(--line)] border-t border-[var(--line)] text-xs">
                    <div class="flex items-center justify-between py-2">
                        <span class="text-[var(--ink-3)]">Carrier &amp; mode</span>
                        <span>{{ $shipment->carrier->name }} <span class="font-mono text-[var(--ink-2)]">({{ $shipment->carrier->code }} · {{ $shipment->carrier->service_type }})</span></span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-[var(--ink-3)]">Consignee</span>
                        <span>{{ $shipment->customer->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-[var(--ink-3)]">Total load</span>
                        <span class="font-mono tabular-nums">{{ number_format($shipment->weight_kg) }} kg · {{ $shipment->volume_m3 }} m³</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-[var(--ink-3)]">Planned arrival</span>
                        <span class="font-mono tabular-nums">{{ $shipment->expected_arrival_at?->format('M j, Y · H:i') ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-[var(--ink-3)]">Estimated arrival</span>
                        <span class="font-mono font-medium tabular-nums {{ $card['delayed'] ? 'text-amber-600 dark:text-amber-300' : '' }}">{{ ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->format('M j, Y · H:i') ?? '—' }}</span>
                    </div>
                    <div class="py-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--ink-3)]">Delay rule ({{ $scenario->group_code ?? 'DEFAULT' }})</span>
                            <span class="font-mono text-[11px] tabular-nums text-[var(--ink-2)]">Low ≤{{ $delay['threshold_low'] ?? 6 }}h · Med ≤{{ $delay['threshold_high'] ?? 24 }}h ({{ $card['delayed'] ? '+' . $delay['delay_hours'] . 'h' : '0h' }})</span>
                        </div>
                        <p class="mt-1 text-[11px] text-[var(--ink-3)]">{{ $delay['reason'] ?? '' }}</p>
                    </div>
                </div>
            </div>

            <!-- Section 2: Exceptions -->
            <div class="border-b border-[var(--line)] px-5 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-medium text-[var(--ink-2)]">Exceptions</h3>
                        <span class="font-mono text-xs text-[var(--ink-3)]">({{ $exceptions->count() }})</span>
                    </div>
                    @if ($openCount > 0)
                        <span class="font-mono text-[11px] font-medium text-rose-600 dark:text-rose-400">{{ $openCount }} open</span>
                    @else
                        <span class="text-[11px] text-[var(--ink-3)]">None active</span>
                    @endif
                </div>
                @if ($exceptions->isEmpty())
                    <p class="mt-2 text-xs text-[var(--ink-3)]">No operational exceptions recorded for this shipment.</p>
                @else
                    <div class="mt-2.5 divide-y divide-[var(--line)] border-t border-[var(--line)]">
                        @foreach ($exceptions as $ex)
                            <div class="py-3 first:pt-2.5 last:pb-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium">{{ \App\Models\ShipmentException::typeLabel($ex->type) }}</span>
                                    <div class="flex items-center gap-1.5">
                                        @include('partials.badge', ['tone' => $excSeverityTone($ex->severity), 'label' => $ex->severity])
                                        @include('partials.badge', ['tone' => $excStatusTone($ex->status), 'label' => $ex->status])
                                    </div>
                                </div>
                                @if ($ex->description)
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--ink-2)]">{{ $ex->description }}</p>
                                @endif
                                <div class="mt-1.5 flex items-center justify-between gap-2">
                                    <span class="font-mono text-[11px] tabular-nums text-[var(--ink-3)]">Detected {{ $ex->detected_at->format('M j, Y · H:i') }}{{ $ex->resolved_at ? ' · Resolved ' . $ex->resolved_at->format('M j, Y · H:i') : '' }}</span>
                                    @if ($canOperate)
                                        @php
                                            $nextStates = $ex->status === 'OPEN' ? ['INVESTIGATING', 'RESOLVED'] : ($ex->status === 'INVESTIGATING' ? ['RESOLVED'] : []);
                                        @endphp
                                        @if (! empty($nextStates))
                                            <div class="flex items-center gap-1.5">
                                                @foreach ($nextStates as $target)
                                                    <form method="POST" action="{{ route('exceptions.update', $ex) }}">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $target }}" />
                                                        <button type="submit" class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-2 py-0.5 text-[11px] font-medium hover:border-[var(--line-strong)]">Mark {{ strtolower($target) }}</button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Section 3: Tracking Timeline -->
            <div class="border-b border-[var(--line)] px-5 py-4">
                <div class="mb-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-semibold text-[var(--ink-2)]">Tracking timeline</h3>
                        <span class="font-mono text-xs text-[var(--ink-3)]">({{ $events->count() }})</span>
                    </div>
                    <span class="text-[11px] font-medium text-[var(--ink-3)]">{{ $shipment->origin->code }} → {{ $events->whereNotNull('latitude')->isNotEmpty() ? 'Checkpoints → ' : '' }}{{ $shipment->destination->code }}</span>
                </div>
                <ol class="relative ml-1.5 space-y-4 border-l border-[var(--line-strong)]">
                    @if ($shipment->current_status !== 'DELIVERED')
                        <li class="ml-4">
                            <span class="absolute -left-[5px] mt-1.5 size-2.5 rounded-full border-2 border-dashed border-[var(--ink-2)] bg-[var(--bg-base)]"></span>
                            <div class="border-b border-dashed border-[var(--line)] pb-3">
                                <div class="flex items-baseline justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="truncate text-xs font-semibold text-[var(--ink-2)]">{{ $shipment->destination->code }} · {{ $shipment->destination->name }} ({{ $shipment->destination->city }})</span>
                                        <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 font-mono text-[10px] font-semibold text-[var(--ink-3)]">TARGET DESTINATION</span>
                                    </div>
                                    <time class="shrink-0 font-mono text-[11px] tabular-nums text-[var(--ink-3)]">ETA {{ ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->format('M j · H:i') }}</time>
                                </div>
                                <div class="mt-1 flex items-center justify-between text-[10px] text-[var(--ink-3)]">
                                    <span class="inline-flex items-center gap-1 font-mono tabular-nums">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                                        <span>{{ number_format($shipment->destination->latitude, 4) }}°, {{ number_format($shipment->destination->longitude, 4) }}°</span>
                                    </span>
                                    <span>Remaining leg to {{ $shipment->destination->code }}</span>
                                </div>
                            </div>
                        </li>
                    @endif
                    @foreach ($events as $idx => $ev)
                        <li class="ml-4">
                            <span class="absolute -left-[4.5px] mt-1.5 size-2 rounded-full {{ $ev->event_type === 'EXCEPTION' ? 'bg-rose-500' : ($idx === 0 ? 'border border-[#080B0C] bg-[#C6F432]' : 'bg-[var(--ink-3)]') }}"></span>
                            <div class="border-b border-[var(--line)] pb-3.5 last:border-b-0 last:pb-0">
                                <div class="flex items-baseline justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="truncate text-xs font-semibold">{{ $ev->location_name }}</span>
                                        <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 font-mono text-[10px] font-semibold text-[var(--ink-2)]">{{ $ev->event_type }}</span>
                                    </div>
                                    <time class="shrink-0 font-mono text-[11px] tabular-nums text-[var(--ink-3)]">{{ $ev->occurred_at->format('M j · H:i') }}</time>
                                </div>
                                @if ($ev->description)
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--ink-2)]">{{ $ev->description }}</p>
                                @endif
                                <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-[10px] text-[var(--ink-3)]">
                                    @if ($ev->latitude !== null && $ev->longitude !== null)
                                        <span class="inline-flex items-center gap-1 font-mono tabular-nums">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                                            <span>{{ number_format($ev->latitude, 4) }}°, {{ number_format($ev->longitude, 4) }}°</span>
                                        </span>
                                    @else
                                        <span></span>
                                    @endif
                                    <span>Recorded by {{ $ev->creator->name ?? 'System' }}</span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                    @if (! $hasOriginEvent)
                        <li class="ml-4">
                            <span class="absolute -left-[5px] mt-1.5 size-2.5 rounded-full border-2 border-[#080B0C] bg-white dark:border-white dark:bg-[#080B0C]"></span>
                            <div class="pt-0.5">
                                <div class="flex items-baseline justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="truncate text-xs font-semibold">{{ $shipment->origin->code }} · {{ $shipment->origin->name }} ({{ $shipment->origin->city }})</span>
                                        <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 font-mono text-[10px] font-semibold text-[var(--ink-2)]">ORIGIN PORT</span>
                                    </div>
                                    <time class="shrink-0 font-mono text-[11px] tabular-nums text-[var(--ink-3)]">Dep {{ $shipment->expected_departure_at?->format('M j · H:i') }}</time>
                                </div>
                                <div class="mt-1 flex items-center justify-between text-[10px] text-[var(--ink-3)]">
                                    <span class="inline-flex items-center gap-1 font-mono tabular-nums">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                                        <span>{{ number_format($shipment->origin->latitude, 4) }}°, {{ number_format($shipment->origin->longitude, 4) }}°</span>
                                    </span>
                                    <span>Corridor origin anchor</span>
                                </div>
                            </div>
                        </li>
                    @endif
                </ol>
            </div>

            <!-- Section 4: Cargo Manifest & Corridor Weather -->
            <div class="space-y-4 px-5 py-4">
                <div>
                    <h4 class="mb-2 text-xs font-medium text-[var(--ink-2)]">Cargo manifest ({{ $shipment->items->count() }})</h4>
                    <div class="divide-y divide-[var(--line)] border-t border-[var(--line)] text-xs">
                        @foreach ($shipment->items as $item)
                            <div class="flex items-center justify-between py-2">
                                <div>
                                    <span>{{ $item->description }}</span>
                                    <span class="ml-2 font-mono text-[11px] text-[var(--ink-3)]">×{{ $item->quantity }}</span>
                                </div>
                                <span class="font-mono tabular-nums text-[var(--ink-2)]">{{ number_format($item->weight_kg) }} kg · {{ $item->volume_m3 }} m³</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="border-t border-[var(--line)] pt-3.5">
                    <div class="mb-2 flex items-center justify-between text-xs">
                        <span class="inline-flex items-center gap-1.5 text-[var(--ink-2)]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[var(--ink-3)]"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                            <span>Corridor weather</span>
                        </span>
                        <span class="text-[11px] text-[var(--ink-3)]">Open-Meteo ({{ ($weather['origin']['source'] ?? 'cached') === 'live' ? 'live' : 'cached' }} · informational)</span>
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-[var(--line)] rounded-md border border-[var(--line)] bg-[var(--bg-surface)] text-xs">
                        <div class="p-2.5">
                            <div class="text-[11px] text-[var(--ink-3)]">{{ $shipment->origin->code }}</div>
                            <div class="mt-0.5 font-mono text-xs font-medium tabular-nums">{{ $weather['origin']['temperature_c'] }}°C</div>
                            <div class="truncate text-[10px] text-[var(--ink-3)]">{{ $weather['origin']['label'] }}</div>
                        </div>
                        <div class="p-2.5">
                            <div class="text-[11px] text-[var(--ink-3)]">Current</div>
                            <div class="mt-0.5 font-mono text-xs font-medium tabular-nums">{{ $weather['current']['temperature_c'] }}°C</div>
                            <div class="truncate text-[10px] text-[var(--ink-3)]">{{ $weather['current']['label'] }}</div>
                        </div>
                        <div class="p-2.5">
                            <div class="text-[11px] text-[var(--ink-3)]">{{ $shipment->destination->code }}</div>
                            <div class="mt-0.5 font-mono text-xs font-medium tabular-nums">{{ $weather['destination']['temperature_c'] }}°C</div>
                            <div class="truncate text-[10px] text-[var(--ink-3)]">{{ $weather['destination']['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Right Column: Full-Height Cartographic Map -->
        <section class="isolate relative z-0 flex flex-col bg-[#aad3df] lg:min-h-[420px]">
            <div class="relative h-[360px] w-full sm:h-[420px] lg:h-auto lg:flex-1">
                <div id="shipment-detail-map" class="absolute inset-0 h-full w-full"></div>
                <div id="shipment-detail-globe" data-testid="globe-3d-container" class="absolute inset-0 z-10 hidden h-full w-full select-none overflow-hidden"></div>
                @include('partials.map-projection-toggle', ['prefix' => 'detail'])
                <div class="pointer-events-none absolute right-2.5 top-2.5 z-[400] flex max-w-[calc(100%-1.25rem)] flex-wrap items-center gap-2.5 rounded-md border border-[var(--line-strong)] bg-[var(--bg-overlay)] px-2.5 py-1.5 text-[11px] font-medium shadow-md sm:right-4 sm:top-4 sm:gap-4 sm:px-3 sm:text-xs">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-[2.5px] w-3.5 bg-[#080B0C] dark:bg-[#C6F432] sm:w-4"></span>
                        Completed leg
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-0 w-3.5 border-t-2 border-dashed border-[var(--ink-2)] sm:w-4"></span>
                        Remaining leg
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block size-2 rounded-full border border-[#080B0C] bg-[#C6F432]"></span>
                        Transit stop ({{ $card['transit_stop_count'] }})
                    </span>
                </div>
            </div>
            @if ($card['map_current'])
                <div class="border-t border-[var(--line)] bg-[var(--bg-overlay)] px-3.5 py-2.5 text-xs md:pointer-events-none md:absolute md:bottom-4 md:left-4 md:z-[400] md:max-w-md md:rounded-md md:border md:border-[var(--line-strong)] md:shadow-lg">
                    <div class="flex flex-wrap items-center gap-1.5 font-mono text-[10px] font-bold text-[var(--ink-2)]">
                        <span class="rounded border border-[var(--line)] bg-[var(--bg-surface)] px-1.5 py-0.5 text-[var(--ink-1)]">1. {{ $shipment->origin->code }}</span>
                        @foreach ($card['transit_stops'] as $i => $stop)
                            <span>━━▶</span>
                            <span class="rounded border border-[#080B0C] bg-[#C6F432] px-1.5 py-0.5 text-[#080B0C]">{{ $i + 2 }}. {{ $stop['name'] }}</span>
                        @endforeach
                        <span>{{ $shipment->current_status === 'DELIVERED' ? '━━▶' : '╌╌▶' }}</span>
                        <span class="rounded px-1.5 py-0.5 {{ $shipment->current_status === 'DELIVERED' ? 'border border-[#080B0C] bg-[#C6F432] text-[#080B0C]' : 'border border-[var(--line)] bg-[var(--bg-surface)] text-[var(--ink-1)]' }}">{{ $card['transit_stop_count'] + 2 }}. {{ $shipment->destination->code }}</span>
                    </div>
                    <div class="mt-1 font-semibold">Latest telemetry: {{ $card['map_current']['location_name'] }}</div>
                    <div class="mt-0.5 font-mono text-[11px] font-medium tabular-nums text-[var(--ink-2)]">{{ number_format($card['map_current']['latitude'], 4) }}°, {{ number_format($card['map_current']['longitude'], 4) }}°{{ ! empty($card['map_current']['time']) ? ' · ' . $card['map_current']['time'] : '' }}</div>
                </div>
            @endif
        </section>
    </div>

    <!-- Printable Bill of Lading Modal -->
    <div id="bill-modal" class="fixed inset-0 z-[1500] hidden items-center justify-center bg-black/60 p-4">
        <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg border-2 border-[#080B0C] bg-white p-6 text-[#080B0C] shadow-2xl">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b-2 border-[#080B0C] pb-4">
                <div>
                    <div class="inline-flex items-center gap-2 rounded bg-[#C6F432] px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider text-[#080B0C]">Official Electronic Bill of Lading (e-B/L)</div>
                    <h2 class="mt-1.5 font-mono text-xl font-bold tracking-tight">WAYBILL #{{ $shipment->shipment_number }}</h2>
                    <p class="text-xs font-medium text-[#2A3338]">CargoTrace Supply Chain Corridor Telemetry · Issued {{ $shipment->created_at?->format('M j, Y · H:i') }}</p>
                </div>
                <div class="flex items-center gap-2 print:hidden">
                    <button type="button" onclick="window.print()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[#080B0C] bg-[#C6F432] px-3 text-xs font-bold text-[#080B0C] hover:bg-[#b5e422]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print / Save PDF
                    </button>
                    <button type="button" onclick="toggleBillModal(false)" class="inline-flex h-8 items-center gap-1 rounded-md border border-[#CBD5E1] bg-white px-2.5 text-xs font-semibold text-[#080B0C] hover:bg-[#F3F5F6]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        Close
                    </button>
                </div>
            </div>
            <div class="mt-4 grid gap-4 border-b border-[#D0D7DE] pb-4 text-xs sm:grid-cols-3">
                <div>
                    <div class="font-mono text-[10px] font-bold uppercase text-[#526068]">Consignee / Customer</div>
                    <div class="mt-1 font-bold">{{ $shipment->customer->name ?? 'Assigned Consignee' }}</div>
                    <div class="text-[11px] text-[#2A3338]">{{ $shipment->customer->email ?? '—' }}</div>
                </div>
                <div>
                    <div class="font-mono text-[10px] font-bold uppercase text-[#526068]">Assigned Carrier &amp; Mode</div>
                    <div class="mt-1 font-bold">{{ $shipment->carrier->name }}</div>
                    <div class="font-mono text-[11px] text-[#2A3338]">Code: {{ $shipment->carrier->code }} · Mode: {{ $shipment->carrier->service_type }}</div>
                </div>
                <div>
                    <div class="font-mono text-[10px] font-bold uppercase text-[#526068]">Lifecycle &amp; Cargo Class</div>
                    <div class="mt-1 font-bold">{{ strtoupper($statusLabel[$shipment->current_status] ?? $shipment->current_status) }}</div>
                    <div class="text-[11px] text-[#2A3338]">Commodity: {{ $shipment->cargo_type }}</div>
                </div>
            </div>
            <div class="mt-4 border-b border-[#D0D7DE] pb-4 text-xs">
                <div class="font-mono text-[10px] font-bold uppercase text-[#526068]">Corridor Routing &amp; Schedule</div>
                <div class="mt-2 grid gap-3 sm:grid-cols-3">
                    <div class="rounded border border-[#D0D7DE] bg-[#F8FAFC] p-3">
                        <div class="font-mono text-[10px] font-bold text-[#526068]">1. PORT OF LOADING (ORIGIN)</div>
                        <div class="mt-1 font-bold">{{ $shipment->origin->code }} — {{ $shipment->origin->name }}</div>
                        <div class="text-[11px] text-[#2A3338]">{{ $shipment->origin->city }}, {{ $shipment->origin->country }}</div>
                        <div class="mt-1.5 font-mono text-[11px]">Dep: {{ $shipment->expected_departure_at?->format('M j, Y · H:i') }}</div>
                    </div>
                    <div class="rounded border border-[#D0D7DE] bg-[#F8FAFC] p-3">
                        <div class="font-mono text-[10px] font-bold text-[#526068]">2. TRANSIT CHECKPOINTS ({{ $checkpointEvents->count() }})</div>
                        @if ($checkpointEvents->isEmpty())
                            <div class="mt-1 text-[11px] text-[#526068]">Direct corridor routing (no intermediate stops recorded)</div>
                        @else
                            <div class="mt-1 space-y-1">
                                @foreach ($checkpointEvents as $cp)
                                    <div class="text-[11px] font-semibold">● {{ $cp->location_name }} <span class="font-mono text-[10px] font-normal text-[#526068]">({{ $cp->occurred_at->format('M j · H:i') }})</span></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="rounded border border-[#D0D7DE] bg-[#F8FAFC] p-3">
                        <div class="font-mono text-[10px] font-bold text-[#526068]">3. PORT OF DISCHARGE (DESTINATION)</div>
                        <div class="mt-1 font-bold">{{ $shipment->destination->code }} — {{ $shipment->destination->name }}</div>
                        <div class="text-[11px] text-[#2A3338]">{{ $shipment->destination->city }}, {{ $shipment->destination->country }}</div>
                        <div class="mt-1.5 font-mono text-[11px]">ETA: {{ ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->format('M j, Y · H:i') }}</div>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <div class="mb-2 flex items-center justify-between">
                    <span class="font-mono text-[10px] font-bold uppercase text-[#526068]">Itemized Cargo Manifest ({{ $shipment->items->count() }} line items)</span>
                    <span class="font-mono text-xs font-bold">Declared Total: {{ number_format($shipment->weight_kg) }} kg · {{ $shipment->volume_m3 }} m³</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-[#D0D7DE] text-left text-xs">
                        <thead>
                            <tr class="border-b border-[#D0D7DE] bg-[#F3F5F6] font-mono text-[11px] text-[#2A3338]">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Description of Goods</th>
                                <th class="px-3 py-2 text-right">Qty</th>
                                <th class="px-3 py-2 text-right">Gross Weight (kg)</th>
                                <th class="px-3 py-2 text-right">Volume (m³)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D0D7DE]">
                            @foreach ($shipment->items as $idx => $item)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-[#526068]">{{ $idx + 1 }}</td>
                                    <td class="px-3 py-2 font-semibold">{{ $item->description }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $item->quantity }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format($item->weight_kg) }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $item->volume_m3 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-5 flex items-center justify-between border-t border-[#D0D7DE] pt-3 text-[11px] text-[#526068]">
                <span>Verified by CargoTrace Corridor Control · Delay Rule: {{ $scenario->group_code ?? 'DEFAULT' }}</span>
                <span class="font-mono font-bold text-[#080B0C]">AUTHENTICATED WAYBILL #{{ $shipment->shipment_number }}</span>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/globe3d.js') }}"></script>
@endpush

@push('scripts')
<script>
    function toggleDetailForm(which) {
        const cp = document.getElementById('form-checkpoint');
        const ex = document.getElementById('form-exception');
        const btnCp = document.getElementById('btn-checkpoint');
        const btnEx = document.getElementById('btn-exception');
        if (which === 'checkpoint') {
            const show = cp.classList.contains('hidden');
            cp.classList.toggle('hidden', !show);
            ex.classList.add('hidden');
            btnCp.classList.toggle('border-[#080B0C]', show);
            btnCp.classList.toggle('bg-[#C6F432]', show);
            btnCp.classList.toggle('font-semibold', show);
            btnCp.classList.toggle('text-[#080B0C]', show);
            btnEx.classList.remove('border-[#080B0C]', 'bg-[#C6F432]', 'font-semibold', 'text-[#080B0C]');
        } else {
            const show = ex.classList.contains('hidden');
            ex.classList.toggle('hidden', !show);
            cp.classList.add('hidden');
            btnEx.classList.toggle('border-[#080B0C]', show);
            btnEx.classList.toggle('bg-[#C6F432]', show);
            btnEx.classList.toggle('font-semibold', show);
            btnEx.classList.toggle('text-[#080B0C]', show);
            btnCp.classList.remove('border-[#080B0C]', 'bg-[#C6F432]', 'font-semibold', 'text-[#080B0C]');
        }
    }

    function toggleArchiveConfirm(show) {
        document.getElementById('archive-idle').classList.toggle('hidden', show);
        const confirm = document.getElementById('archive-confirm');
        confirm.classList.toggle('hidden', !show);
        confirm.classList.toggle('flex', show);
    }

    function toggleBillModal(show) {
        const modal = document.getElementById('bill-modal');
        modal.classList.toggle('hidden', !show);
        modal.classList.toggle('flex', show);
    }
    document.getElementById('bill-modal').addEventListener('click', function (e) {
        if (e.target === this) toggleBillModal(false);
    });

    const ctHubs = @json(collect($hubSuggestions)->keyBy('name'));
    const ctLocationInput = document.getElementById('location_name');
    if (ctLocationInput) {
        ctLocationInput.addEventListener('change', function () {
            const hub = ctHubs[this.value];
            const preview = document.getElementById('checkpoint-preview-name');
            if (hub) {
                document.getElementById('ev_lat').value = hub.latitude.toFixed(4);
                document.getElementById('ev_lng').value = hub.longitude.toFixed(4);
                setGeocodeStatus('ok', 'Selected preset coordinates (' + hub.latitude.toFixed(4) + ', ' + hub.longitude.toFixed(4) + ')');
                if (preview) preview.textContent = this.value;
            } else if (preview) {
                preview.textContent = this.value.trim() || 'Select transit stop (e.g. Port of Singapore)';
            }
        });
        ctLocationInput.addEventListener('input', function () {
            const preview = document.getElementById('checkpoint-preview-name');
            if (preview) preview.textContent = this.value.trim() || 'Select transit stop (e.g. Port of Singapore)';
        });
    }

    function setGeocodeStatus(kind, message) {
        const el = document.getElementById('geocode-status');
        if (!el) return;
        el.classList.remove('hidden');
        el.textContent = message;
        el.className = 'mt-1 text-[11px] ' + (kind === 'ok' ? 'text-emerald-600 dark:text-emerald-400' : (kind === 'error' ? 'text-rose-500' : 'text-[var(--ink-2)]'));
    }

    async function geocodeWaypoint() {
        const nameInput = document.getElementById('location_name');
        const q = nameInput.value.trim();
        if (!q) {
            setGeocodeStatus('empty', 'Enter a port or city name before geocoding.');
            return;
        }
        setGeocodeStatus('loading', 'Querying OpenStreetMap Nominatim...');
        const btn = document.getElementById('btn-geocode');
        if (btn) btn.disabled = true;
        try {
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), 5000);
            const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, signal: ctrl.signal });
            clearTimeout(timer);
            if (!res.ok) {
                setGeocodeStatus('error', 'Geocoding service returned HTTP ' + res.status + '. Enter coordinates manually.');
                return;
            }
            const hits = await res.json();
            if (Array.isArray(hits) && hits.length > 0) {
                const foundLat = Number(hits[0].lat).toFixed(4);
                const foundLng = Number(hits[0].lon).toFixed(4);
                document.getElementById('ev_lat').value = foundLat;
                document.getElementById('ev_lng').value = foundLng;
                setGeocodeStatus('ok', 'Found coordinates (' + foundLat + ', ' + foundLng + ') — ' + hits[0].display_name);
            } else {
                setGeocodeStatus('empty', 'No matching location found in Nominatim. Pick a hub preset or enter coordinates manually.');
            }
        } catch (err) {
            setGeocodeStatus('error', err && err.name === 'AbortError' ? 'Nominatim request timed out after 5 seconds. Pick a hub preset or enter coordinates manually.' : 'Could not reach Nominatim geocoding service. Enter coordinates manually.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

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
        const isDelivered = {{ $shipment->current_status === 'DELIVERED' ? 'true' : 'false' }};
        const isDelayed = {{ ($delay['delayed'] ?? false) ? 'true' : 'false' }};
        const destColor = isDelivered ? '#047857' : (isDelayed ? '#D97706' : '#080B0C');
        L.circleMarker([o.latitude, o.longitude], { radius: 6, color: '#080B0C', weight: 2.2, fillColor: '#FFFFFF', fillOpacity: 1 }).addTo(map)
            .bindPopup('<b>Origin: ' + o.location_name + ' (' + o.code + ')</b><br>' + o.city)
            .bindTooltip('Origin: ' + o.code, { permanent: true, direction: 'left', offset: [-8, 0], className: 'ct-tooltip' });
        L.circleMarker([d.latitude, d.longitude], { radius: 6.2, color: '#080B0C', weight: 2.2, fillColor: destColor, fillOpacity: 1 }).addTo(map)
            .bindPopup('<b>Destination: ' + d.location_name + ' (' + d.code + ')</b><br>' + d.city)
            .bindTooltip('Dest: ' + d.code, { permanent: true, direction: 'bottom', offset: [0, 8], className: 'ct-tooltip' });
        const latlngs = [[o.latitude, o.longitude]];
        corridor.intermediate_stops.forEach(function (stop, idx) {
            L.circleMarker([stop.latitude, stop.longitude], { radius: 7, color: '#080B0C', fillColor: '#C6F432', fillOpacity: 1 }).addTo(map)
                .bindPopup('<b>Transit stop</b><br>' + stop.location_name + '<br>' + stop.event_type + ' · ' + stop.occurred_at)
                .bindTooltip('Transit #' + (idx + 1) + ': ' + String(stop.location_name).split('·')[0].trim(), { permanent: true, direction: 'top', offset: [0, -6], className: 'ct-tooltip' });
            latlngs.push([stop.latitude, stop.longitude]);
        });
        latlngs.push([d.latitude, d.longitude]);
        // Completed leg solid, remaining leg dashed (same as the index map).
        const routeBounds = L.latLngBounds(latlngs);
        if (isDelivered) {
            L.polyline(latlngs, { color: '#080B0C', weight: 2.5 }).addTo(map);
        } else if (latlngs.length <= 2) {
            // Not moved yet: the whole corridor is still remaining.
            L.polyline(latlngs, { color: '#3D4850', weight: 2.1, dashArray: '5 6' }).addTo(map);
        } else {
            L.polyline(latlngs.slice(0, latlngs.length - 1), { color: '#080B0C', weight: 2.5 }).addTo(map);
            L.polyline(latlngs.slice(latlngs.length - 2), { color: '#3D4850', weight: 2.1, dashArray: '5 6' }).addTo(map);
        }
        map.invalidateSize();
        map.fitBounds(routeBounds, { padding: [30, 30] });
        window.addEventListener('load', function () { map.invalidateSize(); });

        const globeEntry = @json($globeEntry);
        const detailGlobeEl = document.getElementById('shipment-detail-globe');
        if (detailGlobeEl && window.CargoTraceGlobe) {
            const detailGlobe = window.CargoTraceGlobe.mount(detailGlobeEl, [globeEntry], {{ $shipment->id }}, function (id) {
                window.location.href = '/shipments?focus=' + id;
            });
            window.CargoTraceGlobe.initProjection({
                btn2d: document.getElementById('detail-proj-2d'),
                btn3d: document.getElementById('detail-proj-3d'),
                mapEl: mapEl,
                globeEl: detailGlobeEl,
                onShow2D: function () { map.invalidateSize(); },
                onShow3D: function () { detailGlobe.render(); }
            });
        }
    });
</script>
@endpush
@endsection
