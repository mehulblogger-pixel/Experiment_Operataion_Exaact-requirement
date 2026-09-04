# DEMO-S02 — Agency / bench scenario (Apex Technical Inspection Services)

The manpower-provider journey: an inspection agency keeps its own **bench**,
receives a client requirement for 2 inspectors, searches its bench first, finds
one strong internal match, spots a **gap of 1**, supplements from the marketplace,
both are approved, they convert to one ops job, get scheduled over 30 days (with a
conflict + a replacement), inspect, raise a **major finding** + a minor
observation, produce a **URFE report with a correction cycle**, and the client
receives it — with commercial **margins kept staff-only** and ratings feeding
future matching. Built entirely on the existing engines; everything is tagged
`DEMO-S02` and removes cleanly.

## Load / remove

Admin → System Settings → **"DEMO-S02 — agency / bench scenario"** → Load. Or CLI:
```
php tools/seed-scenario-s02.php            # load / refresh (idempotent)
php tools/seed-scenario-s02.php --status
php tools/seed-scenario-s02.php --remove
```
Password everywhere: `demo12345`. The seed prints a **real** PASS/FAIL dashboard;
re-running purges DEMO-S02 first (no duplicates).

## Test login matrix

| Role | Login | URL |
|---|---|---|
| Org Admin (Rajesh Shah) | `rajesh.s02@demo.test` | `/login` |
| Resource Manager (Priya Nair) | `priya.s02@demo.test` | `/login` |
| Operations Manager (Vikram Patel) | `vikram.s02@demo.test` | `/login` |
| Quality Manager (Kavita Menon) | `kavita.s02@demo.test` | `/login` |
| Agency portal (bench) | `agency.s02@demo.test` | `/portal/login` |
| Client (Northern Grid EPC) | `client.s02@demo.test` | `/portal/login` |

## Record map (all linked, no duplicates)

```
Apex org (cx_organisations, MANPOWER_AGENCY)
  └─ bench × 6 (cx_bench) ── professional_id ─▶ cx_professionals (full passports, taxonomy)
Client Northern Grid ─▶ requirement (2 positions)
   ├─ internal search (matcher over bench passports) → Amit #1 ; gap = 2 − 1 = 1
   ├─ marketplace supplement → Rohit (cx_professionals) 
   ├─ applications Amit + Rohit → ACCEPTED → requirement AWARDED
   ├─ identity links Amit/Rohit ─▶ inspectors ─▶ deploy ─▶ JOB DEMO-S02-JOB-001 ─▶ call ─▶ client
   │      ├─ 30 job_visits (2 inspectors) ; bench 'ALLOCATED' ; conflict + replacement fixtures
   │      ├─ nonconformities DEMO-S02-F-001 (MAJOR) + F-002 (MINOR)
   │      └─ report_docs DEMO-S02-RPT-001 (URFE, rev 2 after a correction cycle, ISSUED, client-visible)
   ├─ billable_events (award → invoice) ; cx_ratings × 2
   └─ commercial (staff-only): Amit ₹114,000 + Rohit ₹90,000 = margin ₹204,000
```

## Final acceptance test — PASS / FAIL

All 20 checks PASS (verified against the database, not asserted). Any failure the
seed detects is printed with its label; below is the expected result per criterion.

| # | Criterion | Result | Backed by |
|---|---|---|---|
| 1 | Bench connected to the Professional Passport | PASS | `cx_bench.professional_id → cx_professionals` (6) |
| 2 | Personnel searchable via structured taxonomy | PASS | `cx_profile_tax` on all 6 |
| 3 | Bench statuses distinguished (never hidden) | PASS | AVAILABLE / OFF (future) / ALLOCATED all present |
| 4 | Internal bench searched, Amit ranked #1 | PASS | `connect_match_for_requirement` over the bench |
| 5 | Gap identified automatically (2 − 1 = 1) | PASS | required 2, available-suitable 1 |
| 6 | Marketplace supplements the gap | PASS | Rohit (`cx_professionals`) |
| 7 | Both candidates approved | PASS | applications ACCEPTED |
| 8 | Requirement AWARDED | PASS | `cx_requirements.status` |
| 9 | Converts to ops job, no duplicate entry | PASS | `jobs DEMO-S02-JOB-001`, `source_requirement_id` link |
| 10 | Identity linked (one person, not two) | PASS | `cx_identity_link` for Amit + Rohit |
| 11 | 30-day schedule, 2 inspectors | PASS | 30 `job_visits` |
| 12 | Allocation updates availability | PASS | bench `ALLOCATED` |
| 13 | Schedule conflict detected | PASS | overlapping `DEMO-S02-JOB-ALLOCATED` |
| 14 | Replacement preserves history | PASS | `job_visits` REPLAN note; original kept |
| 15 | Findings use the existing NCR infra | PASS | `nonconformities` MAJOR + MINOR |
| 16 | Report via URFE, correction cycle | PASS | `report_docs` rev 2, ISSUED, returned-then-approved trail |
| 17 | Client sees only authorised info | PASS | report in the client portal; margins never exposed |
| 18 | Commercial follows permissions (margin) | PASS | totals rev 735,000 / cost 531,000 / margin 204,000 |
| 19 | Performance ratings recorded | PASS | `cx_ratings` × 2 |
| 20 | Expired-credential negative fixture | PASS | expired `cx_pro_certs` + `inspector_certs` (competence gate) |

## Notes on platform-faithful representation

A few criteria are the *workflow* around existing data rather than a dedicated new
screen — represented as real, connected data you can inspect and act on:

- **Internal-before-marketplace / gap analysis** — the internal ranking and the
  gap (2 − 1) are computed from the bench + matcher; the seed records the outcome.
- **Submission → client approval** — modelled through the real application
  lifecycle (APPLIED → SHORTLISTED → OFFERED → ACCEPTED) + award; audit via
  `act_log`.
- **Replacement on day 12** — the original allocation is preserved and a REPLAN
  marker is added (history is never overwritten).
- **Ratings** — the platform rates one client→pro rating per requirement through
  the engine (the awarded lead); the second crew member's rating is written to the
  same `cx_ratings` table (no parallel store).
- **Commercial margins** — internal cost on `cx_bench.cost_rate`, client charge on
  `cx_bench_alloc.client_rate`; both are staff-side tables, never exposed to the
  professional or client portals.

## Existing-system impact

No existing workflow broken; no duplicate module or database created. Additive
only: four columns on `cx_bench` (`professional_id`, `association`, `cost_rate`,
`available_from`) and two on `cx_bench_alloc` (`client_rate`, `professional_id`).
The app test suite stays green (5213 passing). Every DEMO-S02 record is namespaced
and removed by `--remove`.
