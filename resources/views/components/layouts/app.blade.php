@props(['title' => 'Dashboard'])

@php
$user = auth()->user();
$unreadNotifications = $user->notifications()->whereNull('read_at')->count();
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

            <a href="{{ route('notifications.index') }}" title="Notifications"
               @if(request()->routeIs('notifications.*')) aria-current="page" @endif
               class="flex size-9 items-center justify-center rounded-lg transition-colors {{ request()->routeIs('notifications.*') ? 'bg-raised text-accent' : 'text-ink-2 hover:bg-raised hover:text-ink' }}">
                <x-lucide-bell class="size-5" stroke-width="1.5"/>
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

                <a href="{{ route('audit.index') }}" title="Audit log"
                   @if(request()->routeIs('audit.*')) aria-current="page" @endif
                   class="flex size-9 items-center justify-center rounded-lg transition-colors {{ request()->routeIs('audit.*') ? 'bg-raised text-accent' : 'text-ink-2 hover:bg-raised hover:text-ink' }}">
                    <x-lucide-scroll-text class="size-5" stroke-width="1.5"/>
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
                    <details class="relative">
                        <summary class="flex size-8 cursor-pointer list-none items-center justify-center rounded-lg text-ink-2 transition-colors hover:bg-raised hover:text-ink">
                            <x-lucide-bell class="size-4.5" stroke-width="1.5"/>
                            @if ($unreadNotifications > 0)
                                <span class="absolute -mt-3 ml-3 flex size-4 items-center justify-center rounded-full bg-danger font-mono text-[9px] font-medium text-base">
                                    {{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}
                                </span>
                            @endif
                        </summary>
                        <div class="absolute right-0 z-20 mt-2 w-80 rounded-xl border border-line bg-overlay p-2 shadow-[0_8px_24px_rgba(0,0,0,0.5)]">
                            @php $latest = $user->notifications()->with('shipment')->latest()->limit(5)->get(); @endphp
                            @forelse ($latest as $item)
                                <a href="{{ $item->shipment ? route('shipments.show', $item->shipment) : route('notifications.index') }}"
                                   class="block rounded-lg px-3 py-2 transition-colors hover:bg-raised">
                                    <span class="flex items-center gap-2">
                                        @if ($item->isUnread())
                                            <span class="size-1.5 shrink-0 rounded-full bg-accent"></span>
                                        @else
                                            <span class="size-1.5 shrink-0 rounded-full bg-transparent"></span>
                                        @endif
                                        <span class="truncate text-xs {{ $item->isUnread() ? 'font-medium text-ink' : 'text-ink-2' }}">{{ $item->title }}</span>
                                    </span>
                                    <span class="mt-0.5 block pl-3.5 font-mono text-[10px] text-ink-3">{{ $item->created_at->format('M j · H:i') }}</span>
                                </a>
                            @empty
                                <p class="px-3 py-4 text-center text-xs text-ink-3">No notifications yet</p>
                            @endforelse
                            <a href="{{ route('notifications.index') }}" class="mt-1 block rounded-lg px-3 py-2 text-center text-xs text-accent transition-colors hover:bg-raised">
                                View all notifications
                            </a>
                        </div>
                    </details>
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
