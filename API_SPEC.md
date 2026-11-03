# CargoTrace — REST API Specification

> Base requirements: endpoints [S12], contract & quality rules [S13], role permissions [S5].
> This is the **contract**: an endpoint not listed here must be added here first, then implemented [R1.4]. The brief explicitly allows extra endpoints "sesuai desain" [S12].
> Base URL: `/api`. All request and response bodies are JSON. Authentication via **Laravel Sanctum** bearer tokens [S12, S14].

---

## 1. Global conventions [S13]

### Status codes
| Code | Meaning | When |
|---|---|---|
| 200 | OK | successful read/update |
| 201 | Created | successful create |
| 401 | Unauthorized | missing/invalid token |
| 403 | Forbidden | authenticated but not allowed |
| 404 | Not Found | resource does not exist (or not owned by a customer) |
| 422 | Unprocessable Entity | validation failure |

### Error envelope `[DESIGN, consistent per S13]`
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": ["The selected status is invalid."]
  }
}
```
- 401/403/404 → `{"message": "..."}` only.
- Stack traces never appear in any response [S13].

### Pagination `[S10, S13]`
Laravel paginator envelope on all list endpoints:
```json
{
  "data": [ ... ],
  "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 52 },
  "links": { "first": "...", "last": "...", "next": "...", "prev": null }
}
```
List query params: `page`, `per_page` (server-capped), `sort` (e.g. `-expected_arrival_at`), plus the filters in §3.

---

## 2. Authentication [S12]

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/api/register` | public | name, email, password (+confirm), role fixed to CUSTOMER `[DESIGN]` — staff accounts are created by admin, not by self-registration [S5] |
| POST | `/api/login` | public | email + password → Sanctum token |
| POST | `/api/logout` | auth | revoke current token |
| GET | `/api/user` | auth | current user profile + role |

**Login response** `[DESIGN]`:
```json
{
  "token": "1|xxxxxxxx...",
  "user": { "id": 7, "name": "Rina", "email": "rina@example.com", "role": "CUSTOMER" }
}
```

---

## 3. Shipments [S12]

| Method | Path | Roles [S5, S13] |
|---|---|---|
| GET | `/api/shipments` | ADMIN, OPERATOR: all; CUSTOMER: only own |
| POST | `/api/shipments` | ADMIN, OPERATOR |
| GET | `/api/shipments/{id}` | ADMIN, OPERATOR; CUSTOMER: own only |
| PUT/PATCH | `/api/shipments/{id}` | ADMIN, OPERATOR (per permission design) |
| DELETE | `/api/shipments/{id}` | ADMIN `[DESIGN]` |
| GET | `/api/shipments/{id}/tracking` | same read rules as above |
| POST | `/api/shipments/{id}/tracking` | ADMIN, OPERATOR |

### GET /api/shipments — filters [S10]
`shipment_number` / reference (partial match), `status`, `carrier_id`, `origin` (location id or code), `destination`, `delayed` (`true`/`false` — delay status), `date_from`, `date_to` (against expected departure/arrival `[DESIGN]`), `sort`, `page`, `per_page`. All filtering, sorting, and pagination happen **server-side** [S10].

### POST /api/shipments — body
`shipment_number` (unique), `customer_id`, `carrier_id`, `origin_location_id`, `destination_location_id`, `cargo_type`, `weight_kg`, `volume_m3`, `expected_departure_at`, `expected_arrival_at`. Server sets `current_status = CREATED` [S6] and validates `destination ≠ origin`, `expected_arrival ≥ expected_departure` `[DESIGN]`.

### PUT/PATCH /api/shipments/{id}
Updatable: descriptive/logistics fields, `estimated_arrival_at` (feeds the delay rule [S8]), `customer_id`. `current_status` is **not** directly updatable — it changes only through the tracking endpoint (§4) so no free-form status input exists [S6].

