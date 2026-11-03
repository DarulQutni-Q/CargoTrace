# CargoTrace — Product Requirements Document (PRD)

> **Source of truth:** `CargoTrace UAS Final Project Brief.pptx` (25 slides, revision `4f44589cb4e84c9a`).
> **Course:** Web Programming 2 — Final Exam (UAS). **Project type:** portfolio-grade web application, not a CRUD demo [S1].

## 0. How to read this document

- Every requirement carries a traceability tag `[S#]` that points to the slide it comes from. Nothing may be treated as a brief requirement if it has no tag.
- `[DESIGN]` = an engineering decision the brief leaves open. Allowed, but must stay consistent and be documented.
- `[TBD]` = must be confirmed before or during the build (usually group-specific config). Never assume a value for these.
- Companion documents: [RULES.md](RULES.md) (execution contract), [DESIGN.md](DESIGN.md) (binding visual spec), [DATA_MODEL.md](DATA_MODEL.md), [API_SPEC.md](API_SPEC.md), [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md), [ACCEPTANCE_CHECKLIST.md](ACCEPTANCE_CHECKLIST.md), [GROUP_SCENARIO.md](GROUP_SCENARIO.md).
- If this document contradicts the PPTX, the PPTX wins and this document must be fixed.

---

## 1. Product overview

- **Name:** CargoTrace [S1]
- **One-liner:** Global Shipment Visibility & Tracking Platform [S1]
- **Domain:** shipment visibility for a logistics company and its customers [S3]

### 1.1 Problem being solved [S3]
1. Shipment information is scattered and hard to monitor.
2. Customers do not know the latest status and journey history of their shipments.
3. Delays and exceptions are not visible in one central place.
4. Operators need a dashboard to prioritize follow-up actions.

### 1.2 Solution [S3]
1. Manage shipments, carriers, locations, and tracking events.
2. Display a journey timeline and location points on a map.
3. Detect lateness using defined, deterministic rules.
4. Manage exceptions and show operational metrics.

### 1.3 Positioning principle [S1]
> "Build a software portfolio project — not just a CRUD website."

Consequences: working business rules, real engineering quality, understandable code, and documentation that a recruiter can verify. Functionality must actually run, not just render [S2].

---

## 2. Goals

### 2.1 Learning outcomes [S2]
- Translate business needs into features and a data model.
- Build a Laravel application with MySQL and correct relations.
- Implement business rules, a REST API, and external service integration.
- Test, document, and deploy the application.

### 2.2 Portfolio outcomes [S2]
- Public GitHub repository with a meaningful commit history.
- An online application a recruiter can try.
- English README and technical documentation.
- A demo plus a code ownership interview.

### 2.3 Assessment principles [S2]
- Functions must run, not only display.
- The student must understand every line of submitted code.
- Engineering quality is graded together with feature quality.
- Third-party source code must be attributed.

---

## 3. Scope

### 3.1 In scope (mandatory) [S4]
- Shipment lifecycle and status transitions.
- Tracking timeline and location map.
- ETA/delay rules and exception management.
- Role-based access control.
- REST API, external API integration, dashboard, testing, deployment.

The MVP must remain **engineering-complex**; it does not need to imitate a full enterprise system [S4].

### 3.2 Out of scope (must NOT be built) [S4]
- Direct integration with real ports, customs, or actual carriers.
- Real financial payments or commercial transactions.
- Real-time GPS tracking from physical devices.
- Mandatory machine learning / AI prediction.
- Complex microservices architecture.

### 3.3 Demo data policy [S4]
Synthetic demo data is allowed, but it must be **consistent, documented, and realistic**. Seeding is a first-class requirement, not an afterthought (see DATA_MODEL.md § Seed data policy).

---

## 4. Users, roles, and permissions

> Rule: **every route, page, and endpoint must enforce authorization on the server** [S5]. Hiding buttons in the frontend is not authorization [S13].

| Role | Permissions [S5] |
|---|---|
| **Administrator** | Manage users and configuration. Manage carriers and locations. View all shipments and the dashboard. View the audit log. |
| **Logistics Operator** | Create and update shipments. Add tracking events. Manage exceptions. View operational shipments. |
| **Customer** | View only their own shipments. View status, ETA, and timeline. View the route on the map. View notifications and exceptions relevant to them. |

