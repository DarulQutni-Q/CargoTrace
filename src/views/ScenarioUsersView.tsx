import React, { useState } from 'react';
import { Database, Download, Key, Layers } from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import { exceptionTypeLabel, roleLabel } from '../domain/rules';
import { useStore } from '../domain/store';
import { ExceptionType, ScenarioConfig, User, UserRole } from '../domain/types';

const PRESETS: ScenarioConfig[] = [
  {
    groupCode: 'DEFAULT',
    routeCorridor: 'Southeast Asia → Europe',
    lowThresholdHours: 6,
    highThresholdHours: 24,
    primaryException: ExceptionType.CustomsHold,
    kpiFocus: 'On-Time Delivery & Exception Rate',
  },
  {
    groupCode: 'GROUP_A',
    routeCorridor: 'Southeast Asia → Europe',
    lowThresholdHours: 12,
    highThresholdHours: 24,
    primaryException: ExceptionType.CustomsHold,
    kpiFocus: 'On-Time Delivery Rate (%)',
  },
  {
    groupCode: 'GROUP_B',
    routeCorridor: 'Asia → North America',
    lowThresholdHours: 18,
    highThresholdHours: 36,
    primaryException: ExceptionType.WeatherDelay,
    kpiFocus: 'Average Delay Duration (hours)',
  },
  {
    groupCode: 'GROUP_C',
    routeCorridor: 'Southeast Asia → Australia',
    lowThresholdHours: 24,
    highThresholdHours: 48,
    primaryException: ExceptionType.PortCongestion,
    kpiFocus: 'Exception Resolution Time',
  },
];

