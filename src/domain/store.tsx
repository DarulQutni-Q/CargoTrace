import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import {
  AuditLog,
  Carrier,
  DelayAssessment,
  ExceptionSeverity,
  ExceptionStatus,
  ExceptionType,
  Location,
  ScenarioConfig,
  Shipment,
  ShipmentException,
  ShipmentItem,
  ShipmentNotification,
  ShipmentStatus,
  TrackingEvent,
  User,
  UserRole,
} from './types';
import { buildInitialSeed, SeedState } from './seed';
import {
  assessDelay,
  exceptionTypeLabel,
  statusLabel,
  validateExceptionTransition,
  validateTrackingEvent,
} from './rules';

const STORAGE_KEY = 'cargotrace_state_v2';
const AUTH_KEY = 'cargotrace_auth_v2';
const THEME_KEY = 'cargotrace_theme_v2';

export interface RouteState {
  path: string;
  query: Record<string, string>;
}

interface StoreContextValue {
  state: SeedState;
  currentUser: User | null;
  route: RouteState;
  theme: 'dark' | 'light';
  toggleTheme: () => void;
  navigate: (path: string, query?: Record<string, string>) => void;
  login: (email: string, password?: string) => { ok: boolean; error?: string };
  register: (name: string, email: string, password: string, confirm: string) => {
    ok: boolean;
    errors?: Record<string, string>;
  };
  logout: () => void;
  switchUserById: (id: number) => void;
  resetDemoData: () => void;
  visibleShipments: Shipment[];
  assessShipmentDelay: (shipment: Shipment) => DelayAssessment;
  updateScenarioConfig: (config: ScenarioConfig) => void;
  saveUser: (user: Omit<User, 'id'> & { id?: number }) => {
    ok: boolean;
    errors?: Record<string, string>;
  };
  createShipment: (
    data: {
      customer_id: number;
      carrier_id: number;
      origin_location_id: number;
      destination_location_id: number;
      cargo_type: string;
      weight_kg: number;
      volume_m3: number;
      expected_departure_at: string;
      expected_arrival_at: string;
      estimated_arrival_at: string | null;
      transit_stop?: {
        location_name: string;
        latitude: number;
        longitude: number;
      } | null;
    },
    items: Array<{
      description: string;
      quantity: number;
      weight_kg: number;
      volume_m3: number;
    }>,
  ) => Shipment;
  updateShipment: (
    id: number,
    data: {
      customer_id: number;
      carrier_id: number;
      origin_location_id: number;
      destination_location_id: number;
      cargo_type: string;
      weight_kg: number;
      volume_m3: number;
      expected_departure_at: string;
      expected_arrival_at: string;
      estimated_arrival_at: string | null;
      transit_stop?: {
        location_name: string;
        latitude: number;
        longitude: number;
      } | null;
    },
    items: Array<{
      description: string;
      quantity: number;
      weight_kg: number;
      volume_m3: number;
    }>,
  ) => Shipment | null;
  archiveShipment: (id: number) => void;
  addTrackingEvent: (
    shipmentId: number,
    data: {
      event_type: string;
      occurred_at: string;
      location_name: string;
      latitude: number | null;
      longitude: number | null;
      description: string | null;
      estimated_arrival_at?: string | null;
    },
  ) => { ok: boolean; errors?: Record<string, string> };
  createException: (
    shipmentId: number,
    data: {
      type: ExceptionType;
      severity: ExceptionSeverity;
      detected_at: string;
      location_name?: string;
      description?: string;
      estimated_arrival_at?: string | null;
    },
  ) => { ok: boolean; errors?: Record<string, string> };
  updateExceptionStatus: (
    exceptionId: number,
    status: ExceptionStatus,
  ) => { ok: boolean; error?: string };
  markNotificationRead: (id: number) => void;
  markAllNotificationsRead: () => void;
  saveCarrier: (
    carrier: Omit<Carrier, 'id'> & { id?: number },
  ) => { ok: boolean; errors?: Record<string, string> };
  saveLocation: (
    location: Omit<Location, 'id'> & { id?: number },
  ) => { ok: boolean; locationId?: number; errors?: Record<string, string> };
  ensureLocationPreset: (preset: Omit<Location, 'id'>) => number;
  claimShipmentByNumber: (shipmentNumber: string) => {
    ok: boolean;
    shipmentId?: number;
    error?: string;
  };
  formatDateTime: (iso: string | null | undefined, includeYear?: boolean) => string;
  formatShortDate: (iso: string | null | undefined) => string;
}

const StoreContext = createContext<StoreContextValue | null>(null);

export function formatDateTime(iso: string | null | undefined, includeYear = false): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const month = months[d.getUTCMonth()];
  const day = d.getUTCDate();
  const year = d.getUTCFullYear();
  const hh = String(d.getUTCHours()).padStart(2, '0');
  const mm = String(d.getUTCMinutes()).padStart(2, '0');
  return includeYear ? `${month} ${day}, ${year} · ${hh}:${mm}` : `${month} ${day} · ${hh}:${mm}`;
}

