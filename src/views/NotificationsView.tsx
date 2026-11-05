import React from 'react';
import { AppLayout } from '../components/AppLayout';
import { Badge } from '../components/Badge';
import { useStore } from '../domain/store';

export const NotificationsView: React.FC = () => {
  const {
    state,
    currentUser,
    navigate,
    markNotificationRead,
    markAllNotificationsRead,
    formatDateTime,
  } = useStore();

  const userNotifications = state.notifications
    .filter((n) => n.user_id === currentUser?.id)
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());

  const unreadCount = userNotifications.filter((n) => n.read_at === null).length;

  return (
    <AppLayout
      title="Notifications"
      subtitle={`${userNotifications.length} notifications · ${unreadCount} unread`}
    >
      <div className="mx-auto max-w-4xl space-y-4 p-3 sm:space-y-5 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface px-4 py-3.5 sm:px-5 sm:py-4">
          <div>
            <h2 className="text-base font-semibold text-ink">Event dispatch log</h2>
            <p className="mt-0.5 text-xs text-ink-3">
              Alerts generated on status transitions, exception events, and delivery completion
            </p>
          </div>
          <button
            type="button"
            onClick={markAllNotificationsRead}
            className="h-8 rounded-md border border-line bg-raised px-3 text-xs font-medium text-ink hover:border-line-strong cursor-pointer"
          >
            Mark all read
          </button>
        </div>

        <div className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
          {userNotifications.map((item) => {
            const isUnread = item.read_at === null;
            const shipment = item.shipment_id
              ? state.shipments.find((s) => s.id === item.shipment_id)
              : null;

            return (
              <div
                key={item.id}
                className={`p-5 transition-colors ${
                  isUnread ? 'bg-raised/60' : 'bg-surface'
                }`}
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Badge
                        tone={
                          item.type.includes('EXCEPTION')
                            ? 'red'
                            : item.type.includes('RESOLVED')
                            ? 'green'
                            : 'neutral'
                        }
                      >
                        {item.type}
                      </Badge>
                      <span className="font-mono text-xs text-ink-3 tabular-nums">
                        {formatDateTime(item.created_at, true)}
                      </span>
                    </div>
                    <h3 className="text-sm font-medium text-ink">{item.title}</h3>
                    <p className="text-xs leading-relaxed text-ink-2">{item.body}</p>
                  </div>

                  <div className="flex items-center gap-2">
                    {shipment && (
                      <button
                        type="button"
                        onClick={() => navigate(`/shipments/${shipment.id}`)}
                        className="rounded border border-line bg-base px-2.5 py-1 font-mono text-xs font-medium text-ink hover:border-line-strong cursor-pointer"
                      >
                        {shipment.shipment_number} →
                      </button>
                    )}
                    {isUnread && (
                      <button
                        type="button"
                        onClick={() => markNotificationRead(item.id)}
                        className="text-xs font-medium text-ink-2 hover:text-ink cursor-pointer"
                      >
                        Mark read
                      </button>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </AppLayout>
  );
};
