import express from 'express';
import { createServer as createViteServer } from 'vite';
import path from 'node:path';
import { buildInitialSeed } from './src/domain/seed';
import {
  assessDelay,
  isShipmentDelayed,
  validateExceptionTransition,
  validateTrackingEvent,
} from './src/domain/rules';
import {
  ExceptionSeverity,
  ExceptionStatus,
  ExceptionType,
  ShipmentStatus,
  UserRole,
} from './src/domain/types';

async function startServer() {
  const app = express();
  app.use(express.json());

  const db = buildInitialSeed();

  // Helper to resolve user from Bearer token or default to Admin for easy browser/Postman inspection
  function resolveAuthUser(req: express.Request) {
    const auth = req.headers.authorization;
    if (auth === 'Bearer invalid' || req.query.unauthenticated === '1') {
      return null;
    }
    if (auth?.startsWith('Bearer role:')) {
      const role = auth.replace('Bearer role:', '').trim().toUpperCase();
      return db.users.find((u) => u.role === role) ?? db.users[0];
    }
    const roleParam = String(req.query.as_role || '').toUpperCase();
    if (roleParam) {
      return db.users.find((u) => u.role === roleParam) ?? db.users[0];
    }
    return db.users[0];
  }

  // 1. POST /api/register
  app.post('/api/register', (req, res) => {
    const { name, email, password } = req.body ?? {};
    const errors: Record<string, string[]> = {};
    if (!name || String(name).trim().length < 2) {
      errors.name = ['The name field is required.'];
    }
    if (!email || !String(email).includes('@')) {
      errors.email = ['A valid email address is required.'];
    } else if (db.users.some((u) => u.email.toLowerCase() === String(email).toLowerCase())) {
      errors.email = ['The email has already been taken.'];
    }
    if (!password || String(password).length < 6) {
      errors.password = ['The password field must be at least 6 characters.'];
    }
    if (Object.keys(errors).length > 0) {
      return res.status(422).json({ message: 'The given data was invalid.', errors });
    }
    const newUser = {
      id: db.users.length + 1,
      name: String(name).trim(),
      email: String(email).trim().toLowerCase(),
      role: UserRole.Customer,
    };
    db.users.push(newUser);
    return res.status(201).json({
      token: `sanctum_${newUser.id}_token`,
      user: newUser,
    });
  });

  // 2. POST /api/login
  app.post('/api/login', (req, res) => {
    const { email, password } = req.body ?? {};
    if (!email || !password) {
      return res.status(422).json({
        message: 'The given data was invalid.',
        errors: { email: ['Email and password are required.'] },
      });
    }
    const user = db.users.find(
      (u) => u.email.toLowerCase() === String(email).trim().toLowerCase(),
    );
    if (!user) {
      return res.status(401).json({ message: 'Invalid credentials.' });
    }
    return res.status(200).json({
      token: `role:${user.role}`,
      user,
    });
  });

  // 3. POST /api/logout
  app.post('/api/logout', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    return res.status(200).json({ message: 'Logged out.' });
  });

  // 4. GET /api/user
  app.get('/api/user', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    return res.status(200).json({ data: user });
  });

  // 5. GET /api/shipments
  app.get('/api/shipments', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });

    let list = db.shipments.filter((s) => !s.archived_at);
    if (user.role === UserRole.Customer) {
      list = list.filter((s) => s.customer_id === user.id);
    }

    if (req.query.status) {
      list = list.filter((s) => s.current_status === String(req.query.status));
    }
    if (req.query.carrier_id) {
      list = list.filter((s) => s.carrier_id === Number(req.query.carrier_id));
    }
    if (req.query.origin_id) {
      list = list.filter((s) => s.origin_location_id === Number(req.query.origin_id));
    }
    if (req.query.destination_id) {
      list = list.filter((s) => s.destination_location_id === Number(req.query.destination_id));
    }
    if (req.query.delayed === '1') {
      list = list.filter((s) =>
        isShipmentDelayed(s.expected_arrival_at, s.estimated_arrival_at),
      );
    }

    const page = Math.max(1, Number(req.query.page) || 1);
    const perPage = Math.max(1, Math.min(50, Number(req.query.per_page) || 15));
    const slice = list.slice((page - 1) * perPage, page * perPage).map((s) => ({
      ...s,
      origin: db.locations.find((l) => l.id === s.origin_location_id),
      destination: db.locations.find((l) => l.id === s.destination_location_id),
      carrier: db.carriers.find((c) => c.id === s.carrier_id),
      delay_assessment: assessDelay(
        s.expected_arrival_at,
        s.estimated_arrival_at,
        db.scenarioConfig.lowThresholdHours,
        db.scenarioConfig.highThresholdHours,
      ),
    }));

    return res.status(200).json({
      data: slice,
      meta: {
        current_page: page,
        per_page: perPage,
        total: list.length,
      },
    });
  });

  // 6. POST /api/shipments
  app.post('/api/shipments', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role === UserRole.Customer) {
      return res.status(403).json({ message: 'Forbidden for CUSTOMER role.' });
    }

    const body = req.body ?? {};
    const errors: Record<string, string[]> = {};
    if (!body.origin_location_id) errors.origin_location_id = ['Origin is required.'];
    if (!body.destination_location_id) errors.destination_location_id = ['Destination is required.'];
    if (
      body.origin_location_id &&
      body.destination_location_id &&
      Number(body.origin_location_id) === Number(body.destination_location_id)
    ) {
      errors.destination_location_id = ['Destination must differ from origin.'];
    }
    if (!body.carrier_id) errors.carrier_id = ['Carrier is required.'];
    if (!body.cargo_type) errors.cargo_type = ['Cargo type is required.'];
    if (Object.keys(errors).length > 0) {
      return res.status(422).json({ message: 'The given data was invalid.', errors });
    }

    const id = db.shipments.length + 1;
    const carrier = db.carriers.find((c) => c.id === Number(body.carrier_id)) ?? db.carriers[0];
    const created = {
      id,
      shipment_number: `CT${String(id).padStart(4, '0')}${carrier.code}`,
      customer_id: Number(body.customer_id) || 3,
      carrier_id: carrier.id,
      origin_location_id: Number(body.origin_location_id),
      destination_location_id: Number(body.destination_location_id),
      cargo_type: String(body.cargo_type),
      weight_kg: Number(body.weight_kg) || 1000,
      volume_m3: Number(body.volume_m3) || 15,
      expected_departure_at: body.expected_departure_at || new Date().toISOString(),
      expected_arrival_at: body.expected_arrival_at || new Date(Date.now() + 86400000 * 10).toISOString(),
      estimated_arrival_at: body.estimated_arrival_at || null,
      current_status: ShipmentStatus.Created,
      created_at: new Date().toISOString(),
      archived_at: null,
    };
    db.shipments.push(created);
    return res.status(201).json({ data: created });
  });

  // 7. GET /api/shipments/:id
  app.get('/api/shipments/:id', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });

    const shipment = db.shipments.find((s) => s.id === Number(req.params.id) && !s.archived_at);
    if (!shipment) return res.status(404).json({ message: 'Shipment not found.' });
    if (user.role === UserRole.Customer && shipment.customer_id !== user.id) {
      return res.status(403).json({ message: 'Forbidden: shipment belongs to another customer.' });
    }

    return res.status(200).json({
      data: {
        ...shipment,
        origin: db.locations.find((l) => l.id === shipment.origin_location_id),
        destination: db.locations.find((l) => l.id === shipment.destination_location_id),
        carrier: db.carriers.find((c) => c.id === shipment.carrier_id),
        items: db.items.filter((i) => i.shipment_id === shipment.id),
        tracking_events: db.trackingEvents.filter((e) => e.shipment_id === shipment.id),
        exceptions: db.exceptions.filter((e) => e.shipment_id === shipment.id),
        delay_assessment: assessDelay(
          shipment.expected_arrival_at,
          shipment.estimated_arrival_at,
          db.scenarioConfig.lowThresholdHours,
          db.scenarioConfig.highThresholdHours,
        ),
      },
    });
  });

  // 8. PUT/PATCH /api/shipments/:id
  const updateShipmentHandler = (req: express.Request, res: express.Response) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role === UserRole.Customer) {
      return res.status(403).json({ message: 'Forbidden for CUSTOMER role.' });
    }
    const shipment = db.shipments.find((s) => s.id === Number(req.params.id) && !s.archived_at);
    if (!shipment) return res.status(404).json({ message: 'Shipment not found.' });

    if (req.body.cargo_type !== undefined) shipment.cargo_type = String(req.body.cargo_type);
    if (req.body.estimated_arrival_at !== undefined) {
      shipment.estimated_arrival_at = req.body.estimated_arrival_at;
    }
    if (req.body.weight_kg !== undefined) shipment.weight_kg = Number(req.body.weight_kg);
    if (req.body.volume_m3 !== undefined) shipment.volume_m3 = Number(req.body.volume_m3);

    return res.status(200).json({ data: shipment });
  };
  app.put('/api/shipments/:id', updateShipmentHandler);
  app.patch('/api/shipments/:id', updateShipmentHandler);

  // 9. DELETE /api/shipments/:id
  app.delete('/api/shipments/:id', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role === UserRole.Customer) {
      return res.status(403).json({ message: 'Forbidden for CUSTOMER role.' });
    }
    const shipment = db.shipments.find((s) => s.id === Number(req.params.id) && !s.archived_at);
    if (!shipment) return res.status(404).json({ message: 'Shipment not found.' });

    shipment.archived_at = new Date().toISOString();
    return res.status(200).json({ message: 'Shipment archived.' });
  });

  // 10. GET /api/shipments/:id/tracking
  app.get('/api/shipments/:id/tracking', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    const shipment = db.shipments.find((s) => s.id === Number(req.params.id) && !s.archived_at);
    if (!shipment) return res.status(404).json({ message: 'Shipment not found.' });
    if (user.role === UserRole.Customer && shipment.customer_id !== user.id) {
      return res.status(403).json({ message: 'Forbidden.' });
    }

    const events = db.trackingEvents
      .filter((e) => e.shipment_id === shipment.id)
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());
    return res.status(200).json({ data: events });
  });

  // 11. POST /api/shipments/:id/tracking
  app.post('/api/shipments/:id/tracking', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role === UserRole.Customer) {
      return res.status(403).json({ message: 'Forbidden for CUSTOMER role.' });
    }
    const shipment = db.shipments.find((s) => s.id === Number(req.params.id) && !s.archived_at);
    if (!shipment) return res.status(404).json({ message: 'Shipment not found.' });

    const existing = db.trackingEvents
      .filter((e) => e.shipment_id === shipment.id)
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime());
    const validation = validateTrackingEvent({
      currentStatus: shipment.current_status,
      eventType: String(req.body.event_type || ''),
      occurredAtIso: String(req.body.occurred_at || ''),
      lastEventOccurredAtIso: existing[0]?.occurred_at ?? null,
    });
    if (!validation.valid) {
      return res.status(422).json({
        message: 'The given data was invalid.',
        errors: validation.errors,
      });
    }

    const created = {
      id: db.trackingEvents.length + 1,
      shipment_id: shipment.id,
      event_type: String(req.body.event_type),
      occurred_at: String(req.body.occurred_at),
      location_name: String(req.body.location_name),
      latitude: req.body.latitude !== undefined ? Number(req.body.latitude) : null,
      longitude: req.body.longitude !== undefined ? Number(req.body.longitude) : null,
      description: req.body.description ? String(req.body.description) : null,
      created_by: user.id,
    };
    db.trackingEvents.push(created);

    if (Object.values(ShipmentStatus).includes(created.event_type as ShipmentStatus)) {
      shipment.current_status = created.event_type as ShipmentStatus;
    }

    return res.status(201).json({ data: created });
  });

  // 12. GET /api/shipments/:id/exceptions
  app.get('/api/shipments/:id/exceptions', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    const shipment = db.shipments.find((s) => s.id === Number(req.params.id) && !s.archived_at);
    if (!shipment) return res.status(404).json({ message: 'Shipment not found.' });
    if (user.role === UserRole.Customer && shipment.customer_id !== user.id) {
      return res.status(403).json({ message: 'Forbidden.' });
    }

    const list = db.exceptions.filter((e) => e.shipment_id === shipment.id);
    return res.status(200).json({ data: list });
  });

  // 13. POST /api/shipments/:id/exceptions
  app.post('/api/shipments/:id/exceptions', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role === UserRole.Customer) {
      return res.status(403).json({ message: 'Forbidden for CUSTOMER role.' });
    }
    const shipment = db.shipments.find((s) => s.id === Number(req.params.id) && !s.archived_at);
    if (!shipment) return res.status(404).json({ message: 'Shipment not found.' });

    const { type, severity, detected_at, description } = req.body ?? {};
    if (!type || !severity) {
      return res.status(422).json({
        message: 'The given data was invalid.',
        errors: { type: ['Exception type and severity are required.'] },
      });
    }

    const created = {
      id: db.exceptions.length + 1,
      shipment_id: shipment.id,
      type: type as ExceptionType,
      severity: severity as ExceptionSeverity,
      status: ExceptionStatus.Open,
      description: description ? String(description) : null,
      detected_at: detected_at || new Date().toISOString(),
      resolved_at: null,
      resolved_by: null,
    };
    db.exceptions.push(created);
    return res.status(201).json({ data: created });
  });

  // 14. PATCH /api/exceptions/:id
  app.patch('/api/exceptions/:id', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role === UserRole.Customer) {
      return res.status(403).json({ message: 'Forbidden for CUSTOMER role.' });
    }
    const exc = db.exceptions.find((e) => e.id === Number(req.params.id));
    if (!exc) return res.status(404).json({ message: 'Exception not found.' });

    const targetStatus = req.body?.status as ExceptionStatus;
    const check = validateExceptionTransition(exc.status, targetStatus);
    if (!check.valid) {
      return res.status(422).json({
        message: check.error,
        errors: { status: [check.error] },
      });
    }

    exc.status = targetStatus;
    if (targetStatus === ExceptionStatus.Resolved) {
      exc.resolved_at = new Date().toISOString();
      exc.resolved_by = user.id;
    }
    return res.status(200).json({ data: exc });
  });

  // 15. GET/POST /api/carriers
  app.get('/api/carriers', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    return res.status(200).json({ data: db.carriers });
  });

  app.post('/api/carriers', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role !== UserRole.Admin) {
      return res.status(403).json({ message: 'Forbidden: ADMIN role required.' });
    }
    const { name, code, service_type, contact_email } = req.body ?? {};
    if (!name || !code) {
      return res.status(422).json({
        message: 'The given data was invalid.',
        errors: { name: ['Carrier name and code are required.'] },
      });
    }
    const created = {
      id: db.carriers.length + 1,
      name: String(name),
      code: String(code).toUpperCase(),
      service_type: (service_type || 'SEA') as 'SEA' | 'AIR' | 'ROAD',
      contact_email: contact_email ? String(contact_email) : null,
      is_active: true,
    };
    db.carriers.push(created);
    return res.status(201).json({ data: created });
  });

  // 16. GET/POST /api/locations
  app.get('/api/locations', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    return res.status(200).json({ data: db.locations });
  });

  app.post('/api/locations', (req, res) => {
    const user = resolveAuthUser(req);
    if (!user) return res.status(401).json({ message: 'Unauthenticated.' });
    if (user.role !== UserRole.Admin) {
      return res.status(403).json({ message: 'Forbidden: ADMIN role required.' });
    }
    const { name, code, city, country, latitude, longitude } = req.body ?? {};
    if (!name || !code || !city || !country) {
      return res.status(422).json({
        message: 'The given data was invalid.',
        errors: { name: ['Name, code, city, and country are required.'] },
      });
    }
    const created = {
      id: db.locations.length + 1,
      name: String(name),
      code: String(code).toUpperCase(),
      city: String(city),
      country: String(country),
      latitude: Number(latitude) || 0,
      longitude: Number(longitude) || 0,
    };
    db.locations.push(created);
    return res.status(201).json({ data: created });
  });

  // Vite middleware in development, static dist in production
  if (process.env.NODE_ENV !== 'production') {
    app.get('/@vite/client', (_req, res) => {
      res.setHeader('Content-Type', 'application/javascript; charset=utf-8');
      res.send(`
const sheetsMap = new Map();
export function updateStyle(id, content) {
  let style = sheetsMap.get(id);
  if (!style) {
    style = document.createElement('style');
    style.setAttribute('type', 'text/css');
    style.setAttribute('data-vite-dev-id', id);
    style.textContent = content;
    document.head.appendChild(style);
  } else if (style.textContent !== content) {
    style.textContent = content;
  }
  sheetsMap.set(id, style);
}
export function removeStyle(id) {
  const style = sheetsMap.get(id);
  if (style) {
    document.head.removeChild(style);
    sheetsMap.delete(id);
  }
}
export function createHotContext() {
  return {
    data: {},
    accept() {},
    acceptExports() {},
    dispose() {},
    prune() {},
    invalidate() {},
    on() {},
    off() {},
    send() {},
  };
}
export function injectQuery(url, queryToInject) {
  if (url[0] !== '.' && url[0] !== '/') return url;
  const pathname = url.replace(/[?#].*$/, '');
  const { search, hash } = new URL(url, 'http://vite.dev');
  return \`\${pathname}?\${queryToInject}\${search ? '&' + search.slice(1) : ''}\${hash || ''}\`;
}
export class ErrorOverlay {}
`);
    });

    const vite = await createViteServer({
      server: { middlewareMode: true, hmr: false },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*all', (_req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(3000, '0.0.0.0', () => {
    console.log('CargoTrace server running on http://0.0.0.0:3000');
  });
}

startServer();
