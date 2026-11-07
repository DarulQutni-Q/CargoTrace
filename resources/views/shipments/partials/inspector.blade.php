{{-- Active shipment inspector card (split view). Rendered on initial page
     load from the index view and on focus change from the focus endpoint,
     so both paths share this exact markup. Expects $focused (shipmentCard
     array). The tone closures come from the parent view when included, and
     are defined here when the partial is rendered standalone. --}}
@php
    $badgeTone = $badgeTone ?? function (string $tone): array {
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
    $statusTone = $statusTone ?? fn (string $st): string => match ($st) {
        'CREATED' => 'neutral', 'BOOKED' => 'amber', 'PICKED_UP' => 'teal',
        'IN_TRANSIT' => 'blue', 'DELIVERED' => 'green', default => 'neutral',
    };
    $delayTone = $delayTone ?? fn (string $state): string => match ($state) {
        'low' => 'amber', 'medium' => 'orange', 'high' => 'red',
        'on_time' => 'green', default => 'neutral',
    };
@endphp
            @if ($focused)
                @php
                    $fBarClass = $focused['current_status'] === 'DELIVERED' ? 'bg-emerald-600 dark:bg-emerald-400'
                        : ($focused['delayed'] ? 'bg-amber-500 dark:bg-amber-400' : 'bg-[#080B0C] dark:bg-[#C6F432]');
                    $fStatusTone = $badgeTone($statusTone($focused['current_status']));
                    $fDelayTone = $badgeTone($delayTone($focused['delay_state']));
                @endphp
                <div id="shipment-inspector" data-focused-id="{{ $focused['id'] }}" class="border-t border-[var(--line)] bg-[var(--bg-overlay)] p-4 md:absolute md:bottom-4 md:left-4 md:right-4 md:z-[400] md:rounded-lg md:border md:border-[var(--line-strong)] md:shadow-2xl xl:left-auto xl:w-[440px]">
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