### POST /api/shipments/{id}/tracking — body
`event_type`, `occurred_at`, `location_name`, `latitude`, `longitude` (nullable), `description` (nullable).
Server behavior [S6, S7]:
1. If `event_type` maps to a status, validate the transition against the allowed map (§5) → 422 on invalid transition.
2. Append the event (never overwrite existing events [S7]).
3. Validate `occurred_at` ≥ previous event's `occurred_at` [S7].
4. Set `shipments.current_status` and re-evaluate the delay rule if `estimated_arrival_at` is present [S8].
5. Write an audit entry [S10] and create notifications for the customer/observers [S10].

### GET /api/shipments/{id}/tracking — response
Events in chronological order [S7], each with `event_type`, `occurred_at`, `location_name`, coordinates, `description`, creator, and an `is_exception` flag (normal vs exception events distinguished [S7]).

---

## 4. Related resources [S12]

| Method | Path | Roles |
|---|---|---|
| GET | `/api/carriers` | ADMIN, OPERATOR (list with pagination/filter); CUSTOMER sees carriers of own shipments through shipment payloads `[DESIGN]` |
| POST | `/api/carriers` | ADMIN (manage carriers [S5]) |
| GET | `/api/locations` | ADMIN, OPERATOR; same customer rule as carriers |
| POST | `/api/locations` | ADMIN |
| GET | `/api/shipments/{id}/exceptions` | read rules as shipment; CUSTOMER: exceptions of own shipments only [S5] |
| POST | `/api/shipments/{id}/exceptions` | ADMIN, OPERATOR (create exception [S5]) |
| PATCH | `/api/exceptions/{id}` | ADMIN, OPERATOR — update `severity`, `status` (OPEN→INVESTIGATING→RESOLVED [S8]); on RESOLVED, `resolved_at` is set server-side [S8] |

Exception body [S8]: `type` (one of the six), `severity`, `description` (nullable), `detected_at`. Creating an exception writes an exception-linked tracking event [S7, DATA_MODEL §3.6], an audit entry [S10], and a notification [S10].

---

## 5. Status transition rules [S6]

Single allowed map (validated server-side; the only place it is defined):

```
CREATED     → BOOKED
BOOKED      → PICKED_UP
PICKED_UP   → IN_TRANSIT
IN_TRANSIT  → DELIVERED
DELIVERED   → (terminal; revert only via a special audited admin procedure [S6])
```

Any other transition returns **422** with a message naming the current status and allowed next statuses. The map lives in one service constant (not in controllers, not in the frontend) [S6, R6.4].

---

## 6. Authorization matrix [S5, S13]

Enforced server-side via policies/middleware on every endpoint [S5, S13] — hiding UI buttons is never sufficient [S13]. Automated tests must exercise the "no" cells [S13].

| Resource | ADMIN | OPERATOR | CUSTOMER |
|---|---|---|---|
| Users/config management | CRUD | none | none |
| Carriers / locations | CRUD [S5] | read | read own shipments' context only |
| Shipments read | all [S5] | all (operational view [S5]) | own only [S5] |
| Shipments create/update | yes [S5] | yes [S5] | none |
| Shipments delete | yes `[DESIGN]` | no | no |
| Tracking create | yes [S5] | yes [S5] | none |
| Exceptions create/update | yes [S5] | yes [S5] | read own shipments' exceptions [S5] |
| Dashboard | global [S5] | operational [S5] | own data `[DESIGN derived]` |
| Audit log | read [S5] | none | none |
| Notifications | own [S10] | own | own (relevant ones [S5]) |

---

## 7. Validation summary [S16]

- Every write endpoint is validated with a FormRequest [R4]; invalid input → 422 with per-field errors [S13].
- Mass assignment is impossible: models declare `$fillable` [S16].
- Enum-valued fields (status, role, exception type/severity/status) validated against the fixed value sets (DATA_MODEL §4) [S6, S8].

---

## 8. Postman collection [S13, S18]

- A Postman collection (with environment variables for `base_url` and `token`) ships in the repository and is kept in sync with this spec.
- Test evidence: the collection covers every endpoint above, including 401 (no token), 403 (wrong role), and 422 (invalid input) cases [S13].
