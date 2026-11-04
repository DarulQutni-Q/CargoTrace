<x-layouts.app title="Dashboard">
    <div class="space-y-6 p-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ([
                'Total shipments' => $stats['total'],
                'In transit' => $stats['in_transit'],
                'Delivered' => $stats['delivered'],
                'Delayed' => $stats['delayed'],
                'Open exceptions' => $stats['open_exceptions'],
            ] as $label => $value)
                <div class="rounded-xl border border-line bg-surface p-4">
                    <p class="text-xs text-ink-2">{{ $label }}</p>
                    <p class="mt-1.5 font-mono text-3xl tabular-nums text-ink">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-line bg-surface p-5">
                <h2 class="text-sm font-medium text-ink-2">Shipments by status</h2>
                <div class="mt-4 h-52">
                    <canvas id="chart-by-status" data-chart='@json($byStatus)'></canvas>
                </div>
            </section>

            <section class="rounded-xl border border-line bg-surface p-5">
                <h2 class="text-sm font-medium text-ink-2">Shipments by carrier</h2>
                <div class="mt-4 h-52">
                    <canvas id="chart-by-carrier" data-chart='@json($byCarrier)'></canvas>
                </div>
            </section>
        </div>

        <section class="rounded-xl border border-line bg-surface p-5">
            <h2 class="text-sm font-medium text-ink-2">Rates</h2>
            <div class="mt-4 grid gap-6 sm:grid-cols-2">
                <div>
                    <div class="flex items-baseline justify-between text-xs">
                        <span class="text-ink-2">Delay rate</span>
                        <span class="font-mono text-ink">{{ $delayRate }}%</span>
                    </div>
                    <div class="mt-2 h-1 rounded-full bg-raised">
                        <div class="h-1 rounded-full bg-warn" style="width: {{ $delayRate }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex items-baseline justify-between text-xs">
                        <span class="text-ink-2">Exception rate</span>
                        <span class="font-mono text-ink">{{ $exceptionRate }}%</span>
                    </div>
                    <div class="mt-2 h-1 rounded-full bg-raised">
                        <div class="h-1 rounded-full bg-danger" style="width: {{ $exceptionRate }}%"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @vite('resources/js/dashboard.js')
</x-layouts.app>
