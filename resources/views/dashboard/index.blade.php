@extends('layouts.app')

@section('title', 'Dashboard')
@section('header_title', 'Dashboard')
@section('header_subtitle', 'Corridor metrics, carrier performance, and exception triage')

@php
    $statusLabel = ['CREATED' => 'Created', 'BOOKED' => 'Booked', 'PICKED_UP' => 'Picked up', 'IN_TRANSIT' => 'In transit', 'DELIVERED' => 'Delivered'];
    $statusTone = fn (string $st): string => match ($st) {
        'CREATED' => 'neutral', 'BOOKED' => 'amber', 'PICKED_UP' => 'teal',
        'IN_TRANSIT' => 'blue', 'DELIVERED' => 'green', default => 'neutral',
    };
    $delayTone = fn (string $state): string => match ($state) {
        'low' => 'amber', 'medium' => 'orange', 'high' => 'red',
        'on_time' => 'green', default => 'neutral',
    };
@endphp

@section('content')
<div class="mx-auto max-w-[1440px] space-y-5 p-3 sm:space-y-6 sm:p-6">
    @if (session('status'))
        <div class="rounded-md border border-[#C6F432] bg-[#C6F432]/20 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                · Primary exception: {{ \App\Models\ShipmentException::typeLabel($scenario->primary_exception ?? 'CUSTOMS_HOLD') }}
                · Focus KPI: {{ $scenario->kpi_focus ?? 'On-Time Delivery & Exception Rate' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('scenario.index') }}" class="inline-flex h-8 items-center rounded-md border border-[var(--line)] bg-[var(--bg-base)] px-3 text-xs font-medium">Configure scenario</a>
            @endif
            <a href="{{ route('shipments.index') }}" class="inline-flex h-8 items-center rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">Open map →</a>
        </div>
    </div>

    <!-- Customer Portal Hub -->
    @if (auth()->user()->isCustomer())
        <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-xl">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-[#C6F432] px-2 py-0.5 font-mono text-[10px] font-bold text-[#080B0C]">CUSTOMER PORTAL</span>
                        <h2 class="text-sm font-bold">Order Shipment Baru atau Lacak &amp; Hubungkan Nomor Resi</h2>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed opacity-80">
                        Sebagai <strong>Customer / Consignee</strong>, Anda dapat mengajukan pesanan pengiriman baru (status awal <span class="font-mono font-semibold">CREATED</span>), memasukkan nomor referensi resi (misal <span class="font-mono font-semibold">CT0015MAC</span> atau <span class="font-mono font-semibold">CT0001NFR</span>) untuk dihubungkan ke akun Anda, serta memantau peta GPS, cuaca koridor, SLA keterlambatan, dan notifikasi secara real-time.
                    </p>
                </div>
                <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                    <form method="POST" action="{{ route('shipments.claim') }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        @csrf
                        <div class="relative flex-1 sm:flex-initial">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-[var(--ink-3)]"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="text" name="shipment_number" required placeholder="Masukkan No. Resi (cth: CT0015MAC)" class="h-8 w-full rounded-md border border-[var(--line)] bg-[var(--bg-base)] pl-8 pr-2.5 font-mono text-xs placeholder:font-sans placeholder:text-[var(--ink-3)] sm:w-56" />
                        </div>
                        <button type="submit" class="h-8 rounded-md border border-[var(--line)] bg-[var(--bg-raised)] px-3 text-xs font-semibold">Link &amp; Track Resi</button>
                    </form>
                    <a href="{{ route('shipments.create') }}" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[#C6F432] px-3 text-xs font-semibold text-[#080B0C]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        Request shipment
                    </a>
                </div>
            </div>
            @if ($errors->has('shipment_number'))
                <p class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $errors->first('shipment_number') }}</p>
            @endif
            @if ($metrics['total_shipments'] === 0 && $claimSamples->isNotEmpty())
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-[var(--line)] pt-3 text-xs">
                    <span class="font-medium text-[var(--ink-3)]">Akun Anda belum memiliki resi aktif. Klik untuk menghubungkan resi contoh:</span>
                    @foreach ($claimSamples as $sample)
                        <form method="POST" action="{{ route('shipments.claim') }}">
                            @csrf
                            <input type="hidden" name="shipment_number" value="{{ $sample->shipment_number }}" />
                            <button type="submit" class="inline-flex items-center gap-1 rounded border border-[var(--line)] bg-[var(--bg-base)] px-2 py-1 font-mono text-[11px] font-semibold hover:border-[#080B0C] hover:bg-[#C6F432] hover:text-[#080B0C]">+ {{ $sample->shipment_number }} ({{ $sample->cargo_type }})</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- 6-KPI Strip -->
    <div class="grid grid-cols-2 divide-y divide-[var(--line)] overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] sm:grid-cols-3 lg:grid-cols-6 lg:divide-x lg:divide-y-0">
        <a href="{{ route('shipments.index') }}" class="block px-3 py-4 text-left transition-colors hover:bg-[var(--bg-raised)]">
            <div class="text-xs opacity-70">Total shipments</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['total_shipments'] }}</div>
            <div class="mt-1 text-xs opacity-70">Active corridor units</div>
        </a>
        <a href="{{ route('shipments.index', ['status' => 'IN_TRANSIT']) }}" class="block px-3 py-4 text-left transition-colors hover:bg-[var(--bg-raised)]">
            <div class="text-xs opacity-70">In transit</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['in_transit'] }}</div>
            <div class="mt-1 whitespace-nowrap text-xs opacity-70">En route to destination</div>
        </a>
        <a href="{{ route('shipments.index', ['status' => 'DELIVERED']) }}" class="block px-3 py-4 text-left transition-colors hover:bg-[var(--bg-raised)]">
            <div class="text-xs opacity-70">Delivered</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['delivered'] }}</div>
            <div class="mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400">POD confirmed</div>
        </a>
        <a href="{{ route('shipments.index', ['delayed' => '1']) }}" class="block px-3 py-4 text-left transition-colors hover:bg-[var(--bg-raised)]">
            <div class="text-xs opacity-70">Delayed</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['delayed'] }}</div>
            <div class="mt-1 font-mono text-xs font-semibold text-amber-700 dark:text-amber-400">Avg +{{ $metrics['avg_delay_hours'] }}h drift</div>
        </a>
        <a href="{{ route('shipments.index', ['exceptions' => '1']) }}" class="block px-3 py-4 text-left transition-colors hover:bg-[var(--bg-raised)]">
            <div class="text-xs opacity-70">Open exceptions</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['open_exceptions'] }}</div>
            <div class="mt-1 whitespace-nowrap text-xs font-semibold text-rose-700 dark:text-rose-400">Unresolved incidents</div>
        </a>
        <div class="px-3 py-4">
            <div class="text-xs opacity-70">On-time rate</div>
            <div class="mt-1.5 font-mono text-2xl font-semibold">{{ $metrics['on_time_rate'] }}%</div>
            <div class="mt-1 text-xs opacity-70">Corridor SLA</div>
        </div>
    </div>

    <!-- Charts: by status, by carrier, 6-month trend (3 columns, like the original) -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
            <h3 class="text-sm font-semibold">Shipments by lifecycle status</h3>
            <p class="mt-0.5 text-xs opacity-70">Live counts from the database</p>
            <div class="mt-4 h-60"><canvas id="chart-status"></canvas></div>
        </div>
        <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
            <h3 class="text-sm font-semibold">Shipments &amp; delays by carrier</h3>
            <p class="mt-0.5 text-xs opacity-70">On-time vs delayed, per carrier</p>
            <div class="mt-4 h-60"><canvas id="chart-carrier"></canvas></div>
        </div>
        <div class="rounded-lg border border-[var(--line)] bg-[var(--bg-surface)] p-4 sm:p-5">
            <h3 class="text-sm font-semibold">6-month corridor volume</h3>
            <p class="mt-0.5 text-xs opacity-70">Shipments departing per month (Jun – Nov)</p>
            <div class="mt-4 h-60"><canvas id="chart-trend"></canvas></div>
        </div>
    </div>

    <!-- Priority Attention Table -->
    <div class="overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--bg-surface)]">
        <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-3.5">
            <div>
                <h3 class="text-sm font-semibold">Priority attention queue</h3>
                <p class="mt-0.5 text-xs opacity-70">Delayed shipments and open exceptions, worst delay first</p>
            </div>
            <a href="{{ route('shipments.index', ['delayed' => '1']) }}" class="text-xs font-medium underline">View all delayed →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] bg-[var(--bg-base)] text-xs opacity-75">
                        <th class="px-5 py-3 font-medium">Reference</th>
                        <th class="px-5 py-3 font-medium">Route corridor</th>
                        <th class="px-5 py-3 font-medium">Carrier</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">ETA drift</th>
                        <th class="px-5 py-3 font-medium">Exception</th>
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
                            <td class="px-5 py-3">@include('partials.badge', ['tone' => $statusTone($s->current_status), 'label' => $statusLabel[$s->current_status] ?? $s->current_status])</td>
                            <td class="px-5 py-3">
                                @if ($s->delay_assessment['delayed'])
                                    @include('partials.badge', ['tone' => $delayTone($s->delay_assessment['state']), 'label' => '+' . $s->delay_assessment['delay_hours'] . 'h (' . $s->estimated_arrival_at?->format('M j') . ')'])
                                @else
                                    <span class="text-xs">On schedule</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($s->open_exception)
                                    @include('partials.badge', ['tone' => 'red', 'label' => \App\Models\ShipmentException::typeLabel($s->open_exception->type) . ' · ' . $s->open_exception->status])
                                @else
                                    <span class="text-xs opacity-70">None</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('shipments.show', $s) }}" class="text-xs font-semibold underline">Inspect →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-xs opacity-70">All monitored shipments are operating on schedule with zero active exceptions.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let ctCharts = [];
    function renderDashboardCharts() {
        if (typeof Chart === 'undefined') return;
        const isDark = document.documentElement.classList.contains('dark');
        const surfaceBg = getComputedStyle(document.documentElement).getPropertyValue('--bg-surface').trim() || (isDark ? '#0E1314' : '#F7F9FA');
        const axisColor = getComputedStyle(document.documentElement).getPropertyValue('--ink-3').trim() || '#64748B';
        ctCharts.forEach((c) => c.destroy());
        ctCharts = [];

        const statusCtx = document.getElementById('chart-status');
        if (statusCtx) {
            ctCharts.push(new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: @json(array_map(fn ($k) => $statusLabel[$k] ?? $k, array_keys($byStatus))),
                    datasets: [{
                        data: @json(array_values($byStatus)),
                        backgroundColor: isDark ? ['#3A4448', '#6B797E', '#A3B1B6', '#C6F432', '#10B981'] : ['#94A3A8', '#647177', '#3A4448', '#080B0C', '#C6F432'],
                        borderColor: surfaceBg,
                        borderWidth: 2,
                    }],
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, color: axisColor } } } },
            }));
        }
        const carrierCtx = document.getElementById('chart-carrier');
        if (carrierCtx) {
            ctCharts.push(new Chart(carrierCtx, {
                type: 'bar',
                data: {
                    labels: @json($byCarrier->pluck('name')),
                    datasets: [
                        { label: 'On schedule', data: @json($byCarrier->pluck('on_time')), backgroundColor: isDark ? '#C6F432' : '#080B0C', borderRadius: 4 },
                        { label: 'Delayed', data: @json($byCarrier->pluck('delayed')), backgroundColor: '#F59E0B', borderRadius: 4 },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, color: axisColor } } },
                },
            }));
        }
        const trendCtx = document.getElementById('chart-trend');
        if (trendCtx) {
            ctCharts.push(new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
                    datasets: [{
                        label: 'Shipments in corridor',
                        data: @json($trend),
                        borderColor: isDark ? '#C6F432' : '#080B0C',
                        backgroundColor: isDark ? 'rgba(198, 244, 50, 0.12)' : 'rgba(198, 244, 50, 0.35)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 3.5,
                        pointBackgroundColor: '#C6F432',
                        pointBorderColor: '#080B0C',
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, suggestedMax: 4, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } },
                },
            }));
        }
    }
    document.addEventListener('DOMContentLoaded', renderDashboardCharts);
    window.addEventListener('ct:theme', renderDashboardCharts);
</script>
@endpush
@endsection
