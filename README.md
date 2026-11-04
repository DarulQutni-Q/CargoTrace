# CargoTrace

Global shipment visibility and tracking for a logistics operation — the
final project for Web Programming 2 (UAS).

Logistics teams lose track of shipments because the information lives in
phone calls, spreadsheets, and carrier portals. Customers cannot see
where their cargo is. Delays surface too late. CargoTrace puts every
shipment in one place: a tracked lifecycle, a historical journey
timeline, a live world map, deterministic delay detection, exception
management, and an operations dashboard — with a REST API for programmatic
access.

## Features

- **Shipment lifecycle** — `CREATED → BOOKED → PICKED_UP → IN_TRANSIT →
  DELIVERED`, transitions validated server-side against a single allowed
  map; `DELIVERED` is terminal.
- **Tracking timeline** — append-only events (never overwritten),
  chronological validation, current position from the latest valid event.
- **ETA & delay rule** — deterministic classification (LOW / MEDIUM /
  HIGH) from configurable thresholds shown with the reason and values used.
- **Exceptions** — the six brief-mandated types with an OPEN →
  INVESTIGATING → RESOLVED lifecycle and server-stamped resolution.
- **World map** — Leaflet with dark Esri/OSM tiles, great-circle routes,
  live position markers, popups; geocoding through OpenStreetMap Nominatim
  with explicit timeout/error/empty handling.
- **Dashboard** — real database numbers (no static values), role-scoped.
- **Search & filter** — reference, status, carrier, origin, destination,
  delay, date range; server-side sorting and pagination.
- **Notifications** — in-app bell and page for status changes and
  exception lifecycles on shipments the user may see.
- **Audit log** — actor, action, entity, and changed values for every
  important change; administrator only.
- **REST API** — Sanctum tokens, JSON envelope, 200/201/401/403/404/422
  semantics, Postman collection included.

### Roles

| Role | Can |
| --- | --- |
| Administrator | users & config, carriers & locations, all shipments, audit log |
| Logistics Operator | create/update shipments, record tracking events, manage exceptions |
| Customer | view own shipments, timeline, map, ETA, and relevant notifications |

Authorization is enforced server-side on every route and endpoint; the
interface only mirrors what the server allows.

## Tech stack

PHP 8.3+ · Laravel 12 · Blade · Tailwind CSS v4 · vanilla JavaScript +
Fetch · MySQL 8 · Eloquent + migrations/seeders · Laravel Sanctum ·
Laravel HTTP Client · Leaflet + Esri/OSM tiles · Chart.js · PHPUnit.

## Architecture

HTTP handling stays separate from business logic. Controllers validate
input; services own the rules and the transactions.

```mermaid
flowchart LR
    Browser --> Routes["Routes + Middleware"]
    Routes --> Controllers
    Controllers --> Services["Service Layer"]
    Services --> Models["Models / Eloquent"]
    Models --> DB[(MySQL)]
    Services --> Ext["External APIs (geocoding)"]
```

Business services: `ShipmentService`, `TrackingService`, `EtaService`,
`ExceptionService`, plus `NotificationService`, `AuditLogger`, and
`GeocodingService`. Status transitions and the delay thresholds live in
one place each (`ShipmentStatus::allowedNext()`, `config/cargotrace.php`).

## Data model

```mermaid
erDiagram
    USERS ||--o{ SHIPMENTS : "is customer of"
    CARRIERS ||--o{ SHIPMENTS : "carries"
    LOCATIONS ||--o{ SHIPMENTS : "origin / destination"
    SHIPMENTS ||--o{ SHIPMENT_ITEMS : "contains"
    SHIPMENTS ||--o{ TRACKING_EVENTS : "journey history"
    SHIPMENTS ||--o{ SHIPMENT_EXCEPTIONS : "disruptions"
    USERS ||--o{ TRACKING_EVENTS : "records"
    USERS ||--o{ NOTIFICATIONS : "receives"
    USERS ||--o{ AUDIT_LOGS : "acts in"
```

Every relation and design decision is documented in the codebase and
defensible in review: unique shipment numbers and carrier/location codes,
restricting foreign keys, soft delete on shipments only, and indexes for
the list, timeline, and dashboard queries.

## Setup

Requirements: PHP 8.3+, Composer, Node 20+, Docker (for MySQL).

```bash
git clone <repository-url> && cd cargotrace
composer install
cp .env.example .env            # adjust credentials if needed
php artisan key:generate
docker compose up -d            # MySQL 8.4 on 127.0.0.1:3306
php artisan migrate --seed      # schema + demo data
npm install && npm run build
php artisan serve               # http://localhost:8000
```

### Demo accounts (password: `password`)

| Role | Email |
| --- | --- |
| Administrator | admin@cargotrace.test |
| Logistics Operator | operator@cargotrace.test |
| Customer | customer@cargotrace.test |

The seeder builds a Southeast Asia → Europe corridor: fictional carriers,
real port locations with coordinates, 14 shipments across every status
including on-time and delayed estimates, believable tracking events, and
open, investigating, and resolved exceptions.

## Tests

```bash
php artisan test        # 69 tests: auth, CRUD, authorization, transitions,
                        # chronology, ETA rule, exceptions, API, map data
```

The suite runs on SQLite in-memory for speed; the application itself runs
on MySQL 8. Negative cases are covered too: invalid logins, invalid status
transitions, out-of-order events, foreign-resource access, and geocoding
timeouts, errors, and empty responses.

## API

`postman/CargoTrace.postman_collection.json` covers every endpoint — set
`{{base_url}}`, run **Login** (the token is captured automatically), and
explore. Highlights:

```
POST   /api/register                 POST   /api/login
POST   /api/logout                   GET    /api/user
GET    /api/shipments?status=&delayed=&sort=...
POST   /api/shipments                GET/PUT/DELETE /api/shipments/{id}
GET/POST /api/shipments/{id}/tracking
GET/POST /api/shipments/{id}/exceptions
PATCH  /api/exceptions/{id}          GET/POST /api/carriers  GET/POST /api/locations
```

Errors use one JSON envelope (`message` + field `errors`), validation
failures return 422, unauthorized access 401/403, and unknown or
foreign-owned resources 404.

## Group scenario

Delay thresholds, route, primary exception, and KPI come from
`config/cargotrace.php` + `.env` (`CARGOTRACE_*`) — never from code. The
brief's example defaults are LOW ≤ 6 h, MEDIUM ≤ 24 h, HIGH beyond; the
lecturer's group values replace them without touching a line of PHP.

## Deployment

The app targets a standard PHP host: set the environment variables from
`.env.example`, point `DB_*` at MySQL 8, run `php artisan migrate --seed`,
build assets with `npm run build`, and serve `public/`. Secrets stay in
the environment; nothing in the repository needs changing per host.

## Known limitations & future work

- Notifications are in-app only; email delivery is the documented next step.
- One external integration (geocoding); a weather provider is the natural
  second integration and is explicitly **not** used as delay evidence.
- `estimated_arrival_at` is operator/carrier-fed; automating it from
  tracking telemetry is future work.
- Single dark theme; a light theme is out of scope for this milestone.

## Attribution

All libraries, fonts, icons, tile providers, and external sources are
recorded in [ATTRIBUTION.md](ATTRIBUTION.md), as the project brief
requires.