Authorization matrix per endpoint is defined in [API_SPEC.md § Authorization matrix].

---

## 5. Functional requirements

### FR-1 — Shipment lifecycle [S6]

- **Canonical status flow (brief's example of a valid flow):**
  `CREATED → BOOKED → PICKED_UP → IN_TRANSIT → DELIVERED`
- Transitions are validated in the **backend**. Free-form status input is not allowed.
- Terminal status such as `DELIVERED` must not return to an initial status **without a special procedure**. `[DESIGN]` The special procedure is an explicit, authorized, audited revert action (see IMPLEMENTATION_PLAN.md P10); it is not part of the normal transition map.
- Status must be controlled by business rules, not by unvalidated input [S6].
- Allowed-transition map: see API_SPEC.md § Status transition rules.
- **Minimum shipment fields [S6]:** shipment number, customer, carrier, origin, destination, cargo type, weight/volume, expected departure, expected arrival, current status.

### FR-2 — Tracking & timeline [S7]

**Tracking event data [S7]:**
- Shipment reference and status/event type.
- Timestamp of the event and the location where it happened.
- Latitude/longitude when available.
- Description and the user who created the event.
- Validate chronological order and the shipment relation.

**Timeline rules [S7]:**
- Events are **historical** — adding a new event must never overwrite or remove old events.
- Display events in chronological order.
- Show status, time, location, and description per event.
- Visually distinguish normal events from exception events.
- "Current location" = location from the latest valid event.
- A customer can only access timelines of shipments they own.

### FR-3 — ETA, delay & exceptions [S8]

**ETA & delay [S8]:**
- Compare `estimated arrival` against `expected arrival`.
- Compute the lateness duration.
- Classify lateness with **configurable thresholds** (per group; see GROUP_SCENARIO.md).
- Display the reason and the values used for the classification.
- Rule: if ETA > expected arrival → mark the shipment delayed.
- Brief's example thresholds: 0–6 h → LOW; > 6–24 h → MEDIUM; > 24 h → HIGH. `[TBD]` Actual values come from the group configuration [S20, S23].
- Rules must be **deterministic and testable**; AI/ML is not required [S8].

**Exception management [S8]:**
- Exception types (exactly these six): `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, `DOCUMENTATION_ISSUE`.
- Severity and lifecycle status: `OPEN / INVESTIGATING / RESOLVED`.
- Store the detection time and the resolution time.

### FR-4 — Map [S9]

- Use **Leaflet.js + OpenStreetMap**.
- Markers for origin, destination, and tracking points.
- Popups contain status, time, and location.
- Store coordinates consistently (see DATA_MODEL.md).
- Include attribution and comply with the tile provider's policy.

### FR-5 — External API integration [S9]

- Minimum **one** integration: geocoding or weather. **Two are recommended.** `[TBD]` which one(s) — confirm with group config.
- All requests go through the **Laravel HTTP Client**.
- Handle timeout, error responses, and empty data explicitly.
- API keys live in environment variables only — never in the repository.
- Weather data must **not** be presented as delay prediction evidence [S9].

### FR-6 — Dashboard [S10]

All numbers come from the database — no static values [S10].

Metrics:
1. Total shipments.
2. In-transit, delivered, delayed counts.
3. Open exceptions count.
4. Shipments by status; shipments by carrier.
5. Delay rate / exception rate. `[DESIGN]` Formulas: delay rate = delayed shipments ÷ total shipments; exception rate = shipments with ≥ 1 exception ÷ total shipments.

Charts use **Chart.js** [S14]. Dashboard is role-scoped: customers see only their own data [S5, S7]. `[DESIGN derived]`

### FR-7 — Search & filter [S10]

- Search by shipment/reference number.
- Filters: status, carrier, origin, destination.
- Date range and delay status.
- Sorting and **server-side pagination** (always on the server, never client-side only).

### FR-8 — Notifications & audit [S10]

- **In-app notification** is the minimum. Email notification is an advanced feature `[TBD]` — only if decided in IMPLEMENTATION_PLAN.md P13.
- Audit log records important changes.
- Each audit entry records: **actor, action, time, and the related entity** [S10].

---

## 6. Data model requirements [S11]

The brief mandates these nine entities (full design in [DATA_MODEL.md](DATA_MODEL.md)):

| Table | Role |
|---|---|
| `users` | accounts & roles |
| `shipments` | core shipment |
| `shipment_items` | cargo details |
| `carriers` | logistics operators |
| `locations` | origin/destination |
| `tracking_events` | journey history |
| `shipment_exceptions` | disruptions |
| `notifications` | user notifications |
| `audit_logs` | change trail |

Constraints policy [S11]: foreign keys, unique constraints, relevant indexes, timestamps; **soft delete only where actually needed**.

The ERD must be produced and **explained by the student** — cardinality and design decisions must be defensible in the code interview [S11, S19].

---

## 7. REST API requirements [S12, S13]

Full contract in [API_SPEC.md](API_SPEC.md). Non-negotiable qualities [S12, S13]:

- JSON request/response everywhere.
- Correct status codes: 200/201 success; 401/403 auth; 404 missing resource; 422 validation.
- Validation, authentication, authorization, pagination on every applicable endpoint.
- Consistent error format; error responses must not leak stack traces.
- A Postman collection ships with the project.
- Automated tests must cover unauthorized access attempts, not only happy paths [S13].
- Authentication tokens via **Laravel Sanctum** [S12, S14].
- The brief's endpoint list is the minimum; extra endpoints are allowed but must be added to API_SPEC.md **before** implementation.

---

## 8. Architecture [S15]

> Separate HTTP handling from business logic. Do not put all logic in controllers.

```
Browser → Routes + Middleware → Controllers → Service Layer → Models / Eloquent
```

- **Business services** (named in the brief [S15]): `ShipmentService`, `TrackingService`, `EtaService`, `ExceptionService`. They validate business rules and wrap database transactions.
- **Persistence:** MySQL through Eloquent.
- **External services:** weather/geocoding through the Laravel HTTP Client, with timeout/error/environment-configuration handling [S15].

---

## 9. Tech stack [S14]

Use one main stack. No additional frontend framework is required.

| Layer | Technology |
|---|---|
| Application | PHP 8.3+, Laravel 12+ (or the stable version available on campus), Blade templates, Tailwind CSS, vanilla JavaScript + Fetch API |
| Data & integration | MySQL 8+, Eloquent ORM + migrations/seeders, Laravel REST API, Laravel Sanctum, Laravel HTTP Client, Leaflet.js + OpenStreetMap, Chart.js |
| Engineering & delivery | Git + GitHub, PHPUnit **or** Pest, Postman, Docker (advanced/mandatory only if the group was trained on it `[TBD]`), GitHub Actions CI/CD (advanced `[TBD]`), Cloud/VPS deployment |

---

## 10. Security requirements [S16]

Security is part of the acceptance criteria, not an optional extra [S16].

- Password hashing and auth middleware.
- Authorization enforced in the backend.
- Form/request validation.
- CSRF / XSS / SQL-injection prevention.
- Mass-assignment protection.
- `.env` and API keys never enter GitHub.

---

## 11. Testing requirements [S16]

Mandatory automated coverage:
1. Authentication — valid login, invalid login, access without login.
2. Shipment CRUD + validation + authorization.
3. Tracking events and invalid status transitions.
4. ETA/delay rule.
5. At minimum, several automated API/feature tests.
6. The README explains how to run the tests.

---

## 12. Group scenario configurability [S8, S20, S23]

Every group receives distinct parameters (delay thresholds, routes, carriers, exception cases, seed data). The lecturer may change requirements during the interview [S20].

Consequences for the build:
- All scenario parameters must live in configuration (`config/cargotrace.php` + `.env`), not hard-coded.
- Switching scenario = changing config, nothing else.
- Current group values: see [GROUP_SCENARIO.md](GROUP_SCENARIO.md) — marked `[TBD]` until the lecturer assigns them.

---

## 13. Deliverables [S18]

Verifiable artifacts, not just source files [S18].

**Product & repository:**
- Public GitHub repository.
- Live deployed application + a demo account.
- English README covering: problem, features, setup, tests, deployment.
- `.env.example` and seed/demo data.
- Screenshots and a short demo video (optional).

**Technical documentation:**
- ERD and data dictionary.
- Architecture diagram.
- Postman collection / API documentation.
- Automated test suite and test evidence.
- Known limitations and future work.
- Attribution for libraries, templates, and external code.

---

## 14. Presentation & code ownership interview [S19]

The demo proves the application runs; the interview proves the student understands the implementation [S19].

**Live demo scenario:** run a shipment scenario, add a tracking event, show the timeline/map, trigger a delay/exception, show the API and the dashboard.

**Code interview:** the lecturer picks random files/functions; the student explains flow and design decisions, explains authorization and validation, and makes a small live modification.

**Example questions to be able to answer [S19]:**
- Why is this relation `hasMany`?
- What happens if this validation is removed?
- How is an invalid status prevented?
- Change the delay threshold to the group's scenario.

---

## 15. Acceptance criteria — minimum to pass [S22]

Use [ACCEPTANCE_CHECKLIST.md](ACCEPTANCE_CHECKLIST.md) before the UAS.

**Application:**
- All roles and access controls work.
- Shipment lifecycle and tracking work.
- The ETA/delay rule produces correct results.
- Map and external API handle errors.
- Search/filter/pagination work.
- The dashboard uses real data.

**Engineering:**
- Migrations and seeders run.
- Automated tests run.
- The API has validation and authorization.
- No secrets in the repository.
- A live deployment exists.
- Documentation is sufficient for another developer to set the project up.

---

## 16. Definition of done [S24]

CargoTrace is considered finished when it can be **run, tested, explained, and accessed online** [S24].

| Phase | Criteria |
|---|---|
| Build | Mandatory features work. Database is consistent. Business rules are validated. UI is responsive. |
| Verify | Automated tests pass. Roles/access are tested. API can be tried. No committed secrets. |
| Present | Live URL available. GitHub and English README complete. Documentation available. Members can explain the code. |

---

## 17. Grading rubric [S21]

Weights may be adjusted per the course RPS; the rubric emphasizes engineering ability [S21].

| Aspect | Weight |
|---|---|
| Functionality & business rules | 25% |
| Database design & Laravel architecture | 15% |
| REST API & external/map integration | 15% |
| Security & authorization | 10% |
| Testing & reliability | 10% |
| UI/UX & dashboard | 8% |
| Deployment & GitHub workflow | 7% |
| Documentation in English | 5% |
| Code ownership interview | 5% |
| **Total** | **100%** |

Individual grades may differ from the group grade based on the interview and Git contribution [S21].

---

## 18. Academic integrity [S20]

- Using libraries/frameworks is allowed; submitting code you do not understand is not.
- All groups must record the source of external code/templates.
- Never claim someone else's repository as your own work.
- Commit history must show the development process.
- Each member must understand the modules assigned to them.
- AI-generated code is allowed **if the lecturer's policy allows it**, but must be verified and explainable [S20].

---

## 19. Open decisions `[TBD]`

These must be resolved before or during the build. Do not invent values.

| # | Decision | Where it lands | Default until confirmed |
|---|---|---|---|
| 1 | Group scenario values (thresholds, route, primary exception, KPI) | GROUP_SCENARIO.md + `config/cargotrace.php` | Brief's example: 6/24 h thresholds [S8] |
| 2 | External integrations: which one(s) — geocoding, weather, or both | IMPLEMENTATION_PLAN.md P12 | One (minimum allowed) [S9] |
| 3 | Email notification: build or skip | IMPLEMENTATION_PLAN.md P13 | In-app only [S10] |
| 4 | Docker and GitHub Actions CI/CD: build or skip | IMPLEMENTATION_PLAN.md P15 | Skip unless the group was trained [S14] |
| 5 | Deployment target (VPS / PaaS) | IMPLEMENTATION_PLAN.md P15 | Any live URL that satisfies [S22] |
| 6 | Test runner: PHPUnit or Pest | RULES.md | Pick one, use it everywhere [S14] |
| 7 | UI language | RULES.md | English (recruiter-facing portfolio) `[DESIGN]` |
