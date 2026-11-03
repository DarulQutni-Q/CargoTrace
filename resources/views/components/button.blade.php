@props(['type' => 'submit', 'variant' => 'primary'])

@php
$variants = [
    'primary' => 'bg-accent-strong text-white hover:bg-accent',
    'secondary' => 'border border-line bg-raised text-ink hover:border-line-strong',
    'ghost' => 'text-ink-2 hover:bg-raised hover:text-ink',
];
@endphp

<button type="{{ $type }}" {{ $attributes->merge([
    'class' => 'inline-flex h-9 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition-colors '.
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/60 disabled:pointer-events-none disabled:opacity-50 '.
        $variants[$variant],
]) }}>
    {{ $slot }}
</button>
