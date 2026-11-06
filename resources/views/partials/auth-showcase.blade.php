{{-- Auth showcase panel: faithful port of AuthShowcasePanel from the original
     React UI (src/views/AuthView.tsx at 6bbaefa). The dashboard mockup data
     (94.2%, CT0009MAC, Colombo, ...) is hardcoded in the original too;
     it is a marketing mockup, not live data. Shown on lg screens and up. --}}
<div class="hidden lg:flex lg:p-4 xl:p-5">
    <div class="relative flex w-full flex-col justify-between overflow-hidden rounded-[28px] border border-white/10 bg-[#080B0C] px-10 py-10 text-white shadow-2xl xl:px-14 xl:py-12">
        {{-- Decorative cartographic arcs & volt-lime glow --}}
        <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-25" viewBox="0 0 800 900" fill="none" aria-hidden="true">
            <circle cx="680" cy="140" r="280" stroke="#C6F432" stroke-opacity="0.35" stroke-dasharray="4 6" />
            <circle cx="680" cy="140" r="420" stroke="#FFFFFF" stroke-opacity="0.12" />
            <circle cx="120" cy="820" r="340" stroke="#C6F432" stroke-opacity="0.22" stroke-dasharray="3 6" />
            <path d="M-40 520 C 180 360, 460 340, 840 470" stroke="#C6F432" stroke-opacity="0.45" stroke-width="2" />
            <path d="M-40 620 C 220 450, 510 430, 840 580" stroke="#FFFFFF" stroke-opacity="0.16" stroke-width="1.2" stroke-dasharray="5 5" />
        </svg>
        <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-[#C6F432]/15 blur-3xl" aria-hidden="true"></div>

        {{-- Top headline --}}
        <div class="relative z-10 max-w-md">
            <h2 class="text-[30px] font-semibold leading-[1.2] tracking-tight text-white xl:text-[34px]">
                The simplest way to manage your cargo corridor
            </h2>
            <p class="mt-2.5 text-[14px] leading-relaxed text-[#A3B1B6]">
                Enter your credentials to access real-time vessel telemetry, deterministic delay detection, and exception workflows.
            </p>
        </div>

        {{-- Center layered product UI showcase --}}
        <div class="relative z-10 my-6 flex items-center justify-center">
            <div class="relative w-full max-w-[520px] pr-6 xl:pr-10">
                {{-- Main dashboard card --}}
                <div class="rounded-2xl border border-white/15 bg-[#0D1214]/95 p-5 text-white shadow-[0_30px_70px_-15px_rgba(0,0,0,0.85)] backdrop-blur-md">
                    <div class="flex items-center justify-between gap-2 pb-4">
                        <div>
                            <p class="text-[13px] font-semibold text-white">Corridor Dashboard</p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/[0.04] px-2 py-1 text-[10px] text-[#A3B1B6]">
                                    <span class="size-3.5 rounded-full bg-[#C6F432] text-center text-[8px] font-bold leading-[14px] text-[#080B0C]">A</span>
                                    All Corridors ▾
                                </span>
                                <span class="rounded-md border border-white/10 bg-white/[0.04] px-2 py-1 font-mono text-[10px] text-[#6B797E]">‹ Oct 30 - Nov 20, 2026 ›</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="flex -space-x-1.5">
                                <span class="flex size-5 items-center justify-center rounded-full bg-[#C6F432] text-[9px] font-semibold text-[#080B0C] ring-1 ring-[#080B0C]">AW</span>
                                <span class="flex size-5 items-center justify-center rounded-full bg-white text-[9px] font-semibold text-[#080B0C] ring-1 ring-[#080B0C]">BS</span>
                                <span class="flex size-5 items-center justify-center rounded-full bg-[#172024] text-[9px] font-semibold text-white ring-1 ring-white/20">CH</span>
                            </div>
                            <span class="rounded-md bg-[#C6F432] px-2 py-1 text-[10px] font-semibold text-[#080B0C]">+14 Active</span>
                        </div>
                    </div>

                    {{-- Two KPI sparkline cards --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-white/10 bg-[#080B0C] p-3.5">
                            <p class="text-[10px] text-[#6B797E]">On-Time Corridor / Day</p>
                            <div class="mt-1.5 flex items-end justify-between">
                                <div>
                                    <p class="font-mono text-lg font-semibold text-white tabular-nums">94.2%</p>
                                    <p class="mt-1 whitespace-nowrap text-[10px] text-[#C6F432]">↑ +4.8% last week</p>
                                </div>
                                <svg class="h-9 w-20 overflow-visible" viewBox="0 0 80 36" fill="none">
                                    <path d="M2 28 C 16 28, 20 14, 34 18 C 46 22, 54 6, 78 8" stroke="#C6F432" stroke-width="2" stroke-linecap="round" />
                                    <circle cx="58" cy="10" r="3" fill="#C6F432" />
                                </svg>
                            </div>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-[#080B0C] p-3.5">
                            <p class="text-[10px] text-[#6B797E]">Mean Transit Window</p>
                            <div class="mt-1.5 flex items-end justify-between">
                                <div>
                                    <p class="font-mono text-lg font-semibold text-white tabular-nums">18.5 d</p>
                                    <p class="mt-1 text-[10px] text-[#A3B1B6]">LOW ≤ 6h · MED ≤ 24h</p>
                                </div>
                                <svg class="h-9 w-16 overflow-visible" viewBox="0 0 64 36" fill="none">
                                    <path d="M2 24 C 18 16, 30 26, 44 14 C 52 8, 58 12, 62 6" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Corridor utilization table --}}
                    <div class="mt-4">
                        <p class="mb-2 text-[11px] font-medium text-[#A3B1B6]">Corridor Utilization &amp; Active Vessels</p>
                        <div class="space-y-2 border-t border-white/10 pt-2.5 text-[10px]">
                            @php
                                $utilRows = [
                                    ['code' => 'S', 'name' => 'SIN → RTM Sea Line', 'status' => 'ON TIME', 'statusColor' => 'text-[#C6F432]', 'load' => '82%', 'bar' => 'w-4/5'],
                                    ['code' => 'B', 'name' => 'BKK → HAM Air Cargo', 'status' => 'MED DELAY', 'statusColor' => 'text-amber-400', 'load' => '64%', 'bar' => 'w-3/5'],
                                    ['code' => 'J', 'name' => 'JKT → RTM Deepwater', 'status' => 'ON TIME', 'statusColor' => 'text-[#C6F432]', 'load' => '91%', 'bar' => 'w-11/12'],
                                    ['code' => 'S', 'name' => 'SUB → RTM Feeder', 'status' => 'DELIVERED', 'statusColor' => 'text-white', 'load' => '100%', 'bar' => 'w-full'],
                                    ['code' => 'M', 'name' => 'MNL → HAM Express', 'status' => 'ON TIME', 'statusColor' => 'text-[#C6F432]', 'load' => '58%', 'bar' => 'w-3/5'],
                                ];
                            @endphp
                            @foreach ($utilRows as $row)
                                <div class="grid grid-cols-12 items-center gap-2 py-0.5">
                                    <div class="col-span-5 flex items-center gap-2 truncate">
                                        <span class="flex size-4 shrink-0 items-center justify-center rounded-full bg-[#C6F432] font-mono text-[9px] font-bold text-[#080B0C]">{{ $row['code'] }}</span>
                                        <span class="truncate text-white">{{ $row['name'] }}</span>
                                    </div>
                                    <div class="col-span-3 font-mono text-[9px] {{ $row['statusColor'] }}">{{ $row['status'] }}</div>
                                    <div class="col-span-2 font-mono text-[#6B797E] tabular-nums">{{ $row['load'] }}</div>
                                    <div class="col-span-2 flex items-center gap-1.5">
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                                            <div class="h-full rounded-full bg-[#C6F432] {{ $row['bar'] }}"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Overlapping floating card: live telemetry dispatch --}}
                <div class="absolute -right-3 top-14 w-[235px] rounded-2xl border border-[#C6F432]/40 bg-[#080B0C] p-4 text-white shadow-[0_25px_50px_-12px_rgba(0,0,0,0.9)] xl:-right-6 xl:w-[250px]">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-white">Live Telemetry</span>
                        <span class="flex size-4 items-center justify-center rounded-full border border-white/15 text-[10px] text-[#6B797E]">×</span>
                    </div>
                    <div class="mt-2.5">
                        <p class="text-[9px] text-[#6B797E]">Active Corridor Shipment</p>
                        <div class="mt-1 flex items-center gap-1.5">
                            <div class="flex flex-1 items-center justify-between rounded-md border border-white/10 bg-[#0D1214] px-2 py-1 text-[10px]">
                                <span class="font-mono font-medium text-white">CT0009MAC</span>
                                <span class="text-[9px] text-[#6B797E]">BKK → HAM</span>
                            </div>
                            <span class="rounded-md bg-[#C6F432] px-2 py-1 text-[9px] font-semibold text-[#080B0C]">Track</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-[9px] text-[#6B797E]">Checkpoints</p>
                        <div class="mt-1.5 space-y-2">
                            @php
                                $checkpoints = [
                                    ['loc' => 'Colombo Deepwater Hub', 'coord' => '6.9497° N, 79.8428° E', 'tag' => '05:30', 'dot' => 'bg-[#C6F432]'],
                                    ['loc' => 'Departed Bangkok Port', 'coord' => '13.6900° N, 100.7501° E', 'tag' => '19:30', 'dot' => 'bg-white'],
                                    ['loc' => 'Suvarnabhumi Cargo', 'coord' => 'Origin Gate Cleared', 'tag' => '06:30', 'dot' => 'bg-[#6B797E]'],
                                ];
                            @endphp
                            @foreach ($checkpoints as $cp)
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="size-2 shrink-0 rounded-full {{ $cp['dot'] }}"></span>
                                        <div class="min-w-0">
                                            <p class="truncate text-[10px] font-medium text-white">{{ $cp['loc'] }}</p>
                                            <p class="truncate font-mono text-[8px] text-[#6B797E]">{{ $cp['coord'] }}</p>
                                        </div>
                                    </div>
                                    <span class="rounded bg-white/[0.06] px-1.5 py-0.5 font-mono text-[8px] text-[#A3B1B6]">{{ $cp['tag'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-3 border-t border-white/10 pt-2.5">
                        <p class="text-[9px] text-[#6B797E]">Corridor Feed Endpoint</p>
                        <div class="mt-1 flex items-center justify-between gap-1.5 rounded-md border border-white/10 bg-[#0D1214] px-2 py-1">
                            <span class="truncate font-mono text-[9px] text-[#A3B1B6]">/api/shipments/9/tracking</span>
                            <span class="rounded bg-[#C6F432] px-1.5 py-0.5 text-[8px] font-semibold text-[#080B0C]">JSON</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bottom maritime & logistics partner strip --}}
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4 border-t border-white/15 pt-5 text-white/75">
            <div class="flex items-center gap-1.5 text-xs font-medium tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#C6F432]"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"/><path d="M12 10v4"/><path d="M12 2v3"/></svg>
                <span>Maersk Line</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-semibold tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#C6F432]"><circle cx="12" cy="5" r="3"/><line x1="12" x2="12" y1="22" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/></svg>
                <span>Port of Rotterdam</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#C6F432]"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                <span>PSA Singapore</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#C6F432]"><path d="M12.8 19.6A2 2 0 1 0 14 16H2"/><path d="M17.5 8a2.5 2.5 0 1 1 2 4H2"/><path d="M9.8 4.4A2 2 0 1 1 11 8H2"/></svg>
                <span>Open-Meteo</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#C6F432]"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                <span>Hapag-Lloyd</span>
            </div>
        </div>
    </div>
</div>
