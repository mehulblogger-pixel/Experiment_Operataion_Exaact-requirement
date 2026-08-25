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
| 04 | Calls / Service Requests | P1 | ⬜ | — |
| 05 | Jobs (Job 360) | P1 | ✅ done & pushed | 2026-08-24 |
| 06 | Inspection / IDEMS core + Applicability | P0 | ✅ done & pushed | 2026-08-24 |
| 07 | Vetting / Technical Review / Approval | P0 | ✅ done & pushed | 2026-08-24 |
| 08 | Report Release / Issue | P0 | ✅ done & pushed | 2026-08-24 |
| 09 | Invoicing | P0 | ⬜ | — |
| 10 | Client Portal | P0 | ⬜ | — |
| 11 | Vendor / Supplier-Inspector Centre | P1 | ⬜ | — |
| 12 | NCR | P1 | ✅ done & pushed | 2026-08-24 |
| 13 | CAPA | P2 | ⬜ | — |
| 14 | Settings | P2 | ⬜ | — |
| 15 | Clients / Customer 360 | P2 | ⬜ | — |
| 16 | Vendors / Vendor 360 | P2 | ⬜ | — |
| 17 | Leads | P2 | ⬜ | — |
| 18 | Orders / Contracts (Contract 360) | P1 | ⬜ | — |
| 19 | Inquiries / Requirements | P2 | ⬜ | — |
| 20 | Project Costing | P1 | ⬜ | — |
| 21 | Hold / Witness Points | P0 | ✅ done & pushed | 2026-08-24 |
| 22 | Complaints | P1 | ⬜ | — |
| 23 | Equipment (Equipment 360) | P1 | ⬜ | — |
| 24 | Competence (Competence 360) | P0 | ✅ done & pushed | 2026-08-24 |
| 25 | Impartiality | P0 | ✅ done & pushed | 2026-08-24 |
| 26 | Identity | P0 | ⬜ | — |
| 27 | Confidentiality | P0 | ⬜ | — |
| 28 | Audits | P2 | ⬜ | — |
| 29 | Data Control / Governance | P0 | ⬜ | — |
| 30 | Vouchers / Expenses | P1 | ⬜ | — |
| 31 | Attendance / Reconciliation | P1 | ⬜ | — |
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
