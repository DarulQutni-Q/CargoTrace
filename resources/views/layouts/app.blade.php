<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'CargoTrace') · Global Shipment Visibility &amp; Tracking</title>
    <meta name="description" content="Real-time supply chain and shipment visibility web application with multi-leg GPS tracking, deterministic ETA delay detection, exception management, and corridor analytics." />

    <!-- Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Leaflet 1.9.4 & Chart.js CDN for Standalone Blade Rendering -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* Strict 3-Color Palette: #080B0C (Obsidian) · #FFFFFF (Pure White - Default) · #C6F432 (Acid Volt) */
        :root {
            --bg-base: #FFFFFF;
            --bg-surface: #F3F5F6;
            --bg-raised: #E5E9EC;
            --ink-1: #080B0C;
            --ink-2: #2A3338;
            --ink-3: #526068;
            --brand: #C6F432;
            --line: rgba(8, 11, 12, 0.14);
        }
        html.dark {
            --bg-base: #080B0C;
            --bg-surface: #101518;
            --bg-raised: #171E22;
            --ink-1: #FFFFFF;
            --ink-2: #D0D8DC;
            --ink-3: #8F9EA5;
            --brand: #C6F432;
            --line: rgba(255, 255, 255, 0.12);
        }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background-color: var(--bg-base);
            color: var(--ink-1);
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="min-h-screen antialiased selection:bg-[#C6F432] selection:text-[#080B0C]">
    <!-- Interactive App Root -->
    <div id="root">
        <div class="min-h-screen bg-[var(--bg-base)] text-[var(--ink-1)]">
            <!-- Mobile Slide-Over Backdrop -->
            <div id="mobile-backdrop" onclick="toggleMobileSidebar(false)" class="fixed inset-0 z-[1050] hidden bg-black/50 backdrop-blur-xs lg:hidden"></div>

            <!-- Responsive Left Sidebar (240px fixed on Desktop, Slide-over Drawer on Mobile) -->
            <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-[1100] flex w-60 -translate-x-full flex-col border-r border-[var(--line)] bg-[var(--bg-surface)] transition-transform duration-200 ease-out lg:z-30 lg:translate-x-0">
                <div class="flex h-14 items-center justify-between border-b border-[var(--line)] px-4">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-left">
                        <div class="flex size-7 items-center justify-center rounded-md bg-[#080B0C] text-[#C6F432]">
                            <span class="font-mono text-xs font-bold">CT</span>
                        </div>
                        <span class="text-sm font-semibold tracking-tight">CargoTrace</span>
                    </a>
                    <button type="button" onclick="toggleMobileSidebar(false)" class="inline-flex size-7 items-center justify-center rounded-md text-xs lg:hidden" aria-label="Close navigation menu">✕</button>
                </div>

                <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4 text-[13px]">
                    <div class="space-y-1">
                        <p class="px-2.5 pb-1 text-[11px] font-medium opacity-70">Operations</p>
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium {{ request()->routeIs('dashboard') ? 'bg-[#080B0C] text-white' : 'hover:bg-[var(--bg-raised)]' }}">Dashboard</a>
                        <a href="{{ route('shipments.index') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium {{ request()->routeIs('shipments.*') ? 'bg-[#080B0C] text-white' : 'hover:bg-[var(--bg-raised)]' }}">Shipments</a>
                        <a href="{{ route('notifications.index') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium {{ request()->routeIs('notifications.*') ? 'bg-[#080B0C] text-white' : 'hover:bg-[var(--bg-raised)]' }}">Notifications
                            @php $unreadCount = auth()->check() ? auth()->user()->notifications()->whereNull('read_at')->count() : 0; @endphp
                            @if ($unreadCount > 0)
                                <span class="ml-auto rounded bg-[#C6F432] px-1.5 py-0.5 font-mono text-[10px] font-bold text-[#080B0C]">{{ $unreadCount }}</span>
                            @endif
                        </a>
                    </div>

                    <div class="space-y-1">
                        <p class="px-2.5 pb-1 text-[11px] font-medium opacity-70">Administration</p>
                        <a href="{{ route('carriers.index') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium {{ request()->routeIs('carriers.*') ? 'bg-[#080B0C] text-white' : 'hover:bg-[var(--bg-raised)]' }}">Carriers</a>
                        <a href="{{ route('locations.index') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium {{ request()->routeIs('locations.*') ? 'bg-[#080B0C] text-white' : 'hover:bg-[var(--bg-raised)]' }}">Port locations</a>
                        @if (auth()->check() && auth()->user()->isAdmin())
                            <a href="{{ route('scenario.index') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium {{ request()->routeIs('scenario.*') || request()->routeIs('users.*') ? 'bg-[#080B0C] text-white' : 'hover:bg-[var(--bg-raised)]' }}">Users &amp; delay rules</a>
                            <a href="{{ route('audit.index') }}" class="flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium {{ request()->routeIs('audit.*') ? 'bg-[#080B0C] text-white' : 'hover:bg-[var(--bg-raised)]' }}">Audit log</a>
                        @endif
                    </div>
                </nav>

                <div class="border-t border-[var(--line)] p-3 text-xs">
                    @if (auth()->check())
                        <div class="mb-2.5 flex items-center justify-between gap-2 px-1.5">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ auth()->user()->name }}</div>
                                <div class="font-mono text-[10px] opacity-70">{{ auth()->user()->role }}</div>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-2 py-1 text-[11px] font-medium">Log out</button>
                            </form>
                        </div>
                    @endif
                    <div class="mb-2.5 px-1.5 py-1">
                        <div class="flex items-center justify-between">
                            <span class="opacity-70">Delay threshold</span>
                            <span class="font-mono font-semibold">≤6h / ≤24h</span>
                        </div>
                        <p class="mt-0.5 truncate opacity-70">Southeast Asia → Europe</p>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area (100% width on Mobile, pl-60 on Desktop) -->
            <div class="flex min-h-screen flex-col lg:pl-60">
                <header class="sticky top-0 z-[1000] flex h-14 items-center justify-between gap-2 border-b border-[var(--line)] bg-[var(--bg-base)] px-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <button type="button" onclick="toggleMobileSidebar(true)" class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border border-[var(--line)] bg-[var(--bg-surface)] lg:hidden" aria-label="Open navigation menu">☰</button>
                        <h1 class="truncate text-sm font-semibold sm:text-base">@yield('header_title', 'CargoTrace')</h1>
                        <span class="hidden truncate text-xs opacity-70 md:inline">@yield('header_subtitle', '')</span>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" onclick="toggleTheme()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs font-medium cursor-pointer">
                            <span id="theme-label">Dark mode</span>
                        </button>
                        <a href="{{ route('shipments.index') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">+ New shipment</a>
                    </div>
                </header>

                <main class="flex-1">
                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileSidebar(open) {
            const sidebar = document.getElementById('app-sidebar');
            const backdrop = document.getElementById('mobile-backdrop');
            if (!sidebar || !backdrop) return;
            if (open) {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                backdrop.classList.add('hidden');
            }
        }

        function toggleTheme() {
            const root = document.documentElement;
            const isDark = root.classList.toggle('dark');
            localStorage.setItem('cargotrace_theme_v1', isDark ? 'dark' : 'light');
            const label = document.getElementById('theme-label');
            if (label) label.textContent = isDark ? 'White mode' : 'Dark mode';
        }
    </script>
    @stack('scripts')
</body>
</html>
