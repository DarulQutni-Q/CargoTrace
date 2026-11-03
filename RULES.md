# CargoTrace — RULES.md

**Execution contract for any agent (or human) building CargoTrace from these documents.**
Read order: [PRD.md](PRD.md) → [DATA_MODEL.md](DATA_MODEL.md) → [API_SPEC.md](API_SPEC.md) → [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) → this file. The source PPTX outranks all documents if they ever disagree.

---

## R1. Anti-hallucination rules (highest priority)

1. **Every requirement must be traceable.** Requirements carry `[S#]` tags pointing to the source slide. If you cannot find a requirement in PRD.md, it does not exist — do not invent features, fields, endpoints, exception types, statuses, or metrics.
2. **Never invent library/framework APIs.** Laravel, Tailwind, Leaflet, Chart.js, Sanctum versions and signatures change. Verify against current official docs (use the Context7 MCP or fetch official documentation) before using an API you are not certain about. If unsure, verify — do not guess.
3. **Ambiguity = ask, do not assume.** If the brief or PRD is unclear on something that changes the build, ask the user. If a decision must be deferred, mark it `[TBD]` in the relevant doc and pick the smallest reversible default, recording the choice.
4. **Update specs before code.** A new endpoint, field, or status must first be added to API_SPEC.md / DATA_MODEL.md, then implemented. Code may never drift ahead of the spec.
5. **The out-of-scope list is hard** (PRD §3.2). No real port/customs/carrier integrations, no payments, no real-time GPS, no ML/AI prediction, no microservices. If asked to add any of these, refuse and point at PRD §3.2 [S4].
6. **No fabrication in data or claims.** Seed data must look real (plausible names, numbers, timestamps). No "Loved by 10,000 teams" style claims anywhere in the UI or README. No invented metrics on the dashboard — every number must come from a real query [S10].

## R2. Skills mandate

> **Pakai skills yang sekiranya berguna. Kalau dipakai, jangan tidak pakai skills (seperti `unslop`, dll.). Begitu pula dengan MCP — pakai juga.**

Translate: **if a relevant skill is available in the session, using it is mandatory, not optional.** Check the available-skills list at the start of every task. Skipping an applicable skill is a violation of this contract, not a style choice.

Mandatory mapping (use when the task matches):

| Task | Skills to load |
|---|---|
| Any user-facing copy, README, docs, UI text | `unslop` (or `antislop` family) — plain words, no hype, no emoji-as-icons, real-looking data |
| UI/layout work (Blade + Tailwind views) | `frontend-design`, `web-design-guidelines`, `antislop-ui` |
| Writing/refactoring PHP or JS | `clean-code`, `antislop-code` |
| Any new business logic (ETA, transitions, authz) | `test-driven-development` |
| Debugging anything | `systematic-debugging` |
| Reviewing your own work before a milestone commit | `requesting-code-review`, `simplify-code`, `clean-code` |
| Architecture changes / codebase health | `codebase-inspection`, `improve-codebase-architecture` |
| Exploring an unfamiliar part of the growing codebase | an `Explore` subagent, not manual scrolling |
| Planning a milestone | the milestone section of IMPLEMENTATION_PLAN.md + sequential thinking where useful |

Load the skill **before** starting the matching work, not after. If two skills overlap, load both — they compose.

## R3. MCP mandate

Use MCP servers when they fit the task. Same rule as R2: available and applicable means use it.

| Need | MCP / tool |
|---|---|
| Current docs for Laravel, Sanctum, Tailwind, Leaflet, Chart.js, MySQL | **Context7** (`resolve-library-id` → `query-docs`) — before using any API from memory |
| Deep questions about framework internals ("how does Sanctum token auth work") | **DeepWiki** on the official repo |
| Verifying UI in a real browser (map markers, popups, responsive layout, focus states) | **Playwright** MCP — navigate, screenshot, click through the flow; do this before declaring any UI task done |
| Git operations (status, add, commit, branch, log) | **Git** MCP or shell git — commits are part of the deliverable [S2, S20] |
| Current version numbers / compatibility facts | Web search or fetch — never hard-code a version from memory |
| Diagrams (architecture diagram, ERD rendering) | Excalidraw MCP or Mermaid-in-docs — deliverables require an architecture diagram and ERD [S18] |
| Reading external API docs (Nominatim/Open-Meteo/etc.) | WebFetch / fetch MCP |

