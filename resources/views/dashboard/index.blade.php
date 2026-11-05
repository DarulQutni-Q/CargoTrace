@extends('layouts.app')

@section('title', 'Dashboard')
@section('header_title', 'Dashboard')
@section('header_subtitle', 'Corridor metrics, carrier performance, and exception triage')

@section('content')
<div class="mx-auto max-w-[1440px] space-y-5 p-3 sm:space-y-6 sm:p-6">
    <!-- Scenario Strip -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] px-4 py-3.5 sm:px-5 sm:py-4">
        <div>
            <div class="flex items-center gap-2 text-xs">
                <span class="font-mono font-semibold">{{ $scenario->group_code ?? 'DEFAULT' }}</span>
                <span>·</span>
                <span>{{ $scenario->route_corridor ?? 'Southeast Asia → Europe' }}</span>
            </div>
            <p class="mt-1 text-xs opacity-75">
                Delay thresholds:
                <span class="font-mono">Low ≤ {{ $scenario->low_threshold_hours ?? 6 }}h · Medium ≤ {{ $scenario->high_threshold_hours ?? 24 }}h</span>
                · Primary exception: {{ $scenario->primary_exception ?? 'CUSTOMS_HOLD' }}
            </p>
        </div>
        <a href="{{ route('shipments.index') }}" class="inline-flex h-8 items-center rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">Open map →</a>
    </div>

    <!-- 6-KPI Strip -->
    <div class="grid grid-cols-2 divide-y divide-[var(--line)] overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] sm:grid-cols-3 lg:grid-cols-6 lg:divide-x lg:divide-y-0">
        <div class="p-4">
            <div class="text-xs opacity-70">Total shipments</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['total_shipments'] }}</div>
            <div class="mt-1 text-xs opacity-70">Active corridor units</div>
        </div>
        <div class="p-4">
            <div class="text-xs opacity-70">In transit</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['in_transit'] }}</div>
            <div class="mt-1 text-xs opacity-70">En route to destination</div>
        </div>
        <div class="p-4">
            <div class="text-xs opacity-70">Delivered</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['delivered'] }}</div>
            <div class="mt-1 text-xs font-semibold text-emerald-700">POD confirmed</div>
        </div>
        <div class="p-4">
            <div class="text-xs opacity-70">Delayed</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['delayed'] }}</div>
            <div class="mt-1 font-mono text-xs font-semibold text-amber-700">ETA drift</div>
        </div>
        <div class="p-4">
            <div class="text-xs opacity-70">Open exceptions</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['open_exceptions'] }}</div>
            <div class="mt-1 text-xs font-semibold text-rose-700">Unresolved incidents</div>
        </div>
        <div class="p-4">
            <div class="text-xs opacity-70">On-time rate</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['on_time_rate'] }}%</div>
            <div class="mt-1 text-xs opacity-70">Corridor SLA</div>
        </div>
    </div>

    <!-- Charts: by status & by carrier -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
            <h3 class="text-sm font-semibold">Shipments by lifecycle status</h3>
            <p class="mt-0.5 text-xs opacity-70">Live counts from the database</p>
            <div class="mt-4 h-[240px]"><canvas id="chart-status"></canvas></div>
        </div>
        <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
            <h3 class="text-sm font-semibold">Shipments &amp; delays by carrier</h3>
            <p class="mt-0.5 text-xs opacity-70">On-time vs delayed, per carrier</p>
            <div class="mt-4 h-[240px]"><canvas id="chart-carrier"></canvas></div>
        </div>
    </div>

    <!-- Priority Attention Table -->
    <div class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-3.5">
            <div>
                <h3 class="text-sm font-semibold">Priority attention queue</h3>
                <p class="mt-0.5 text-xs opacity-70">Active shipments monitored across the corridor</p>
            </div>
            <a href="{{ route('shipments.index') }}" class="text-xs font-medium underline">View all shipments →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs opacity-75">
                        <th class="px-5 py-3 font-medium">Reference</th>
                        <th class="px-5 py-3 font-medium">Route corridor</th>
                        <th class="px-5 py-3 font-medium">Carrier</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]">
                    @forelse ($shipments as $s)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs font-semibold">{{ $s->shipment_number }}</td>
                            <td class="px-5 py-3 text-xs">
                                <span class="font-mono font-bold">{{ $s->origin->code }}</span> {{ $s->origin->city }}
                                →
                                <span class="font-mono font-bold">{{ $s->destination->code }}</span> {{ $s->destination->city }}
                            </td>
                            <td class="px-5 py-3 text-xs">{{ $s->carrier->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs font-semibold">{{ $s->current_status }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('shipments.show', $s) }}" class="text-xs font-semibold underline">Inspect →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-xs opacity-70">All monitored shipments are operating on schedule with zero active exceptions.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;
        const statusCtx = document.getElementById('chart-status');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: @json(array_keys($byStatus)),
                    datasets: [{
                        data: @json(array_values($byStatus)),
                        backgroundColor: ['#9AA5AB', '#080B0C', '#526068', '#C6F432', '#2A7F3D'],
                        borderWidth: 1,
                        borderColor: '#FFFFFF',
                    }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
            });
        }
        const carrierCtx = document.getElementById('chart-carrier');
        if (carrierCtx) {
            new Chart(carrierCtx, {
                type: 'bar',
                data: {
                    labels: @json($byCarrier->pluck('name')),
                    datasets: [
                        { label: 'On time', data: @json($byCarrier->pluck('on_time')), backgroundColor: '#080B0C' },
                        { label: 'Delayed', data: @json($byCarrier->pluck('delayed')), backgroundColor: '#C6F432' },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } },
                    plugins: { legend: { position: 'bottom' } },
                },
            });
        }
    });
</script>
@endpush
@endsection
