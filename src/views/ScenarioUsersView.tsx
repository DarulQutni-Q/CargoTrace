import React, { useState } from 'react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import { exceptionTypeLabel, roleLabel } from '../domain/rules';
import { useStore } from '../domain/store';
import { ExceptionType, ScenarioConfig, User, UserRole } from '../domain/types';

const PRESETS: ScenarioConfig[] = [
  {
    groupCode: 'DEFAULT',
    routeCorridor: 'Jakarta / Surabaya → Singapore → Rotterdam / Hamburg',
    lowThresholdHours: 6,
    highThresholdHours: 24,
    primaryException: ExceptionType.PortCongestion,
    kpiFocus: 'Average Delay Duration (hours)',
  },
  {
    groupCode: 'GROUP_A',
    routeCorridor: 'Surabaya → Singapore → Rotterdam',
    lowThresholdHours: 12,
    highThresholdHours: 24,
    primaryException: ExceptionType.PortCongestion,
    kpiFocus: 'Average Delay Duration (hours)',
  },
  {
    groupCode: 'GROUP_B',
    routeCorridor: 'Jakarta → Colombo → Hamburg',
    lowThresholdHours: 18,
    highThresholdHours: 36,
    primaryException: ExceptionType.CustomsHold,
    kpiFocus: 'On-Time Delivery Rate (%)',
  },
  {
    groupCode: 'GROUP_C',
    routeCorridor: 'Bangkok → Dubai → Antwerp',
    lowThresholdHours: 24,
    highThresholdHours: 48,
    primaryException: ExceptionType.WeatherDelay,
    kpiFocus: 'Shipments with Open Exceptions per Carrier',
  },
];

