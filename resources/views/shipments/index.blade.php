<x-layouts.app title="Tracking">
    <div class="grid h-[calc(100vh-60px)] grid-cols-1 lg:grid-cols-[380px_1fr]">
        <section class="flex min-h-0 flex-col border-r border-line">
            <div class="flex items-center justify-between px-4 pb-2 pt-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-medium">Tracking list</h2>
                    <span class="rounded-[4px] bg-raised px-1.5 py-0.5 font-mono text-[11px] text-ink-2">
                        {{ $shipments->total() }} shipments
                    </span>
                </div>
                <x-lucide-sliders-horizontal class="size-4 text-ink-3" stroke-width="1.5"/>
            </div>

            <form method="GET" action="{{ route('shipments.index') }}" class="px-4">
                @foreach (['status' => $filters['status'], 'delayed' => $filters['delayed'] ? 1 : null, 'open_exceptions' => $filters['open_exceptions'] ? 1 : null] as $key => $value)
                    @if ($value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <div class="relative">
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" stroke-width="1.5"/>
                    <x-input name="q" value="{{ $filters['q'] }}" placeholder="Search order ID, port, carrier..." class="pl-9"
                             aria-label="Search shipments"/>
                </div>
            </form>

            <div class="flex gap-1.5 overflow-x-auto px-4 py-3">
                @php
                    $chips = [
                        ['label' => 'All shipments', 'params' => [], 'active' => ! $filters['status'] && ! $filters['delayed'] && ! $filters['open_exceptions']],
                        ['label' => 'In transit', 'params' => ['status' => 'IN_TRANSIT'], 'active' => $filters['status'] === 'IN_TRANSIT'],
                        ['label' => 'Delayed', 'params' => ['delayed' => 1], 'active' => $filters['delayed']],
                        ['label' => 'Exceptions', 'params' => ['open_exceptions' => 1], 'active' => $filters['open_exceptions']],
                    ];
                @endphp
                @foreach ($chips as $chip)
                    <a href="{{ route('shipments.index', $chip['params']) }}"
                       class="shrink-0 rounded-full px-3 py-1.5 text-xs transition-colors {{ $chip['active'] ? 'bg-raised font-medium text-ink' : 'bg-surface text-ink-2 hover:text-ink' }}">
                        {{ $chip['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 pb-4">
                @php $baseQuery = collect(request()->query())->except('focus')->all(); @endphp
                @forelse ($shipments as $shipment)
                    <x-shipment-card
                        :shipment="$shipment"
                        :selected="$focus?->id === $shipment->id"
                        :href="route('shipments.index', [...$baseQuery, 'focus' => $shipment->id])"/>
                @empty
                    <div class="mt-16 px-4 text-center">
                        <p class="text-sm text-ink-2">No shipments match this view</p>
                        <a href="{{ route('shipments.index') }}" class="mt-2 inline-block text-xs text-accent hover:underline">Clear filters</a>
                    </div>
                @endforelse

                @if ($shipments->hasPages())
                    <nav class="flex items-center justify-between pt-2 text-xs text-ink-3">
                        @if ($shipments->onFirstPage())
                            <span class="opacity-50">Prev</span>
                        @else
                            <a href="{{ $shipments->previousPageUrl() }}" class="hover:text-ink">Prev</a>
                        @endif
                        <span class="font-mono">{{ $shipments->currentPage() }} / {{ $shipments->lastPage() }}</span>
                        @if ($shipments->hasMorePages())
                            <a href="{{ $shipments->nextPageUrl() }}" class="hover:text-ink">Next</a>
                        @else
                            <span class="opacity-50">Next</span>
                        @endif
                    </nav>
                @endif
            </div>
        </section>

        <section class="relative hidden lg:block">
            <div id="world-map" class="h-full w-full" data-shipments='@json($mapData)' data-focus="{{ $focus?->id ?? 0 }}"></div>
            @vite('resources/js/map.js')

            @if ($focus)
                <div class="absolute inset-x-4 bottom-4 z-[500] rounded-xl border border-line bg-surface/90 p-5 shadow-[0_8px_24px_rgba(0,0,0,0.5)] backdrop-blur-md">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-sm font-medium text-ink">{{ $focus->shipment_number }}</span>
                            <x-badge :tone="$focus->current_status->tone()">{{ $focus->current_status->label() }}</x-badge>
                            @if ($focus->estimated_arrival_at !== null && $focus->estimated_arrival_at->gt($focus->expected_arrival_at))
                                <x-badge :tone="$focus->delayTone()">Delayed</x-badge>
                            @endif
                        </div>
                        <a href="{{ route('shipments.show', $focus) }}"
                           class="inline-flex items-center gap-1.5 text-xs text-accent transition-colors hover:text-info">
                            Open full detail
                            <x-lucide-maximize-2 class="size-3.5" stroke-width="1.5"/>
                        </a>
                    </div>

                    <div class="mt-4 grid items-start gap-5 sm:grid-cols-[1.2fr_1fr]">
                        <x-progress-route :shipment="$focus"/>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between gap-3">
                                <span class="text-ink-3">Expected arrival</span>
                                <span class="font-mono text-ink-2">{{ $focus->expected_arrival_at->format('M j · H:i') }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-ink-3">Estimated</span>
                                <span class="font-mono text-ink-2">{{ $focus->estimated_arrival_at?->format('M j · H:i') ?? '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-ink-3">Carrier</span>
                                <span class="text-ink-2">{{ $focus->carrier->name }} <span class="font-mono">{{ $focus->carrier->code }}</span></span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-ink-3">Customer</span>
                                <span class="text-ink-2">{{ $focus->customer->name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
