@props(['title' => 'Dashboard'])

@php
$user = auth()->user();
@endphp

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

            <a href="{{ route('dashboard') }}" title="Dashboard"
               @if(request()->routeIs('dashboard')) aria-current="page" @endif
               class="flex size-9 items-center justify-center rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-raised text-accent' : 'text-ink-2 hover:bg-raised hover:text-ink' }}">
                <x-lucide-layout-dashboard class="size-5" stroke-width="1.5"/>
            </a>

            <a href="{{ route('shipments.index') }}" title="Shipments"
               @if(request()->routeIs('shipments.*')) aria-current="page" @endif
               class="flex size-9 items-center justify-center rounded-lg transition-colors {{ request()->routeIs('shipments.*') ? 'bg-raised text-accent' : 'text-ink-2 hover:bg-raised hover:text-ink' }}">
                <x-lucide-package class="size-5" stroke-width="1.5"/>
            </a>

            @if ($user->isAdmin())
                <a href="{{ route('carriers.index') }}" title="Carriers"
                   @if(request()->routeIs('carriers.*')) aria-current="page" @endif
                   class="flex size-9 items-center justify-center rounded-lg transition-colors {{ request()->routeIs('carriers.*') ? 'bg-raised text-accent' : 'text-ink-2 hover:bg-raised hover:text-ink' }}">
                    <x-lucide-building-2 class="size-5" stroke-width="1.5"/>
                </a>

                <a href="{{ route('locations.index') }}" title="Locations"
                   @if(request()->routeIs('locations.*')) aria-current="page" @endif
                   class="flex size-9 items-center justify-center rounded-lg transition-colors {{ request()->routeIs('locations.*') ? 'bg-raised text-accent' : 'text-ink-2 hover:bg-raised hover:text-ink' }}">
                    <x-lucide-map-pin class="size-5" stroke-width="1.5"/>
                </a>
            @endif

            <div class="mt-auto flex flex-col items-center gap-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out"
                            class="flex size-9 items-center justify-center rounded-lg text-ink-2 transition-colors hover:bg-raised hover:text-ink">
                        <x-lucide-log-out class="size-5" stroke-width="1.5"/>
                    </button>
                </form>
                <div title="{{ $user->name }}"
                     class="flex size-9 items-center justify-center rounded-full border border-line bg-raised text-xs font-medium text-ink-2">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col pl-14">
            <header class="flex h-[60px] items-center justify-between border-b border-line px-6">
                <h1 class="text-lg font-medium">{{ $title }}</h1>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-ink-2">{{ $user->role->label() }}</span>
                    @can('create', \App\Models\Shipment::class)
                        <a href="{{ route('shipments.create') }}"
                           class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-accent-strong px-3 text-sm font-medium text-white transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/60">
                            <x-lucide-plus class="size-4" stroke-width="1.75"/>
                            New shipment
                        </a>
                    @endcan
                </div>
            </header>

            <main class="min-h-0 flex-1">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
