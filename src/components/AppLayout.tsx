import React, { useEffect, useState } from 'react';
import {
  Bell,
  Building2,
  Container,
  LayoutDashboard,
  LogOut,
  MapPin,
  Menu,
  Moon,
  Package,
  Plus,
  RotateCcw,
  ScrollText,
  Sliders,
  Sun,
  X,
} from 'lucide-react';
import { useStore } from '../domain/store';
import { UserRole } from '../domain/types';
import { roleLabel } from '../domain/rules';

export const AppLayout: React.FC<{
  title: string;
  subtitle?: string;
  children: React.ReactNode;
}> = ({ title, subtitle, children }) => {
  const {
    state,
    currentUser,
    route,
    theme,
    toggleTheme,
    navigate,
    logout,
    resetDemoData,
    formatDateTime,
  } = useStore();
  const [bellOpen, setBellOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  // Close mobile drawer automatically when route changes
  useEffect(() => {
    setMobileMenuOpen(false);
  }, [route.path]);

  if (!currentUser) return null;

  const isAdmin = currentUser.role === UserRole.Admin;
  const canCreateShipment =
    currentUser.role === UserRole.Admin || currentUser.role === UserRole.Operator;

  const userNotifications = state.notifications
    .filter((n) => n.user_id === currentUser.id)
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());

  const unreadCount = userNotifications.filter((n) => n.read_at === null).length;
  const latestNotifications = userNotifications.slice(0, 5);

  const isDashboard = route.path === '/' || route.path === '/dashboard';
  const isShipments = route.path.startsWith('/shipments');
  const isNotifications = route.path.startsWith('/notifications');
  const isCarriers = route.path.startsWith('/carriers');
  const isLocations = route.path.startsWith('/locations');
  const isScenario = route.path.startsWith('/scenario');
  const isAudit = route.path.startsWith('/audit');

  const navItemClass = (active: boolean) =>
    `flex h-9 w-full items-center gap-2.5 rounded-md px-2.5 text-[13px] transition-colors whitespace-nowrap cursor-pointer ${
      active
        ? 'bg-[#080B0C] text-[#FFFFFF] dark:bg-[#C6F432] dark:text-[#080B0C] font-semibold shadow-xs'
        : 'font-medium text-ink-2 hover:bg-tint hover:text-ink'
    }`;

  const handleNav = (path: string) => {
    setMobileMenuOpen(false);
    navigate(path);
  };

  return (
    <div className="flex min-h-screen bg-base text-ink">
      {/* Mobile Slide-Over Backdrop (< 1024px) */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 z-[1350] bg-black/50 backdrop-blur-xs lg:hidden"
          onClick={() => setMobileMenuOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Navigation Rail (Slide-over drawer on mobile/tablet, fixed 240px rail on desktop lg+) */}
      <aside
        className={`fixed inset-y-0 left-0 z-[1400] flex w-64 flex-col border-r border-line bg-rail px-3 py-4 transition-transform duration-200 ease-out lg:z-[1100] lg:w-60 lg:translate-x-0 ${
          mobileMenuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'
        }`}
      >
        <div className="mb-4 flex items-center justify-between px-2.5">
          <button
            type="button"
            onClick={() => handleNav('/')}
            className="flex items-center gap-2 text-left cursor-pointer"
          >
            <span className="flex size-6 items-center justify-center rounded bg-[#080B0C] text-[#C6F432] dark:bg-[#C6F432] dark:text-[#080B0C]">
              <Container className="size-3.5" strokeWidth={2} />
            </span>
            <span className="text-[15px] font-semibold tracking-tight text-ink">
              CargoTrace
            </span>
          </button>

          <button
            type="button"
            onClick={() => setMobileMenuOpen(false)}
            aria-label="Close navigation menu"
            className="flex size-7 items-center justify-center rounded-md text-ink-2 hover:bg-tint hover:text-ink lg:hidden cursor-pointer"
          >
            <X className="size-4" strokeWidth={1.75} />
          </button>
        </div>

        <div className="space-y-0.5">
          <p className="mb-1.5 mt-2 px-2.5 text-xs font-semibold text-ink-3">
            Operations
          </p>
          <button
            type="button"
            onClick={() => handleNav('/')}
            aria-current={isDashboard ? 'page' : undefined}
            className={navItemClass(isDashboard)}
          >
            <LayoutDashboard className="size-4 shrink-0" strokeWidth={1.75} />
            <span>Dashboard</span>
          </button>

          <button
            type="button"
            onClick={() => handleNav('/shipments')}
            aria-current={isShipments ? 'page' : undefined}
            className={navItemClass(isShipments)}
          >
            <Package className="size-4 shrink-0" strokeWidth={1.75} />
            <span>Shipments</span>
          </button>

          <button
            type="button"
            onClick={() => handleNav('/notifications')}
            aria-current={isNotifications ? 'page' : undefined}
            className={navItemClass(isNotifications)}
          >
            <Bell className="size-4 shrink-0" strokeWidth={1.75} />
            <span className="flex-1 text-left">Notifications</span>
            {unreadCount > 0 && (
              <span
                className={`rounded px-1.5 py-0.5 font-mono text-[11px] font-semibold tabular-nums ${
                  isNotifications
                    ? 'bg-[#C6F432] text-[#080B0C] dark:bg-[#080B0C] dark:text-[#C6F432]'
                    : 'bg-tint-strong text-ink'
                }`}
              >
                {unreadCount}
              </span>
            )}
          </button>
        </div>

        {isAdmin && (
          <div className="mt-6 space-y-0.5">
            <p className="mb-1.5 px-2.5 text-xs font-semibold text-ink-3">
              Administration
            </p>
            <button
              type="button"
              onClick={() => handleNav('/carriers')}
              aria-current={isCarriers ? 'page' : undefined}
              className={navItemClass(isCarriers)}
            >
              <Building2 className="size-4 shrink-0" strokeWidth={1.75} />
              <span>Carriers</span>
            </button>

            <button
              type="button"
              onClick={() => handleNav('/locations')}
              aria-current={isLocations ? 'page' : undefined}
              className={navItemClass(isLocations)}
            >
              <MapPin className="size-4 shrink-0" strokeWidth={1.75} />
              <span>Port locations</span>
            </button>

            <button
              type="button"
              onClick={() => handleNav('/scenario')}
              aria-current={isScenario ? 'page' : undefined}
              className={navItemClass(isScenario)}
            >
              <Sliders className="size-4 shrink-0" strokeWidth={1.75} />
              <span>Users &amp; delay rules</span>
            </button>

            <button
              type="button"
              onClick={() => handleNav('/audit')}
              aria-current={isAudit ? 'page' : undefined}
              className={navItemClass(isAudit)}
            >
              <ScrollText className="size-4 shrink-0" strokeWidth={1.75} />
              <span>Audit log</span>
            </button>
          </div>
        )}

        {/* Quiet Scenario Footnote + User Profile */}
        <div className="mt-auto space-y-3 pt-4">
          <div className="px-2.5 text-xs text-ink-2">
            <div className="flex items-center justify-between">
              <span className="font-medium text-ink-3">Delay threshold</span>
              <span className="font-mono text-[11px] font-semibold text-ink tabular-nums">
                ≤{state.scenarioConfig.lowThresholdHours}h / ≤{state.scenarioConfig.highThresholdHours}h
              </span>
            </div>
            <p className="mt-0.5 truncate text-[11px] font-medium text-ink-3">
              {state.scenarioConfig.routeCorridor}
            </p>
          </div>

          <div className="flex items-center justify-between gap-2 border-t border-line px-2 pt-3">
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-[#080B0C] text-[#C6F432] dark:bg-[#C6F432] dark:text-[#080B0C] text-xs font-semibold">
                {currentUser.name.slice(0, 1).toUpperCase()}
              </div>
              <div className="min-w-0">
                <p className="truncate text-xs font-semibold text-ink">{currentUser.name}</p>
                <p className="truncate text-[11px] font-medium text-ink-3">{roleLabel(currentUser.role)}</p>
              </div>
            </div>
            <div className="flex items-center gap-0.5">
              {isAdmin && (
                <button
                  type="button"
                  onClick={resetDemoData}
                  title="Reset seeded demo data"
                  aria-label="Reset demo data"
                  className="flex size-7 items-center justify-center rounded-md text-ink-2 transition-colors hover:bg-tint hover:text-ink cursor-pointer"
                >
                  <RotateCcw className="size-3.5" strokeWidth={1.75} />
                </button>
              )}
              <button
                type="button"
                onClick={logout}
                title="Sign out"
                aria-label="Sign out"
                className="flex size-7 items-center justify-center rounded-md text-ink-2 transition-colors hover:bg-tint hover:text-ink cursor-pointer"
              >
                <LogOut className="size-3.5" strokeWidth={1.75} />
              </button>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content Area (full width on mobile, pl-60 on desktop lg+) */}
      <div className="flex min-h-screen min-w-0 flex-1 flex-col lg:pl-60">
        {/* Header elevated above all Leaflet map panes (z-[1200]) */}
        <header className="relative z-[1200] flex h-14 items-center justify-between gap-2 border-b border-line bg-base px-3 sm:px-6">
          <div className="flex items-center gap-2.5 min-w-0">
            <button
              type="button"
              onClick={() => setMobileMenuOpen(true)}
              aria-label="Open navigation menu"
              className="flex size-8 shrink-0 items-center justify-center rounded-md border border-line bg-surface text-ink hover:bg-raised lg:hidden cursor-pointer"
            >
              <Menu className="size-4" strokeWidth={1.75} />
            </button>
            <div className="flex items-baseline gap-2.5 min-w-0">
              <h1 className="text-sm sm:text-[15px] font-semibold tracking-tight text-ink truncate">
                {title}
              </h1>
              {subtitle && (
                <span className="hidden text-xs font-medium text-ink-3 md:inline truncate">
                  {subtitle}
                </span>
              )}
            </div>
          </div>

          <div className="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
            <div className="hidden items-center gap-2 text-xs text-ink-3 xl:flex">
              <span className="font-medium">{roleLabel(currentUser.role)}</span>
              <span aria-hidden="true">·</span>
              <span className="font-semibold text-ink">{currentUser.name}</span>
            </div>

            {/* White Mode / Dark Mode Toggle */}
            <button
              type="button"
              onClick={toggleTheme}
              title={theme === 'dark' ? 'Switch to White Mode' : 'Switch to Dark Mode'}
              aria-label={theme === 'dark' ? 'Switch to White Mode' : 'Switch to Dark Mode'}
              className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-surface px-2 sm:px-2.5 text-xs font-semibold text-ink transition-colors hover:border-line-strong hover:bg-raised cursor-pointer"
            >
              {theme === 'dark' ? (
                <>
                  <Sun className="size-3.5 text-[#C6F432]" strokeWidth={2} />
                  <span className="hidden sm:inline">White mode</span>
                </>
              ) : (
                <>
                  <Moon className="size-3.5 text-[#080B0C]" strokeWidth={2} />
                  <span className="hidden sm:inline">Dark mode</span>
                </>
              )}
            </button>

            {/* Notification Bell Dropdown */}
            <div className="relative">
              <button
                type="button"
                aria-label="Toggle notifications menu"
                onClick={() => setBellOpen((o) => !o)}
                className="relative flex size-8 cursor-pointer items-center justify-center rounded-md border border-line bg-surface text-ink transition-colors hover:border-line-strong hover:bg-raised"
              >
                <Bell className="size-4" strokeWidth={1.75} />
                {unreadCount > 0 && (
                  <span className="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full border border-[#080B0C]/20 bg-[#C6F432] font-mono text-[10px] font-bold text-[#080B0C]">
                    {unreadCount > 9 ? '9+' : unreadCount}
                  </span>
                )}
              </button>

              {bellOpen && (
                <>
                  {/* Click-outside backdrop */}
                  <div
                    className="fixed inset-0 z-[1250]"
                    onClick={() => setBellOpen(false)}
                  />
                  <div className="fixed left-3 right-3 top-14 z-[1300] rounded-lg border border-line-strong bg-overlay p-1.5 shadow-[0_20px_50px_rgba(8,11,12,0.32)] sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:w-80">
                    <div className="flex items-center justify-between border-b border-line px-3 py-2">
                      <span className="text-xs font-semibold text-ink">Notifications</span>
                      <span className="rounded bg-tint px-1.5 py-0.5 font-mono text-[11px] font-medium text-ink-2">
                        {unreadCount} unread
                      </span>
                    </div>
                    {latestNotifications.length > 0 ? (
                      <div className="mt-1 max-h-72 overflow-y-auto space-y-0.5">
                        {latestNotifications.map((item) => (
                          <button
                            key={item.id}
                            type="button"
                            onClick={() => {
                              setBellOpen(false);
                              if (item.shipment_id) {
                                navigate(`/shipments/${item.shipment_id}`);
                              } else {
                                navigate('/notifications');
                              }
                            }}
                            className="block w-full rounded-md px-3 py-2 text-left transition-colors hover:bg-surface cursor-pointer"
                          >
                            <span className="flex items-center gap-2">
                              <span
                                className={`size-1.5 shrink-0 rounded-full ${
                                  item.read_at === null
                                    ? 'bg-[#080B0C] dark:bg-[#C6F432]'
                                    : 'bg-transparent'
                                }`}
                              />
                              <span
                                className={`truncate text-xs ${
                                  item.read_at === null ? 'font-semibold text-ink' : 'font-medium text-ink-2'
                                }`}
                              >
                                {item.title}
                              </span>
                            </span>
                            <span className="mt-0.5 block pl-3.5 font-mono text-[10px] font-medium text-ink-3">
                              {formatDateTime(item.created_at)}
                            </span>
                          </button>
                        ))}
                      </div>
                    ) : (
                      <p className="px-3 py-4 text-center text-xs font-medium text-ink-3">
                        No notifications yet
                      </p>
                    )}
                    <button
                      type="button"
                      onClick={() => {
                        setBellOpen(false);
                        navigate('/notifications');
                      }}
                      className="mt-1 block w-full rounded-md border-t border-line px-3 py-2 text-center text-xs font-semibold text-ink transition-colors hover:bg-surface cursor-pointer"
                    >
                      View all notifications →
                    </button>
                  </div>
                </>
              )}
            </div>

            <button
              type="button"
              onClick={() => navigate('/shipments/create')}
              className="inline-flex h-8 items-center gap-1.5 rounded-md bg-brand px-2.5 sm:px-3 text-xs font-semibold text-brand-ink transition-colors hover:bg-brand-hover focus-visible:outline-none whitespace-nowrap shrink-0 cursor-pointer"
            >
              <Plus className="size-3.5" strokeWidth={2} />
              <span className="hidden sm:inline">
                {canCreateShipment ? 'New shipment' : 'Request shipment'}
              </span>
              <span className="sm:hidden">New</span>
            </button>
          </div>
        </header>

        <main className="relative z-0 min-h-0 flex-1">{children}</main>
      </div>
    </div>
  );
};