type TableKey =
  | 'users'
  | 'shipments'
  | 'shipment_items'
  | 'carriers'
  | 'locations'
  | 'tracking_events'
  | 'shipment_exceptions'
  | 'notifications'
  | 'audit_logs';

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
  const [activeTable, setActiveTable] = useState<TableKey>('shipments');

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

  const sqlEsc = (val: unknown): string => {
    if (val === null || val === undefined) return 'NULL';
    if (typeof val === 'number') return String(val);
    if (typeof val === 'boolean') return val ? '1' : '0';
    return `'${String(val).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
  };

  const handleDownloadMysqlDump = () => {
    const lines: string[] = [
      '-- ============================================================================',
      '-- CargoTrace · MySQL 8.0+ Live Relational Database Dump (9 Core Tables)',
      `-- Generated at: ${new Date().toISOString()}`,
      '-- Engine: InnoDB · Charset: utf8mb4_unicode_ci',
      '-- ============================================================================',
      'CREATE DATABASE IF NOT EXISTS `cargotrace` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
      'USE `cargotrace`;',
      'SET FOREIGN_KEY_CHECKS = 0;',
      '',
    ];

    state.users.forEach((u) => {
      lines.push(
        `INSERT INTO \`users\` (\`id\`, \`name\`, \`email\`, \`password\`, \`role\`) VALUES (${u.id}, ${sqlEsc(u.name)}, ${sqlEsc(u.email)}, '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', ${sqlEsc(u.role)}) ON DUPLICATE KEY UPDATE \`name\`=VALUES(\`name\`), \`role\`=VALUES(\`role\`);`,
      );
    });
    lines.push('');

    state.carriers.forEach((c) => {
      lines.push(
        `INSERT INTO \`carriers\` (\`id\`, \`name\`, \`code\`, \`service_type\`, \`contact_email\`, \`is_active\`) VALUES (${c.id}, ${sqlEsc(c.name)}, ${sqlEsc(c.code)}, ${sqlEsc(c.service_type)}, ${sqlEsc(c.contact_email)}, ${c.is_active ? 1 : 0}) ON DUPLICATE KEY UPDATE \`name\`=VALUES(\`name\`), \`is_active\`=VALUES(\`is_active\`);`,
      );
    });
    lines.push('');

    state.locations.forEach((l) => {
      lines.push(
        `INSERT INTO \`locations\` (\`id\`, \`name\`, \`code\`, \`city\`, \`province\`, \`country\`, \`latitude\`, \`longitude\`) VALUES (${l.id}, ${sqlEsc(l.name)}, ${sqlEsc(l.code)}, ${sqlEsc(l.city)}, ${sqlEsc(l.province ?? null)}, ${sqlEsc(l.country)}, ${l.latitude}, ${l.longitude}) ON DUPLICATE KEY UPDATE \`name\`=VALUES(\`name\`), \`latitude\`=VALUES(\`latitude\`), \`longitude\`=VALUES(\`longitude\`);`,
      );
    });
    lines.push('');

    state.shipments.forEach((s) => {
      lines.push(
        `INSERT INTO \`shipments\` (\`id\`, \`shipment_number\`, \`customer_id\`, \`carrier_id\`, \`origin_location_id\`, \`destination_location_id\`, \`current_status\`, \`cargo_type\`, \`weight_kg\`, \`volume_m3\`, \`expected_departure_at\`, \`expected_arrival_at\`, \`estimated_arrival_at\`, \`archived_at\`) VALUES (${s.id}, ${sqlEsc(s.shipment_number)}, ${s.customer_id}, ${s.carrier_id}, ${s.origin_location_id}, ${s.destination_location_id}, ${sqlEsc(s.current_status)}, ${sqlEsc(s.cargo_type)}, ${s.weight_kg}, ${s.volume_m3}, ${sqlEsc(s.expected_departure_at)}, ${sqlEsc(s.expected_arrival_at)}, ${sqlEsc(s.estimated_arrival_at)}, ${sqlEsc(s.archived_at)}) ON DUPLICATE KEY UPDATE \`current_status\`=VALUES(\`current_status\`), \`estimated_arrival_at\`=VALUES(\`estimated_arrival_at\`);`,
      );
    });
    lines.push('');

    (state.items ?? []).forEach((item) => {
      lines.push(
        `INSERT INTO \`shipment_items\` (\`id\`, \`shipment_id\`, \`description\`, \`quantity\`, \`weight_kg\`, \`volume_m3\`) VALUES (${item.id}, ${item.shipment_id}, ${sqlEsc(item.description)}, ${item.quantity}, ${item.weight_kg}, ${item.volume_m3}) ON DUPLICATE KEY UPDATE \`quantity\`=VALUES(\`quantity\`);`,
      );
    });
    lines.push('');

    state.trackingEvents.forEach((ev) => {
      lines.push(
        `INSERT INTO \`tracking_events\` (\`id\`, \`shipment_id\`, \`event_type\`, \`occurred_at\`, \`location_name\`, \`latitude\`, \`longitude\`, \`description\`, \`created_by\`) VALUES (${ev.id}, ${ev.shipment_id}, ${sqlEsc(ev.event_type)}, ${sqlEsc(ev.occurred_at)}, ${sqlEsc(ev.location_name)}, ${ev.latitude ?? 'NULL'}, ${ev.longitude ?? 'NULL'}, ${sqlEsc(ev.description)}, ${ev.created_by ?? 'NULL'}) ON DUPLICATE KEY UPDATE \`description\`=VALUES(\`description\`);`,
      );
    });
    lines.push('');

    state.exceptions.forEach((ex) => {
      lines.push(
        `INSERT INTO \`shipment_exceptions\` (\`id\`, \`shipment_id\`, \`type\`, \`severity\`, \`status\`, \`detected_at\`, \`resolved_at\`, \`description\`) VALUES (${ex.id}, ${ex.shipment_id}, ${sqlEsc(ex.type)}, ${sqlEsc(ex.severity)}, ${sqlEsc(ex.status)}, ${sqlEsc(ex.detected_at)}, ${sqlEsc(ex.resolved_at)}, ${sqlEsc(ex.description)}) ON DUPLICATE KEY UPDATE \`status\`=VALUES(\`status\`), \`resolved_at\`=VALUES(\`resolved_at\`);`,
      );
    });
    lines.push('');

    state.notifications.forEach((n) => {
      lines.push(
        `INSERT INTO \`notifications\` (\`id\`, \`user_id\`, \`shipment_id\`, \`type\`, \`title\`, \`body\`, \`read_at\`) VALUES (${n.id}, ${n.user_id}, ${n.shipment_id ?? 'NULL'}, ${sqlEsc(n.type)}, ${sqlEsc(n.title)}, ${sqlEsc(n.body)}, ${sqlEsc(n.read_at)}) ON DUPLICATE KEY UPDATE \`read_at\`=VALUES(\`read_at\`);`,
      );
    });
    lines.push('');

    state.auditLogs.forEach((a) => {
      lines.push(
        `INSERT INTO \`audit_logs\` (\`id\`, \`actor_id\`, \`action\`, \`entity_type\`, \`entity_id\`, \`changes\`, \`created_at\`) VALUES (${a.id}, ${a.actor_id ?? 'NULL'}, ${sqlEsc(a.action)}, ${sqlEsc(a.entity_type)}, ${a.entity_id}, ${sqlEsc(JSON.stringify(a.changes))}, ${sqlEsc(a.created_at)}) ON DUPLICATE KEY UPDATE \`action\`=VALUES(\`action\`);`,
      );
    });

    lines.push('SET FOREIGN_KEY_CHECKS = 1;');

    const blob = new Blob([lines.join('\n')], { type: 'application/sql;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'cargotrace_mysql_dump.sql';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  const mysqlTables: {
    key: TableKey;
    label: string;
    eloquentModel: string;
    count: number;
    pk: string;
    fks: string;
    cardinality: string;
    constraints: string;
  }[] = [
    {
      key: 'users',
      label: 'users',
      eloquentModel: 'App\\Models\\User',
      count: state.users.length,
      pk: 'id (BIGINT PK)',
      fks: '—',
      cardinality: '1:N → shipments, tracking_events, notifications, audit_logs',
      constraints: 'UNIQUE(email), INDEX(role)',
    },
    {
      key: 'shipments',
      label: 'shipments',
      eloquentModel: 'App\\Models\\Shipment',
      count: state.shipments.length,
      pk: 'id (BIGINT PK)',
      fks: 'customer_id, carrier_id, origin_location_id, destination_location_id (RESTRICT)',
      cardinality: 'belongsTo(User, Carrier, Location×2) · hasMany(ShipmentItem, TrackingEvent, ShipmentException)',
      constraints: 'UNIQUE(shipment_number), INDEX(customer_id, current_status), SoftDeletes',
    },
    {
      key: 'shipment_items',
      label: 'shipment_items',
      eloquentModel: 'App\\Models\\ShipmentItem',
      count: (state.items ?? []).length,
      pk: 'id (BIGINT PK)',
      fks: 'shipment_id → shipments.id (CASCADE)',
      cardinality: 'N:1 belongsTo(Shipment)',
      constraints: 'INDEX(shipment_id)',
    },
    {
      key: 'carriers',
      label: 'carriers',
      eloquentModel: 'App\\Models\\Carrier',
      count: state.carriers.length,
      pk: 'id (BIGINT PK)',
      fks: '—',
      cardinality: '1:N hasMany(Shipment)',
      constraints: 'UNIQUE(code), INDEX(is_active)',
    },
    {
      key: 'locations',
      label: 'locations',
      eloquentModel: 'App\\Models\\Location',
      count: state.locations.length,
      pk: 'id (BIGINT PK)',
      fks: '—',
      cardinality: '1:N hasMany(Shipment as origin & destination)',
      constraints: 'UNIQUE(code), INDEX(city, province, country)',
    },
    {
      key: 'tracking_events',
      label: 'tracking_events',
      eloquentModel: 'App\\Models\\TrackingEvent',
      count: state.trackingEvents.length,
      pk: 'id (BIGINT PK)',
      fks: 'shipment_id (CASCADE), created_by → users.id (SET NULL)',
      cardinality: 'N:1 belongsTo(Shipment, User) · Append-only chronological history',
      constraints: 'INDEX(shipment_id, occurred_at), INDEX(event_type)',
    },
    {
      key: 'shipment_exceptions',
      label: 'shipment_exceptions',
      eloquentModel: 'App\\Models\\ShipmentException',
      count: state.exceptions.length,
      pk: 'id (BIGINT PK)',
      fks: 'shipment_id → shipments.id (CASCADE)',
      cardinality: 'N:1 belongsTo(Shipment) · OPEN → INVESTIGATING → RESOLVED',
      constraints: 'INDEX(shipment_id, status), INDEX(type)',
    },
    {
      key: 'notifications',
      label: 'notifications',
      eloquentModel: 'App\\Models\\Notification',
      count: state.notifications.length,
      pk: 'id (BIGINT PK)',
      fks: 'user_id (CASCADE), shipment_id (CASCADE)',
      cardinality: 'N:1 belongsTo(User, Shipment)',
      constraints: 'INDEX(user_id, read_at)',
    },
    {
      key: 'audit_logs',
      label: 'audit_logs',
      eloquentModel: 'App\\Models\\AuditLog',
      count: state.auditLogs.length,
      pk: 'id (BIGINT PK)',
      fks: 'actor_id → users.id (SET NULL)',
      cardinality: 'N:1 belongsTo(User) · Immutable JSON state delta log',
      constraints: 'INDEX(entity_type, entity_id), INDEX(created_at)',
    },
  ];

  const selectedTableMeta = mysqlTables.find((t) => t.key === activeTable)!;

  return (
    <AppLayout
      title="Scenario, users & database"
      subtitle="Live delay threshold tuning, RBAC accounts, and MySQL 8+ relational schema (Slide 11)"
    >
      <div className="mx-auto max-w-[1440px] space-y-5 p-3 sm:space-y-6 sm:p-6">
        {/* Group Scenario Configuration */}
        <section className="space-y-5 rounded-lg border border-line bg-surface p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 className="text-base font-semibold text-ink">
                Group scenario &amp; deterministic delay rule (Slide 8 &amp; Slide 23)
              </h2>
              <p className="mt-1 max-w-3xl text-xs text-ink-2">
                Select a group preset (Group A 12h, Group B 18h, Group C 24h) or configure custom
                delay thresholds. Changes immediately recalculate delay classifications across all
                shipments and write an audit log entry.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={resetDemoData}
                className="h-8 rounded-md border border-line bg-raised px-3 text-xs font-medium text-ink hover:border-line-strong cursor-pointer"
              >
                Reset &amp; re-seed 14 demo shipments
              </button>
            </div>
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
        <section className="space-y-4 rounded-lg border border-line bg-surface p-4 sm:p-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-base font-semibold text-ink">
                User accounts &amp; role access (Slide 5)
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

        {/* MySQL 8+ Relational Database & ERD Inspector (Slide 11 Data Model Minimum) */}
        <section className="space-y-5 rounded-lg border border-line bg-surface p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div className="flex items-center gap-2">
                <Database className="size-4 text-ink" />
                <h3 className="text-base font-semibold text-ink">
                  MySQL 8+ Relational Database &amp; Eloquent ERD (Slide 11 Data Model Minimum)
                </h3>
              </div>
              <p className="mt-1 max-w-3xl text-xs text-ink-2">
                All 9 mandatory tables (`users`, `shipments`, `shipment_items`, `carriers`,
                `locations`, `tracking_events`, `shipment_exceptions`, `notifications`,
                `audit_logs`) with InnoDB foreign keys, unique constraints, composite indexes, and
                soft deletes. Synced with `database/migrations/2026_11_01_000001_create_cargotrace_schema.php`
                and `database/cargotrace_mysql.sql`.
              </p>
            </div>
            <button
              type="button"
              onClick={handleDownloadMysqlDump}
              className="inline-flex h-9 items-center gap-2 rounded-md bg-brand px-3.5 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
            >
              <Download className="size-3.5" />
              <span>Export MySQL Dump (.sql)</span>
            </button>
          </div>

          {/* 9 Core Tables Grid */}
          <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
            {mysqlTables.map((t, idx) => {
              const isSelected = t.key === activeTable;
              return (
                <button
                  key={t.key}
                  type="button"
                  onClick={() => setActiveTable(t.key)}
                  className={`flex flex-col justify-between rounded-md border p-3.5 text-left transition-colors cursor-pointer ${
                    isSelected
                      ? 'border-ink bg-raised text-ink dark:border-[#C6F432]'
                      : 'border-line bg-base text-ink-2 hover:border-line-strong'
                  }`}
                >
                  <div>
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-mono text-xs font-bold text-ink">
                        {idx + 1}. {t.label}
                      </span>
                      <span className="rounded border border-line bg-surface px-1.5 py-0.5 font-mono text-[10px] font-semibold text-ink tabular-nums">
                        {t.count} rows
                      </span>
                    </div>
                    <div className="mt-1 font-mono text-[10px] text-ink-3">
                      {t.eloquentModel}
                    </div>
                    <div className="mt-2 text-[11px] leading-snug text-ink-2">
                      {t.cardinality}
                    </div>
                  </div>
                  <div className="mt-2.5 border-t border-line pt-2 font-mono text-[10px] text-ink-3">
                    <Key className="mr-1 inline size-2.5" />
                    {t.constraints}
                  </div>
                </button>
              );
            })}
          </div>

          {/* Selected Table Schema & Live Relational Row Inspector */}
          <div className="rounded-md border border-line bg-base p-4">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-3">
              <div>
                <div className="flex items-center gap-2">
                  <Layers className="size-3.5 text-ink" />
                  <span className="font-mono text-xs font-bold text-ink">
                    TABLE `{selectedTableMeta.label}` ({selectedTableMeta.count} records)
                  </span>
                  <span className="font-mono text-[11px] text-ink-3">
                    · {selectedTableMeta.eloquentModel}
                  </span>
                </div>
                <p className="mt-1 font-mono text-[11px] text-ink-2">
                  PK: {selectedTableMeta.pk} · FK: {selectedTableMeta.fks}
                </p>
              </div>
              <Badge tone="neutral">{selectedTableMeta.constraints}</Badge>
            </div>

            <div className="mt-3 max-h-64 overflow-auto">
              <pre className="font-mono text-[11px] leading-relaxed text-ink-2">
                {JSON.stringify(
                  activeTable === 'users'
                    ? state.users
                    : activeTable === 'shipments'
                    ? state.shipments.slice(0, 8)
                    : activeTable === 'shipment_items'
                    ? (state.items ?? []).slice(0, 8)
                    : activeTable === 'carriers'
                    ? state.carriers
                    : activeTable === 'locations'
                    ? state.locations.slice(0, 10)
                    : activeTable === 'tracking_events'
                    ? state.trackingEvents.slice(0, 8)
                    : activeTable === 'shipment_exceptions'
                    ? state.exceptions.slice(0, 8)
                    : activeTable === 'notifications'
                    ? state.notifications.slice(0, 8)
                    : state.auditLogs.slice(0, 8),
                  null,
                  2,
                )}
              </pre>
            </div>
          </div>
        </section>
      </div>
    </AppLayout>
  );
};