export const ScenarioUsersView: React.FC = () => {
  const { state, updateScenarioConfig, saveUser, resetDemoData } = useStore();
  const cfg = state.scenarioConfig;

  const [groupCode, setGroupCode] = useState<ScenarioConfig['groupCode']>(cfg.groupCode);
  const [corridor, setCorridor] = useState(cfg.routeCorridor);
  const [lowHours, setLowHours] = useState(String(cfg.lowThresholdHours));
  const [highHours, setHighHours] = useState(String(cfg.highThresholdHours));
  const [primaryExc, setPrimaryExc] = useState<ExceptionType>(cfg.primaryException);
  const [kpiFocus, setKpiFocus] = useState(cfg.kpiFocus);

  const [editingUser, setEditingUser] = useState<Partial<User> | null>(null);
  const [userErrors, setUserErrors] = useState<Record<string, string>>({});

  const applyPreset = (preset: ScenarioConfig) => {
    setGroupCode(preset.groupCode);
    setCorridor(preset.routeCorridor);
    setLowHours(String(preset.lowThresholdHours));
    setHighHours(String(preset.highThresholdHours));
    setPrimaryExc(preset.primaryException);
    setKpiFocus(preset.kpiFocus);
    updateScenarioConfig(preset);
  };

  const handleSaveScenario = (e: React.FormEvent) => {
    e.preventDefault();
    const low = Math.max(1, Number(lowHours) || 6);
    const high = Math.max(low + 1, Number(highHours) || 24);
    updateScenarioConfig({
      groupCode,
      routeCorridor: corridor.trim(),
      lowThresholdHours: low,
      highThresholdHours: high,
      primaryException: primaryExc,
      kpiFocus: kpiFocus.trim(),
    });
  };

  const handleSaveUser = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingUser) return;
    const res = saveUser({
      id: editingUser.id,
      name: editingUser.name || '',
      email: editingUser.email || '',
      role: editingUser.role || UserRole.Customer,
    });
    if (!res.ok) {
      setUserErrors(res.errors ?? {});
    } else {
      setUserErrors({});
      setEditingUser(null);
    }
  };

  return (
    <AppLayout
      title="Scenario & users"
      subtitle="Live delay threshold tuning and role-based access control"
    >
      <div className="mx-auto max-w-[1440px] space-y-5 p-3 sm:space-y-6 sm:p-6">
        {/* Group Scenario Configuration */}
        <section className="space-y-5 rounded-lg border border-line bg-surface p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 className="text-base font-semibold text-ink">
                Group scenario &amp; deterministic delay rule
              </h2>
              <p className="mt-1 max-w-3xl text-xs text-ink-2">
                Select a group preset or configure custom delay thresholds. Changes immediately
                recalculate delay classifications across all shipments and write an audit log entry.
              </p>
            </div>
            <button
              type="button"
              onClick={resetDemoData}
              className="h-8 rounded-md border border-line bg-raised px-3 text-xs font-medium text-ink hover:border-line-strong cursor-pointer"
            >
              Reset seed dataset
            </button>
          </div>

          <div className="grid grid-cols-1 divide-y divide-line overflow-hidden rounded-md border border-line bg-base sm:grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-y-0">
            {PRESETS.map((p) => {
              const active = cfg.groupCode === p.groupCode;
              return (
                <button
                  key={p.groupCode}
                  type="button"
                  onClick={() => applyPreset(p)}
                  className={`p-4 text-left transition-colors cursor-pointer ${
                    active ? 'bg-raised text-ink' : 'text-ink-2 hover:bg-surface'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="font-mono text-xs font-medium text-ink">
                      {p.groupCode}
                    </span>
                    {active && <Badge tone="green">Active</Badge>}
                  </div>
                  <div className="mt-1.5 font-mono text-xs text-ink tabular-nums">
                    Low ≤ {p.lowThresholdHours}h · Med ≤ {p.highThresholdHours}h
                  </div>
                  <div className="mt-1 truncate text-xs text-ink-3">{p.routeCorridor}</div>
                  <div className="mt-1 text-xs text-ink-2">
                    {exceptionTypeLabel(p.primaryException)}
                  </div>
                </button>
              );
            })}
          </div>

          <form
            onSubmit={handleSaveScenario}
            className="grid grid-cols-1 gap-4 border-t border-line pt-4 md:grid-cols-3"
          >
            <div>
              <label className="mb-1 block text-xs text-ink-2">
                Low threshold (hours)
              </label>
              <input
                type="number"
                min="1"
                value={lowHours}
                onChange={(e) => {
                  setGroupCode('CUSTOM');
                  setLowHours(e.target.value);
                }}
                className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs text-ink"
              />
            </div>

            <div>
              <label className="mb-1 block text-xs text-ink-2">
                Medium / high threshold (hours)
              </label>
              <input
                type="number"
                min="2"
                value={highHours}
                onChange={(e) => {
                  setGroupCode('CUSTOM');
                  setHighHours(e.target.value);
                }}
                className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs text-ink"
              />
            </div>

            <div>
              <label className="mb-1 block text-xs text-ink-2">
                Primary exception type
              </label>
              <select
                value={primaryExc}
                onChange={(e) => setPrimaryExc(e.target.value as ExceptionType)}
                className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
              >
                {Object.values(ExceptionType).map((ex) => (
                  <option key={ex} value={ex}>
                    {exceptionTypeLabel(ex)}
                  </option>
                ))}
              </select>
            </div>

            <div className="md:col-span-2">
              <label className="mb-1 block text-xs text-ink-2">
                Assigned route corridor
              </label>
              <input
                type="text"
                value={corridor}
                onChange={(e) => setCorridor(e.target.value)}
                className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
              />
            </div>

            <div className="flex items-end justify-end">
              <button
                type="submit"
                className="h-9 rounded-md bg-brand px-4 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
              >
                Save scenario rule
              </button>
            </div>
          </form>
        </section>

        {/* User & Role Management */}
        <section className="space-y-4 rounded-lg border border-line bg-surface p-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-base font-semibold text-ink">
                User accounts &amp; role access
              </h3>
              <p className="mt-0.5 text-xs text-ink-3">
                Administrator, logistics operator, and customer accounts
              </p>
            </div>
            <button
              type="button"
              onClick={() =>
                setEditingUser({ name: '', email: '', role: UserRole.Customer })
              }
              className="h-8 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
            >
              Add user
            </button>
          </div>

          {editingUser && (
            <form
              onSubmit={handleSaveUser}
              className="grid grid-cols-1 gap-3 border-t border-b border-line py-4 sm:grid-cols-4"
            >
              <div>
                <label className="mb-1 block text-xs text-ink-2">Name *</label>
                <input
                  type="text"
                  value={editingUser.name || ''}
                  onChange={(e) => setEditingUser({ ...editingUser, name: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
                />
                {userErrors.name && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {userErrors.name}
                  </p>
                )}
              </div>
              <div>
                <label className="mb-1 block text-xs text-ink-2">Email *</label>
                <input
                  type="email"
                  value={editingUser.email || ''}
                  onChange={(e) => setEditingUser({ ...editingUser, email: e.target.value })}
                  className="h-8 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
                />
                {userErrors.email && (
                  <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                    {userErrors.email}
                  </p>
                )}
              </div>
              <div>
                <label className="mb-1 block text-xs text-ink-2">Role *</label>
                <select
                  value={editingUser.role || UserRole.Customer}
                  onChange={(e) =>
                    setEditingUser({ ...editingUser, role: e.target.value as UserRole })
                  }
                  className="h-8 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
                >
                  <option value={UserRole.Admin}>Administrator</option>
                  <option value={UserRole.Operator}>Logistics Operator</option>
                  <option value={UserRole.Customer}>Customer</option>
                </select>
              </div>
              <div className="flex items-end gap-2">
                <button
                  type="submit"
                  className="h-8 rounded-md bg-brand px-3.5 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
                >
                  Save
                </button>
                <button
                  type="button"
                  onClick={() => setEditingUser(null)}
                  className="h-8 rounded-md border border-line bg-raised px-3 text-xs text-ink-2 cursor-pointer"
                >
                  Cancel
                </button>
              </div>
            </form>
          )}

          <div className="overflow-x-auto">
            <table className="w-full border-collapse text-left text-sm">
              <thead>
                <tr className="border-b border-line bg-base text-xs text-ink-3">
                  <th className="px-4 py-3 font-medium">ID</th>
                  <th className="px-4 py-3 font-medium">Name</th>
                  <th className="px-4 py-3 font-medium">Email</th>
                  <th className="px-4 py-3 font-medium">Role</th>
                  <th className="px-4 py-3 text-right font-medium">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {state.users.map((u) => (
                  <tr key={u.id} className="hover:bg-raised">
                    <td className="px-4 py-3 font-mono text-xs text-ink-3">#{u.id}</td>
                    <td className="px-4 py-3 text-xs font-medium text-ink">{u.name}</td>
                    <td className="px-4 py-3 font-mono text-xs text-ink-2">{u.email}</td>
                    <td className="px-4 py-3">
                      <Badge
                        tone={
                          u.role === UserRole.Admin
                            ? 'amber'
                            : u.role === UserRole.Operator
                            ? 'blue'
                            : 'green'
                        }
                      >
                        {roleLabel(u.role)}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        onClick={() => setEditingUser(u)}
                        className="text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
                      >
                        Edit role
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </AppLayout>
  );
};