export function formatShortDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return `${months[d.getUTCMonth()]} ${d.getUTCDate()}`;
}

function parseBrowserUrl(): RouteState {
  const path = window.location.pathname || '/';
  const search = new URLSearchParams(window.location.search);
  const query: Record<string, string> = {};
  search.forEach((v, k) => {
    query[k] = v;
  });
  return { path, query };
}

export const StoreProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [theme, setTheme] = useState<'dark' | 'light'>(() => {
    try {
      const saved = localStorage.getItem(THEME_KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch {
      // ignore
    }
    return 'light';
  });

  useEffect(() => {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    try {
      localStorage.setItem(THEME_KEY, theme);
    } catch {
      // ignore
    }
  }, [theme]);

  const toggleTheme = () => {
    setTheme((prev) => (prev === 'dark' ? 'light' : 'dark'));
  };

  const [state, setState] = useState<SeedState>(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed && parsed.scenarioConfig) {
          return parsed;
        }
      }
    } catch {
      // ignore storage errors
    }
    return buildInitialSeed();
  });

  const [currentUserId, setCurrentUserId] = useState<number | null>(() => {
    try {
      const saved = localStorage.getItem(AUTH_KEY);
      if (saved !== null) {
        const parsed = JSON.parse(saved);
        return typeof parsed === 'number' ? parsed : 1;
      }
    } catch {
      // ignore
    }
    return 1;
  });

  const [route, setRoute] = useState<RouteState>(() => parseBrowserUrl());

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
      // ignore
    }
  }, [state]);

  useEffect(() => {
    try {
      localStorage.setItem(AUTH_KEY, JSON.stringify(currentUserId));
    } catch {
      // ignore
    }
  }, [currentUserId]);

  useEffect(() => {
    const onPopState = () => setRoute(parseBrowserUrl());
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  const navigate = (path: string, query: Record<string, string> = {}) => {
    const params = new URLSearchParams();
    Object.entries(query).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        params.set(k, v);
      }
    });
    const qs = params.toString();
    const url = qs ? `${path}?${qs}` : path;
    window.history.pushState({}, '', url);
    setRoute({ path, query });
  };

  const currentUser = useMemo(
    () => state.users.find((u) => u.id === currentUserId) ?? null,
    [state.users, currentUserId],
  );

  const visibleShipments = useMemo(() => {
    const active = state.shipments.filter((s) => !s.archived_at);
    if (!currentUser) return [];
    if (currentUser.role === UserRole.Customer) {
      return active.filter((s) => s.customer_id === currentUser.id);
    }
    return active;
  }, [state.shipments, currentUser]);

  const assessShipmentDelay = (shipment: Shipment): DelayAssessment => {
    return assessDelay(
      shipment.expected_arrival_at,
      shipment.estimated_arrival_at,
      state.scenarioConfig.lowThresholdHours,
      state.scenarioConfig.highThresholdHours,
    );
  };

  const updateScenarioConfig = (config: ScenarioConfig) => {
    if (!currentUser || currentUser.role !== UserRole.Admin) {
      return;
    }
    const nowIso = new Date().toISOString();
    const auditEntry: AuditLog = {
      id: Math.max(0, ...state.auditLogs.map((a) => a.id)) + 1,
      actor_id: currentUser.id,
      action: 'scenario.updated',
      entity_type: 'ScenarioConfig',
      entity_id: 1,
      changes: {
        groupCode: { from: state.scenarioConfig.groupCode, to: config.groupCode },
        lowThresholdHours: {
          from: state.scenarioConfig.lowThresholdHours,
          to: config.lowThresholdHours,
        },
        highThresholdHours: {
          from: state.scenarioConfig.highThresholdHours,
          to: config.highThresholdHours,
        },
      },
      created_at: nowIso,
    };
    setState((prev) => ({
      ...prev,
      scenarioConfig: config,
      auditLogs: [auditEntry, ...prev.auditLogs],
    }));
  };

  const saveUser: StoreContextValue['saveUser'] = (input) => {
    if (!currentUser || currentUser.role !== UserRole.Admin) {
      return { ok: false, errors: { name: 'Forbidden: Administrator role required.' } };
    }
    const errors: Record<string, string> = {};
    if (!input.name.trim()) errors.name = 'Name is required.';
    if (!input.email.trim() || !input.email.includes('@')) {
      errors.email = 'Valid email is required.';
    }
    const dup = state.users.find(
      (u) => u.email.toLowerCase() === input.email.trim().toLowerCase() && u.id !== input.id,
    );
    if (dup) errors.email = 'Email is already registered.';
    if (Object.keys(errors).length > 0) return { ok: false, errors };

    const nowIso = new Date().toISOString();
    if (input.id) {
      setState((prev) => ({
        ...prev,
        users: prev.users.map((u) =>
          u.id === input.id
            ? { ...u, name: input.name.trim(), email: input.email.trim(), role: input.role }
            : u,
        ),
        auditLogs: [
          {
            id: Math.max(0, ...prev.auditLogs.map((a) => a.id)) + 1,
            actor_id: currentUser.id,
            action: 'user.updated',
            entity_type: 'User',
            entity_id: input.id!,
            changes: { role: { from: 'previous', to: input.role } },
            created_at: nowIso,
          },
          ...prev.auditLogs,
        ],
      }));
    } else {
      const nextId = Math.max(0, ...state.users.map((u) => u.id)) + 1;
      setState((prev) => ({
        ...prev,
        users: [
          ...prev.users,
          { id: nextId, name: input.name.trim(), email: input.email.trim(), role: input.role },
        ],
        auditLogs: [
          {
            id: Math.max(0, ...prev.auditLogs.map((a) => a.id)) + 1,
            actor_id: currentUser.id,
            action: 'user.created',
            entity_type: 'User',
            entity_id: nextId,
            changes: { role: { from: null, to: input.role } },
            created_at: nowIso,
          },
          ...prev.auditLogs,
        ],
      }));
    }
    return { ok: true };
  };

  const resetDemoData = () => {
    if (!currentUser || currentUser.role !== UserRole.Admin) return;
    const fresh = buildInitialSeed();
    setState(fresh);
    setCurrentUserId(1);
  };

  const login = (email: string, password = 'password') => {
    const normalized = email.trim().toLowerCase();
    if (!normalized || !password.trim()) {
      return { ok: false, error: 'Please enter both your email address and password.' };
    }
    const found = state.users.find((u) => u.email.toLowerCase() === normalized);
    if (!found || password.length < 4) {
      return { ok: false, error: 'These credentials do not match our records.' };
    }
    setCurrentUserId(found.id);
    navigate('/');
    return { ok: true };
  };

  const register = (name: string, email: string, password: string, confirm: string) => {
    const errors: Record<string, string> = {};
    if (!name.trim()) errors.name = 'The name field is required.';
    if (!email.trim() || !email.includes('@')) errors.email = 'A valid email address is required.';
    if (state.users.some((u) => u.email.toLowerCase() === email.trim().toLowerCase())) {
      errors.email = 'The email has already been taken.';
    }
    if (password.length < 8) errors.password = 'The password field must be at least 8 characters.';
    if (password !== confirm) {
      errors.password_confirmation = 'The password field confirmation does not match.';
    }
    if (Object.keys(errors).length > 0) {
      return { ok: false, errors };
    }

    const nextId = Math.max(0, ...state.users.map((u) => u.id)) + 1;
    const newUser: User = {
      id: nextId,
      name: name.trim(),
      email: email.trim(),
      role: UserRole.Customer,
    };

    // Newly registered Customer starts cleanly with 0 shipments (no starter shipment)
    setState((prev) => ({
      ...prev,
      users: [...prev.users, newUser],
    }));
    setCurrentUserId(nextId);
    navigate('/');
    return { ok: true };
  };

  const logout = () => {
    setCurrentUserId(null);
    navigate('/login');
  };

  const switchUserById = (id: number) => {
    setCurrentUserId(id);
    if (route.path === '/login' || route.path === '/register') {
      navigate('/');
    }
  };

  const createShipment: StoreContextValue['createShipment'] = (data, items) => {
    const nextId = Math.max(0, ...state.shipments.map((s) => s.id)) + 1;
    const carrier = state.carriers.find((c) => c.id === data.carrier_id);
    const originLoc = state.locations.find((l) => l.id === data.origin_location_id);
    const destLoc = state.locations.find((l) => l.id === data.destination_location_id);
    const shipmentNumber = `CT${String(nextId).padStart(4, '0')}${carrier?.code ?? 'GEN'}`;
    const nowIso = new Date().toISOString();
    const depIso = new Date(data.expected_departure_at).toISOString();

    const newShipment: Shipment = {
      id: nextId,
      shipment_number: shipmentNumber,
      customer_id: data.customer_id,
      carrier_id: data.carrier_id,
      origin_location_id: data.origin_location_id,
      destination_location_id: data.destination_location_id,
      cargo_type: data.cargo_type,
      weight_kg: data.weight_kg,
      volume_m3: data.volume_m3,
      expected_departure_at: depIso,
      expected_arrival_at: new Date(data.expected_arrival_at).toISOString(),
      estimated_arrival_at: data.estimated_arrival_at
        ? new Date(data.estimated_arrival_at).toISOString()
        : null,
      current_status: ShipmentStatus.Created,
      created_at: nowIso,
      archived_at: null,
    };

    let itemId = Math.max(0, ...state.items.map((i) => i.id)) + 1;
    const newItems: ShipmentItem[] = items
      .filter((i) => i.description.trim() !== '')
      .map((i) => ({
        id: itemId++,
        shipment_id: nextId,
        description: i.description.trim(),
        quantity: Number(i.quantity) || 1,
        weight_kg: Number(i.weight_kg) || 0,
        volume_m3: Number(i.volume_m3) || 0,
      }));

    let nextEventId = Math.max(0, ...state.trackingEvents.map((e) => e.id)) + 1;
    const createdEvents: TrackingEvent[] = [];

    if (originLoc) {
      createdEvents.push({
        id: nextEventId++,
        shipment_id: nextId,
        event_type: ShipmentStatus.Created,
        occurred_at: depIso,
        location_name: `${originLoc.name} · ${originLoc.city}`,
        latitude: originLoc.latitude,
        longitude: originLoc.longitude,
        description: `Shipment registered for corridor ${originLoc.code} → ${destLoc?.code ?? ''}.`,
        created_by: currentUser?.id ?? 1,
      });
    }

    if (data.transit_stop && data.transit_stop.location_name.trim()) {
      const transitTime = new Date(new Date(depIso).getTime() + 2 * 3600 * 1000).toISOString();
      createdEvents.push({
        id: nextEventId++,
        shipment_id: nextId,
        event_type: 'CHECKPOINT',
        occurred_at: transitTime,
        location_name: data.transit_stop.location_name.trim(),
        latitude: data.transit_stop.latitude,
        longitude: data.transit_stop.longitude,
        description: `Planned intermediate transit hub between ${originLoc?.code ?? 'Origin'} and ${destLoc?.code ?? 'Destination'}.`,
        created_by: currentUser?.id ?? 1,
      });
    }

    const nextNotifId = Math.max(0, ...state.notifications.map((n) => n.id)) + 1;
    const createdNotif: ShipmentNotification = {
      id: nextNotifId,
      user_id: data.customer_id,
      shipment_id: nextId,
      type: 'SHIPMENT_CREATED',
      title: `New shipment ${shipmentNumber} registered`,
      body: `${data.cargo_type} scheduled from ${originLoc?.city ?? 'Origin'} to ${destLoc?.city ?? 'Destination'}${
        data.transit_stop ? ` via ${data.transit_stop.location_name}` : ''
      }.`,
      read_at: null,
      created_at: nowIso,
    };

    const auditEntry: AuditLog = {
      id: Math.max(0, ...state.auditLogs.map((a) => a.id)) + 1,
      actor_id: currentUser?.id ?? null,
      action: 'shipment.created',
      entity_type: 'Shipment',
      entity_id: nextId,
      changes: null,
      created_at: nowIso,
    };

    setState((prev) => ({
      ...prev,
      shipments: [newShipment, ...prev.shipments],
      items: [...prev.items, ...newItems],
      trackingEvents: [...prev.trackingEvents, ...createdEvents],
      notifications: [createdNotif, ...prev.notifications],
      auditLogs: [auditEntry, ...prev.auditLogs],
    }));

    return newShipment;
  };

  const updateShipment: StoreContextValue['updateShipment'] = (id, data, items) => {
    const existing = state.shipments.find((s) => s.id === id);
    if (!existing) return null;

    const updated: Shipment = {
      ...existing,
      customer_id: data.customer_id,
      carrier_id: data.carrier_id,
      origin_location_id: data.origin_location_id,
      destination_location_id: data.destination_location_id,
      cargo_type: data.cargo_type,
      weight_kg: data.weight_kg,
      volume_m3: data.volume_m3,
      expected_departure_at: new Date(data.expected_departure_at).toISOString(),
      expected_arrival_at: new Date(data.expected_arrival_at).toISOString(),
      estimated_arrival_at: data.estimated_arrival_at
        ? new Date(data.estimated_arrival_at).toISOString()
        : null,
    };

    const changes: Record<string, { from: unknown; to: unknown }> = {};
    (['cargo_type', 'weight_kg', 'volume_m3', 'carrier_id', 'estimated_arrival_at'] as const).forEach(
      (field) => {
        if (existing[field] !== updated[field]) {
          changes[field] = { from: existing[field], to: updated[field] };
        }
      },
    );

    let itemId = Math.max(0, ...state.items.map((i) => i.id)) + 1;
    const newItems: ShipmentItem[] = items
      .filter((i) => i.description.trim() !== '')
      .map((i) => ({
        id: itemId++,
        shipment_id: id,
        description: i.description.trim(),
        quantity: Number(i.quantity) || 1,
        weight_kg: Number(i.weight_kg) || 0,
        volume_m3: Number(i.volume_m3) || 0,
      }));

    let extraEvents: TrackingEvent[] = [];
    if (data.transit_stop && data.transit_stop.location_name.trim()) {
      const existingEvents = state.trackingEvents.filter((e) => e.shipment_id === id);
      const alreadyHasStop = existingEvents.some(
        (e) =>
          e.location_name.toLowerCase() === data.transit_stop!.location_name.trim().toLowerCase(),
      );
      if (!alreadyHasStop) {
        const latestMs =
          existingEvents.length > 0
            ? Math.max(...existingEvents.map((e) => new Date(e.occurred_at).getTime()))
            : new Date(updated.expected_departure_at).getTime();
        const nextEventId = Math.max(0, ...state.trackingEvents.map((e) => e.id)) + 1;
        extraEvents = [
          {
            id: nextEventId,
            shipment_id: id,
            event_type: 'CHECKPOINT',
            occurred_at: new Date(latestMs + 2 * 3600 * 1000).toISOString(),
            location_name: data.transit_stop.location_name.trim(),
            latitude: data.transit_stop.latitude,
            longitude: data.transit_stop.longitude,
            description: 'Intermediate transit stop added via shipment route editor.',
            created_by: currentUser?.id ?? 1,
          },
        ];
      }
    }

    const nowIso = new Date().toISOString();
    const auditEntry: AuditLog | null =
      Object.keys(changes).length > 0
        ? {
            id: Math.max(0, ...state.auditLogs.map((a) => a.id)) + 1,
            actor_id: currentUser?.id ?? null,
            action: 'shipment.updated',
            entity_type: 'Shipment',
            entity_id: id,
            changes,
            created_at: nowIso,
          }
        : null;

    setState((prev) => ({
      ...prev,
      shipments: prev.shipments.map((s) => (s.id === id ? updated : s)),
      items: [...prev.items.filter((i) => i.shipment_id !== id), ...newItems],
      trackingEvents: [...prev.trackingEvents, ...extraEvents],
      auditLogs: auditEntry ? [auditEntry, ...prev.auditLogs] : prev.auditLogs,
    }));

    return updated;
  };

  const archiveShipment = (id: number) => {
    if (!currentUser || currentUser.role === UserRole.Customer) return;
    const nowIso = new Date().toISOString();
    const auditEntry: AuditLog = {
      id: Math.max(0, ...state.auditLogs.map((a) => a.id)) + 1,
      actor_id: currentUser.id,
      action: 'shipment.archived',
      entity_type: 'Shipment',
      entity_id: id,
      changes: null,
      created_at: nowIso,
    };
    setState((prev) => ({
      ...prev,
      shipments: prev.shipments.map((s) => (s.id === id ? { ...s, archived_at: nowIso } : s)),
      auditLogs: [auditEntry, ...prev.auditLogs],
    }));
  };

  const addTrackingEvent: StoreContextValue['addTrackingEvent'] = (shipmentId, data) => {
    if (!currentUser || currentUser.role === UserRole.Customer) {
      return { ok: false, errors: { event_type: 'Forbidden: Operator or Admin role required.' } };
    }
    const shipment = state.shipments.find((s) => s.id === shipmentId);
    if (!shipment) return { ok: false, errors: { event_type: 'Shipment not found.' } };

    if (!data.location_name.trim()) {
      return { ok: false, errors: { location_name: 'The location name field is required.' } };
    }

    const shipmentEvents = state.trackingEvents
      .filter((e) => e.shipment_id === shipmentId)
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());

    const lastEvent = shipmentEvents[0] ?? null;
    const occurredIso = new Date(data.occurred_at).toISOString();

    const validation = validateTrackingEvent({
      currentStatus: shipment.current_status,
      eventType: data.event_type,
      occurredAtIso: occurredIso,
      lastEventOccurredAtIso: lastEvent?.occurred_at ?? null,
      formatLastTime: (iso) => formatDateTime(iso),
    });

    if (!validation.valid) {
      return { ok: false, errors: validation.errors };
    }

    const nextEventId = Math.max(0, ...state.trackingEvents.map((e) => e.id)) + 1;
    const newEvent: TrackingEvent = {
      id: nextEventId,
      shipment_id: shipmentId,
      event_type: data.event_type,
      occurred_at: occurredIso,
      location_name: data.location_name.trim(),
      latitude: data.latitude,
      longitude: data.longitude,
      description: data.description?.trim() || null,
      created_by: currentUser?.id ?? 1,
    };

    const statusValues = Object.values(ShipmentStatus) as string[];
    const targetStatus = statusValues.includes(data.event_type)
      ? (data.event_type as ShipmentStatus)
      : null;

    const statusChanged = targetStatus !== null && targetStatus !== shipment.current_status;
    const nextEta =
      data.estimated_arrival_at !== undefined && data.estimated_arrival_at
        ? new Date(data.estimated_arrival_at).toISOString()
        : shipment.estimated_arrival_at;
    const etaChanged = nextEta !== shipment.estimated_arrival_at;
    const nowIso = new Date().toISOString();

    setState((prev) => {
      const nextShipments =
        statusChanged || etaChanged
          ? prev.shipments.map((s) =>
              s.id === shipmentId
                ? {
                    ...s,
                    current_status: statusChanged ? targetStatus! : s.current_status,
                    estimated_arrival_at: nextEta,
                  }
                : s,
            )
          : prev.shipments;

      const nextAuditLogs =
        statusChanged || etaChanged
          ? [
              {
                id: Math.max(0, ...prev.auditLogs.map((a) => a.id)) + 1,
                actor_id: currentUser?.id ?? null,
                action: statusChanged ? 'shipment.status_changed' : 'shipment.eta_updated',
                entity_type: 'Shipment',
                entity_id: shipmentId,
                changes: {
                  ...(statusChanged
                    ? { status: { from: shipment.current_status, to: targetStatus! } }
                    : {}),
                  ...(etaChanged
                    ? { estimated_arrival_at: { from: shipment.estimated_arrival_at, to: nextEta } }
                    : {}),
                },
                created_at: nowIso,
              },
              ...prev.auditLogs,
            ]
          : prev.auditLogs;

      const baseNotifId = Math.max(0, ...prev.notifications.map((n) => n.id)) + 1;
      const recipientIds = Array.from(new Set([shipment.customer_id, currentUser.id]));
      const nextNotifications =
        statusChanged || etaChanged
          ? [
              ...recipientIds.map((uid, idx) => ({
                id: baseNotifId + idx,
                user_id: uid,
                shipment_id: shipmentId,
                type: statusChanged ? 'STATUS_CHANGED' : 'ETA_UPDATED',
                title: statusChanged
                  ? `Shipment ${shipment.shipment_number} is now ${statusLabel(targetStatus!)}`
                  : `ETA updated for ${shipment.shipment_number}`,
                body: `Latest position: ${newEvent.location_name}${
                  etaChanged ? ` · New ETA ${formatDateTime(nextEta)}` : ''
                }`,
                read_at: null,
                created_at: nowIso,
              })),
              ...prev.notifications,
            ]
          : prev.notifications;

      return {
        ...prev,
        shipments: nextShipments,
        trackingEvents: [...prev.trackingEvents, newEvent],
        auditLogs: nextAuditLogs,
        notifications: nextNotifications,
      };
    });

    return { ok: true };
  };

  const createException = (
    shipmentId: number,
    data: {
      type: ExceptionType;
      severity: ExceptionSeverity;
      detected_at: string;
      location_name?: string;
      description?: string;
      estimated_arrival_at?: string | null;
    },
  ): { ok: boolean; errors?: Record<string, string> } => {
    if (!currentUser || currentUser.role === UserRole.Customer) {
      return { ok: false, errors: { type: 'Forbidden: Operator or Admin role required.' } };
    }
    const shipment = state.shipments.find((s) => s.id === shipmentId);
    if (!shipment) return { ok: false, errors: { type: 'Shipment not found.' } };

    if (!data.detected_at || Number.isNaN(new Date(data.detected_at).getTime())) {
      return { ok: false, errors: { detected_at: 'A valid detection timestamp is required.' } };
    }

    const detectedIso = new Date(data.detected_at).toISOString();
    const nextExcId = Math.max(0, ...state.exceptions.map((e) => e.id)) + 1;
    const nextEventId = Math.max(0, ...state.trackingEvents.map((e) => e.id)) + 1;

    const shipmentEvents = state.trackingEvents
      .filter((e) => e.shipment_id === shipmentId)
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());
    const lastEvent = shipmentEvents[0] ?? null;
    const origin = state.locations.find((l) => l.id === shipment.origin_location_id);

    const newException: ShipmentException = {
      id: nextExcId,
      shipment_id: shipmentId,
      type: data.type,
      severity: data.severity,
      status: ExceptionStatus.Open,
      description: data.description?.trim() || null,
      detected_at: detectedIso,
      resolved_at: null,
      resolved_by: null,
    };

    const label = exceptionTypeLabel(data.type);
    const linkedEvent: TrackingEvent = {
      id: nextEventId,
      shipment_id: shipmentId,
      event_type: 'EXCEPTION',
      occurred_at: detectedIso,
      location_name:
        data.location_name?.trim() || lastEvent?.location_name || origin?.name || 'Origin terminal',
      latitude: null,
      longitude: null,
      description: `${label} raised (${data.severity}${
        newException.description ? `): ${newException.description}` : ')'
      }`,
      created_by: currentUser?.id ?? 1,
      exception_id: nextExcId,
    };

    const nowIso = new Date().toISOString();
    const nextEta =
      data.estimated_arrival_at !== undefined && data.estimated_arrival_at
        ? new Date(data.estimated_arrival_at).toISOString()
        : shipment.estimated_arrival_at;
    const etaChanged = nextEta !== shipment.estimated_arrival_at;

    setState((prev) => ({
      ...prev,
      shipments: etaChanged
        ? prev.shipments.map((s) =>
            s.id === shipmentId ? { ...s, estimated_arrival_at: nextEta } : s,
          )
        : prev.shipments,
      exceptions: [...prev.exceptions, newException],
      trackingEvents: [...prev.trackingEvents, linkedEvent],
      auditLogs: [
        {
          id: Math.max(0, ...prev.auditLogs.map((a) => a.id)) + 1,
          actor_id: currentUser?.id ?? null,
          action: 'exception.created',
          entity_type: 'ShipmentException',
          entity_id: nextExcId,
          changes: {
            type: { from: null, to: data.type },
            severity: { from: null, to: data.severity },
            ...(etaChanged
              ? { estimated_arrival_at: { from: shipment.estimated_arrival_at, to: nextEta } }
              : {}),
          },
          created_at: nowIso,
        },
        ...prev.auditLogs,
      ],
      notifications: [
        ...Array.from(new Set([shipment.customer_id, currentUser.id])).map((uid, idx) => ({
          id: Math.max(0, ...prev.notifications.map((n) => n.id)) + 1 + idx,
          user_id: uid,
          shipment_id: shipmentId,
          type: 'EXCEPTION_CREATED',
          title: `${label} on ${shipment.shipment_number}`,
          body: `Severity ${data.severity}${
            newException.description ? ` — ${newException.description}` : ''
          }`,
          read_at: null,
          created_at: nowIso,
        })),
        ...prev.notifications,
      ],
    }));

    return { ok: true };
  };

  const updateExceptionStatus: StoreContextValue['updateExceptionStatus'] = (
    exceptionId,
    targetStatus,
  ) => {
    if (!currentUser || currentUser.role === UserRole.Customer) {
      return { ok: false, error: 'Forbidden: Operator or Admin role required.' };
    }
    const existing = state.exceptions.find((e) => e.id === exceptionId);
    if (!existing) return { ok: false, error: 'Exception not found.' };

    const check = validateExceptionTransition(existing.status, targetStatus);
    if (!check.valid) return { ok: false, error: check.error };

    const nowIso = new Date().toISOString();
    const shipment = state.shipments.find((s) => s.id === existing.shipment_id);

    setState((prev) => {
      const updatedExc: ShipmentException = {
        ...existing,
        status: targetStatus,
        resolved_at: targetStatus === ExceptionStatus.Resolved ? nowIso : existing.resolved_at,
        resolved_by:
          targetStatus === ExceptionStatus.Resolved
            ? (currentUser?.id ?? 1)
            : existing.resolved_by,
      };

      const nextNotifications =
        targetStatus === ExceptionStatus.Resolved && shipment
          ? [
              {
                id: Math.max(0, ...prev.notifications.map((n) => n.id)) + 1,
                user_id: currentUser?.id ?? 1,
                shipment_id: shipment.id,
                type: 'EXCEPTION_RESOLVED',
                title: `${exceptionTypeLabel(existing.type)} resolved on ${shipment.shipment_number}`,
                body: 'Resolution recorded by operations.',
                read_at: null,
                created_at: nowIso,
              },
              ...prev.notifications,
            ]
          : prev.notifications;

      return {
        ...prev,
        exceptions: prev.exceptions.map((e) => (e.id === exceptionId ? updatedExc : e)),
        auditLogs: [
          {
            id: Math.max(0, ...prev.auditLogs.map((a) => a.id)) + 1,
            actor_id: currentUser?.id ?? null,
            action: 'exception.status_changed',
            entity_type: 'ShipmentException',
            entity_id: exceptionId,
            changes: {
              status: { from: existing.status, to: targetStatus },
            },
            created_at: nowIso,
          },
          ...prev.auditLogs,
        ],
        notifications: nextNotifications,
      };
    });

    return { ok: true };
  };

  const markNotificationRead = (id: number) => {
    const nowIso = new Date().toISOString();
    setState((prev) => ({
      ...prev,
      notifications: prev.notifications.map((n) => (n.id === id ? { ...n, read_at: nowIso } : n)),
    }));
  };

  const markAllNotificationsRead = () => {
    const nowIso = new Date().toISOString();
    setState((prev) => ({
      ...prev,
      notifications: prev.notifications.map((n) =>
        n.read_at === null ? { ...n, read_at: nowIso } : n,
      ),
    }));
  };

  const saveCarrier: StoreContextValue['saveCarrier'] = (input) => {
    if (!currentUser || currentUser.role !== UserRole.Admin) {
      return { ok: false, errors: { name: 'Forbidden: Administrator role required.' } };
    }
    const errors: Record<string, string> = {};
    if (!input.name.trim()) errors.name = 'The name field is required.';
    if (!input.code.trim()) errors.code = 'The code field is required.';
    const duplicate = state.carriers.find(
      (c) => c.code.toUpperCase() === input.code.trim().toUpperCase() && c.id !== input.id,
    );
    if (duplicate) errors.code = 'The code has already been taken.';
    if (Object.keys(errors).length > 0) return { ok: false, errors };

    const code = input.code.trim().toUpperCase();
    if (input.id) {
      setState((prev) => ({
        ...prev,
        carriers: prev.carriers.map((c) =>
          c.id === input.id
            ? {
                ...c,
                name: input.name.trim(),
                code,
                service_type: input.service_type,
                contact_email: input.contact_email.trim(),
                is_active: input.is_active,
              }
            : c,
        ),
      }));
    } else {
      const nextId = Math.max(0, ...state.carriers.map((c) => c.id)) + 1;
      setState((prev) => ({
        ...prev,
        carriers: [
          ...prev.carriers,
          {
            id: nextId,
            name: input.name.trim(),
            code,
            service_type: input.service_type,
            contact_email: input.contact_email.trim(),
            is_active: input.is_active,
          },
        ],
      }));
    }
    return { ok: true };
  };

  const saveLocation: StoreContextValue['saveLocation'] = (input) => {
    if (!currentUser || currentUser.role !== UserRole.Admin) {
      return { ok: false, errors: { name: 'Forbidden: Administrator role required.' } };
    }
    const errors: Record<string, string> = {};
    if (!input.name.trim()) errors.name = 'The name field is required.';
    if (!input.code.trim()) errors.code = 'The code field is required.';
    if (!input.city.trim()) errors.city = 'The city field is required.';
    if (!input.country.trim()) errors.country = 'The country field is required.';
    if (Number.isNaN(Number(input.latitude)) || input.latitude < -90 || input.latitude > 90) {
      errors.latitude = 'Valid latitude (-90 to 90) is required.';
    }
    if (Number.isNaN(Number(input.longitude)) || input.longitude < -180 || input.longitude > 180) {
      errors.longitude = 'Valid longitude (-180 to 180) is required.';
    }
    const duplicate = state.locations.find(
      (l) => l.code.toUpperCase() === input.code.trim().toUpperCase() && l.id !== input.id,
    );
    if (duplicate) errors.code = 'The code has already been taken.';
    if (Object.keys(errors).length > 0) return { ok: false, errors };

    const code = input.code.trim().toUpperCase();
    if (input.id) {
      setState((prev) => ({
        ...prev,
        locations: prev.locations.map((l) =>
          l.id === input.id
            ? {
                ...l,
                name: input.name.trim(),
                code,
                city: input.city.trim(),
                country: input.country.trim(),
                latitude: Number(input.latitude),
                longitude: Number(input.longitude),
              }
            : l,
        ),
      }));
      return { ok: true, locationId: input.id };
    } else {
      const nextId = Math.max(0, ...state.locations.map((l) => l.id)) + 1;
      setState((prev) => ({
        ...prev,
        locations: [
          ...prev.locations,
          {
            id: nextId,
            name: input.name.trim(),
            code,
            city: input.city.trim(),
            country: input.country.trim(),
            latitude: Number(input.latitude),
            longitude: Number(input.longitude),
          },
        ],
      }));
      return { ok: true, locationId: nextId };
    }
  };

  const ensureLocationPreset: StoreContextValue['ensureLocationPreset'] = (preset) => {
    const code = preset.code.trim().toUpperCase();
    const existingLoc = state.locations.find(
      (l) =>
        l.code.toUpperCase() === code ||
        (l.name.toLowerCase() === preset.name.trim().toLowerCase() &&
          l.city.toLowerCase() === preset.city.trim().toLowerCase()),
    );
    if (existingLoc) {
      return existingLoc.id;
    }
    const nextId = Math.max(0, ...state.locations.map((l) => l.id)) + 1;
    const newLoc: Location = {
      id: nextId,
      name: preset.name.trim(),
      code,
      city: preset.city.trim(),
      country: preset.country.trim(),
      latitude: Number(preset.latitude),
      longitude: Number(preset.longitude),
    };
    setState((prev) => ({
      ...prev,
      locations: [...prev.locations, newLoc],
    }));
    return nextId;
  };

  const claimShipmentByNumber: StoreContextValue['claimShipmentByNumber'] = (shipmentNumber) => {
    if (!currentUser) {
      return { ok: false, error: 'Please sign in first.' };
    }
    const cleanRef = shipmentNumber.trim().toUpperCase();
    if (!cleanRef) {
      return { ok: false, error: 'Masukkan nomor referensi shipment (misal: CT0015MAC atau CT0001NFR).' };
    }
    const target = state.shipments.find(
      (s) => !s.archived_at && s.shipment_number.toUpperCase() === cleanRef,
    );
    if (!target) {
      return {
        ok: false,
        error: `Shipment "${cleanRef}" tidak ditemukan. Cek kembali kode resi (contoh: CT0001NFR).`,
      };
    }
    const nextNotifId = Math.max(0, ...state.notifications.map((n) => n.id)) + 1;
    setState((prev) => ({
      ...prev,
      shipments: prev.shipments.map((s) =>
        s.id === target.id ? { ...s, customer_id: currentUser.id } : s,
      ),
      notifications: [
        {
          id: nextNotifId,
          user_id: currentUser.id,
          shipment_id: target.id,
          type: 'SHIPMENT_LINKED',
          title: `Shipment ${target.shipment_number} linked to your account`,
          body: `${target.cargo_type} is now monitored on your Customer Portal.`,
          read_at: null,
          created_at: new Date().toISOString(),
        },
        ...prev.notifications,
      ],
    }));
    return { ok: true, shipmentId: target.id };
  };

  return (
    <StoreContext.Provider
      value={{
        state,
        currentUser,
        route,
        theme,
        toggleTheme,
        navigate,
        login,
        register,
        logout,
        switchUserById,
        resetDemoData,
        visibleShipments,
        assessShipmentDelay,
        updateScenarioConfig,
        saveUser,
        createShipment,
        updateShipment,
        archiveShipment,
        addTrackingEvent,
        createException,
        updateExceptionStatus,
        markNotificationRead,
        markAllNotificationsRead,
        saveCarrier,
        saveLocation,
        ensureLocationPreset,
        claimShipmentByNumber,
        formatDateTime,
        formatShortDate,
      }}
    >
      {children}
    </StoreContext.Provider>
  );
};

export function useStore(): StoreContextValue {
  const ctx = useContext(StoreContext);
  if (!ctx) throw new Error('useStore must be used inside StoreProvider');
  return ctx;
}
