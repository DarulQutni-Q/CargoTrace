@props(['tone' => 'neutral'])

@php
$tones = [
    'neutral' => 'bg-ink-2/10 text-ink-2',
    'blue' => 'bg-info/10 text-info',
    'teal' => 'bg-teal/10 text-teal',
    'amber' => 'bg-warn/10 text-warn',
    'orange' => 'bg-orange/10 text-orange',
    'green' => 'bg-ok/10 text-ok',
    'red' => 'bg-danger/10 text-danger',
    'live' => 'bg-live/10 text-live',
    'outline-green' => 'border border-ok/40 text-ok',
];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center rounded-[4px] px-2 py-0.5 text-[10px] font-medium uppercase tracking-[0.04em] '.($tones[$tone] ?? $tones['neutral']),
]) }}>{{ $slot }}</span>
