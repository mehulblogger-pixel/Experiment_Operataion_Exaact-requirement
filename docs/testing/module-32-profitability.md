# Inspection Ops — Profitability — Test & Documentation Report

> **Prompt 3 · Module MOD-PROFIT.** Read from `lib/ops.php` (`job_money`, `job_revenue_for`,
> `job_profit`, `job_mandays`, `job_expenses_total`, `job_voucher_total`, `office_overhead_pct`/
> `office_contingency_pct`, `working_days_in_month`), `lib/callprofit.php` (`ops_call_profit`,
> `callprofit_rows`), `lib/mis.php` (`ops_mis`, `mis_summary`, `mis_jobs`, `mis_utilisation`),
> `lib/costing.php` (`ops_sbu_pl`, `costing_run`, `costing_sbu_revenue`, stored allocations),
> `lib/bills.php` (`job_recovered_total`). Views `call_profit.php`, `sbu_pl.php`, `mis.php`.

| | |
|---|---|
| **Module** | Profitability (MOD-PROFIT) · Area Money |
| **Personas** | P-BM/P-SBU/P-MD (P&L), P-ACCTS, P-MASTER |
| **Risk weight** | **High** — the margin management steers on; a figure that disagrees across screens misleads decisions |
| **Verdict** | Complete-with-defects (confirm figure consistency across screens — the design's own promise) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

The P&L engine. `job_profit` is the single per-job computation: revenue (`job_revenue_for`:
whole invoice company-wide, holder/executor split cross-office by `expected_credit`) minus
every cost line — inspector **labour** (daily CTC × man-days), **overhead** (labour ×
office %), **expenses** (`job_expenses_total`), **voucher** (`job_voucher_total`), **subcon**,
**other**, less **recovered** (client-reimbursable bills, capped) — plus **contingency**.
The stated design principle is **"the same figure on every screen"**: per-job tab,
`call-profit`, `sbu-pl`, and `mis` should all derive money from `job_profit` off one filtered
job set with one revenue-date rule. A separate month-end **cost run** (MOD-33) allocates real
cost to SBUs for `sbu-pl`.

Screens: `/call-profit`, `/sbu-pl`, `/mis`, per-job Profitability tab. Tables: `jobs`, `calls`,
`inspectors`, `expenses`, `voucher_entries`, `job_bills`, `cost_allocations`, `offices`.

---

## B. Screen-by-screen catalogue

**`/call-profit`** — per-job/deputation P&L, totals, worst-5 loss list. **`/sbu-pl`** — SBU /
activity / contract P&L (live revenue vs stored `cost_allocations`). **`/mis`** — management
dashboard (operations + financial + utilisation + people). **Job Profitability tab** — the
itemised breakdown.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-PRF-form-001 | Zero invoice is "not an invoice" — does not displace the agreed figure. |
| TC-PRF-form-002 | Cross-office credit only counts when offices differ; same-office credit = 0. |
| TC-PRF-form-003 | Man-days ≥ 1; working days fall back to (days − Sundays) if holidays would zero it. |
| TC-PRF-form-004 | Recovered offset capped at expenses + voucher. |

---

## D. Functions & logic  *(figure consistency — highest scrutiny)*

- **Revenue split** (`job_money`/`job_revenue_for`): whole invoice company-wide; cross-office
  holder keeps invoice − credit, executor keeps credit; the two shares sum to the whole.
  **TC-PRF-fn-001.**
- **Per-job P&L** (`job_profit`): labour + overhead + expenses + voucher + subcon + other −
  recovered; + contingency; profit = revenue − cost; server-side recompute. **TC-PRF-fn-002.**
- **"Same figure everywhere"** — the design intent. **But:** `job_profit` uses the **current**
  month's working days for the daily-cost divisor regardless of the job's real month, so a
  historical job's labour cost drifts and can differ from `costing_run` (GAP-PRF-001); and
  **MIS aggregates a hand-rolled cost** (labour+expenses+subcon), dropping overhead/voucher/
  other/contingency/recovered, so MIS "profit" ≠ call-profit "profit" for the same jobs
  (GAP-PRF-002). **TC-PRF-fn-003..004** — reconcile the four screens to the rupee.
- **SBU P&L** (`ops_sbu_pl`): live revenue vs **stored** cost_allocations — if a run is stale/
  uncommitted, revenue and cost cover different data (GAP-PRF-003). **TC-PRF-fn-005.**

---

## E. Status & lifecycle

No lifecycle — profitability is a computed view over jobs/allocations. **TC-PRF-life-001:**
`jobs_backfill_money` copies the billable value/office from the originating call once, then
profit reads live; **TC-PRF-life-002:** a frozen cost-run month gives a stable SBU P&L.

---

## F. Roles, permissions & data scope

`data.salary`/`can_see_salary` gates cost/salary/overhead/profit columns (without it,
call-profit shows dashes, MIS shows revenue-only). `data.credit` gates credit/advance.
`data.profitability`/`data.revenue` gate the screens. Handlers gated
(`data.profitability||data.revenue||admin` etc.). Scope via `scope_offices()` /
`costing_offices_for_user()`.

- TC-PRF-perm-001 (P&L without `data.salary`) → cost columns hidden.
- TC-PRF-scope-001: a branch sees only its office's jobs.

---

## G. Settings

Global `overhead_pct` (else `OVERHEAD_PCT` 8), `contingency_pct`, per-office overrides, idle
basis, expense-head allocation basis, `holidays`, `OPS_SBUS`, `tat_threshold_days`,
`fy_revenue_target`. **TC-PRF-set-001:** a per-office overhead % overrides the global on that
office's jobs.

---

## H. Cross-module integration

**Jobs/Invoicing** (revenue, billed/paid), **Vouchers/Expenses** (cost — MOD-30), **Overheads/
Cost run** (SBU cost — MOD-33), **Credit** (cross-office split — MOD-31), **Dashboards** (MIS/
exec board — MOD-34). Idempotency: computed, read-only.

---

## I. Data integrity & audit

The "same figure" promise is the integrity contract — **currently not fully met** across MIS
vs call-profit vs SBU-P&L. `job_profit` divisor uses the current month (drift). SBU revenue is
live but cost is frozen. **TC-PRF-int-010:** the per-job figure equals the call-profit line;
**TC-PRF-int-011:** MIS totals equal the sum of `job_profit` (not a hand-rolled cost).

---

## J. Reports & outputs

Call-profit, SBU-P&L, MIS (with/without cost columns by permission), the exec strategic board.
**TC-PRF-out-001:** exports carry cost columns only for `data.salary`; **TC-PRF-out-002:**
"revenue booked" (exec board) vs invoiced value on the same screen are reconciled (they use
different date rules — verify).

---

## K. Negative, edge & resilience

A historical job's labour cost drifting with the calendar month; MIS profit disagreeing with
call-profit; a stale/uncommitted cost run (SBU revenue vs cost mismatch); a zero-invoice job;
a same-office job with a stray credit; a P&L viewed without `data.salary` (dashes).

---

## L. TPIA operational suitability

Gives per-deputation, per-contract and per-SBU margin with cross-office credit handled — the
economics a multi-branch TPIA runs on. The single-`job_profit` design is right; the
figure-consistency drift is the defect to close so every screen agrees.

## M. Management usefulness

Worst-loss lists, SBU P&L, MIS, exec board with FY target/YoY/utilisation. **Its usefulness
depends on the figures agreeing** — the consistency gaps directly undermine it.

## N. UI/UX

Itemised breakdown, permission-gated columns, dashboards. Terminology via `T*()`.

## O. Security

Cost/salary/credit columns permission-gated; exports respect the same; office-scoped. No
write surface (computed).

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | N-A | computed |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | N-A | — |
| 12 Integration | Y | §H |
| 13 Data integrity | **Priority** | §I figure consistency |
| 14 Audit | Partial | computed |
| 15 Outputs | **Priority** | §J money fidelity |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | **Priority** | §M depends on consistency |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | N-A | — |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | Money/profitability |
| 26 Terminology | Y | — |
| 27 Time/FY | **Priority** | §D month divisor |
| 28 Performance | Partial | N+1 in reports |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-PRF-001 | (verify — Major) | `job_profit` uses the **current month's working days** for the daily-cost divisor regardless of the job's real month — a historical job's labour cost drifts as the calendar advances and diverges from `costing_run` (which uses the run month). Use the job's month. |
| GAP-PRF-002 | (verify — Major) | **MIS aggregates a hand-rolled cost** (labour+expenses+subcon), dropping overhead/voucher/other/contingency/recovered — so MIS "profit" ≠ call-profit "profit" for the same jobs, breaking the "same figure everywhere" promise. Aggregate `job_profit`'s `cost`/`profit`. |
| GAP-PRF-003 | (verify) | **SBU-P&L mixes live revenue with frozen cost_allocations** — a stale/uncommitted run makes the two cover different data; and offices on the legacy overhead-% blend with offices on real allocated cost. Enforce a committed run and a single basis. |

---

## R. Traceability

RTM slice: `/call-profit`, `/sbu-pl`, `/mis`, job Profitability tab × dims 1–29 → TC-PRF-* →
results → DEF/GAP. **Verdict: Complete-with-defects** — figure consistency across screens
(the design's own promise) is the exit condition.
