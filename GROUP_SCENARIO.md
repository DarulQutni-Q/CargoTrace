# CargoTrace — Group Scenario Configuration

> Every group receives distinct parameters so results are not identical: routes, delay thresholds, carriers, exception cases, and seed data [S20, S23]. "The numbers and routes above are examples; the lecturer may create different configurations for each group" [S23].
> **Fill this file with the real group assignment before P8 ends.** Until then everything is `[TBD]`. Never hard-code these values in code — they live in `config/cargotrace.php` + `.env` and are read by the services [S8, R6.6].

---

## 1. Group configuration `[TBD]`

| Field | Value | Source |
|---|---|---|
| Group name / letter | `[TBD]` | lecturer |
| Route (origin region → destination region) | `[TBD]` e.g. Southeast Asia → Europe | [S23] |
| Delay threshold — primary | `[TBD]` (12 / 18 / 24 h in the examples) | [S20, S23] |
| Delay thresholds — LOW/MEDIUM/HIGH boundaries | `[TBD]` | [S8] |
| Primary exception focus | `[TBD]` e.g. CUSTOMS_HOLD / WEATHER_DELAY / DOCUMENTATION_ISSUE | [S23] |
| Group KPI | `[TBD]` e.g. on-time delivery rate / average delay duration / exception resolution time | [S23] |
| Carrier set | `[TBD]` distinct per group | [S20] |
| Seed data flavor | `[TBD]` distinct per group | [S20] |

### Open questions to confirm with the lecturer `[TBD]`

1. The group config names **one** delay threshold (e.g. 12 h) while the brief's example rule has **two** boundaries (0–6 h LOW, >6–24 h MEDIUM, >24 h HIGH) [S8]. How does the single group threshold map onto the LOW/MEDIUM/HIGH classes? Options: (a) threshold = MEDIUM boundary, HIGH stays at 24 h; (b) threshold = HIGH boundary, MEDIUM scales; (c) lecturer-provided mapping. Ask — do not assume.
2. Are email notifications expected, or is in-app enough? [S10]
3. Is Docker / GitHub Actions required for this group? [S14]
4. Which external integration(s): geocoding, weather, or both? [S9]
5. Is AI-assisted code allowed under the lecturer's policy? (affects attribution practice) [S20]

---

## 2. Brief's example configurations — **illustration only, not assignments** [S23]

| | Group A | Group B | Group C |
|---|---|---|---|
| Route | Southeast Asia → Europe | Asia → North America | Southeast Asia → Australia |
| Delay threshold | 12 h | 18 h | 24 h |
| Primary exception | customs hold | weather delay | documentation issue |
| KPI | on-time delivery rate | average delay duration | exception resolution time |

---

## 3. KPI formulas `[DESIGN, activated per group KPI]`

- **On-time delivery rate** = delivered shipments with `estimated_arrival_at ≤ expected_arrival_at` ÷ total delivered shipments.
- **Average delay duration** = mean of `max(0, estimated_arrival_at − expected_arrival_at)` over shipments with delay > 0.
- **Exception resolution time** = mean of `resolved_at − detected_at` over resolved exceptions [S8].

The dashboard may add the group KPI as one card, computed from the database [S10].

---

## 4. Implementation mapping

```php
// config/cargotrace.php  [S8, S20]
return [
    'delay' => [
        'low_threshold_hours'  => env('CARGOTRACE_DELAY_LOW', 6),   // brief example default [S8]
        'high_threshold_hours' => env('CARGOTRACE_DELAY_HIGH', 24), // brief example default [S8]
    ],
    'scenario' => [
        'route'             => env('CARGOTRACE_ROUTE'),
        'primary_exception' => env('CARGOTRACE_PRIMARY_EXCEPTION'),
        'kpi'               => env('CARGOTRACE_KPI'),
    ],
];
```

- `EtaService` reads only from this config [S8]. Changing scenario = changing `.env` values + re-seeding demo data for the new route [S20] — zero code changes.
- Seeders build carriers/locations/shipments around the configured route so the demo data stays consistent and realistic [S4, S20].
- The live interview drill: "ubah delay threshold sesuai skenario kelompok" [S19] must be answerable by editing this config in front of the lecturer.
