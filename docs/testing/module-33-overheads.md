# Inspection Ops — Overheads (Office Finance) — Test & Documentation Report

> **Prompt 3 · Module MOD-OVERHEADS.** Read from `lib/ops.php` (`ops_office_finance`,
> `office_overhead_pct`/`office_contingency_pct`/`global_overhead_pct`, `inspector_daily_cost`,
> `boss_profit`) and `lib/costing.php` (`ops_cost_run`, `costing_run`, `office_expenses_save`,
> `costing_spread`, `ALLOC_BASES`, `IDLE_BASES`, `person_split`, `office_uses_real_costs`,
> `cost_month_frozen`). Views `office_finance.php`, `cost_run.php`.

| | |
|---|---|
| **Module** | Overheads / Office Finance (MOD-OVERHEADS) · Area Money/Admin |
| **Personas** | P-ACCTS/P-BM (enter costs), P-MASTER (run/freeze), P-MD (P&L) |
| **Risk weight** | **Medium-High** — office cost that reduces net margin; two divergent models risk mis-stated profit |
| **Verdict** | Complete-with-defects (confirm model reconciliation, ledger link, freeze integrity) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Office finance carries the real monthly overheads and allocates them to Business Units / jobs.
**Two parallel models coexist:** (1) a **legacy overhead % + contingency %** baked into loaded
labour, used by per-contract profitability (`boss_profit`, MOD-32); and (2) the **real-cost
model** — monthly office expense heads entered per office (`office_expenses`), then a month-end
**cost run** (`costing_run`) that allocates engineers' worked/idle days, non-production staff
salary (by `person_sbu_split`), office expense heads (by EQUAL/MANDAYS/REVENUE/HEADCOUNT
basis), and subcon costs into `cost_allocations`, frozen per office+month. Salary is forbidden
as an expense head (double-count guard). The two models are **not reconciled** and there is
**no ledger/books link**.

Screens: `/office-finance` (tabs actual/sbus/pct), `/cost-run`, `/sbu-pl`,
`/m/office-expense-heads`. Tables: `office_expense_heads`, `office_expenses`,
`cost_allocations`, `cost_runs`, `person_sbu_split`, `offices.overhead_pct`.

---

## B. Screen-by-screen catalogue

**`/office-finance`** — **actual** (per-head monthly amounts, copy-last-month), **sbus**
(office SBU list + idle basis), **pct** (per-office overhead/contingency %). **`/cost-run`** —
per office+month: preview, calculate/commit, freeze, reopen. **`/sbu-pl`** — reads the stored
allocations. **Expense-head master** — CRUD (rename/re-basis/retire).

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-OVH-form-001 | Expense amount ≥ 0; blank ≠ zero (blank omits/deletes the head row). |
| TC-OVH-form-002 | Overhead/contingency % empty → NULL → falls back to global/default. |
| TC-OVH-form-003 | `idle_basis` ∈ IDLE_BASES; SBU codes intersected with valid lookup. |
| TC-OVH-form-004 | Person split accepted only for valid office SBUs; meaningful only at 100% (deviations scaled + warned). |
| TC-OVH-form-005 | Salary is **forbidden** as an expense head (double-count guard). |

---

## D. Functions & logic

- **Allocation engine** (`costing_run`): worked days → job's SBU (DAYS_WORKED); idle days
  spread (own-mix/office-mix/split/equal); non-production salary by person split; office heads
  by each head's basis; subcon direct. Total allocated == total spent (remainder on largest,
  `costing_spread`). **TC-OVH-fn-001.**
- **Two models** — legacy `boss_profit` uses overhead% baked into labour + contingency%; the
  real office heads only affect SBU-level P&L via the run. **A contract's margin and the SBU
  P&L can disagree, and entered office overheads never reduce contract-level profit**
  (GAP-OVH-001). **TC-OVH-fn-002.**
- **Freeze** (`cost_month_frozen`): a frozen office-month blocks expense/split edits; reopen
  required. But **freeze is per office-month only** — upstream jobs/salaries can change,
  diverging the frozen snapshot with no drift detection (GAP-OVH-003). **TC-OVH-fn-003.**
- **Copy-forward** can silently double-count if run after partial manual entry (GAP).
  **TC-OVH-fn-004.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| cost run OPEN → FROZEN | freeze | prior calculate required |
| FROZEN → OPEN | reopen | before editing frozen figures |
| office-month | freeze | blocks expense/split edits |

- **TC-OVH-life-001:** a frozen month blocks office-expense and person-split edits.
- **TC-OVH-life-002:** reopen returns to OPEN and re-enables edits.

---

## F. Roles, permissions & data scope

