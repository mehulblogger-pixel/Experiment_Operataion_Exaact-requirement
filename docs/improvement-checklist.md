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
| 12 | NCR | P1 | ⬜ | — |
| 13 | CAPA | P2 | ⬜ | — |
| 14 | Settings | P2 | ⬜ | — |
| 15 | Clients / Customer 360 | P2 | ⬜ | — |
| 16 | Vendors / Vendor 360 | P2 | ⬜ | — |
| 17 | Leads | P2 | ⬜ | — |
| 18 | Orders / Contracts (Contract 360) | P1 | ⬜ | — |
| 19 | Inquiries / Requirements | P2 | ⬜ | — |
| 20 | Project Costing | P1 | ⬜ | — |
| 21 | Hold / Witness Points | P0 | 🛠️ mapping code → edge-case spec | — |
| 22 | Complaints | P1 | ⬜ | — |
| 23 | Equipment (Equipment 360) | P1 | ⬜ | — |
| 24 | Competence (Competence 360) | P0 | ⬜ | — |
| 25 | Impartiality | P0 | ⬜ | — |
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
