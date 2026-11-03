<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $name ?? 'CargoTrace' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-[400px]">
            <div class="mb-8 flex items-center justify-center gap-2.5">
                <div class="flex size-8 items-center justify-center rounded-lg bg-accent-strong">
                    <x-lucide-container class="size-4 text-white" stroke-width="1.75"/>
                </div>
                <span class="text-lg font-semibold tracking-tight">CargoTrace</span>
            </div>

            <div class="rounded-xl border border-line bg-surface p-6 shadow-[0_8px_24px_rgba(0,0,0,0.5)]">
                {{ $slot }}
            </div>

            <p class="mt-4 text-center text-xs leading-relaxed text-ink-3">
                Global shipment visibility for the operations team and their customers
            </p>
        </div>
    </div>
</body>
</html>
