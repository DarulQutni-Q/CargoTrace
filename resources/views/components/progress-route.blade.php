@props(['shipment'])

@php
$start = $shipment->expected_departure_at;
$end = $shipment->expected_arrival_at;
$totalMinutes = max(1, $start->diffInMinutes($end));
$now = now();

if ($now->lt($start)) {
    $percent = 0;
    $remainingHours = (int) ceil($start->floatDiffInHours($now));
    $label = 'Departs in '.intdiv($remainingHours, 24).'D '.($remainingHours % 24).'H';
} elseif ($now->gt($end)) {
    $percent = 100;
    $label = 'Planned arrival passed';
} else {
    $percent = (int) round(min(100, max(0, $start->diffInMinutes($now) / $totalMinutes * 100)));
    $remainingHours = (int) ceil($now->floatDiffInHours($end));
    $label = 'On the way — ETA in '.intdiv($remainingHours, 24).'D '.($remainingHours % 24).'H';
}
@endphp

<div>
    <div class="flex items-center justify-between text-xs">
        <span class="text-ink-2">Route</span>
        <span class="font-mono text-ink-3">{{ $label }}</span>
    </div>

    <div class="relative mt-2.5 h-1 rounded-full bg-raised">
        <div class="absolute inset-y-0 left-0 rounded-full bg-ink" style="width: {{ $percent }}%"></div>
        <span class="absolute top-1/2 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-live ring-4 ring-live/20"
              style="left: {{ $percent }}%"></span>
    </div>

    <div class="mt-2.5 flex items-baseline justify-between font-mono text-xs">
        <span class="text-ink">{{ $shipment->origin->code }}</span>
        <span class="text-ink">{{ $shipment->destination->code }}</span>
    </div>
    <div class="flex items-baseline justify-between text-[11px] text-ink-3">
        <span>{{ $shipment->origin->city }}</span>
        <span class="text-right">{{ $shipment->destination->city }}</span>
    </div>
</div>
