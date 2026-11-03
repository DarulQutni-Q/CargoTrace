# CargoTrace — Implementation Plan (P8 → P15)

> The brief defines P8–P15 as **independent work milestones** — no lectures or consultations after the mid-term [S17]. "Milestones are a project-management guide, not teaching sessions" [S17].
> Each phase below = brief milestone scope [S17] + expanded tasks + the tests/docs/commits that close it (per RULES.md R8.2). Phases are ordered; do not start a later phase before the earlier one is green.

---

## P8 — Foundations: requirement, ERD, architecture, repo, setup/auth [S17]

**Scope from brief:** requirement, use case, ERD, architecture, repo, setup/auth.

- [ ] Finalize GROUP_SCENARIO.md values (ask the lecturer/user for the group's assignment) and `config/cargotrace.php`.
- [ ] Docs: ERD (from DATA_MODEL.md, drawn by the student [S11]), architecture diagram (PRD §8) [S18].
- [ ] Init Laravel project (PHP 8.3+, Laravel 12+) + Git repo on GitHub; first commits show setup, not a dump [S20].
- [ ] Install/configure: Tailwind via Vite [S14], MySQL 8 connection [S14], Sanctum [S12], fonts + Blade Lucide icons; implement the DESIGN.md token layer (CSS variables → Tailwind theme) and the app shell (rail + topbar).
- [ ] Users table + roles (ADMIN/OPERATOR/CUSTOMER) [S5]; Blade login/register/logout pages; auth middleware.
- [ ] `.env.example` committed; `.env` gitignored [S16]. Create `ATTRIBUTION.md` [S18].
- [ ] **Tests [S16]:** valid login, invalid login, unauthenticated access blocked.
- **Commits:** project scaffold, auth, roles, docs.

## P9 — Core entities: shipments, carriers, locations, roles/permissions [S17]

- [ ] Migrations + factories + seeders for `carriers`, `locations`, `shipments`, `shipment_items` [S14, S11].
- [ ] Shipment CRUD (web + API per API_SPEC §3) with FormRequest validation and `$fillable` [S16].
- [ ] Authorization: policies for roles; customer sees only own shipments — enforced server-side [S5, S13].
- [ ] Seed realistic demo data [S4] (DATA_MODEL §6).
- [ ] **Tests [S16]:** shipment CRUD + validation + authorization (including customer-vs-other-shipment 403/404).
- **Commits:** migrations, CRUD, policies, seeders.

## P10 — Tracking events, timeline, status transitions [S17]

- [ ] `tracking_events` table + append-only event creation through `TrackingService` [S7, S15].
- [ ] Status transition map in the service layer; `DELIVERED` terminal without special audited procedure [S6]; add the special revert procedure `[DESIGN]` (admin-only, audited).
- [ ] Shipment timeline page: chronological, status/time/location/description, normal vs exception styling, current location from latest valid event [S7].
- [ ] **Tests [S16]:** event creation, invalid status transition (422), chronology validation, historical preservation.
- **Commits:** tracking service, transition map, timeline UI, tests.

## P11 — ETA/delay, exception management, REST API [S17]

- [ ] `EtaService`: compare `estimated_arrival_at` vs `expected_arrival_at`, compute delay duration, classify LOW/MEDIUM/HIGH from **config** thresholds [S8]; show reason + values used [S8].
- [ ] `ExceptionService` + `shipment_exceptions`: six types [S8], severity, OPEN/INVESTIGATING/RESOLVED lifecycle, `detected_at`/`resolved_at` [S8]; exceptions appear distinctly in the timeline [S7].
- [ ] Complete the REST API surface (API_SPEC): auth, shipments, tracking, carriers, locations, exceptions — with the global error envelope [S13], pagination [S10], status codes [S13].
- [ ] Export the Postman collection [S13].
- [ ] **Tests [S16]:** ETA/delay rule (all severity classes), exception lifecycle, API validation + authorization per matrix.
- **Commits:** eta service, exception service, API, postman.

## P12 — Map + external API integration [S17]

- [ ] Leaflet.js + OpenStreetMap [S9]: markers origin/destination/tracking points, popups with status/time/location, attribution visible [S9].
- [ ] External integration(s) via Laravel HTTP Client [S9]: minimum one, two recommended [S9]. Handlers for timeout, error responses, empty data [S9]. Keys in `.env` only [S9].
- [ ] Weather/geocoding output labeled as information — never as delay prediction [S9].
- [ ] **Tests:** integration failure paths (timeout/error/empty) via faked HTTP responses; UI verified in browser (map renders, popups work) [R8.5].
- **Commits:** map UI, integration service, tests.

## P13 — Dashboard, charts, search/filter, notification [S17]

- [ ] Dashboard with Chart.js [S14]: all metrics from PRD FR-6, computed by real queries [S10], role-scoped [S5].
- [ ] Search & filter: shipment number, status, carrier, origin, destination, date range, delay status — server-side sorting + pagination [S10].
- [ ] In-app notifications [S10] + notification page/mark. Email notification `[TBD]` — build only if decided [S10].
- [ ] Audit log writes for important changes (actor, action, time, entity) [S10]; admin audit log page [S5].
- [ ] **Tests:** dashboard metrics correctness, filter/sort/pagination, notification creation, audit rows.
- **Commits:** dashboard, search, notifications, audit.

## P14 — Security hardening + automated tests [S17]

- [ ] Full security pass [S16]: hashing, backend authorization re-check on every route/endpoint, CSRF/XSS/SQLi review, mass-assignment audit, secrets scan.
- [ ] Grow the suite to cover the whole mandatory list [S16]: auth in/out, shipment CRUD + validation + authz, tracking + invalid transitions, ETA/delay rule, plus unauthorized-access tests [S13].
- [ ] Test runner instructions in README [S16]; test evidence captured [S18].
- **Commits:** hardening fixes, tests, docs.

## P15 — Deployment, English README, API docs, final QA [S17]

- [ ] Deploy to a live URL `[TBD]` target (PRD §19 #5); demo account prepared [S18, S22].
- [ ] English README [S18]: problem, features, setup, how to run tests, deployment notes.
- [ ] Docs bundle [S18]: ERD + data dictionary, architecture diagram, Postman/API docs, test evidence, known limitations + future work, `ATTRIBUTION.md`.
- [ ] Docker / GitHub Actions CI/CD `[TBD]` — only if the group was trained [S14].
- [ ] Final QA against ACCEPTANCE_CHECKLIST.md [S22, S24].
- [ ] Rehearse the demo scenario [S19]: create shipment → add tracking → trigger delay/exception → show timeline/map → show API/dashboard. Practice the interview questions [S19] and a live threshold change (config) [S20].

---

## Milestone close-out rule

A phase is done only when: scope items ticked, phase tests green, docs updated, meaningful commits pushed [S2, S20], and any new decisions recorded in the docs (R8.3).
