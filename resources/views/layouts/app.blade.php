<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'CargoTrace') · Global Shipment Visibility &amp; Tracking</title>
    <meta name="description" content="Real-time supply chain and shipment visibility web application with multi-leg GPS tracking, deterministic ETA delay detection, exception management, and corridor analytics." />

    <!-- Fonts: Plus Jakarta Sans & IBM Plex Mono (same as the original UI) -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Leaflet 1.9.4 & Chart.js CDN for Standalone Blade Rendering -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Tailwind Play CDN: dark: variants must follow the .dark class on <html>
        // (same as the original's tailwind.config), not the OS colour scheme.
        // fontFamily here is what makes the Play CDN generate its preflight
        // and font-sans/font-mono utilities with Plus Jakarta Sans / IBM Plex
        // Mono. Without it the CDN injects its default ui-sans-serif stack
        // AFTER our <style> block and the whole app renders in the fallback
        // font (wider: header subtitles truncate, KPI subtitles wrap).
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
                        mono: ['IBM Plex Mono', 'ui-monospace', 'SFMono-Regular', 'monospace'],
                    },
                },
            },
        };
        // Apply the saved theme before first paint (avoids a white flash in dark mode).
        try {
            if (localStorage.getItem('cargotrace_theme_v1') === 'dark') document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>

    <style>
        /* CargoTrace Tri-Color Architectural System (#080B0C · #FFFFFF · #C6F432) */
        :root {
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, monospace;
            --bg-rail: #F3F5F7;
            --bg-base: #FFFFFF;
            --bg-surface: #F7F9FA;
            --bg-raised: #EAEEF0;
            --bg-overlay: #FFFFFF;
            --bg-map: #DCE2E6;
            --line: #CFD7DC;
            --line-strong: #94A2AB;
            --ink-1: #080B0C;
            --ink-2: #1A2125;
            --ink-3: #3D4850;
            --tint: #EDF1F3;
            --tint-strong: #DCE3E7;
            --brand: #C6F432;
            --brand-hover: #B5E420;
            --brand-ink: #080B0C;
            --brand-muted: rgba(198, 244, 50, 0.28);
            --ok: #047857;
            --warn: #B45309;
            --danger: #BE123C;
            --live: #B45309;
        }
        html.dark {
            --bg-rail: #050708;
            --bg-base: #080B0C;
            --bg-surface: #0D1214;
            --bg-raised: #131A1D;
            --bg-overlay: #172024;
            --bg-map: #0B0F11;
            --line: rgba(255, 255, 255, 0.09);
            --line-strong: rgba(255, 255, 255, 0.18);
            --ink-1: #FFFFFF;
            --ink-2: #B0BEC4;
            --ink-3: #7A8990;
            --tint: rgba(255, 255, 255, 0.055);
            --tint-strong: rgba(255, 255, 255, 0.1);
            --brand: #C6F432;
            --brand-hover: #D2FA4E;
            --brand-ink: #080B0C;
            --brand-muted: rgba(198, 244, 50, 0.14);
            --ok: #10B981;
            --warn: #F59E0B;
            --danger: #EF4444;
            --live: #F59E0B;
        }
        html {
            background-color: var(--bg-base);
            color: var(--ink-1);
            font-family: var(--font-sans);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        html.dark { color-scheme: dark; }
        html:not(.dark) { color-scheme: light; }
        body {
            min-height: 100vh;
            background-color: var(--bg-base);
            color: var(--ink-1);
            font-feature-settings: "cv02", "cv03", "cv04", "cv11";
        }
        ::selection { background-color: #C6F432; color: #080B0C; }
        .font-mono { font-family: var(--font-mono); }

        /* Theme-aware Leaflet canvas (same rules as the original UI) */
        #world-map,
        .leaflet-container {
            position: relative;
            z-index: 0 !important;
            isolation: isolate;
            background: var(--bg-map) !important;
            background-color: var(--bg-map) !important;
            font-family: var(--font-sans);
            outline: none;
        }
        .leaflet-pane,
        .leaflet-tile-pane { background-color: var(--bg-map); }
        /* OSM tiles desaturated to the pale grey canvas look of the original.
           Dark mode darkens them instead of swapping tile sets. */
        html:not(.dark) .leaflet-tile { filter: grayscale(1) contrast(0.9) brightness(1.16); }
        html.dark .leaflet-tile { filter: grayscale(1) brightness(0.62) contrast(1.12); }
        .leaflet-popup-content-wrapper,
        .leaflet-popup-tip {
            background-color: var(--bg-overlay) !important;
            color: var(--ink-1) !important;
            border: 1px solid var(--line-strong);
            box-shadow: 0 12px 32px rgba(8, 11, 12, 0.24);
            border-radius: 8px;
        }
        .leaflet-container a.leaflet-popup-close-button { color: var(--ink-2) !important; }
        .leaflet-control-zoom {
            border: 1px solid var(--line-strong) !important;
            border-radius: 6px !important;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(8, 11, 12, 0.16) !important;
        }
        .leaflet-control-zoom a {
            background-color: var(--bg-overlay) !important;
            color: var(--ink-1) !important;
            border-color: var(--line) !important;
        }
        .leaflet-control-zoom a:hover {
            background-color: var(--bg-raised) !important;
            color: var(--ink-1) !important;
        }
        .leaflet-control-attribution {
            background: var(--bg-overlay) !important;
            color: var(--ink-2) !important;
            font-size: 10px !important;
            border-top-left-radius: 4px;
            padding: 2px 6px !important;
            opacity: 0.94;
        }
        .leaflet-control-attribution a { color: var(--ink-1) !important; font-weight: 500; }
        .ct-tooltip.leaflet-tooltip {
            background-color: var(--bg-overlay);
            color: var(--ink-1);
            border: 1px solid var(--line-strong);
            border-radius: 6px;
            padding: 4px 8px;
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(8, 11, 12, 0.22);
        }
        .ct-tooltip.leaflet-tooltip::before { display: none; }
        .ct-port-label {
            background: #080B0C;
            border: 1.5px solid #C6F432;
            color: #C6F432;
            font-family: var(--font-mono);
            font-size: 10px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(8, 11, 12, 0.35);
        }
    </style>
</head>
<body class="min-h-screen antialiased selection:bg-[#C6F432] selection:text-[#080B0C]">
    @php
        $ctUser = auth()->user();
        $ctUnread = $ctUser ? $ctUser->notifications()->whereNull('read_at')->count() : 0;
        $ctLatest = $ctUser ? $ctUser->notifications()->with('shipment')->latest()->limit(5)->get() : collect();
        $ctScenario = \Illuminate\Support\Facades\DB::table('scenario_configs')->first();
        $ctRoleLabel = $ctUser ? match ($ctUser->role) {
            'ADMIN' => 'Administrator',
            'LOGISTICS_OPERATOR' => 'Logistics Operator',
            default => 'Customer',
        } : '';
        $ctIsAdmin = $ctUser && $ctUser->isAdmin();
    @endphp
    <div class="flex min-h-screen bg-[var(--bg-base)] text-[var(--ink-1)]">
        <!-- Mobile Slide-Over Backdrop (< 1024px) -->
        <div id="mobile-backdrop" onclick="toggleMobileSidebar(false)" class="fixed inset-0 z-[1350] hidden bg-black/50 backdrop-blur-xs lg:hidden" aria-hidden="true"></div>

        <!-- Navigation Rail (slide-over drawer on mobile/tablet, fixed 240px rail on desktop lg+) -->
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-[1400] flex w-64 -translate-x-full flex-col border-r border-[var(--line)] bg-[var(--bg-rail)] px-3 py-4 transition-transform duration-200 ease-out lg:z-[1100] lg:w-60 lg:translate-x-0">
            <div class="mb-4 flex items-center justify-between px-2.5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-left">
                    <span class="flex size-6 items-center justify-center rounded bg-[#080B0C] text-[#C6F432] dark:bg-[#C6F432] dark:text-[#080B0C]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M22 7.7c0-.6-.4-1.2-.8-1.5l-6.3-3.9a1.72 1.72 0 0 0-1.7 0l-6.3 3.9c-.5.3-.8.9-.8 1.5V16.6c0 .6.4 1.2.8 1.5l6.3 3.9a1.72 1.72 0 0 0 1.7 0l6.3-3.9c.5-.3.8-.9.8-1.5Z"/><path d="M10 21.9V14L2.1 9.1"/><path d="m10 14 11.9-6.9"/><path d="M14 19.8v-8.1"/><path d="M18 17.5V9.4"/></svg>
                    </span>
                    <span class="text-[15px] font-semibold tracking-tight text-[var(--ink-1)]">CargoTrace</span>
                </a>
                <button type="button" onclick="toggleMobileSidebar(false)" aria-label="Close navigation menu" class="flex size-7 items-center justify-center rounded-md text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)] lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="space-y-0.5">
                <p class="mb-1.5 mt-2 px-2.5 text-xs font-semibold text-[var(--ink-3)]">Operations</p>
                <a href="{{ route('dashboard') }}" class="flex h-9 w-full items-center gap-2.5 whitespace-nowrap rounded-md px-2.5 text-[13px] transition-colors {{ request()->routeIs('dashboard') ? 'bg-[#080B0C] font-semibold text-[#FFFFFF] shadow-xs dark:bg-[#C6F432] dark:text-[#080B0C]' : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('shipments.index') }}" class="flex h-9 w-full items-center gap-2.5 whitespace-nowrap rounded-md px-2.5 text-[13px] transition-colors {{ request()->routeIs('shipments.*') ? 'bg-[#080B0C] font-semibold text-[#FFFFFF] shadow-xs dark:bg-[#C6F432] dark:text-[#080B0C]' : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="m7.5 4.27 9 5.15"/></svg>
                    <span>Shipments</span>
                </a>
                <a href="{{ route('notifications.index') }}" class="flex h-9 w-full items-center gap-2.5 whitespace-nowrap rounded-md px-2.5 text-[13px] transition-colors {{ request()->routeIs('notifications.*') ? 'bg-[#080B0C] font-semibold text-[#FFFFFF] shadow-xs dark:bg-[#C6F432] dark:text-[#080B0C]' : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <span class="flex-1 text-left">Notifications</span>
                    @if ($ctUnread > 0)
                        <span class="rounded px-1.5 py-0.5 font-mono text-[11px] font-semibold tabular-nums {{ request()->routeIs('notifications.*') ? 'bg-[#C6F432] text-[#080B0C] dark:bg-[#080B0C] dark:text-[#C6F432]' : 'bg-[var(--tint-strong)] text-[var(--ink-1)]' }}">{{ $ctUnread }}</span>
                    @endif
                </a>
            </div>

            @if ($ctIsAdmin)
            <div class="mt-6 space-y-0.5">
                <p class="mb-1.5 px-2.5 text-xs font-semibold text-[var(--ink-3)]">Administration</p>
                <a href="{{ route('carriers.index') }}" class="flex h-9 w-full items-center gap-2.5 whitespace-nowrap rounded-md px-2.5 text-[13px] transition-colors {{ request()->routeIs('carriers.*') ? 'bg-[#080B0C] font-semibold text-[#FFFFFF] shadow-xs dark:bg-[#C6F432] dark:text-[#080B0C]' : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                    <span>Carriers</span>
                </a>
                <a href="{{ route('locations.index') }}" class="flex h-9 w-full items-center gap-2.5 whitespace-nowrap rounded-md px-2.5 text-[13px] transition-colors {{ request()->routeIs('locations.*') ? 'bg-[#080B0C] font-semibold text-[#FFFFFF] shadow-xs dark:bg-[#C6F432] dark:text-[#080B0C]' : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Port locations</span>
                </a>
                <a href="{{ route('scenario.index') }}" class="flex h-9 w-full items-center gap-2.5 whitespace-nowrap rounded-md px-2.5 text-[13px] transition-colors {{ request()->routeIs('scenario.*') || request()->routeIs('users.*') ? 'bg-[#080B0C] font-semibold text-[#FFFFFF] shadow-xs dark:bg-[#C6F432] dark:text-[#080B0C]' : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/></svg>
                    <span>Users &amp; delay rules</span>
                </a>
                <a href="{{ route('audit.index') }}" class="flex h-9 w-full items-center gap-2.5 whitespace-nowrap rounded-md px-2.5 text-[13px] transition-colors {{ request()->routeIs('audit.*') ? 'bg-[#080B0C] font-semibold text-[#FFFFFF] shadow-xs dark:bg-[#C6F432] dark:text-[#080B0C]' : 'font-medium text-[var(--ink-2)] hover:bg-[var(--tint)] hover:text-[var(--ink-1)]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><path d="M15 12H9"/><path d="M15 8H9"/><path d="M19 13a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h1"/><path d="M5 13a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4"/><path d="M8 21h12"/><path d="M8 3h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>
                    <span>Audit log</span>
                </a>
            </div>
            @endif

            <!-- Quiet Scenario Footnote + User Profile -->
            <div class="mt-auto space-y-3 pt-4">
                <div class="px-2.5 text-xs text-[var(--ink-2)]">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-[var(--ink-3)]">Delay threshold</span>
                        <span class="font-mono text-[11px] font-semibold tabular-nums text-[var(--ink-1)]">≤{{ $ctScenario->low_threshold_hours ?? 6 }}h / ≤{{ $ctScenario->high_threshold_hours ?? 24 }}h</span>
                    </div>
                    <p class="mt-0.5 truncate text-[11px] font-medium text-[var(--ink-3)]">{{ $ctScenario->route_corridor ?? 'Southeast Asia → Europe' }}</p>
                </div>

                @if ($ctUser)
                <div class="flex items-center justify-between gap-2 border-t border-[var(--line)] px-2 pt-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#080B0C] text-xs font-semibold text-[#C6F432] dark:bg-[#C6F432] dark:text-[#080B0C]">{{ strtoupper(substr($ctUser->name, 0, 1)) }}</div>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-[var(--ink-1)]">{{ $ctUser->name }}</p>
                            <p class="truncate text-[11px] font-medium text-[var(--ink-3)]">{{ $ctRoleLabel }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-0.5">
                        @if ($ctUser->isAdmin())
                            <form method="POST" action="{{ route('scenario.reset') }}" onsubmit="return confirm('Reset the demo dataset to the seeded state? All changes since the last seed will be lost.');">
                                @csrf
                                <button type="submit" title="Reset seeded demo data" aria-label="Reset seeded demo data" class="flex size-7 items-center justify-center rounded-md text-[var(--ink-2)] transition-colors hover:bg-[var(--tint)] hover:text-[var(--ink-1)]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="Sign out" aria-label="Sign out" class="flex size-7 items-center justify-center rounded-md text-[var(--ink-2)] transition-colors hover:bg-[var(--tint)] hover:text-[var(--ink-1)]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
        </aside>

        <!-- Main Content Area (full width on mobile, pl-60 on desktop lg+) -->
        <div class="flex min-h-screen min-w-0 flex-1 flex-col lg:pl-60">
            <!-- Header elevated above all Leaflet map panes (z-[1200]) -->
            <header class="relative z-[1200] flex h-14 items-center justify-between gap-2 border-b border-[var(--line)] bg-[var(--bg-base)] px-3 sm:px-6">
                <div class="flex min-w-0 items-center gap-2.5">
                    <button type="button" onclick="toggleMobileSidebar(true)" aria-label="Open navigation menu" class="flex size-8 shrink-0 items-center justify-center rounded-md border border-[var(--line)] bg-[var(--bg-surface)] text-[var(--ink-1)] hover:bg-[var(--bg-raised)] lg:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div class="flex min-w-0 items-baseline gap-2.5">
                        <h1 class="shrink-0 truncate text-sm font-semibold tracking-tight text-[var(--ink-1)] sm:text-[15px]">@yield('header_title', 'CargoTrace')</h1>
                        <span class="hidden truncate text-xs font-medium text-[var(--ink-3)] md:inline">@yield('header_subtitle', '')</span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1.5 sm:gap-2.5">
                    @if ($ctUser)
                    <div class="hidden items-center gap-2 text-xs text-[var(--ink-3)] xl:flex">
                        <span class="font-medium">{{ $ctRoleLabel }}</span>
                        <span aria-hidden="true">·</span>
                        <span class="font-semibold text-[var(--ink-1)]">{{ $ctUser->name }}</span>
                    </div>
                    @endif

                    <button type="button" onclick="toggleTheme()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2 text-xs font-semibold text-[var(--ink-1)] transition-colors hover:border-[var(--line-strong)] hover:bg-[var(--bg-raised)] sm:px-2.5" aria-label="Toggle theme">
                        <span id="theme-icon-moon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#080B0C] dark:text-[#C6F432]"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></span>
                        <span id="theme-icon-sun" class="hidden"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#C6F432]"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg></span>
                        <span id="theme-label" class="hidden sm:inline">Dark mode</span>
                    </button>

                    <div class="relative">
                        <button type="button" onclick="toggleBellDropdown(event)" aria-label="Notifications" class="relative flex size-8 items-center justify-center rounded-md border border-[var(--line)] bg-[var(--bg-surface)] text-[var(--ink-1)] transition-colors hover:border-[var(--line-strong)] hover:bg-[var(--bg-raised)]">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                            @if ($ctUnread > 0)
                                <span class="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full border border-[#080B0C]/20 bg-[#C6F432] font-mono text-[10px] font-bold text-[#080B0C]">{{ $ctUnread > 9 ? '9+' : $ctUnread }}</span>
                            @endif
                        </button>
                        <div id="bell-dropdown" class="absolute right-0 top-full z-[1300] mt-2 hidden w-80 overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] shadow-lg">
                            <div class="divide-y divide-[var(--line)]">
                                @forelse ($ctLatest as $ctNote)
                                    <a href="{{ $ctNote->shipment ? route('shipments.show', $ctNote->shipment) : route('notifications.index') }}" class="flex items-start gap-2.5 px-4 py-3 transition-colors hover:bg-[var(--bg-raised)]">
                                        @if (is_null($ctNote->read_at))
                                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#C6F432]"></span>
                                        @else
                                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-[var(--ink-3)]"></span>
                                        @endif
                                        <span class="min-w-0">
                                            <span class="block truncate text-xs font-medium text-[var(--ink-1)]">{{ $ctNote->title }}</span>
                                            <span class="mt-0.5 block font-mono text-[11px] text-[var(--ink-3)]">{{ $ctNote->created_at->format('M j · H:i') }}</span>
                                        </span>
                                    </a>
                                @empty
                                    <p class="px-4 py-3 text-xs text-[var(--ink-3)]">No notifications yet.</p>
                                @endforelse
                            </div>
                            <a href="{{ route('notifications.index') }}" class="block border-t border-[var(--line)] bg-[var(--bg-base)] px-4 py-2.5 text-xs font-semibold text-[var(--ink-1)] transition-colors hover:bg-[var(--bg-raised)]">View all notifications →</a>
                        </div>
                    </div>

                    <a href="{{ route('shipments.create') }}" class="inline-flex h-8 shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-md bg-[var(--brand)] px-2.5 text-xs font-semibold text-[var(--brand-ink)] transition-colors hover:bg-[var(--brand-hover)] sm:px-3">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        <span class="hidden sm:inline">{{ $ctUser && $ctUser->isCustomer() ? 'Request shipment' : 'New shipment' }}</span>
                        <span class="sm:hidden">{{ $ctUser && $ctUser->isCustomer() ? 'Request' : 'New' }}</span>
                    </a>
                </div>
            </header>

            <main class="relative z-0 min-h-0 flex-1">@yield('content')</main>
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

        function applyThemeLabel() {
            const isDark = document.documentElement.classList.contains('dark');
            const label = document.getElementById('theme-label');
            const moon = document.getElementById('theme-icon-moon');
            const sun = document.getElementById('theme-icon-sun');
            if (label) label.textContent = isDark ? 'White mode' : 'Dark mode';
            if (moon) moon.classList.toggle('hidden', isDark);
            if (sun) sun.classList.toggle('hidden', !isDark);
        }

        function toggleTheme() {
            const root = document.documentElement;
            const isDark = root.classList.toggle('dark');
            try { localStorage.setItem('cargotrace_theme_v1', isDark ? 'dark' : 'light'); } catch (e) {}
            applyThemeLabel();
            window.dispatchEvent(new CustomEvent('ct:theme', { detail: { dark: isDark } }));
        }

        function toggleBellDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('bell-dropdown');
            if (dropdown) dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('bell-dropdown');
            if (dropdown && !dropdown.classList.contains('hidden') && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        document.addEventListener('DOMContentLoaded', applyThemeLabel);
    </script>
    @stack('scripts')
</body>
</html>