## R4. Library-first mandate

> **Jangan build manual kalau bisa pakai library atau framework.**

Concretely, **never hand-roll** any of these. Use the library and the framework's provided mechanisms:

| Do NOT hand-roll | Use instead |
|---|---|
| Auth scaffolding, password hashing, sessions | Laravel's built-in auth primitives (Hash facade, middleware); Sanctum for API tokens [S12] |
| API token issuing/revoking | Laravel Sanctum [S12, S14] |
| Request validation | FormRequest classes + Laravel validation rules [S16] |
| Pagination | Laravel paginator (server-side, per [S10]) |
| Authorization checks | Policies / Gates + middleware — server-side on every route/endpoint [S5, S13] |
| ORM relations, querying | Eloquent relations, query builder, scopes [S14] |
| Migrations, seeding, factories | Artisan generators, factories, seeders [S14] |
| Status-transition validation | One explicit allowed-transitions map in a service (single source of truth) [S6] |
| HTTP calls to external APIs | Laravel HTTP Client [S9, S14] |
| Asset building, Tailwind processing | Vite + npm packages [S14] |
| Maps, markers, popups | Leaflet.js + OpenStreetMap tiles [S9] |
| Charts | Chart.js [S14] |
| CSRF/XSS/SQLi protection | Laravel's built-in mechanisms (CSRF middleware, Blade escaping, query bindings) [S16] |
| Mass-assignment protection | `$fillable`/`$guarded` [S16] |
| Dates/time math for ETA rules | Carbon |
| Installing anything | Composer (PHP), npm (JS). Never vendor files by hand. |

Allowed sources for third-party code: **official documentation and first-party packages only** for core functionality. Random tutorial/blog code must not be pasted in. Every external source — including libraries, templates, and AI assistance per policy — goes into `ATTRIBUTION.md` [S18, S20].

## R5. Scope guardrails

- Build exactly the feature set in PRD §5. Nothing beyond it without an explicit user decision recorded in PRD §19.
- Stack is fixed [S14]: Blade + Tailwind + vanilla JS + Fetch. **No** React/Vue/Livewire/Inertia/Alpine — the brief explicitly mandates vanilla JavaScript + Fetch API and does not require an extra frontend framework, so adding one violates the stack.
- All UI work follows [DESIGN.md](DESIGN.md) — it is the binding visual spec and its quality bar is the reference dashboard. Quality comes from the mandated stack done well (Blade components, Tailwind tokens, Vite, npm/Composer packages for fonts, icons, charts, maps), never from swapping in another framework.
- The brief names `ShipmentService`, `TrackingService`, `EtaService`, `ExceptionService` [S15]. Keep those names; controllers stay thin; business rules and DB transactions live in services.
- If the lecturer changes requirements mid-interview [S20], change config values first (GROUP_SCENARIO.md), not code.

## R6. Engineering standards

