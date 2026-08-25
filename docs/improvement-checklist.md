# EXAACT TPIA OS — Improvement Program Checklist

Living record of the non-destructive, module-by-module improvement program.
The full drill-down spec is in **`docs/improvement-program.docx`**; the per-module
edge-case analyses live in **`docs/edge-cases/`**.

**Rules of engagement**
- One module at a time. Nothing simultaneous.
- Before building a module: a very-detailed edge-case analysis (down to individual
  buttons) is written to `docs/edge-cases/NN-*.md` and reviewed.
- Non-destructive: nothing existing is deleted; improvements are additive.
- Each module ships tested, on branch `claude/quotation-management-workflow-5dokb2`.

**Status key**
`⬜ not started` · `📝 edge-cases drafted (awaiting go)` · `🛠️ building` · `✅ done & pushed`

---

## Progress

| # | Module | Priority | Status | Commit / date |
|---|--------|----------|--------|----------------|
| 01 | Masters | P2 | ⬜ | — |
| 02 | Users / Access / Roles | P0 | ⬜ | — |
| 03 | Quotations | P1 | ⬜ | — |
| 04 | Calls / Service Requests | P1 | ✅ done & pushed | 2026-08-24 |
| 05 | Jobs (Job 360) | P1 | ✅ done & pushed | 2026-08-24 |
| 06 | Inspection / IDEMS core + Applicability | P0 | ✅ done & pushed | 2026-08-24 |
| 07 | Vetting / Technical Review / Approval | P0 | ✅ done & pushed | 2026-08-24 |
| 08 | Report Release / Issue | P0 | ✅ done & pushed | 2026-08-24 |
| 09 | Invoicing | P0 | ⬜ | — |
| 10 | Client Portal | P0 | ⬜ | — |
| 11 | Vendor / Supplier-Inspector Centre | P1 | ⬜ | — |
| 12 | NCR | P1 | ✅ done & pushed | 2026-08-24 |
| 13 | CAPA | P2 | ✅ done & pushed | 2026-08-24 |
| 14 | Settings | P2 | ⬜ | — |
| 15 | Clients / Customer 360 | P2 | ✅ done & pushed | 2026-08-24 |
| 16 | Vendors / Vendor 360 | P2 | ✅ done & pushed | 2026-08-24 |
| 17 | Leads | P2 | ⬜ | — |
| 18 | Orders / Contracts (Contract 360) | P1 | ⬜ | — |
| 19 | Inquiries / Requirements | P2 | ⬜ | — |
| 20 | Project Costing | P1 | ⬜ | — |
| 21 | Hold / Witness Points | P0 | ✅ done & pushed | 2026-08-24 |
| 22 | Complaints | P1 | ✅ done & pushed | 2026-08-24 |
| 23 | Equipment (Equipment 360) | P1 | ✅ done & pushed | 2026-08-25 |
| 24 | Competence (Competence 360) | P0 | ✅ done & pushed | 2026-08-24 |
| 25 | Impartiality | P0 | ✅ done & pushed | 2026-08-24 |
| 26 | Identity | P0 | ⬜ | — |
| 27 | Confidentiality | P0 | ⬜ | — |
| 28 | Audits | P2 | ⬜ | — |
| 29 | Data Control / Governance | P0 | ⬜ | — |
| 30 | Vouchers / Expenses | P1 | ✅ done & pushed | 2026-08-24 |
| 31 | Attendance / Reconciliation | P1 | ✅ done & pushed | 2026-08-24 |
| 32 | Profitability (canonical engine) | P0 | ⬜ | — |
| 33 | Overheads | P1 | ⬜ | — |
| 34 | Dashboards / Command Centre | P2 | ⬜ | — |
| 35 | Recruitment / Workforce | P1 | ⬜ | — |
| 36 | Licensing / SaaS Admin | P1 | ⬜ | — |
| 37 | Global Search | P1 | ⬜ | — |
| 38 | Notification Centre | P2 | ⬜ | — |
| **39** | **My Work** | **P1** | **✅ done & pushed** | 2026-08-24 |
| 40 | Activity Timeline | P2 | ⬜ | — |
| 41 | Document Control | P1 | ⬜ | — |
| 42 | Change Control | P2 | ⬜ | — |
| 43 | Training | P2 | ⬜ | — |
| 44 | Evidence | P1 | ⬜ | — |
| 45 | AI / Intelligence | P3 | ⬜ | — |
| 46 | Integrations | P2 | ⬜ | — |
| 47 | Mobile / PWA | P1 | ⬜ | — |
| 48 | Report Template Builder | P1 | ⬜ | — |
| 49 | Entity 360 Standard | P2 | ⬜ | — |
| 50 | Cross-module Consistency & Regression | P0 | ⬜ | — |

