@props(['shipment', 'selected' => false])

@php
$cardClasses = $selected
    ? 'border-line-strong bg-raised'
    : 'border-line bg-surface hover:bg-raised/60';
@endphp

<a href="{{ route('shipments.show', $shipment) }}" {{ $attributes->merge([
    'class' => "block rounded-xl border p-4 transition-colors {$cardClasses}",
]) }}>
    <div class="flex items-center justify-between gap-2">
        <span class="font-mono text-sm font-medium text-ink">{{ $shipment->shipment_number }}</span>
        <x-badge :tone="$shipment->current_status->tone()">{{ $shipment->current_status->label() }}</x-badge>
    </div>

    <div class="mt-3 flex items-center gap-2 text-ink-3">
        <span class="font-mono text-xs">{{ $shipment->origin->code }}</span>
        <span class="h-px flex-1 border-t border-dashed border-line-strong"></span>
        <span class="font-mono text-xs text-ink-2">{{ $shipment->plannedDurationHours() }}H</span>
        <x-lucide-plane class="size-3.5" stroke-width="1.5"/>
        <span class="font-mono text-xs">{{ $shipment->destination->code }}</span>
    </div>

    <div class="mt-2 flex items-baseline justify-between gap-2 text-[13px]">
        <span class="truncate text-ink-2">{{ $shipment->origin->city }}, {{ $shipment->origin->country }}</span>
        <span class="shrink-0 truncate text-right text-ink-2">{{ $shipment->destination->city }}, {{ $shipment->destination->country }}</span>
    </div>

    <div class="mt-2.5 flex items-center justify-between gap-2 text-xs text-ink-3">
        <span class="flex min-w-0 items-center gap-1.5">
            <span class="size-1.5 shrink-0 rounded-full"
                  style="background: hsl({{ abs(crc32($shipment->carrier->code)) % 360 }} 62% 55%)"></span>
            <span class="truncate">{{ $shipment->carrier->name }}</span>
            @if ($shipment->carrier->service_type)
                <span class="font-mono">· {{ $shipment->carrier->service_type }}</span>
            @endif
        </span>
        <span class="shrink-0 font-mono">ETA {{ $shipment->expected_arrival_at->format('M j') }}</span>
    </div>
</a>