1. PHP 8.3+, Laravel 12+ (or campus-available stable), MySQL 8+ [S14].
2. All business rules validated in the backend [S5, S6]. The frontend only reflects what the server enforces.
3. Every controller action validates via FormRequest. Every model defines `$fillable` [S16].
4. Status transitions validated against one map; `DELIVERED` is terminal without an explicit special procedure [S6].
5. Tracking events are append-only — never update or delete old events [S7].
6. Deterministic, unit-testable rules for ETA/delay/exceptions [S8]. Thresholds read from `config/cargotrace.php`, never hard-coded.
7. External API calls: explicit timeout, error-response handling, and empty-data handling; keys only in `.env` [S9]. Weather results are labeled as information, never as delay prediction [S9].
8. Dashboard queries hit the database [S10]. No cached hard-coded numbers.
9. Error responses use one consistent JSON envelope; stack traces never leak [S13].
10. `.env` is gitignored; commit only `.env.example` [S16]. Scan before every push.
11. Migration + seeder must run from a clean clone without manual SQL [S22].
12. Test runner: one of PHPUnit or Pest — decided once (PRD §19 #6), used everywhere. Tests cover at minimum the mandatory list in PRD §11, including unauthorized-access attempts [S13, S16].
13. **Every change — small or large — is committed** when its work unit is complete; never leave finished work uncommitted, never bundle unrelated changes, and never one giant "final commit" [S2, S20]. Commit messages are **natural sentences that explain what changed and why** — e.g. `Validate status transitions in the service layer before events are persisted` or `Handle geocoding timeout so the map still renders without coordinates`. Never conventional-commit prefixes (`feat:`, `fix:`, `chore:`) and never vague one-worders (`update`, `fix`, `final`). English, imperative mood; add a body paragraph when the why is not obvious from the subject.
14. **Branch names follow the same principle** (when branching is used): descriptive kebab-case that states the work — `tracking-timeline`, `delay-threshold-config`, `map-route-arc` — never generic names (`dev`, `test`, `feature-1`, `patch`, `update`). One branch per feature; merge back when its tests and checklist items are green.
14. UI must be responsive [S24]. Verify on mobile and desktop widths via Playwright before closing a UI task.

## R7. Academic integrity & code ownership

This project is graded by a **code ownership interview** where random files are inspected and small live modifications are requested [S19, S20]. Therefore:

1. Code must stay **explainable**: clear naming, comments only where the why is non-obvious, no clever tricks the student cannot walk through line by line.
2. Prefer boring, standard Laravel idioms over exotic solutions. The student must be able to answer "why is this relation hasMany?" and "what breaks if this validation is removed?" about any line [S19].
3. After generating any code, produce a short plain-language explanation of the key files (route → controller → service → model flow) so the student can study it before the interview. Do this proactively per milestone.
4. Maintain `ATTRIBUTION.md` from day one: every library, template, code snippet, and AI-assisted portion, with source and what it was used for [S18, S20].
5. Commit history must look like real development [S2, S20] — feature by feature, following IMPLEMENTATION_PLAN.md.
6. Never claim another repository's code as original work [S20].

## R8. Process rules

1. Follow IMPLEMENTATION_PLAN.md phase order (P8 → P15) [S17]. Do not jump ahead to map/dashboard work before lifecycle and tracking are done and tested.
2. Each phase ends with: tests added and green → ACCEPTANCE_CHECKLIST items for that phase ticked → docs updated → meaningful commits made.
3. Any decision made during the build gets recorded in the relevant `.md` (PRD §19, GROUP_SCENARIO.md, or IMPLEMENTATION_PLAN.md). Decisions in chat that never reach a doc do not count as decisions.
4. Before declaring the project done: run the full ACCEPTANCE_CHECKLIST.md top to bottom. Anything unticked is not done [S22, S24].
5. UI work is verified with rendered screenshots (Playwright), not by reading the code and assuming it looks right.
6. Docs, README, and all deliverable documentation are written in **English** [S18]. UI copy defaults to English `[DESIGN]` (PRD §19 #7). Chat with the user may be in Indonesian.

## R9. Style rules for everything user-facing

[DESIGN.md](DESIGN.md) is the binding visual specification — follow it exactly and run its §12 checklist per screen. The rules below are the floor for copy, README, and docs, derived from the `unslop` skill:

- Plain words. No hype vocabulary ("supercharge", "seamless", "unlock", "revolutionize"). No exclamation marks in interface text.
- No emoji used as icons or decoration in the UI; one consistent icon set instead.
- Headlines and labels state what the thing does. Buttons: one primary, at most one secondary, per context.
- Numbers in one consistent format; data comes from the database and looks real.
- No loops of decorative motion; transitions only where they explain something.
- Every screen must be recognizably CargoTrace (a shipment-visibility product), not a generic admin template.

## R10. Definition of done for any task

A task is done when **all** of these hold:
1. Code implements the spec (PRD/API_SPEC/DATA_MODEL), not an approximation.
2. Tests covering it pass, including negative/authorization cases where applicable.
3. Docs updated if anything changed.
4. Verified in a real browser if it has UI.
5. Committed with a meaningful message; attribution added if new external code was used.
6. The student-facing explanation (R7.3) exists for new modules.
