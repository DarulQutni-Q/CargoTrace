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
                @if ($filters['status'])<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                @if ($filters['delayed'])<input type="hidden" name="delayed" value="1">@endif
                <div class="relative">
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-3" stroke-width="1.5"/>
                    <x-input name="q" value="{{ $filters['q'] }}" placeholder="Search order ID, port, carrier..." class="pl-9"
                             aria-label="Search shipments"/>
                </div>
            </form>

            <div class="flex gap-1.5 overflow-x-auto px-4 py-3">
                @php
                    $chips = [
                        ['label' => 'All shipments', 'params' => [], 'active' => ! $filters['status'] && ! $filters['delayed']],
                        ['label' => 'In transit', 'params' => ['status' => 'IN_TRANSIT'], 'active' => $filters['status'] === 'IN_TRANSIT'],
                        ['label' => 'Delayed', 'params' => ['delayed' => 1], 'active' => $filters['delayed']],
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
                @forelse ($shipments as $shipment)
                    <x-shipment-card :shipment="$shipment"/>
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

        <section class="hidden items-center justify-center lg:flex">
            <div class="max-w-xs text-center">
                <x-lucide-map class="mx-auto size-8 text-ink-3" stroke-width="1.25"/>
                <p class="mt-3 text-sm text-ink-2">The live world map lands with the map milestone</p>
                <p class="mt-1 text-xs text-ink-3">Routes, positions, and popups for every shipment</p>
            </div>
        </section>
    </div>
</x-layouts.app>