---

## Completion log

_Each module, once done, gets a dated entry here: what was added, what was preserved,
which edge cases were handled, and the commit._

<!-- Append entries below as modules complete. -->

### Module 23 — Equipment (Equipment 360) · 2026-08-25
**Decision:** (A) impact-flagging 360; a maintenance/lifecycle layer and forcing the string-only
IDEMS-JSON instruments through the FK deferred to a deliberate later change.
**Found:** the measuring-equipment control is already solid — a real master (`equipment`), a
never-overwritten certificate history (`equipment_calibrations`), a report→instrument link
(`report_equipment`), a non-overridable at-issue hard block that judges each instrument against
the certificate in force **on the inspection date**, and a 30-day expiry-reminder cron. The
forward question ("does this report's instrument have a live certificate?") is fully guarded.
**The gap:** the **reverse** question was never asked — when a certificate lapses or is later
found bad, *which already-released reports and jobs rested on this instrument?* No code queried
`report_equipment WHERE equipment_id=?`, though the FK exists and is indexed (`ix_req_equip`). The
reminder emailed a human about the instrument but named no affected report; the detail screen was
a register, not a 360.
**Added (additive, read-only, NO auto-invalidation):**
- `reports_using_equipment($eqId)` — the reverse lookup (joins `report_docs`), every report that
  named the instrument with its status, job, work date and the certificate it was stamped against.
- `equipment_calibration_impact($eqId)` — a per-report verdict: **Covered** (rested on a valid PASS
  certificate on the work date — stays Covered even after that certificate later expires) vs
  **Review** (the certificate it rested on was later marked FAIL or removed/revoked, or no valid
  certificate covered the work date). Only **released** reports (APPROVED/ISSUED/finalised) count as
  impact; a draft is listed but not counted (it re-hits the hard block on issue). Ordinary expiry
  after the work and supersession by a newer certificate do **not** raise Review (no over-flagging).
