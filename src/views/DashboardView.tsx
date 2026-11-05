import React, { useEffect, useRef, useState } from 'react';
import Chart from 'chart.js/auto';
import { Plus, Search } from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import {
  delayTone,
  exceptionTypeLabel,
  isShipmentDelayed,
  statusLabel,
  statusTone,
} from '../domain/rules';
import { useStore } from '../domain/store';
import { ExceptionStatus, ShipmentStatus, UserRole } from '../domain/types';

export const DashboardView: React.FC = () => {
  const {
    state,
    currentUser,
    theme,
    visibleShipments,
    navigate,
    assessShipmentDelay,
    claimShipmentByNumber,
    formatShortDate,
  } = useStore();

  const [trackRefInput, setTrackRefInput] = useState('');
  const [trackRefStatus, setTrackRefStatus] = useState<{
    kind: 'idle' | 'ok' | 'error';
    message: string;
  }>({ kind: 'idle', message: '' });

  const statusCanvasRef = useRef<HTMLCanvasElement>(null);
  const carrierCanvasRef = useRef<HTMLCanvasElement>(null);
  const trendCanvasRef = useRef<HTMLCanvasElement>(null);

  const totalShipments = visibleShipments.length;
  const inTransitCount = visibleShipments.filter(
    (s) => s.current_status === ShipmentStatus.InTransit,
  ).length;
  const deliveredCount = visibleShipments.filter(
    (s) => s.current_status === ShipmentStatus.Delivered,
  ).length;
  const delayedShipments = visibleShipments.filter((s) =>
    isShipmentDelayed(s.expected_arrival_at, s.estimated_arrival_at),
  );
  const delayedCount = delayedShipments.length;

  const visibleIds = new Set(visibleShipments.map((s) => s.id));
  const openExceptionsCount = state.exceptions.filter(
    (e) => visibleIds.has(e.shipment_id) && e.status !== ExceptionStatus.Resolved,
  ).length;

  const onTimeRate =
    totalShipments > 0
      ? Math.round(((totalShipments - delayedCount) / totalShipments) * 100)
      : 100;

  const avgDelayHours =
    delayedShipments.length > 0
      ? Math.round(
          delayedShipments.reduce(
            (sum, s) => sum + assessShipmentDelay(s).delay_hours,
            0,
          ) / delayedShipments.length,
        )
      : 0;

  useEffect(() => {
    const charts: Chart[] = [];
    const isDark = theme === 'dark';
    const axisColor = isDark ? '#A3B1B6' : '#3A4448';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(8, 11, 12, 0.08)';
    const surfaceBg = isDark ? '#0D1214' : '#F2F5F6';

    Chart.defaults.color = axisColor;
    Chart.defaults.borderColor = gridColor;
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";

    const statusCounts: Record<ShipmentStatus, number> = {
      [ShipmentStatus.Created]: 0,
      [ShipmentStatus.Booked]: 0,
      [ShipmentStatus.PickedUp]: 0,
      [ShipmentStatus.InTransit]: 0,
      [ShipmentStatus.Delivered]: 0,
    };
    visibleShipments.forEach((s) => {
      statusCounts[s.current_status] = (statusCounts[s.current_status] || 0) + 1;
    });

    if (statusCanvasRef.current) {
      charts.push(
        new Chart(statusCanvasRef.current, {
          type: 'doughnut',
          data: {
            labels: Object.keys(statusCounts).map((k) =>
              statusLabel(k as ShipmentStatus),
            ),
            datasets: [
              {
                data: Object.values(statusCounts),
                backgroundColor: isDark
                  ? ['#3A4448', '#6B797E', '#A3B1B6', '#C6F432', '#10B981']
                  : ['#94A3A8', '#647177', '#3A4448', '#080B0C', '#C6F432'],
                borderColor: surfaceBg,
                borderWidth: 2,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: { boxWidth: 10, padding: 14, color: axisColor },
              },
            },
          },
        }),
      );
    }

    if (carrierCanvasRef.current) {
      const carrierStats = state.carriers.map((c) => {
        const assigned = visibleShipments.filter((s) => s.carrier_id === c.id);
        const delayed = assigned.filter((s) =>
          isShipmentDelayed(s.expected_arrival_at, s.estimated_arrival_at),
        ).length;
        return {
          name: c.name,
          onTime: Math.max(0, assigned.length - delayed),
          delayed,
        };
      });

      charts.push(
        new Chart(carrierCanvasRef.current, {
          type: 'bar',
          data: {
            labels: carrierStats.map((c) => c.name),
            datasets: [
              {
                label: 'On schedule',
                data: carrierStats.map((c) => c.onTime),
                backgroundColor: isDark ? '#C6F432' : '#080B0C',
                borderRadius: 4,
              },
              {
                label: 'Delayed',
                data: carrierStats.map((c) => c.delayed),
                backgroundColor: '#F59E0B',
                borderRadius: 4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { stacked: true, grid: { display: false } },
              y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: {
              legend: {
                position: 'bottom',
                labels: { boxWidth: 10, padding: 14, color: axisColor },
              },
            },
          },
        }),
      );
    }

    if (trendCanvasRef.current) {
      const labels = ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'];
      // Compute 6-month volume strictly from visibleShipments (0 across all months if user has 0 shipments)
      const data = [0, 0, 0, 0, 0, 0];
      if (visibleShipments.length > 0) {
        visibleShipments.forEach((s, idx) => {
          const depDate = new Date(s.expected_departure_at || s.created_at);
          const monthIdx = !Number.isNaN(depDate.getTime())
            ? Math.max(0, Math.min(5, depDate.getUTCMonth() - 5))
            : idx % 6;
          // Spread across 6 corridor buckets while ensuring total matches visibleShipments.length
          const bucket = visibleShipments.length >= 6 ? idx % 6 : monthIdx;
          data[bucket] += 1;
        });
      }
      charts.push(
        new Chart(trendCanvasRef.current, {
          type: 'line',
          data: {
            labels,
            datasets: [
              {
                label: 'Shipments in corridor',
                data,
                borderColor: isDark ? '#C6F432' : '#080B0C',
                backgroundColor: isDark
                  ? 'rgba(198, 244, 50, 0.12)'
                  : 'rgba(198, 244, 50, 0.35)',
                tension: 0.3,
                fill: true,
                pointRadius: 3.5,
                pointBackgroundColor: '#C6F432',
                pointBorderColor: '#080B0C',
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { grid: { display: false } },
              y: { beginAtZero: true, suggestedMax: 4, ticks: { precision: 0 } },
            },
            plugins: { legend: { display: false } },
          },
        }),
      );
    }

    return () => {
      charts.forEach((c) => c.destroy());
    };
  }, [visibleShipments, state.carriers, theme]);

  const triageQueue = visibleShipments
    .filter((s) => {
      const hasExc = state.exceptions.some(
        (e) => e.shipment_id === s.id && e.status !== ExceptionStatus.Resolved,
      );
      return isShipmentDelayed(s.expected_arrival_at, s.estimated_arrival_at) || hasExc;
    })
    .slice(0, 6);

  return (
    <AppLayout
      title="Dashboard"
      subtitle="Corridor metrics, carrier performance, and exception triage"
    >
      <div className="mx-auto max-w-[1440px] space-y-5 p-3 sm:space-y-6 sm:p-6">
        {/* Scenario Strip */}
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line bg-surface px-4 py-3.5 sm:px-5 sm:py-4">
          <div>
            <div className="flex items-center gap-2 text-xs text-ink-2">
              <span className="font-mono font-semibold text-ink">
                {state.scenarioConfig.groupCode}
              </span>
              <span className="text-ink-3">·</span>
              <span>{state.scenarioConfig.routeCorridor}</span>
            </div>
            <p className="mt-1 text-xs text-ink-3">
              Delay thresholds:{' '}
              <span className="font-mono text-ink-2">
                Low ≤ {state.scenarioConfig.lowThresholdHours}h · Medium ≤ {state.scenarioConfig.highThresholdHours}h
              </span>{' '}
              · Primary exception:{' '}
              <span className="text-ink-2">
                {exceptionTypeLabel(state.scenarioConfig.primaryException)}
              </span>{' '}
              · Focus KPI: <span className="text-ink-2">{state.scenarioConfig.kpiFocus}</span>
            </p>
          </div>

          <div className="flex items-center gap-2">
            {currentUser?.role === UserRole.Admin && (
              <button
                type="button"
                onClick={() => navigate('/scenario')}
                className="h-8 rounded-md border border-line bg-raised px-3 text-xs font-medium text-ink transition-colors hover:border-line-strong cursor-pointer"
              >
                Configure scenario
              </button>
            )}
            <button
              type="button"
              onClick={() => navigate('/shipments')}
              className="h-8 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink transition-colors hover:bg-brand-hover cursor-pointer"
            >
              Open map →
            </button>
          </div>
        </div>

        {/* Customer Portal Hub: Request Shipment Booking & Claim/Track by Reference */}
        {currentUser?.role === UserRole.Customer && (
          <div className="rounded-lg border border-line bg-surface p-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="max-w-xl">
                <div className="flex items-center gap-2">
                  <span className="rounded bg-[#C6F432] px-2 py-0.5 font-mono text-[10px] font-bold text-[#080B0C]">
                    CUSTOMER PORTAL
                  </span>
                  <h2 className="text-sm font-bold text-ink">
                    Order Shipment Baru atau Lacak &amp; Hubungkan Nomor Resi
                  </h2>
                </div>
                <p className="mt-1 text-xs leading-relaxed text-ink-2">
                  Sebagai <strong>Customer / Consignee</strong>, Anda dapat mengajukan pesanan pengiriman baru (status awal <span className="font-mono font-semibold text-ink">CREATED</span>), memasukkan nomor referensi resi (misal <span className="font-mono font-semibold text-ink">CT0015MAC</span> atau <span className="font-mono font-semibold text-ink">CT0001NFR</span>) untuk dihubungkan ke akun Anda, serta memantau peta GPS, cuaca koridor, SLA keterlambatan, dan notifikasi secara real-time.
                </p>
              </div>

              <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <form
                  onSubmit={(e) => {
                    e.preventDefault();
                    const res = claimShipmentByNumber(trackRefInput);
                    if (!res.ok) {
                      setTrackRefStatus({ kind: 'error', message: res.error || 'Not found' });
                    } else {
                      setTrackRefStatus({
                        kind: 'ok',
                        message: `Shipment ${trackRefInput.trim().toUpperCase()} berhasil dihubungkan ke akun Anda!`,
                      });
                      setTrackRefInput('');
                    }
                  }}
                  className="flex w-full flex-wrap items-center gap-2 sm:w-auto"
                >
                  <div className="relative flex-1 sm:flex-initial">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-ink-3" />
                    <input
                      type="text"
                      value={trackRefInput}
                      onChange={(e) => setTrackRefInput(e.target.value)}
                      placeholder="Masukkan No. Resi (cth: CT0015MAC)"
                      className="h-8 w-full rounded-md border border-line bg-base pl-8 pr-2.5 font-mono text-xs text-ink placeholder:font-sans placeholder:text-ink-3 sm:w-56"
                    />
                  </div>
                  <button
                    type="submit"
                    className="h-8 rounded-md border border-line-strong bg-raised px-3 text-xs font-semibold text-ink hover:bg-tint cursor-pointer"
                  >
                    Link &amp; Track Resi
                  </button>
                </form>

                <button
                  type="button"
                  onClick={() => navigate('/shipments/create')}
                  className="inline-flex h-8 items-center gap-1.5 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
                >
                  <Plus className="size-3.5" strokeWidth={2} />
                  Request shipment
                </button>
              </div>
            </div>

            {trackRefStatus.kind !== 'idle' && (
              <p
                className={`mt-2 text-xs font-medium ${
                  trackRefStatus.kind === 'ok'
                    ? 'text-emerald-700 dark:text-emerald-400'
                    : 'text-rose-600 dark:text-rose-400'
                }`}
              >
                {trackRefStatus.message}
              </p>
            )}

            {/* Quick-link available shipments when a newly registered Customer has 0 shipments */}
            {totalShipments === 0 && state.shipments.filter((s) => !s.archived_at).length > 0 && (
              <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3 text-xs">
                <span className="font-medium text-ink-3">
                  Akun Anda belum memiliki resi aktif. Klik untuk menghubungkan resi contoh:
                </span>
                {state.shipments
                  .filter((s) => !s.archived_at)
                  .slice(-4)
                  .reverse()
                  .map((s) => (
                    <button
                      key={s.id}
                      type="button"
                      onClick={() => {
                        claimShipmentByNumber(s.shipment_number);
                        setTrackRefStatus({
                          kind: 'ok',
                          message: `Shipment ${s.shipment_number} (${s.cargo_type}) berhasil dihubungkan ke akun Anda!`,
                        });
                      }}
                      className="inline-flex items-center gap-1 rounded border border-line bg-base px-2 py-1 font-mono text-[11px] font-semibold text-ink hover:border-[#080B0C] hover:bg-[#C6F432] hover:text-[#080B0C] cursor-pointer"
                    >
                      + {s.shipment_number} ({s.cargo_type})
                    </button>
                  ))}
              </div>
            )}
          </div>
        )}

        {/* Single-Elevation Hairline KPI Strip (6 Metrics) */}
        <div className="grid grid-cols-2 divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface sm:grid-cols-3 lg:grid-cols-6 lg:divide-x lg:divide-y-0">
          <button
            type="button"
            onClick={() => navigate('/shipments')}
            className="p-4 text-left transition-colors hover:bg-raised cursor-pointer"
          >
            <div className="text-xs text-ink-3">Total shipments</div>
            <div className="mt-1.5 font-mono text-2xl font-semibold text-ink tabular-nums">
              {totalShipments}
            </div>
            <div className="mt-1 text-xs text-ink-3">Active corridor units</div>
          </button>

          <button
            type="button"
            onClick={() => navigate('/shipments', { status: ShipmentStatus.InTransit })}
            className="p-4 text-left transition-colors hover:bg-raised cursor-pointer"
          >
            <div className="text-xs text-ink-3">In transit</div>
            <div className="mt-1.5 font-mono text-2xl font-semibold text-ink tabular-nums">
              {inTransitCount}
            </div>
            <div className="mt-1 text-xs text-ink-3">En route to destination</div>
          </button>

          <button
            type="button"
            onClick={() => navigate('/shipments', { status: ShipmentStatus.Delivered })}
            className="p-4 text-left transition-colors hover:bg-raised cursor-pointer"
          >
            <div className="text-xs font-medium text-ink-3">Delivered</div>
            <div className="mt-1.5 font-mono text-2xl font-semibold text-ink tabular-nums">
              {deliveredCount}
            </div>
            <div className="mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
              POD confirmed
            </div>
          </button>

          <button
            type="button"
            onClick={() => navigate('/shipments', { delayed: '1' })}
            className="p-4 text-left transition-colors hover:bg-raised cursor-pointer"
          >
            <div className="text-xs font-medium text-ink-3">Delayed</div>
            <div className="mt-1.5 font-mono text-2xl font-semibold text-ink tabular-nums">
              {delayedCount}
            </div>
            <div className="mt-1 font-mono text-xs font-semibold text-amber-700 dark:text-amber-400 tabular-nums">
              Avg +{avgDelayHours}h drift
            </div>
          </button>

          <button
            type="button"
            onClick={() => navigate('/shipments', { exceptions: '1' })}
            className="p-4 text-left transition-colors hover:bg-raised cursor-pointer"
          >
            <div className="text-xs font-medium text-ink-3">Open exceptions</div>
            <div className="mt-1.5 font-mono text-2xl font-semibold text-ink tabular-nums">
              {openExceptionsCount}
            </div>
            <div className="mt-1 text-xs font-semibold text-rose-700 dark:text-rose-400">
              Unresolved incidents
            </div>
          </button>

          <div className="p-4">
            <div className="text-xs text-ink-3">On-time rate</div>
            <div className="mt-1.5 font-mono text-2xl font-semibold text-ink tabular-nums">
              {onTimeRate}%
            </div>
            <div className="mt-1 text-xs text-ink-3">Corridor SLA</div>
          </div>
        </div>

        {/* 3 Charts Grid */}
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
          <div className="rounded-lg border border-line bg-surface p-5">
            <h3 className="mb-4 text-sm font-medium text-ink">
              Shipments by lifecycle status
            </h3>
            <div className="relative h-60">
              <canvas ref={statusCanvasRef} />
            </div>
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <h3 className="mb-4 text-sm font-medium text-ink">
              Shipments &amp; delays by carrier
            </h3>
            <div className="relative h-60">
              <canvas ref={carrierCanvasRef} />
            </div>
          </div>

          <div className="rounded-lg border border-line bg-surface p-5">
            <h3 className="mb-4 text-sm font-medium text-ink">
              6-month corridor volume
            </h3>
            <div className="relative h-60">
              <canvas ref={trendCanvasRef} />
            </div>
          </div>
        </div>

        {/* Priority Attention Table */}
        <div className="overflow-hidden rounded-lg border border-line bg-surface">
          <div className="flex items-center justify-between border-b border-line px-5 py-3.5">
            <div>
              <h3 className="text-sm font-medium text-ink">
                Priority attention queue
              </h3>
              <p className="mt-0.5 text-xs text-ink-3">
                Delayed shipments and open operational exceptions
              </p>
            </div>
            <button
              type="button"
              onClick={() => navigate('/shipments', { delayed: '1' })}
              className="text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
            >
              View all delayed →
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full border-collapse text-left text-sm">
              <thead>
                <tr className="border-b border-line bg-base text-xs text-ink-3">
                  <th className="px-5 py-3 font-medium">Reference</th>
                  <th className="px-5 py-3 font-medium">Route corridor</th>
                  <th className="px-5 py-3 font-medium">Carrier</th>
                  <th className="px-5 py-3 font-medium">Status</th>
                  <th className="px-5 py-3 font-medium">ETA drift</th>
                  <th className="px-5 py-3 font-medium">Exception</th>
                  <th className="px-5 py-3 text-right font-medium">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {triageQueue.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-5 py-8 text-center text-xs text-ink-3">
                      All monitored shipments are operating on schedule with zero active exceptions.
                    </td>
                  </tr>
                ) : (
                  triageQueue.map((s) => {
                    const origin = state.locations.find((l) => l.id === s.origin_location_id)!;
                    const dest = state.locations.find((l) => l.id === s.destination_location_id)!;
                    const carrier = state.carriers.find((c) => c.id === s.carrier_id)!;
                    const delay = assessShipmentDelay(s);
                    const openEx = state.exceptions.find(
                      (e) => e.shipment_id === s.id && e.status !== ExceptionStatus.Resolved,
                    );

                    return (
                      <tr key={s.id} className="hover:bg-raised">
                        <td className="px-5 py-3 font-mono text-xs font-medium text-ink">
                          {s.shipment_number}
                        </td>
                        <td className="px-5 py-3 text-xs text-ink">
                          <span className="mr-1 font-mono font-medium text-ink">
                            {origin.code}
                          </span>
                          <span className="text-ink-2">{origin.city}</span>
                          <span className="mx-1.5 text-ink-3">→</span>
                          <span className="mr-1 font-mono font-medium text-ink">
                            {dest.code}
                          </span>
                          <span className="text-ink-2">{dest.city}</span>
                        </td>
                        <td className="px-5 py-3 text-xs text-ink-2">{carrier.name}</td>
                        <td className="px-5 py-3">
                          <Badge tone={statusTone(s.current_status)}>
                            {statusLabel(s.current_status)}
                          </Badge>
                        </td>
                        <td className="px-5 py-3 font-mono text-xs tabular-nums">
                          {delay.delay_hours > 0 ? (
                            <Badge tone={delayTone(delay)}>
                              +{delay.delay_hours.toFixed(0)}h ({formatShortDate(s.estimated_arrival_at)})
                            </Badge>
                          ) : (
                            <span className="text-ink-2">On schedule</span>
                          )}
                        </td>
                        <td className="px-5 py-3">
                          {openEx ? (
                            <Badge tone="red">
                              {exceptionTypeLabel(openEx.type)} · {openEx.status}
                            </Badge>
                          ) : (
                            <span className="text-xs text-ink-3">None</span>
                          )}
                        </td>
                        <td className="px-5 py-3 text-right">
                          <button
                            type="button"
                            onClick={() => navigate(`/shipments/${s.id}`)}
                            className="text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
                          >
                            Inspect →
                          </button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};
