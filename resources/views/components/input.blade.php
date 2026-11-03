@props(['type' => 'text'])

<input type="{{ $type }}" {{ $attributes->merge([
    'class' => 'w-full rounded-lg border border-line bg-raised px-3 py-2 text-sm text-ink transition-colors '.
        'placeholder:text-ink-3 focus:border-line-strong focus:outline-none focus:ring-2 focus:ring-accent/50',
]) }} />
