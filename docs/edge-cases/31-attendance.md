# Module 31 — Attendance / Reconciliation · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-24). Decision: (A) reconciliation view + flags; separating the stores deferred. Read-only cross-check; the existing stores, punch flow and entry-time guards are preserved (asserted by tests in `tests/test_module31_attendance.php`). P1.

---

## 0. Headline: the concepts are conflated — surface the difference, don't re-plumb

Six time concepts, but only **site presence** (`site_visits`, GPS ENTRY/EXIT) is a distinct
store. **Working = attendance = billed hours** are all the single `voucher_entries.hours`
number; a *second* attendance record (the `attendance` table — status + self-punch) runs in
parallel and is **never reconciled** to it. Travel is km/money only (no travel-time); inspection
time is derived from the on-site window; billable is distinct only inside the separate PDSO
module. Nothing cross-checks **on-site minutes vs logged hours vs attendance status**.

**Reconciliation today:** exactly one screen (`/attendance-recon`) — and it compares voucher
day-counts to an uploaded **HR payroll CSV**, not the internal concepts against each other.

**The five spec checks:**
- Excessive hours ✅ (daily cap, but only at data-entry, never reported)
- Missing check-in/out ✅ (off by default)
- Overlapping jobs ✅ but **day-level only** (no time-of-day overlap)
- Missing attendance ⚠️ only against the external HR CSV
- **Impossible timing ❌ absent** — a negative ENTRY→EXIT span is silently dropped, never flagged.

So Module 31 = a **reconciliation view that cross-checks the internal data** and surfaces all
five anomalies (adding the missing impossible-timing check), reusing the stores that exist. It
does **not** re-architect the conflated stores (that's §4-B).

---

## 1. Proposed additive layer (recommended = §4-A)

1. **`attend_anomalies($inspectorId, $month)`** — a read-only reconciliation over existing data,
   returning a list of flags:
   - **Impossible timing** — a `site_visits` day whose EXIT `at` is *before* its ENTRY `at`
     (negative on-site span). *This is the absent check.*
   - **Excessive hours** — a day whose `SUM(voucher_entries.hours)` exceeds `hours_cap()`
     (reuse the existing cap; surfaced as a flag, not only an entry-time block).
   - **Missing check-out** — a job with an ENTRY punch but no EXIT (open presence), reusing the
     `site_visit`/timesheet "incomplete" signal.
   - **Presence vs hours mismatch** — a day with on-site presence but **zero** voucher hours, or
     voucher hours but **no** presence: the two disconnected numbers made visible.
   - **Overlapping jobs** — the existing day-level `inspector_busy_dates` clash, surfaced (not
     re-computed).
2. **A "Reconciliation / flags" section** on the existing **timesheet** (per inspector, per
   month — where presence + attendance already show), and a compact office-wide count on the
   `/attendance-recon` screen so a coordinator sees anomalies without an HR upload.

Reuses `site_visits`, `voucher_entries`, `hours_cap`, `inspector_busy_dates`, the timesheet
build. No schema change; no new permission (reuses `timesheet_can`/`is_coordinator_level`).

**Deferred (noted):** fully **separating** the conflated stores (distinct attendance vs working
vs billable time) — a data-model migration; offered as §4-B, better done deliberately.

## 2. Edge cases

1. **No punches / no voucher for the month** → no anomalies (nothing to reconcile); the section
   says "nothing to flag", never an error.
2. **Impossible timing** → EXIT `at` < ENTRY `at` on the same job/day is flagged with both
   timestamps; uses the server `at` (authoritative), not the untrusted device clock.
3. **ENTRY with no EXIT (still on site)** → "missing check-out" flag, but only once the day is
   past (an in-progress day today is not an anomaly).
4. **Presence but zero hours** → flagged as "on site, no hours logged"; **hours but no presence**
   → "hours logged, no check-in" — but only when site check-in is expected (geofenced/required),
   else it's advisory, mirroring how `site_visit_close_missing` is off by default.
5. **Excessive hours** → a day over `hours_cap` is flagged even if it slipped in via an override;
   the flag reads the stored hours, doesn't re-block.
6. **Overlapping jobs** → the existing day-level clash is surfaced; time-of-day overlap between
   two site-visit windows is a *nice-to-have* noted but not required (day-level matches today's
   guard).
7. **Leave / week-off / office days** → excluded from "missing attendance" and presence checks
   (a leave day legitimately has no site presence).
8. **PDSO deputation days** → the deputation timesheet is its own approved record; the recon flags
   the core stores, and does not double-flag an approved deputation day.
9. **Performance** → the anomalies are a handful of grouped queries per inspector-month; the
   office-wide count is one pass, not per-inspector N+1.
10. **Read-only** → the recon flags; it changes no attendance/voucher data and blocks nothing.
11. **Mobile** → the flags list is single-column.

## 3. Guardrails (must stay green)

- The daily-hours cap at voucher entry, the punch ordering guards, the double-booking soft-stop,
  `site_visit_close_missing`, the HR-CSV `/attendance-recon`, and the timesheet build — all
  unchanged (the recon only *reads* them).
- `test_attend`, `test_punch`, `test_geo`, `test_site_geolocation`, `test_discipline_alloc`
  (double-booking), `test_pdso` (billable preserved) — untouched.

---

## 4. OPEN DECISION — surface the anomalies, or also separate the stores?

- **(A) Reconciliation view + the missing impossible-timing check (recommended, P1):** surface
  all five anomalies from the existing data, reusing the stores and the cap. Makes the conflation
  *visible* (presence vs hours) without a migration. No schema change; no new permission.
- **(B) Also separate the conflated time stores** — give working / attendance / billable their
  own columns/tables distinct from `voucher_entries.hours`, and reconcile them structurally. A
  data-model migration touching the voucher/attendance core and payroll math; higher risk, its
  own migration + tests. Better done deliberately, after the recon shows where they actually
  diverge.

Default if you don't specify: **(A)**.

## 5. Tests

1. `attend_anomalies`: flags a negative ENTRY→EXIT span (impossible timing); a day over the cap
   (excessive hours); an ENTRY with no EXIT on a past day (missing check-out); presence-without-
   hours and hours-without-presence; nothing on a clean month.
2. The timesheet shows the reconciliation flags; the anomalies are read-only.
3. Leave/week-off days are not false-flagged.
4. The existing cap/punch/double-booking guards and the HR-CSV recon are unchanged; no new
   permission.
