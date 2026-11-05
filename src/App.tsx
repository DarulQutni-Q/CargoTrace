import React from 'react';
import { ShieldAlert } from 'lucide-react';
import { AppLayout } from './components/AppLayout';
import { StoreProvider, useStore } from './domain/store';
import { UserRole } from './domain/types';
import { roleLabel } from './domain/rules';
import { DashboardView } from './views/DashboardView';
import { ShipmentsIndexView } from './views/ShipmentsIndexView';
import { ShipmentShowView } from './views/ShipmentShowView';
import { ShipmentFormView } from './views/ShipmentFormView';
import { NotificationsView } from './views/NotificationsView';
import { CarrierFormView, CarriersListView } from './views/CarriersView';
import { LocationFormView, LocationsListView } from './views/LocationsView';
import { ScenarioUsersView } from './views/ScenarioUsersView';
import { AuditView } from './views/AuditView';
import { LoginView, RegisterView } from './views/AuthView';

const ForbiddenView: React.FC<{ requiredRole: string }> = ({ requiredRole }) => {
  const { currentUser, navigate } = useStore();
  return (
    <AppLayout title="403 Forbidden" subtitle="Role-based access control policy">
      <div className="mx-auto max-w-lg p-8">
        <div className="rounded-lg border border-rose-500/30 bg-surface p-6 text-center space-y-3">
          <div className="mx-auto flex size-10 items-center justify-center rounded-full bg-rose-500/15 text-rose-400">
            <ShieldAlert className="size-5" strokeWidth={1.75} />
          </div>
          <h2 className="text-base font-semibold text-ink">403 Forbidden — Access Denied</h2>
          <p className="text-xs leading-relaxed text-ink-2">
            Your current account role (
            <span className="font-mono font-medium text-ink">
              {currentUser ? roleLabel(currentUser.role) : 'Guest'}
            </span>
            ) is not authorized to access this resource. This route requires{' '}
            <span className="font-mono font-medium text-ink">{requiredRole}</span> privileges.
          </p>
          <div className="pt-2">
            <button
              type="button"
              onClick={() => navigate('/')}
              className="h-8 rounded-md bg-brand px-4 text-xs font-semibold text-brand-ink hover:bg-brand-hover cursor-pointer"
            >
              ← Return to dashboard
            </button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
};

const RouterView: React.FC = () => {
  const { currentUser, route } = useStore();
  const path = route.path;

  if (!currentUser) {
    if (path === '/register') return <RegisterView />;
    return <LoginView />;
  }

  if (path === '/login') return <LoginView />;
  if (path === '/register') return <RegisterView />;

  const isAdmin = currentUser.role === UserRole.Admin;
  const canOperate =
    currentUser.role === UserRole.Admin || currentUser.role === UserRole.Operator;

  if (path === '/' || path === '/dashboard') {
    return <DashboardView />;
  }

  if (path === '/shipments') {
    return <ShipmentsIndexView />;
  }

  if (path === '/shipments/create') {
    return <ShipmentFormView />;
  }

  const editShipmentMatch = path.match(/^\/shipments\/(\d+)\/edit$/);
  if (editShipmentMatch) {
    if (!canOperate) {
      return <ForbiddenView requiredRole="Administrator or Logistics Operator" />;
    }
    return <ShipmentFormView editId={Number(editShipmentMatch[1])} />;
  }

  const showShipmentMatch = path.match(/^\/shipments\/(\d+)$/);
  if (showShipmentMatch) {
    return <ShipmentShowView shipmentId={Number(showShipmentMatch[1])} />;
  }

  if (path === '/notifications') {
    return <NotificationsView />;
  }

  if (path === '/carriers') {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <CarriersListView />;
  }
  if (path === '/carriers/create') {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <CarrierFormView />;
  }
  const editCarrierMatch = path.match(/^\/carriers\/(\d+)\/edit$/);
  if (editCarrierMatch) {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <CarrierFormView editId={Number(editCarrierMatch[1])} />;
  }

  if (path === '/locations') {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <LocationsListView />;
  }
  if (path === '/locations/create') {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <LocationFormView />;
  }
  const editLocationMatch = path.match(/^\/locations\/(\d+)\/edit$/);
  if (editLocationMatch) {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <LocationFormView editId={Number(editLocationMatch[1])} />;
  }

  if (path === '/scenario') {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <ScenarioUsersView />;
  }

  if (path === '/audit') {
    if (!isAdmin) return <ForbiddenView requiredRole="Administrator" />;
    return <AuditView />;
  }

  return <DashboardView />;
};

export const App: React.FC = () => {
  return (
    <StoreProvider>
      <RouterView />
    </StoreProvider>
  );
};

export default App;
