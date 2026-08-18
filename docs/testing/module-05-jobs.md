# Inspection Ops — Jobs / Scheduling / Deputation — Test & Documentation Report

> **Prompt 3 · Module MOD-JOBS.** Read from `lib/ops.php` (`ops_jobs`,
> `job_save_fields`, `job_mandays`, `job_money`, `job_profit`, `job_call_required_dates`,
> `ops_check_compoff`, `job_glance`), `lib/schedule.php` (`sched_resolve`,
> `sched_continuous/pattern/monthly`, `inspector_busy_on`, `job_visits_sync`),
> `lib/joblock.php` (`job_lock_state`, `job_lock_block`, `joblock_sweep`),
> `lib/contracts.php` (`contract_gate`), `lib/pdso.php` (deputation engine), views
> `jobs.php`, `job_form.php`, `job_detail.php`, `job_close.php`, `schedule.php`.

| | |
|---|---|
| **Module** | Jobs / Scheduling / Deputation (MOD-JOBS) · Area Operations |
| **Personas** | P-COORD (allocate/schedule/close), P-INSP (field/close own), P-BM/P-SBU (oversight/unlock), P-MASTER, P-CLIENT-neg |
| **Risk weight** | **High** — puts a person on a client site; drives man-days, credit, billing, TAT and every downstream report |
| **Verdict** | Complete-with-defects (confirm scheduling limits, contract gate, lock, and credit at runtime) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

A **Job** is the allocation of a forwarded Call to *who does the work* — an internal
inspector/engineer or a sub-contracting agency — on *which dates*, under *which
contract*, owing *which deliverables*. It is the operational unit that carries
man-days, inter-office credit, expenses, TAT and the report set. A long site
**Deputation** (PDSO) is the same job extended with a manpower roster, a site
register, an activity timesheet and client attendance approval.

Lifecycle: Call `FORWARDED` → **allocated** (job created, call → `ALLOCATED`) →
scheduled (visit rows) → executed → **closed** (report + expenses + bills + attendance
+ final docs) → report sign-off → invoiced. A **grace-period lock** freezes an
un-closed job after N days; only a manager can reopen it.

Screens: `/jobs` (allocation board: filter by engineer / month / date range / office /
unallocated), `/job-new?call=` `/job-edit?id=`, `/job?id=` (detail: visits, expenses,
profit, docs, deputation tabs), `/job-close`, `/job-reassign`, `/job-visit-close`,
`/schedule` (board), `/availability`, `/job-unlock`; deputation `/dep-*`.
Tables: `jobs`, `job_visits`, `expenses`, `inspector_day_status`, `dep_manpower`,
`dep_site_log`, `dep_timesheet`, `dep_approvals`.

---

## B. Screen-by-screen catalogue

**`/job-new` / `/job-edit`** — WHO (`inspector_id` **or** `subcon_id` — at least one
required); executing office; engagement type (SINGLE / CONTINUOUS / MULTIPLE / PATTERN /
MONTHLY) with its parameters (`days_count`, `months_count`, `pattern_kind`, `pattern_n`,
`schedule_weekdays`, `schedule_end_date`, `inspection_dates`, `force_dates`); man-days &
`manmonth_basis`/`min_days`; deliverables (multiselect → CSV); chargeable heads;
inter-office credit (rate/expected/direction, only when contracting ≠ executing);
invoice value (auto = rate × man-days); impartiality declaration; competence/site-doc
override boxes (only when a rule fires); per-visit inspector split.

**`/jobs`** — board: code, client, inspector, scheduled, reporting freq, (credit if
`data.credit`), TAT, status, money-state; CSV export.

**`/job?id=`** — detail: assignment brief (who/where/contact), visits (per-day, reassign),
expenses, `job_profit`, docs/reports, deputation tabs (roster/site-log/timesheet/approval),
close/unlock actions.

**`/job-close`** — report upload date, report link, expenses (travel/local/food/lodging/
misc/extra), nil-heads declaration, attendance override (manager), per-visit-day close,
final-docs gate.

**`/schedule`, `/availability`** — allocation board and per-engineer day status
(available / leave / training) driving `inspector_busy_on`.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-JOBS-form-001 | **WHO required:** neither inspector nor subcon → refused ("Choose who will carry out this job"). |
| TC-JOBS-form-002 | **Executing office only:** a contracting-office coordinator opening `/job-new` on a call executed elsewhere is refused (`call_can_allocate`); can view, cannot allocate. |
| TC-JOBS-form-003 | **Closed call** cannot be allocated again (stale link / direct URL) → redirect with notice. |
| TC-JOBS-form-004 | **Cross-office credit:** contracting ≠ executing ⇒ `expected_credit > 0` required; same-office ⇒ credit forced to 0 and never demanded (regression: same-office allocate must not be blocked by a credit box). |
| TC-JOBS-form-005 | Man-days ≤ 0 defaults to `max(1, visit count)`; invoice auto = rate × man-days; manual invoice honoured when `invoice_value_auto=0`. |
| TC-JOBS-form-006 | Deliverables: posted set stored; if none ticked, service line's owed formats fill them (`svc_report_codes`); never blanked by an absent field on edit. |

