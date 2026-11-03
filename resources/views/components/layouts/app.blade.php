@props(['title' => 'Dashboard'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · CargoTrace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-10 flex w-14 flex-col items-center gap-1 border-r border-line bg-rail py-3">
            <a href="{{ route('dashboard') }}" title="CargoTrace"
               class="mb-2 flex size-9 items-center justify-center rounded-lg bg-accent-strong text-white">
                <x-lucide-container class="size-4" stroke-width="1.75"/>
            </a>

            <a href="{{ route('dashboard') }}" title="Dashboard" aria-current="page"
               class="flex size-9 items-center justify-center rounded-lg bg-raised text-accent">
                <x-lucide-layout-dashboard class="size-5" stroke-width="1.5"/>
            </a>

            <div class="mt-auto flex flex-col items-center gap-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out"
                            class="flex size-9 items-center justify-center rounded-lg text-ink-2 transition-colors hover:bg-raised hover:text-ink">
                        <x-lucide-log-out class="size-5" stroke-width="1.5"/>
                    </button>
                </form>
                <div title="{{ auth()->user()->name }}"
                     class="flex size-9 items-center justify-center rounded-full border border-line bg-raised text-xs font-medium text-ink-2">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </div>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col pl-14">
            <header class="flex h-[60px] items-center justify-between border-b border-line px-6">
                <h1 class="text-lg font-medium">{{ $title }}</h1>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-ink-2">{{ auth()->user()->role->label() }}</span>
                </div>
            </header>

            <main class="flex-1 px-6 py-6">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
