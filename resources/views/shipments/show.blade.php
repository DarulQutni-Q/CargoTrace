<x-layouts.app title="{{ $shipment->shipment_number }}">
    <div class="mx-auto max-w-4xl space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="font-mono text-xl font-semibold">{{ $shipment->shipment_number }}</h2>
                <x-badge :tone="$shipment->current_status->tone()">{{ $shipment->current_status->label() }}</x-badge>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $shipment)
                    <a href="{{ route('shipments.edit', $shipment) }}"
                       class="inline-flex h-8 items-center rounded-lg border border-line bg-raised px-3 text-sm text-ink transition-colors hover:border-line-strong">
                        Edit
                    </a>
                @endcan
                @can('delete', $shipment)
                    <form method="POST" action="{{ route('shipments.destroy', $shipment) }}"
                          onsubmit="return confirm('Archive this shipment? Tracking history is preserved.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex h-8 items-center rounded-lg border border-line bg-raised px-3 text-sm text-ink-2 transition-colors hover:border-danger/40 hover:text-danger">
                            Archive
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <section class="rounded-xl border border-line bg-surface p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-ink-2">Route</h3>
                <span class="font-mono text-xs text-ink-3">{{ $shipment->plannedDurationHours() }}H planned</span>
            </div>

            <div class="mt-4 grid items-center gap-4 sm:grid-cols-[1fr_auto_1fr]">
                <div>
                    <p class="font-mono text-sm text-ink">{{ $shipment->origin->code }}</p>
                    <p class="mt-1 text-sm text-ink-2">{{ $shipment->origin->city }}, {{ $shipment->origin->country }}</p>
                    <p class="mt-2 text-xs text-ink-3">Depart {{ $shipment->expected_departure_at->format('M j, Y · H:i') }}</p>
                </div>
                <div class="flex items-center gap-2 text-ink-3">
                    <span class="h-px w-10 border-t border-dashed border-line-strong"></span>
                    <x-lucide-plane class="size-4" stroke-width="1.5"/>
                    <span class="h-px w-10 border-t border-dashed border-line-strong"></span>
                </div>
                <div class="sm:text-right">
                    <p class="font-mono text-sm text-ink">{{ $shipment->destination->code }}</p>
                    <p class="mt-1 text-sm text-ink-2">{{ $shipment->destination->city }}, {{ $shipment->destination->country }}</p>
                    <p class="mt-2 text-xs text-ink-3">Arrive {{ $shipment->expected_arrival_at->format('M j, Y · H:i') }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-line bg-surface p-6">
                <h3 class="text-sm font-medium text-ink-2">Cargo &amp; parties</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Cargo type</dt>
                        <dd class="text-ink">{{ $shipment->cargo_type }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Weight / volume</dt>
                        <dd class="font-mono text-ink">{{ number_format((float) $shipment->weight_kg, 2) }} kg · {{ number_format((float) $shipment->volume_m3, 2) }} m³</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Carrier</dt>
                        <dd class="text-ink">{{ $shipment->carrier->name }} <span class="font-mono text-ink-3">{{ $shipment->carrier->code }}</span></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-ink-3">Customer</dt>
                        <dd class="text-ink">{{ $shipment->customer->name }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-line bg-surface p-6">
                <h3 class="text-sm font-medium text-ink-2">Items ({{ $shipment->items->count() }})</h3>
                <div class="mt-4 space-y-2">
                    @forelse ($shipment->items as $item)
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-ink">{{ $item->description }} <span class="font-mono text-ink-3">×{{ $item->quantity }}</span></span>
                            <span class="shrink-0 font-mono text-xs text-ink-3">{{ number_format((float) $item->weight_kg, 2) }} kg</span>
                        </div>
                    @empty
                        <p class="text-sm text-ink-3">No item lines recorded</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