---

## D. Functions & logic  *(scheduling + money — high scrutiny)*

- **`sched_resolve`** expands the engagement into the actual date set:
  - **SINGLE** → one date; **CONTINUOUS** → N working days from start, **stepping over
    non-working days** (`sched_continuous`, note states skipped count);
    **MULTIPLE** → client-named dates (`call_dates_parse`); **PATTERN** →
    start..end × weekday/interval (`sched_pattern`); **MONTHLY** → 1st-to-last-day working
    days with man-month claimable on calendar or MIN_DAYS basis (`sched_monthly`,
    `manmonth_rule`). **TC-JOBS-sched-001..005** — one per type; verify counts, end date,
    working-day stepping, and man-month claimable arithmetic.
- **`inspector_busy_on`** — a date is busy if the engineer has a non-AVAILABLE day
  status, a `job_visits` row on another job, or another open job whose resolved dates
  cover it. **TC-JOBS-sched-006:** double-booking is surfaced (warn) on allocate/reassign.
- **`job_visits_sync`** — one visit row per date; a per-day inspector split assigns
  different engineers to different days; the main inspector still gets the full brief.
- **`job_mandays` / `job_money` / `job_profit`** — man-days = the quantity; revenue,
  credit and profit derive from it. **TC-JOBS-money-001:** recomputed **server-side**
  (never trust a JS-only figure); **TC-JOBS-money-002:** `job_profit` == the figure on
  every screen (detail, call-profit, MIS) to the paisa.
- **`ops_check_compoff`** — a Sunday visit date seeds a comp-off entitlement.
- **Numbering:** `ops_next_code('jobs','job_code','JOB')` — unique; PO line consumption
  increments `po_line_items.consumed` by man-days on new jobs only (TC-JOBS-money-003).

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| Call FORWARDED → job created | allocate | WHO present; executing office; **contract gate** (not expired / quantity left); competence + impartiality; call → ALLOCATED |
| open → closed | job-close | report date (unless NOREPORT); bills for chargeable heads; attendance check-in (or manager override + reason); **all visit days closed**; **final docs** (Final Inspection Report + Release Note) on file |
| closed → report_approval PENDING → signed | manager sign-off | reporting manager route |
| open → **locked** | grace sweep | not closed within N days of inspection end; only manager `/job-unlock` reopens |

- **TC-JOBS-life-001:** **contract gate** — allocating against an expired/quantity-exhausted
  contract is refused; a granted exception is *consumed on allocation*, not on grant.
- **TC-JOBS-life-002:** **close idempotency** — a re-posted close form (refresh /
  offline replay) must NOT file a second expenses row or double the claim.
- **TC-JOBS-life-003:** **lock** — a locked job refuses edit/close/advance/reassign
  (`job_lock_block`) but still accepts report/photo uploads; only a manager reopens.
- **TC-JOBS-life-004:** multi-day job cannot close while any visit day is open, and the
  final day needs its final docs (`job_visits_open_days`, `job_final_docs_missing`).

---

## F. Roles, permissions & data scope

Allocate/edit/reassign: `is_coordinator_level()` (+admin). Close: inspector or
coordinator (own scope). Advance status: coordinator / `data.credit` / `finance.reconcile`.
Unlock: manager. Credit figures hidden unless `data.credit`. Scope: a coordinator sees
only their office/SBU jobs (`scope_clause('j.executing_office_id','j.sbu')`).

- TC-JOBS-perm-001 (P-INSP crafted `/job-new` POST) → refused (allocation is
  coordinator-level, not just a hidden button).
- TC-JOBS-perm-002 (P-INSP) can close **their own** job but the money/credit columns
  stay hidden.
- TC-JOBS-scope-001: no cross-office jobs in board, export, or by crafted `id`.

---

## G. Settings

Engagement types & man-month bases; working-week/holidays (drives stepping); comp-off
policy; job-close **grace days** (`job_close_grace_days`) + lock enable; attendance
check-in requirement (off by default — site phone bans); competence/site-doc gates
(inspection pack); reporting frequency master; DEPUTATION service toggle
(`svc_globally_active('DEPUTATION')`). **TC-JOBS-set-001:** grace-days change moves the
lock due-date; **TC-JOBS-set-002:** attendance requirement off ⇒ close not blocked on
check-in; on ⇒ blocked unless manager overrides with a reason.

---

## H. Cross-module integration

