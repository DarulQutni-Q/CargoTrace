import React, { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import { useStore } from '../domain/store';

export const AuditView: React.FC = () => {
  const { state, formatDateTime } = useStore();
  const [searchQuery, setSearchQuery] = useState('');
  const [actionFilter, setActionFilter] = useState('ALL');

  const logs = useMemo(
    () =>
      [...state.auditLogs].sort(
        (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
      ),
    [state.auditLogs],
  );

  const uniqueActions = useMemo(
    () => Array.from(new Set(logs.map((l) => l.action))).sort(),
    [logs],
  );

  const filteredLogs = useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    return logs.filter((log) => {
      if (actionFilter !== 'ALL' && log.action !== actionFilter) return false;
      if (!q) return true;
      const actor = log.actor_id ? state.users.find((u) => u.id === log.actor_id) : null;
      const actorName = (actor?.name ?? 'System').toLowerCase();
      const entityLabel = `${log.entity_type} #${log.entity_id}`.toLowerCase();
      const deltaStr = log.changes ? JSON.stringify(log.changes).toLowerCase() : '';
      return (
        actorName.includes(q) ||
        log.action.toLowerCase().includes(q) ||
        entityLabel.includes(q) ||
        deltaStr.includes(q)
      );
    });
  }, [logs, searchQuery, actionFilter, state.users]);

  return (
    <AppLayout
      title="Audit log"
      subtitle={`${logs.length} recorded state transitions and configuration updates`}
    >
      <div className="mx-auto max-w-[1440px] space-y-6 p-6">
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line bg-surface px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-ink">Immutable Audit Trail</h2>
            <p className="mt-0.5 text-xs text-ink-3">
              Filter state transitions by actor, action type, entity ID, or field delta
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2.5">
            <div className="relative w-64 sm:w-72">
              <Search
                className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-ink-3"
                strokeWidth={1.75}
              />
              <input
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search actor, entity, field delta..."
                aria-label="Search audit log"
                className="h-8 w-full rounded-md border border-line bg-base pl-8 pr-3 text-xs font-medium text-ink placeholder:text-ink-3 focus:border-line-strong focus:outline-none"
              />
            </div>

            <select
              value={actionFilter}
              onChange={(e) => setActionFilter(e.target.value)}
              aria-label="Filter audit log by action"
              className="h-8 rounded-md border border-line bg-base px-2.5 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
            >
              <option value="ALL">All actions ({logs.length})</option>
              {uniqueActions.map((act) => (
                <option key={act} value={act}>
                  {act}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="overflow-hidden rounded-lg border border-line bg-surface">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line bg-base text-left text-xs text-ink-3">
                <th className="px-5 py-3 font-medium">Timestamp</th>
                <th className="px-5 py-3 font-medium">Actor</th>
                <th className="px-5 py-3 font-medium">Action</th>
                <th className="px-5 py-3 font-medium">Entity</th>
                <th className="px-5 py-3 font-medium">State delta (before → after)</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {filteredLogs.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-8 text-center text-xs text-ink-3">
                    No audit log entries match the active search or action filter.
                  </td>
                </tr>
              ) : (
                filteredLogs.map((log) => {
                  const actor = log.actor_id
                    ? state.users.find((u) => u.id === log.actor_id)
                    : null;
                  return (
                    <tr key={log.id} className="align-top hover:bg-raised">
                      <td className="px-5 py-3 font-mono text-xs text-ink-3 tabular-nums">
                        {formatDateTime(log.created_at, true)}
                      </td>
                      <td className="px-5 py-3 text-xs font-medium text-ink">
                        {actor?.name ?? 'System'}
                      </td>
                      <td className="px-5 py-3">
                        <Badge tone="neutral">{log.action}</Badge>
                      </td>
                      <td className="px-5 py-3 font-mono text-xs text-ink-2">
                        {log.entity_type} #{log.entity_id}
                      </td>
                      <td className="px-5 py-3 font-mono text-xs">
                        {log.changes ? (
                          <div className="space-y-1">
                            {Object.entries(log.changes).map(([field, change]) => (
                              <div key={field} className="text-ink-2">
                                <span className="font-medium text-ink">{field}:</span>{' '}
                                <span className="text-ink-3">
                                  {JSON.stringify(change.from)}
                                </span>{' '}
                                →{' '}
                                <span className="text-ink">
                                  {JSON.stringify(change.to)}
                                </span>
                              </div>
                            ))}
                          </div>
                        ) : (
                          <span className="text-ink-3">Initial creation</span>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
};
