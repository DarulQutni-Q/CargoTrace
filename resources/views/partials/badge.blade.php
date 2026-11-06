{{-- Badge: port of the AI Studio reference Badge component (src/components/Badge.tsx).
     Usage: @include('partials.badge', ['tone' => 'green', 'label' => 'Active']) --}}
@php
    $badgeTones = [
        'neutral' => ['dot' => 'bg-[var(--ink-3)]', 'text' => 'text-[var(--ink-2)]'],
        'blue' => ['dot' => 'bg-[#080B0C] dark:bg-[#C6F432]', 'text' => 'text-[var(--ink-1)]'],
        'teal' => ['dot' => 'bg-teal-600 dark:bg-teal-400', 'text' => 'text-teal-800 dark:text-teal-300'],
        'amber' => ['dot' => 'bg-amber-600 dark:bg-amber-400', 'text' => 'text-amber-800 dark:text-amber-300'],
        'orange' => ['dot' => 'bg-orange-600 dark:bg-orange-400', 'text' => 'text-orange-800 dark:text-orange-300'],
        'green' => ['dot' => 'bg-emerald-600 dark:bg-emerald-400', 'text' => 'text-emerald-800 dark:text-emerald-300'],
        'red' => ['dot' => 'bg-rose-600 dark:bg-rose-400', 'text' => 'text-rose-800 dark:text-rose-300'],
    ];
    $badgeTone = $badgeTones[$tone ?? 'neutral'] ?? $badgeTones['neutral'];
@endphp
<span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded border border-[var(--line)] bg-[var(--tint)] px-2 py-0.5 text-xs font-semibold {{ $badgeTone['text'] }}"><span class="size-1.5 rounded-full {{ $badgeTone['dot'] }}"></span>{{ $label }}</span>
