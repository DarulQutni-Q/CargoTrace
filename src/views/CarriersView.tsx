import React, { useMemo, useState } from 'react';
import { Plus, Search } from 'lucide-react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import { useStore } from '../domain/store';
import { Carrier } from '../domain/types';

export const CarriersListView: React.FC = () => {
  const { state, navigate } = useStore();
  const [searchQuery, setSearchQuery] = useState('');
  const [modeFilter, setModeFilter] = useState<string>('ALL');

  const filteredCarriers = useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    return state.carriers.filter((c) => {
      if (modeFilter !== 'ALL' && (c.service_type || 'SEA') !== modeFilter) {
        return false;
      }
      if (!q) return true;
      return (
        c.name.toLowerCase().includes(q) ||
        c.code.toLowerCase().includes(q) ||
        (c.contact_email && c.contact_email.toLowerCase().includes(q))
      );
    });
  }, [state.carriers, searchQuery, modeFilter]);

  return (
    <AppLayout
      title="Carriers"
      subtitle={`${state.carriers.length} registered logistics carriers`}
    >
      <div className="mx-auto max-w-[1440px] space-y-5 p-3 sm:space-y-6 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line bg-surface px-4 py-4 sm:px-5">
          <div>
            <h2 className="text-base font-semibold text-ink">Carrier directory</h2>
            <p className="mt-0.5 text-xs text-ink-3">
              Sea, air, and road carriers operating across international shipping corridors
            </p>
          </div>
          <div className="flex w-full flex-wrap items-center gap-2.5 sm:w-auto">
            <div className="relative w-full sm:w-64">
              <Search
                className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-ink-3"
                strokeWidth={1.75}
              />
              <input
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search carrier, code, email..."
                aria-label="Search carriers"
                className="h-8 w-full rounded-md border border-line bg-base pl-8 pr-3 text-xs font-medium text-ink placeholder:text-ink-3 focus:border-line-strong focus:outline-none"
              />
            </div>

            <select
              value={modeFilter}
              onChange={(e) => setModeFilter(e.target.value)}
              aria-label="Filter carriers by transport mode"
              className="h-8 rounded-md border border-line bg-base px-2.5 text-xs font-medium text-ink focus:border-line-strong focus:outline-none"
            >
              <option value="ALL">All modes</option>
              <option value="SEA">SEA</option>
              <option value="AIR">AIR</option>
              <option value="ROAD">ROAD</option>
            </select>

            <button
              type="button"
              onClick={() => navigate('/carriers/create')}
              className="inline-flex h-8 items-center gap-1.5 rounded-md bg-brand px-3 text-xs font-semibold text-brand-ink transition-colors hover:bg-brand-hover cursor-pointer"
            >
              <Plus className="size-3.5" strokeWidth={2} />
              Add carrier
            </button>
          </div>
        </div>

        <div className="overflow-hidden rounded-lg border border-line bg-surface">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line bg-base text-left text-xs text-ink-3">
                <th className="px-5 py-3 font-medium">Carrier name</th>
                <th className="px-5 py-3 font-medium">Code</th>
                <th className="px-5 py-3 font-medium">Transport mode</th>
                <th className="px-5 py-3 font-medium">Dispatch email</th>
                <th className="px-5 py-3 font-medium">Active shipments</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 text-right font-medium">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {filteredCarriers.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-8 text-center text-xs text-ink-3">
                    No carriers match the active search or mode filter.
                  </td>
                </tr>
              ) : (
                filteredCarriers.map((carrier) => {
                  const shipmentsCount = state.shipments.filter(
                    (s) => !s.archived_at && s.carrier_id === carrier.id,
                  ).length;
                  return (
                    <tr key={carrier.id} className="hover:bg-raised">
                      <td className="px-5 py-3 text-xs font-medium text-ink">{carrier.name}</td>
                      <td className="px-5 py-3 font-mono text-xs text-ink-2">
                        {carrier.code}
                      </td>
                      <td className="px-5 py-3">
                        <Badge tone="neutral">{carrier.service_type || 'SEA'}</Badge>
                      </td>
                      <td className="px-5 py-3 font-mono text-xs text-ink-2">
                        {carrier.contact_email || '—'}
                      </td>
                      <td className="px-5 py-3 font-mono text-xs text-ink tabular-nums">
                        {shipmentsCount}
                      </td>
                      <td className="px-5 py-3">
                        <Badge tone={carrier.is_active ? 'green' : 'neutral'}>
                          {carrier.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </td>
                      <td className="px-5 py-3 text-right">
                        <button
                          type="button"
                          onClick={() => navigate(`/carriers/${carrier.id}/edit`)}
                          className="text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
                        >
                          Edit
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

export const CarrierFormView: React.FC<{ editId?: number }> = ({ editId }) => {
  const { state, navigate, saveCarrier } = useStore();
  const existing = editId ? state.carriers.find((c) => c.id === editId) : undefined;

  const [name, setName] = useState(existing?.name ?? '');
  const [code, setCode] = useState(existing?.code ?? '');
  const [serviceType, setServiceType] = useState<Carrier['service_type']>(
    existing?.service_type ?? 'SEA',
  );
  const [contactEmail, setContactEmail] = useState(existing?.contact_email ?? '');
  const [isActive, setIsActive] = useState(existing ? existing.is_active : true);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const res = saveCarrier({
      id: existing?.id,
      name,
      code,
      service_type: serviceType,
      contact_email: contactEmail,
      is_active: isActive,
    });
    if (!res.ok) {
      setErrors(res.errors ?? {});
    } else {
      navigate('/carriers');
    }
  };

  return (
    <AppLayout title={existing ? 'Edit carrier' : 'Add carrier'}>
      <div className="mx-auto max-w-xl p-3 sm:p-6">
        <form
          onSubmit={handleSubmit}
          className="space-y-4 rounded-lg border border-line bg-surface p-4 sm:p-6"
        >
          <div>
            <label className="mb-1.5 block text-xs text-ink-2">Carrier name *</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Meridian Air Cargo"
              className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
            />
            {errors.name && (
              <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                {errors.name}
              </p>
            )}
          </div>

          <div>
            <label className="mb-1.5 block text-xs text-ink-2">Carrier code *</label>
            <input
              type="text"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              placeholder="MAC"
              className="h-9 w-full rounded-md border border-line bg-base px-3 font-mono text-xs uppercase text-ink"
            />
            {errors.code && (
              <p className="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                {errors.code}
              </p>
            )}
          </div>

          <div>
            <label className="mb-1.5 block text-xs text-ink-2">Transport mode</label>
            <select
              value={serviceType}
              onChange={(e) => setServiceType(e.target.value as Carrier['service_type'])}
              className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
            >
              <option value="SEA">SEA</option>
              <option value="AIR">AIR</option>
              <option value="ROAD">ROAD</option>
            </select>
          </div>

          <div>
            <label className="mb-1.5 block text-xs text-ink-2">Dispatch email</label>
            <input
              type="email"
              value={contactEmail}
              onChange={(e) => setContactEmail(e.target.value)}
              className="h-9 w-full rounded-md border border-line bg-base px-3 text-xs text-ink"
            />
          </div>

          <label className="flex items-center gap-2 text-xs text-ink-2">
            <input
              type="checkbox"
              checked={isActive}
              onChange={(e) => setIsActive(e.target.checked)}
            />
            Active for new shipment bookings
          </label>

          <div className="flex items-center gap-3 pt-2">
            <button
              type="submit"
              className="h-8 rounded-md bg-brand px-4 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
            >
              Save carrier
            </button>
            <button
              type="button"
              onClick={() => navigate('/carriers')}
              className="text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
};
