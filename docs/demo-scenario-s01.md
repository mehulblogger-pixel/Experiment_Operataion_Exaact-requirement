# DEMO-S01 — Scenario seed: Transmission Technician Marketplace Journey

A complete, interconnected, production-like test scenario that exercises the whole
ecosystem end to end — **Marketplace → Matching → Application → Selection →
Unified Identity → Operations Job (PDSO) → Scheduling → Field Execution → Finding
→ Evidence → Report → Client Delivery → Rating → Completion** — using **only the
existing engines** (no duplicate systems). Everything is tagged `DEMO-S01` and is
fully removable.

## How to load / remove

```
cd phpapp
php tools/seed-scenario-s01.php            # load / refresh (idempotent)
php tools/seed-scenario-s01.php --status   # report what exists
php tools/seed-scenario-s01.php --remove   # remove ALL DEMO-S01 data
```

The seed prints a **scenario dashboard** at the end with a real (never faked)
PASS/FAIL for each stage — a status is PASS only when the underlying record/state
actually exists. Re-running purges DEMO-S01 first, so it never duplicates.
Password for every demo account: **`demo12345`**.

## A. Scenario creation summary (records created)

| Object | Identity | Engine reused |
|---|---|---|
| Professional passport | Arjun Mehta (`DEMO-S01-PRO-001`) — 42 taxonomy nodes (Electrical→Power→T&D→roles/skills/equipment/voltage), 5 projects, 5 certs (3 valid / 1 expiring / 1 expired), Vadodara + Pan-India, tier **credential-verified** | `cx_professionals` + taxonomy graph / geo / privacy / verification / credentials |
| 4 more candidates | B strong, C medium, D weak, E ineligible/busy | same |
| Client | DEMO-S01 Power Projects Pvt Ltd (`is_client`, marketplace org) | `business_partners` / `cx_organisations` |
| Requirement | 220 kV Substation Inspection (OPEN → **AWARDED**) | `cx_requirements` |
| Applications | Arjun (awarded), B, C (withdrawn), D, E (rejected); duplicate blocked | `cx_applications` |
| Unified identity | Arjun ↔ internal inspector `DEMO-S01-INS-01` (a link, **not a duplicate person**) | `cx_identity_link` |
| Engagement | man-days, ₹8,500/day, 5 days | `cx_engagements` |
| Operations job | **DEMO-S01-JOB-001** (PDSO deputation, assigned, linked to a client call) | `jobs` + `calls` + deploy bridge |
| Scheduling | 5 working days allocated to Arjun | `job_visits` |
| Conflict fixture | Candidate B has an overlapping deputation on the same dates | `jobs` |
| Finding | **DEMO-S01-F-001** (Observation / Minor → CLOSED), linked to job + report | `nonconformities` (`ncr_create`) |
| Evidence | 3 photos linked to the report (no orphans) | `report_files` |
| Report | **DEMO-S01-RPT-001** (ISSUED, verification code, client-visible PDF) | `report_docs` |
| Billing | award sent to the billable-events ledger | `billable_events` (bridge) |
| Rating | client → professional 5★, would-rehire | `cx_ratings` (`cx_rating_add`) |
| Client bench | Arjun saved as preferred (rehire-ready) | `cx_client_bench` |

## B. Test login matrix (all password `demo12345`)

| Role | Login | URL | Can test |
|---|---|---|---|
| Marketplace Professional | `arjun.s01@demo.test` | `/pro/login` | Passport, skills, certs (expiry states), the opportunity, apply, assignment, credentials |
| Client User | `client.s01@demo.test` | `/portal/login` | Requirement, matches, shortlist/select, **Reports** (DEMO-S01-RPT-001), deputations, bench, invoices |
| Marketplace Administrator | `admin.s01@demo.test` | `/login` | Taxonomy admin, identity console, match weights, verification desk |
| Operations Coordinator | `coord.s01@demo.test` | `/login` | Requirement desk, source manpower, DEMO-S01-JOB-001, scheduling/allocation |
| Technical Reviewer | `reviewer.s01@demo.test` | `/login` | Report review/vetting, findings |
| Report Approver / Issuer | `approver.s01@demo.test` | `/login` | Report approve + issue |

*(Every login is an `@`-style ID; the four staff accounts sign in at `/login` with the e-mail shown as the username.)*

## C. Scenario record map (all linked, no duplicates)

```
DEMO-S01-PRO-001 (Arjun, cx_professionals)
   └─ cx_identity_link ──▶ inspector DEMO-S01-INS-01   (one person, not two)
   └─ cx_applications (awarded) ──▶ cx_requirements (AWARDED)
                                        └─ cx_engagements ──▶ billable_events
                                        └─ deploy ──▶ jobs DEMO-S01-JOB-001 ──▶ calls DEMO-S01-CALL-01 ──▶ client
                                                          ├─ job_visits × 5 (allocated to Arjun)
                                                          ├─ nonconformities DEMO-S01-F-001 (CLOSED)
                                                          └─ report_docs DEMO-S01-RPT-001 (ISSUED)
                                                                 ├─ report_files × 3 (evidence)
                                                                 └─ visible in the client portal (Reports)
   └─ cx_client_bench (client's preferred roster) ; cx_ratings (client → pro 5★)
```

The **same person is never duplicated** across marketplace → operations →
reporting: the marketplace professional and the internal inspector are one
identity via `cx_identity_link`.

## D. Exceptions / negative cases created

| Case | Seeded fixture | Expected result |
|---|---|---|
| Duplicate application | Arjun applies twice | 2nd returns 0 — blocked |
| Candidate withdrawn | C: APPLIED → WITHDRAWN | terminal, not selectable |
| Candidate rejected | E: APPLIED → REJECTED | terminal |
| Invalid transition | E: REJECTED → ACCEPTED | refused by the lifecycle |
| Expired certificate | Arjun's Basic HSE Training (expired) | stays visible, flagged **Expired** (not deleted) |
| Expiring certificate | Arjun's Working-at-Height (≤60d) | flagged **Expiring** |
| Weak / off-discipline | D (civil) ranks low; E (mechanical, busy) low | ranking A > B > C > D, E busy |
| Schedule conflict | Candidate B has an overlapping deputation | conflict visible to the desk |
| Unlinked pro can't staff a job | (general) marketplace pro must be identity-linked | ISO competence gate |

## E. Existing-system impact

- **No existing workflow broken.** The seed only *calls* existing services and
  writes to existing tables; the app's own test suite stays green.
- **No duplicate core infrastructure created.** It reuses the professional
  passport, taxonomy graph, geo/privacy/verification engines, `cx_requirements`/
  `cx_applications`, the identity link, the deploy bridge, PDSO `jobs`/`job_visits`,
  the `nonconformities` engine, the `report_docs` report engine + `report_files`
  evidence, `cx_ratings`, `billable_events` and the client portal.
- **All scenario records use existing system services** and are namespaced
  `DEMO-S01`, so `--remove` cleans them without touching other data.

## F. Acceptance test order (log in and follow)

Professional → Client → Operations → Reviewer → Approver → Client, per the 32-step
sequence in the prompt. At each step check: (1) the record was created, (2) the
status changed correctly, (3) the next user sees the right information. The seed
leaves the journey in its **completed** end-state (report issued, client can see
it) so every downstream check has real data behind it; the review/approve/issue
*controls* remain fully exercisable on any report from the staff desk.