- An **"Reports & jobs using this instrument"** panel on the equipment detail with a calibration-
  impact banner ("N released reports may need a controlled quality review — nothing is
  auto-invalidated"), honest that string-only (unlinked) instruments aren't covered.
- The expiry reminder now carries the **blast-radius count** ("Used on N released reports — review
  at /equip-edit?id=…").
**Preserved (verified by tests):** the `document.issue` calibration hard block
(`report_equipment_block` → `equipment_calibration_on`, never overridable), the never-overwritten
history, the `calibration_id` stamp-at-add-time, the reminder cron and register banner — all
unchanged. No schema change; no new permission (reuses `equipment_can_manage` / `master.manage`).
First automated coverage of the reverse impact lookup.
**Edge cases:** `docs/edge-cases/23-equipment.md`.
**Tests:** `tests/test_module23_equipment.php` (18 assertions). Suite 2623 passed / 3 pre-existing
baseline failures.

### Module 31 — Attendance / Reconciliation · 2026-08-24
**Decision:** (A) reconciliation view + flags; fully separating the conflated stores deferred to
a deliberate migration.
**Found:** only site presence (`site_visits`) is a distinct store — working/attendance/billed
hours all collapse into `voucher_entries.hours`, with a second `attendance` record never
reconciled to it. The one recon screen only compares to an external HR CSV. Of the five checks,
**impossible timing was entirely absent** (negative check-in→check-out spans silently dropped).
**Added (additive, read-only):** `attend_anomalies($inspectorId, $from, $to)` — cross-checks the
existing data and flags all five: **impossible timing** (EXIT before ENTRY — the new check),
**excessive hours** (over `hours_cap`), **missing check-out** (ENTRY, no EXIT, past day),
**overlapping jobs** (two jobs a day), and **presence↔hours mismatch** (on site but no voucher
hours; hours but no check-in when check-in is expected). Surfaced as a **Reconciliation flags**
panel + a count card on the timesheet; leave/off days excused.
**Preserved (verified by tests):** the daily-hours cap at entry, the punch-ordering guards, the
double-booking soft-stop, `site_visit_close_missing`, the HR-CSV recon, and the timesheet build —
all unchanged (recon only reads). No schema change; no new permission. First coverage of the
impossible-timing check.
**Edge cases:** `docs/edge-cases/31-attendance.md`.
**Tests:** `tests/test_module31_attendance.php` (11 assertions). Suite 2605 passed / 3 pre-existing
baseline failures.

### Module 30 — Vouchers / Expenses (fast field capture) · 2026-08-24
**Decision:** (A) quick-add expense + receipt photo + job bridge; GPS auto-capture from
check-in deferred. Answered the user's question: the coordinator's job-close `expenses` are
client-billable job costs (kept separate); the inspector's own expense goes on the monthly
voucher — and the quick-add's optional job + the job-screen "Log my expense" bridge make a
job-linked expense land there.
**Found:** the voucher is a monthly 12-column grid; no per-expense receipt photo (only one
whole-voucher file); the R5 maker-checker/reopen guards are solid and must be preserved.
**Added (additive):**
- Per-line receipt storage (`receipt_data`/`_mime`/`_name` via `ensure_column`, backward
  compatible).
- `voucher-quick-add` — one form (amount + type + optional job + note + receipt photo) that
  auto-fills claimant/date/currency, opens THIS month's voucher, writes one categorised line
  (catch-all head when none chosen), rolls up the total — all through `can_edit_voucher`, so a
  submitted/paid/frozen month is refused, never written past.
- `voucher-line-receipt` — serves a line's receipt to a permitted viewer only; a 📎 on lines
  that have one.
- Job bridge: an assigned inspector's "🧾 Log my expense" on the job screen opens the quick-add
  with the job pre-selected (`/voucher?addjob=…`).
**Preserved (verified by tests):** the R5 maker≠checker approval guard, the PAID-reopen guard,
the DRAFT-only edit lock, the month-frozen lock, the monthly grid and pull-from-jobs, and the
per-job client-billable `expenses` — all unchanged. No new permission.
**Edge cases:** `docs/edge-cases/30-vouchers.md`.
**Tests:** `tests/test_module30_vouchers.php` (13 assertions). Suite 2594 passed / 3 pre-existing
baseline failures.

### Module 16 — Vendors / Vendor 360 · 2026-08-24
**Decision:** (A) consolidated scorecard + CAPA section, reusing the existing engines; vendor
financials deferred (no vendor financial data exists — a module of its own).
**Found:** the Vendor 360 is mature but its composite signals are scattered (quality performance,
delivery risk, expediting, qualification currency separately), there's no CAPA section, and no
vendor financial data.
**Added (additive, read-only, NO new scoring math):**
- `idems_vendor_scorecard()` — one card assembling the EXISTING signals: performance score+band
  (`idems_vendor_performance`, the headline — not recomputed), delivery risk, expediting
  reliability, qualification currency (status/valid-until/reassess-overdue), and open NCR/
  complaint counts.
- `idems_vendor_capas()` — the CAPAs linked to the vendor via its NCRs/complaints, de-duplicated.
- A **Scorecard card** at the top of the Vendor 360 and a **Corrective actions** panel.
**Preserved (verified by tests):** the scoring engines (`idems_vendor_performance`/`_delivery_
risk`/`_expediting_perf`), the qualification lifecycle, the existing panels/register/portal — all
unchanged. No new score formula; no schema change; no new permission. First automated coverage of
the composite score.
**Edge cases:** `docs/edge-cases/16-vendor-360.md`.
**Tests:** `tests/test_module16_vendor360.php` (12 assertions). Suite 2581 passed / 3 pre-existing
baseline failures.

### Module 15 — Client / Customer 360 · 2026-08-24
**Decision:** (A) fill the cheap missing sections; defer per-client margin to the canonical
profitability engine (Module 32) and a shared 360 scaffold to Module 49.
**Found:** `/customer` is the canonical Customer 360 and already rich, but missing an
issued-reports list (only a rejected COUNT existed), the full multi-site list (primary only),
satisfaction, margin, and forecast demand.
**Added (additive, read-only, reusing the gated `c360_load` assembly):**
- `c360_reports()` — the reports actually issued to the client (fills the biggest gap; gated by
  the reporting module) + an "Reports issued" panel linking to each `/document`.
- `c360_sites()` — the full site list (was primary-only) shown in the contacts panel.
- `c360_satisfaction()` — latest + average CSAT from `satisfaction_surveys` when the module is on
  and permitted; a Satisfaction card, skipped cleanly otherwise.
**Deferred (noted):** per-client margin → Module 32 (no bespoke profit formula here, per the
program rule); shared 360 component → Module 49; upcoming-demand forecast.
**Preserved (verified by tests):** the `c360_on()` gating + `c360_try()` crash-safety, the Money
section's single-source financials, `/partner` and `/ledger` — all unchanged. No schema change;
no new permission. First direct automated coverage of the Customer 360 assembly.
**Edge cases:** `docs/edge-cases/15-client-360.md`.
**Tests:** `tests/test_module15_client360.php` (12 assertions). Suite 2571 passed / 3 pre-existing
baseline failures.

### Module 04 — Calls (one user-facing lifecycle) · 2026-08-24
**Decision:** (A) present the unified lifecycle; raw system status for admins only. No writes to
either status column; R6 transition rules untouched.
**Found:** calls carry two status systems — legacy `status` (OPEN/FORWARDED/ALLOCATED) and
operational `op_status` (`CALL_STATUSES` = every spec lifecycle stage). They aren't synced;
`tosrm_call_status()` already returns the single value (op_status, else derived from legacy).
But the call detail **leaked the raw legacy status to every user**, the register showed a
job-count 3-state, and the real lifecycle label only appeared in the manager-gated TOSRM panel.
**Added (additive, read-only):** `call_status_label($call)` — the one user-facing lifecycle
label + pill tone, from `tosrm_call_status` over `CALL_STATUSES`. The call detail now shows this
unified label (raw legacy/op values shown **only to admins** as "system: …"); the register shows
the unified status pill per row (the existing scheduling chips kept, additive).
**Preserved (verified by tests):** the two status columns, `tosrm_derive_status`, the R6
transition rules, the TOSRM panel/playbook/nowband, and the register scheduling chips — all
unchanged. No new status; no writes; no new permission.
**Edge cases:** `docs/edge-cases/04-calls.md`.
**Tests:** `tests/test_module04_calls.php` (18 assertions). Suite 2559 passed / 3 pre-existing
baseline failures.

### Module 22 — Complaints (unified workflow + SLA) · 2026-08-24
**Decision:** (A) surface stage + SLA + tests — no schema change; effectiveness stays on CAPA.
**Found:** complaints already run the full path (create→ack→triage→investigate→decide→CAPA→
notify→close) with a real close-gate, plus two configurable SLA clocks with reminders. Gaps: no
single "where is this?" stage (status is only OPEN/CLOSED), SLA was two clocks not one badge,
and no lifecycle test coverage.
**Added (additive, read-only):** `cmp_stage($c)` — a derived stage (Received → Acknowledged →
Triaged → Investigated → Decided → Corrective action → Complainant told → Closed) with position
and next step, from the existing columns; and `cmp_sla($c)` — one consolidated SLA badge (On
track / Ack overdue / Decision overdue / Met / Met-late, the last read from stored dates). A
**progress strip + SLA badge** on the complaint detail, and a **stage + SLA** in the register's
State column.
**Preserved (verified by tests):** the close-gate (incl. upheld⇒CAPA-required), the §7.5.4 decide
impartiality gate, the SLA clocks/settings, and the portal intake — all unchanged. No schema
change; no new permission. Also fills the previously-absent lifecycle/SLA/close-gate coverage.
**Edge cases:** `docs/edge-cases/22-complaints.md`.
**Tests:** `tests/test_module22_complaints.php` (24 assertions). Suite 2541 passed / 3
pre-existing baseline failures.

### Module 13 — CAPA (configurable RCA) · 2026-08-24
**Decision:** (A) configurable methods + gate tests only — the optional structured 5-Why aid is
deferred.
**Found:** CAPA is strong — the spec's headline (an effectiveness gate that blocks closure:
verification required, `effective='NO'` refuses "done") already exists. Gaps: the RCA method list
was a hardcoded const (not configurable), and the close/verify gates had no unit tests.
**Added (additive):**
- `capa_rc_methods()` reads an **editable lookup** (`capa_rc_method`), seeded from the built-in
  defaults via `lk_ensure_type_map` in `capa_migrate` (its own type — no collision with the
  NCDCA `rc_method` lookup). The picker and the `capa-cause` validation both read it, so a body
  can add/rename methods through the masters editor; empty lookup → the const (backward
  compatible); legacy stored codes keep their labels.
- Filled the previously-missing behavioural coverage of `capa_close_missing` /
  `capa_close_block`: every requirement (root cause, method, similar-check, action, completion,
  verification) blocks closure until met, and a CAPA verified NOT effective cannot be closed.
**Preserved (verified by tests):** the effectiveness gate, the close checklist, the lifecycle,
and the NCDCA `rc_method` lookup — all unchanged. No schema change; no new permission.
**Edge cases:** `docs/edge-cases/13-capa.md`.
**Tests:** `tests/test_module13_capa.php` (14 assertions). Suite 2517 passed / 3 pre-existing
baseline failures.

### Module 12 — NCR (toward a Quality Case) · 2026-08-24
**Decision:** (A) surface & fix only — reuse the mature NCR tables; keep RCA/verification on
CAPA (Module 13).
**Found:** the NCR subsystem is mature (4-state lifecycle, gated closure, event timeline, six
auto-origins, bidirectional NCR↔CAPA link). Two concrete gaps: the per-job NCR chip linked
`/ncr?job=` but the register **ignored** the param (landing on the full register), and the
Job-360 Quality section was just a count chip.
**Added (additive):**
- Fixed the register to honour **`?job=`/`?report=`** (scoped, office-scope respected), so the
  job/report chip lands on that entity's nonconformities.
- `ncr_for_job()` + `ncr_reachable()` helpers.
- A Job-360 **Quality panel** (fold) listing the job's NCRs — ref, severity, status,
  owner/due, and the **linked CAPA** — each linking to the NCR detail, with a raise-NCR link.
  Gated on NCR reachability (permission + accreditation pack); auto-opens when any is open.
**Preserved (verified by tests):** the NCR lifecycle constants, `ncr_close_missing` gate, the
`ncr_create` funnel and the NCR↔CAPA coupling — all unchanged. No new permission.
**Edge cases:** `docs/edge-cases/12-ncr.md`.
**Tests:** `tests/test_module12_ncr.php` (13 assertions). Suite 2503 passed / 3 pre-existing
baseline failures.

### Module 25 — Impartiality / Conflict of Interest · 2026-08-24
**Decision:** (A) familiarity (repeated assignment) is an advisory Review, never a hard block;
threshold a setting (`impartiality_familiarity_jobs`, default 6).
**Found:** impartiality already HARD-blocks allocation on a declared OPEN/UNACCEPTABLE threat
(non-overridable), with a declare→decide lifecycle — but it computes nothing, has no
repeated-assignment/rotation logic, and the gate had **no test coverage**.
**Added (additive, read-only):** `inspector_impartiality($inspectorId, $ctx)` → CLEAR / REVIEW /
CONFLICT. CONFLICT mirrors `imp_block` (a declared blocking threat, client-scoped or
person-general); REVIEW adds the one computable COI signal — **repeated assignment to the same
client ≥ threshold in 12 months** (consider rotation) — plus a due/expired declaration. An
advisory verdict **pill on the suggested-inspector chips** at allocation (shown next to the
competence pill, only when not Clear). `imp_familiarity_threshold()` setting.
**Preserved (verified by tests):** the non-overridable declared-threat hard block, the register,
the decide lifecycle, the per-job declaration checkbox — all unchanged. No new hard control; no
new permission. Also fills the previously-missing behavioural coverage of `imp_block` (OPEN
blocks; client-scoped only blocks that client; a decided threat clears).
**Noted, not changed:** the impartiality screen is gated on `mod.competence.view` rather than
`mod.impartiality.view` — flagged for a future permission cleanup, left untouched.
**Edge cases:** `docs/edge-cases/25-impartiality.md`.
**Tests:** `tests/test_module25_impartiality.php` (17 assertions). Suite 2490 passed / 3
pre-existing baseline failures.

### Module 24 — Competence (eligibility at allocation) · 2026-08-24
**Decision:** (A) the verdict mirrors the existing gate; wrong-discipline / out-of-SBU are
advisory "Check", not new hard blocks.
**Found:** allocation already hard-blocks a lapsed **mandatory** cert (manager override), with an
opt-in authorisation gate; advisory `tosrm_competence_warn` for assigned jobs. But there was no
single per-(inspector × job) verdict shown **while choosing**, `skill_ids`/`sbus` were unused,
and the competence spine had **no automated test coverage**.
**Added (additive, read-only):** `inspector_eligibility($inspectorId, $ctx)` → a verdict
ELIGIBLE / EXPIRING / CHECK / BLOCKED that mirrors the save-time gate (lapsed mandatory cert =
BLOCKED; authorisation = BLOCKED only when enforcement is on) and adds advisory signals
(expiring cert = EXPIRING; wrong discipline / out-of-SBU = CHECK). A verdict **pill on the
suggested-inspector chips** at allocation, so the call is visible before submit — nobody hidden.
**Preserved (verified by tests):** the `pack_fire('work.assign')` hard gate, the override
authority, and the enforcement toggle — all unchanged. No new hard control; no new permission.
Also fills the previously-missing behavioural coverage of the gate (mandatory vs non-mandatory).
**Edge cases:** `docs/edge-cases/24-competence.md`.
**Tests:** `tests/test_module24_competence.php` (15 assertions). Suite 2473 passed / 3
pre-existing baseline failures.

### Module 21 — Hold / Witness Points · 2026-08-24
**Decision:** (A) warn loudly, no new hard blocks — the Release Note stays the one hard gate.
**Found:** the subsystem already exists (hw_points; HOLD/WITNESS/REVIEW/CLEARANCE;
OPEN/CLEARED/WAIVED/CANCELLED; auto-derived + manual; audited), hard-blocks the Release Note
(master override) and is checked at report submit; advisory elsewhere. Shown on job / report /
release checklist / register / nav. Missing at the completion moments and on lists.
**Added (additive, advisory):** `hwp_job_summary($jobId)` (open count + by-type + label) and
`hwp_open_counts_for_jobs($ids)` (batched, no per-row query storm); a prominent open-points
**warning on the job-close screen** and on the **day-by-day completion panel** — "the
manufacturer should not proceed/despatch until cleared or waived", explaining that closing does
not clear them. (The schedule board is a person×day availability matrix — a poor fit for a
per-job badge — so it was deliberately not badged; managers see open points via the job blockers
header, the /hold-points register and the nav badge.)
**Preserved (verified by tests):** the Release Note hard gate, the hw_points model, the job
hold/witness fold, and the register — all unchanged. No new hard block; no new permission.
**Edge cases:** `docs/edge-cases/21-hold-witness.md`.
**Tests:** `tests/test_module21_hold_witness.php` (11 assertions). Suite 2459 passed / 3
pre-existing baseline failures.

### Module 05 — Jobs (Job 360) · 2026-08-24
**Found:** the job screen is already a rich 360 (4 tabs, a nowband next-action heuristic, a
glance chip index, and panels for schedule/site/reports/costs/billing). The gap vs the
universal-UX rule: no single **Stage · Owner · Blockers** header (the nowband gives the next
step but not the owner, and blockers were scattered across separate banners).
**Added (additive, read-only):** `job_now($job)` → stage label, current **owner** (Coordinator
→ Inspector → Reviewer/approver → Inspector-to-close → —, reflecting Module 07 review state),
and a **consolidated blockers list** (lock, HOLD reasons, open hold/witness points, bills
required) each linking to its panel. A compact **Stage · With · Blockers** strip now sits above
the nowband. Money blockers are hidden from a field inspector.
**Preserved (verified by tests):** the four tabs, every fold pinned by the declutter tests, the
`job_glance` keys, the `$fieldInspector`/`$canSeeProfit` gates, and the deep-link opener — all
untouched (the header is a new strip above the tab container). No new permission.
**Edge cases:** `docs/edge-cases/05-job-360.md`.
**Tests:** `tests/test_module05_job360.php` (14 assertions). Suite 2448 passed / 3 pre-existing
baseline failures.

### Module 06 — Inspection / IDEMS core + Applicability · 2026-08-24
**Decision:** (A) surface & formalize only — reuse the existing applicability mechanism, do
not add a second (inspection-type) mapping engine.
**Found:** applicability already existed — per-job `deliverables`, a `service_report_map`
(service→report types, client overrides), a soft-narrowed create form (never to nothing, with
a "Need a different one?" escape), and a per-job reports panel. So this module surfaces it,
not rebuilds it.
**Added (additive, read-only):** `idems_job_applicability($job)` — the applicable formats with
**where each came from** (service agreement / client-specific / chosen on the call) and the
**not-applicable** formats (catalogue minus agreed, minus already-written). The job "Reports on
this job" panel now shows the source note per format and a **collapsed "Other formats — not
applicable to this job"** list, each still one click to raise anyway (flagged "not allocated").
**Preserved (verified by tests):** the create form's never-narrow-to-nothing + escape hatch; the
deliverables/service-map mechanism; no report type ever hidden; no new permission.
**Edge cases:** `docs/edge-cases/06-applicability.md`.
**Tests:** `tests/test_module06_applicability.php` (13 assertions). Suite 2434 passed / 3
pre-existing baseline failures.

### Module 08 — Report Release / Issue · 2026-08-24
**Added (additive, read-only):** a **"Ready to issue" panel** on the report screen that
previews the same gates the finalize handler enforces — approval complete, issuer≠approver
(viewer-specific), QA-critical, and the instrument-calibration/signer accreditation pack —
each shown as ✓ ok / ⚠ warn / ⛔ block with its reason, plus an overall ready/not-ready line
and **immutability/revision** clarity ("issuing locks it; corrections are a new revision").
Shown only for an APPROVED, not-yet-finalized report to a finalizer.
**Preserved (verified by tests):** the finalize handler and every gate unchanged — issuer≠
approver, approval-complete, QA-critical audited override, uncalibrated-instrument hard block,
unauthorised-signer warn+NCR, immutable seal/snapshot/freeze. `pack_fire` is a pure evaluator,
reused read-only. No new permission.
**Edge cases:** `docs/edge-cases/08-report-release-issue.md`.
**Tests:** `tests/test_module08_issue_readiness.php` (13 assertions). Suite 2421 passed / 3
pre-existing baseline failures.

### Module 07 — Vetting / Technical Review / Approval · 2026-08-24
**Decisions taken:** self-review → soft warning + acknowledge (11.1-A); notify on return →
**also email** (11.2); status labels disambiguated (11.3-yes).
**Added (additive UX, no control weakened):**
- **Provenance strip** on the report screen — Prepared / Vetted / Approved / Issued, each
  with actor + date or pending/not-required, built read-only from stored fields.
- **Return-reason banner** — a returned/rejected report now shows the reviewer's actual
  reason, who and when, prominently at the top (was "read the remark below").
- **Status disambiguation** — REJECTED → "Rejected — revise & resubmit"; a returned draft →
  "Returned for correction" (display only; stored status unchanged).
- **Soft self-review acknowledgement** — if you prepared a report, vetting/approving your
  own work now needs a one-tick confirmation (never blocks; preserves master exception).
- **Email to the inspector on return** — reject / send-back / vetting-return now notify the
  inspector (previously no notification fired at all). Best-effort, never blocks.
- **Segregation visibility** — the approver is told why they cannot issue their own approval.
**Preserved (verified by tests):** issuer≠approver finalize guard; mandatory return reasons;
all gates and transitions; no new permission; report status model untouched.
**Edge cases:** `docs/edge-cases/07-vetting-review-approval.md`.
**Tests:** `tests/test_module07_quality_gate.php` (25 assertions). Suite 2408 passed / 3
pre-existing baseline failures.

### Module 39 — My Work · 2026-08-24
**Added (non-destructive):**
- New `/my-work` route + `views/ops/my_work.php`: one role-relevant landing page that
  groups the existing pending-task buckets into lanes (Do now · My reports · My jobs ·
  My money · Quality), phone-first for field inspectors.
- New **"Returned for correction"** bucket: reports a vetter RETURNED or an approver SENT
  BACK (reset to DRAFT) are now surfaced distinctly from ordinary new drafts — via a new
  `/documents?mine=returned` filter — so an inspector always sees a report has come back.
- Nav gains a top-level **My Work** destination; the dashboard "Your pending tasks" panel
  links to it.
**Preserved:** `ops_pending_tasks()` is the single source (reused, not duplicated); the
dashboard/operations-home panel, `/my-jobs`, `/vouchers`, `/documents`, all statuses and
permissions unchanged. No new permission. Report status model untouched.
**Edge cases handled:** see `docs/edge-cases/39-my-work.md` — returned-vs-rejected disjoint
(no double count), fresh draft excluded, unlinked-inspector notice, empty state, office
user sees no personal lanes, lane grouping, per-tile pluralised links, accessibility.
**Tests:** `tests/test_my_work.php` (16 assertions). Suite 2385 passed / 3 pre-existing
baseline failures unrelated to this change.
**Commit:** on branch `claude/quotation-management-workflow-5dokb2`.