View overheads/cost-run: `mod.overheads.view` (+ salary-visible/admin for the run). Edit office
finance: admin/`settings.manage`. Run/freeze: master/`settings.manage`/`users.manage.global`.
Global %: settings.manage/master. Data scope: non-global users restricted to `home_office_id`;
global grant = master/`users.manage.global`/`settings.manage`/`data.profitability`.

- TC-OVH-perm-001 (run/freeze without authority) → refused.
- TC-OVH-scope-001: a branch user edits only its own office.

---

## G. Settings

Global `overhead_pct`/`contingency_pct` (else `OVERHEAD_PCT` 8), per-office overrides, idle
basis, expense-head master (rename/re-basis/retire; retired heads shown for months holding
money). **TC-OVH-set-001:** a per-office % overrides the global; **TC-OVH-set-002:** a retired
head still shows for months with amounts.

---

## H. Cross-module integration

**Profitability** (legacy % → `boss_profit`; real cost → `sbu-pl` — MOD-32), **Jobs/Vouchers**
(worked days + expenses feed the run), **Ledger/Books** — **NO integration** (office overheads
are a standalone analytical layer; nothing posts to the GL — GAP-OVH-002). Audit:
`idems_log('office', COST_RUN/COST_FREEZE/COST_REOPEN)`.

---

## I. Data integrity & audit

Allocation total == spent (no rupee lost). Cost run/freeze/reopen logged. **Gaps:** the two
overhead models can disagree; no GL reconciliation; freeze snapshot can drift from live source;
split integrity advisory (scaled not rejected); HEADCOUNT weighting convoluted; seeded OVERHEAD
+ CONTINGENCY heads overlap the legacy contingency% (double-count risk). **TC-OVH-int-010:**
the allocation sums to the month's spend; **TC-OVH-int-011:** a contract's overhead is not
double-counted between the % model and a CONTINGENCY head.

---

## J. Reports & outputs

The office-finance grid, the cost-run allocation, the SBU P&L. No document of its own.
**TC-OVH-out-001:** the SBU P&L cost equals the frozen allocation for the month.

---

## K. Negative, edge & resilience

A contract margin disagreeing with SBU P&L (two models); a copy-forward after partial entry
(double-count); a frozen month with changed upstream jobs (drift); a split ≠ 100% (scaled); a
salary entered as a head (forbidden); a retired head with historical amounts (still shown).

---

## L. TPIA operational suitability

Models real office finance — monthly overhead heads allocated to SBUs on a chosen basis, with
a freeze for period close. The right shape, but the coexistence of the legacy % model and the
real-cost model (unreconciled, no GL link) is the defect to resolve so profit is stated once.

## M. Management usefulness

Per-SBU real cost, office-cost basis transparency (`office_cost_basis_text`), period freeze.
Confirm the two models agree (or retire one).

## N. UI/UX

Grid entry with copy-last-month, cost-run freeze, basis picker. Terminology via `T*()`.

## O. Security

Edit/run/freeze gated; office-scoped; frozen months immutable (but drift undetected). No GL
write.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D |
| 5 Statuses | Y | §E freeze |
| 6 Validation | Y | §C |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | Y | §F |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §D run |
| 12 Integration | **Priority** | §H no GL / two models |
| 13 Data integrity | **Priority** | §I double-count/drift |
| 14 Audit | Y | §I |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | Y | §O |
| 21 Import | N-A | — |
| 22 Notifications | N-A | — |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | Y | Money |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | month/freeze |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-OVH-001 | (verify — Major) | **Two divergent overhead models coexist** — per-contract profit uses a flat overhead% + contingency%; the real office-expense heads only affect SBU-level P&L. A contract's margin and the SBU P&L can disagree, and entered office overheads never reduce contract-level profit. Reconcile or retire one; watch the CONTINGENCY-head vs contingency% double-count. |
| GAP-OVH-002 | (verify) | **No ledger/books link** — office overheads live in a bespoke table with no bridge to books/tally; reconciliation with the GL is manual. Add a bridge or reconciliation check. |
| GAP-OVH-003 | (verify) | **Freeze is per office-month only** — upstream jobs/salaries can change after a freeze, diverging the frozen `cost_allocations` snapshot from live source with no drift detection. Add drift detection or lock upstream. |
| GAP-OVH-004 | — | Split integrity is advisory (scaled, not rejected); copy-forward can double-count after partial entry; HEADCOUNT weighting is convoluted. |

---

## R. Traceability

RTM slice: `/office-finance`, `/cost-run`, `/sbu-pl` × dims 1–29 → TC-OVH-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — model reconciliation, a GL link, and freeze-drift
detection are the exit conditions.