**Calls** (allocation source; carry-forward of changed call fields; `ALLOCATED` stamp),
**Contracts** (gate + number + `contract_ref_ensure`/`boss_id`), **Competence /
Impartiality / Identity** (pack gates on assign), **IDEMS** (deliverables → owed reports;
final-docs close gate), **Invoicing** (`job-bill`, invoice value, po-line consumption),
**Credit/Reconcile** (`expected_credit`/`credit_recon`), **Vouchers/Expenses** (close row),
**Quotation** (`crm_apply_quote_to_job` advance/report-vs-payment). Idempotency:
double-submit allocate must not create two jobs (unique code); double-close must not
double-bill — TC-JOBS-int-001..002.

---

## I. Data integrity & audit

`idems_log('job',…)` / status events record allocate, lock, unlock, close. Man-days ↔
invoice value ↔ credit must reconcile; PO line `consumed` must match summed man-days;
`job_visits` must equal the resolved date set (no orphans after reassign). Lock stamp
(`locked_at`) and unlock reason are on the record. Attendance override records who/why.
**TC-JOBS-int-010:** reassign rewrites only visit rows, never the main inspector or the
job's figures.

---

## J. Reports & outputs

Assignment email (full brief; per-date emails on a split), closure email, jobs CSV
(with/without credit by permission), the job feeds the report header prefill and the
invoice. Deputation: site register, activity timesheet, client attendance approval sheet.
**TC-JOBS-out-001:** assignment email lists the correct engineer, dates, site, contact,
deliverables; **TC-JOBS-out-002:** CSV credit column present only for `data.credit`.

---

## K. Negative, edge & resilience

Allocate with nobody on it; same-office job that mustn't ask for credit; a cross-office
job with a blank credit; a pattern that resolves to zero dates; a Sunday-only job
(comp-off); a job whose contract is exhausted mid-allocation; closing twice; closing a
multi-day job with an open day; closing without the final docs; editing a locked job;
reassigning a locked job; an offline-queued close replay.

---

## L. TPIA operational suitability

Models the real deputation: who/where/when against a contract, working-day-aware
scheduling, man-day/man-month economics, availability board, per-day engineer splits,
site attendance and timesheet for long deputations, final-docs-before-close discipline,
and a grace-lock that stops figures being rewritten weeks later. Strong fit for both
single-visit inspection and continuous site operations.

## M. Management usefulness

Allocation board with unallocated filter; TAT and delay; per-job/-call profit; credit
exposure; availability/capacity outlook; lock alerts to the four responsible people.
Confirm TAT and profit match the dates and figures.

## N. UI/UX

Guided allocate form that shows the contract position *before* typing; engagement type
drives the date picker; "who does the work" is explicit; reassign without re-opening the
whole form; deputation tabs. Terminology (job/engineer/office via `T*()`).

## O. Security

Authorisation on allocate/close/reassign/advance/unlock (server-side, not hidden
buttons); CSRF on posts; scoped coordinator cannot touch another office's job via crafted
`id`; contract gate, competence gate and lock hold on crafted POSTs; credit columns
gated by `data.credit`.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D; scheduling + money priority |
| 5 Statuses | Y | §E (allocate→close→lock) |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §L |
| 12 Integration | Y | §H |
| 13 Data integrity | Partial | credit/PO/visits reconcile |
| 14 Audit | Y | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | (call is the source) |
| 22 Notifications | Y | assignment/closure/lock |
| 23 Offline | **Y** | close replay must be idempotent |
| 24 AI | Partial | deputation timesheet-vs-attendance advisory |
| 25 Licensing | Y | DEPUTATION service toggle |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | working days, grace lock, TAT |
| 28 Performance | Partial | board/availability at volume |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-JOBS-001 | (verify — potential Major) | Confirm the **contract gate** (`contract_gate`) and **scheduling limits** are enforced on the allocation POST for a crafted request, and that a granted exception is consumed exactly once. |
| GAP-JOBS-002 | (verify — potential Major) | Confirm **close idempotency**: a replayed close form files no second expenses row and does not double the claim (offline queue is the real risk). |
| GAP-JOBS-003 | (verify) | Confirm `job_visits` always equals the resolved date set after reassign (no orphan/duplicate rows) and PO `consumed` matches summed man-days. |
| GAP-JOBS-004 | — | Confirm same-office jobs never carry a stray `expected_credit`, and cross-office credit reconciles with `credit_recon`. |

---

## R. Traceability

RTM slice: `/jobs`, `/job-*`, `/schedule`, `/availability`, `/dep-*` × dims 1–29 →
TC-JOBS-* → results → DEF/GAP. **Verdict: Complete-with-defects** — scheduling-limit /
contract-gate enforcement, close idempotency, and credit/visit reconciliation are the
exit conditions.
