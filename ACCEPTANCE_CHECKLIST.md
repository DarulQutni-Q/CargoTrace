# CargoTrace — Acceptance Checklist

> Sources: [S22] (minimum to pass), [S24] (definition of done), [S18] (deliverables), [S13] (API quality), [S16] (security/testing), [S2] (portfolio outcomes).
> Use this checklist before the UAS [S22]. A project ships only when every box is ticked. Phase owners: see IMPLEMENTATION_PLAN.md.

## A. Application [S22]

- [ ] All roles (ADMIN, OPERATOR, CUSTOMER) work with their permission sets [S5, S22].
- [ ] Shipment lifecycle + status transitions validated in the backend; invalid transitions rejected [S6, S22].
- [ ] Tracking timeline works and never overwrites history [S7, S22].
- [ ] ETA/delay rule produces correct results for every severity class [S8, S22].
- [ ] Map renders markers and popups; errors from the tile/integration layer are handled [S9, S22].
- [ ] External API integration handles timeout / error / empty responses [S9, S22].
- [ ] Search, filter, sorting, server-side pagination all work [S10, S22].
- [ ] Dashboard shows real database numbers (no static values) [S10, S22].
- [ ] Notifications appear for the right users [S10, S22].
- [ ] UI is responsive [S24].

## B. Engineering [S22]

- [ ] `php artisan migrate` + `db:seed` run from a clean clone [S22].
- [ ] Automated test suite runs from the README instructions and passes [S16, S22].
- [ ] Tests cover: auth (valid/invalid/unauthenticated), shipment CRUD + validation + authorization, tracking + invalid transitions, ETA/delay rule [S16], unauthorized access attempts [S13].
- [ ] API validated, authorized, consistent error envelope, correct status codes (200/201/401/403/404/422) [S12, S13].
- [ ] Service layer in place: `ShipmentService`, `TrackingService`, `EtaService`, `ExceptionService`; controllers thin [S15].
- [ ] No secrets in the repository: `.env` gitignored, only `.env.example` committed, API keys only in env [S16, S22].
- [ ] Thresholds and scenario parameters come from config, changeable without code edits [S8, S20].
- [ ] Value sets (statuses, roles, exception types) enforced server-side [S6, S8].

## C. Delivery [S24 Verify + Present]

- [ ] Live deployment reachable [S22, S24].
- [ ] Demo account credentials documented and working [S18].
- [ ] Public GitHub repository with meaningful commit history showing the development process [S2, S20].
- [ ] English README: problem, features, setup, tests, deployment [S18].
- [ ] `.env.example` + documented seed/demo data [S4, S18].
- [ ] ERD + data dictionary [S11, S18].
- [ ] Architecture diagram [S15, S18].
- [ ] Postman collection / API documentation matching the built API [S13, S18].
- [ ] Test suite + test evidence [S16, S18].
- [ ] Known limitations + future work section [S18].
- [ ] `ATTRIBUTION.md` covers every library, template, and external code source [S18, S20].

## D. Presentation readiness [S19, S24]

- [ ] Demo scenario rehearsed: run a shipment, add a tracking event, show timeline/map, trigger delay/exception, show API + dashboard [S19].
- [ ] Every team member can explain their modules: relation design ("why hasMany?"), effect of removing a validation, how invalid status is prevented, and can change the delay threshold live via config [S19, S20].
- [ ] Random file/function walkthrough practiced — no file the team cannot explain [S19].
- [ ] Lecturer-driven requirement change is survivable: scenario switch = config change only [S20, S23].

## E. Rubric self-check [S21]

| Aspect | Weight | Where it is demonstrated |
|---|---|---|
| Functionality & business rules | 25% | A1–A4, B7–B8 |
| Database design & Laravel architecture | 15% | B1, B5 + DATA_MODEL.md |
| REST API & external/map integration | 15% | A5–A6, B4 + API_SPEC.md |
| Security & authorization | 10% | B2–B4, B6 |
| Testing & reliability | 10% | B2–B3 |
| UI/UX & dashboard | 8% | A7–A10 |
| Deployment & GitHub workflow | 7% | C1, C3 |
| Documentation in English | 5% | C4–C11 |
| Code ownership interview | 5% | D1–D4 |
