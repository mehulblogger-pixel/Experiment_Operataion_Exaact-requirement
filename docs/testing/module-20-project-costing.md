# Inspection Ops — Project Costing — Test & Documentation Report

> **Prompt 3 · Module MOD-PROJCOST.** Read from `lib/projcosting.php` (`pc_line_calc`,
> `pc_rollup`, `pc_restamp`, `pc_locked`, `pc_make_quote`, `pc_make_requisition`,
> `pc_defaults`, `pc_can`/`pc_can_edit`/`pc_can_approve`, `ops_projcosting`) and
> `lib/costing.php` (`ops_cost_run`, `costing_run`, `cost_month_frozen`) — the **budget/bid**
> build-up vs the month-end **actuals** allocation. Views `project_costing*.php`, `cost_run.php`.

| | |
|---|---|
| **Module** | Project Costing (MOD-PROJCOST) · Area Sales/Money |
| **Personas** | P-BDM/P-COORD (build a bid), P-BM/P-MASTER (approve), P-ACCTS (cost run) |
| **Risk weight** | **High** — the bid economics behind a quote; a wrong loading or margin under-prices real work |
| **Verdict** | Complete-with-defects (confirm margin maths, scoping, and budget-vs-actual gap) |

## Revision history
| Ver | Date | Author | Change |
|---|---|---|---|
| v1.0 | (stamp) | QA (Prompt 3) | Initial. |

---

## A. Module overview

Two related engines. **(A) Project Costing** (`projcosting.php`) is the bid/deputation
**cost build-up**: per-role lines carry cost heads (personnel, food, travel …), a
**loading** (overhead + insurance + bad-debt + contingency), a target margin or manual
rate, and a **negotiation** uplift, resolved into revenue/cost/profit/margin per line and
rolled up to the project (`pc_line_calc` → `pc_rollup` → `pc_restamp`). It converts to a
**quotation** (`pc_make_quote`, one line per role) or a recruitment **requisition**
(`pc_make_requisition`). Lifecycle DRAFT → SUBMITTED → APPROVED (+REJECTED), locked once
SUBMITTED/APPROVED.

**(B) Cost Run** (`costing.php`) is the month-end **actuals** allocation: real inspector
worked/idle days, staff salary splits, office expenses and subcon costs allocated to
SBUs/jobs (`cost_allocations`), frozen per office+month. This is the actual side of
profitability (MOD-32).

Screens: `/project-costings`, `/project-costing?id=`, `/project-costing-print`,
`/cost-run`. Tables: `project_costings`, `project_costing_lines`, `cost_allocations`,
`cost_runs`, `office_expenses`.

---

## B. Screen-by-screen catalogue

**`/project-costing`** — header (client, office, basis LONG/DAILY/FIXED, load-on COST/RATE,
overhead/insurance/bad-debt/contingency/negotiation %s) + role lines (cost heads JSON,
BOQ qty, target margin, manual rate, one-off mobilisation). Actions: save, submit, approve,
reject, reopen, attach to quote/opportunity, **to-quote**, **to-requisition**, print.
**`/cost-run`** — per office+month: calculate (OPEN), **freeze** (FROZEN), reopen; shows
inspector worked/idle, staff salary split, office expenses, subcon.

---

## C. Field & validation

| TC ID | Title → Expected |
|---|---|
| TC-PC-form-001 | Percentages numeric; **confirm non-negative and sane-sum** (GAP: currently unclamped). |
| TC-PC-form-002 | Cost heads stored only when in the catalogue and non-zero. |
| TC-PC-form-003 | `pc_make_quote` requires ≥1 line; margin maths guard divide-by-zero. |
| TC-PC-form-004 | Basis LONG (man-month × BOQ) / DAILY (man-day × days) / FIXED (lump, qty 1) apply correctly. |
| TC-PC-form-005 | A locked (SUBMITTED/APPROVED) costing is read-only. |

---

## D. Functions & logic  *(margin maths — highest scrutiny)*

- **`pc_line_calc`:** direct cost = Σ heads; loading % = overhead+insurance+baddebt+
  contingency; **load-on COST** → loaded = direct×(1+load%); **load-on RATE** → loadings
  recovered on the sell rate. Sell rate = manual override, else derived from target margin;
  proposed = rate×(1+negotiation%). Revenue = proposed×qty; cost = loaded×qty + one-off;
  margin = profit/revenue. **TC-PC-calc-001..004** — verify each basis and both load bases
  to the rupee.
- **Margin edge:** target_margin ≥ 100 (COST) or tm+load ≥ 100 (RATE) silently falls back
  to loaded/direct → a hidden ~0-margin bid. **TC-PC-calc-005 (GAP-PC-002).**
- **`pc_rollup`/`pc_restamp`:** project totals + per-head totals; headline exp_revenue/
  cost/profit persisted. **TC-PC-fn-001.**
- **Cost Run** (`costing_run`): allocates a month's real spend to SBUs/jobs — worked days
  by daily CTC to the job's SBU, idle days spread, office expenses by basis
  (EQUAL/HEADCOUNT/MANDAYS/REVENUE), subcon direct. Frozen month blocks recalculation.
  **TC-PC-fn-002.**

---

## E. Status & lifecycle

| From → To | Trigger | Guard |
|---|---|---|
| DRAFT → SUBMITTED | submit | locks editing |
| SUBMITTED → APPROVED | approve | approver (see §F) |
| SUBMITTED/APPROVED → REJECTED/DRAFT | reject/reopen | authorised |
| costing → quote / requisition | to-quote / to-req | ≥1 line; permission |
| cost run OPEN → FROZEN | freeze | month fixed; blocks voucher edits |

- **TC-PC-life-001:** a SUBMITTED/APPROVED costing cannot be edited (`pc_locked`).
- **TC-PC-life-002:** a frozen cost-run month blocks recalculation and expense edits.

---

## F. Roles, permissions & data scope

View (`pc_can`): quotes/hiring/inquiries view or admin/master. Edit: quotes/hiring edit or
admin/master. Approve (`pc_can_approve`): admin/master or `crm.quote.approve`. **Cost run:**
salary-visible + admin; office-scoped (`costing_offices_for_user`). **Project costing has
NO office/owner scoping** — any viewer sees all bids (**GAP-PC-001**).

- TC-PC-perm-001 (edit without permission) → refused.
- TC-PC-perm-002 (**independence**): confirm the creator cannot also approve their own
  costing where independence is required.

---

## G. Settings

`costing_overhead_pct` (8), `costing_insurance_pct` (0.5), `costing_baddebt_pct` (0),
`costing_contingency_pct` (0.5), `costing_negotiation_pct` (5), `costing_food_per_day`
(250); head catalogue (`projcosting_head`). **GST is hard-coded 18% in `pc_make_quote`**
(GAP). **TC-PC-set-001:** default loadings apply on a new costing.

---

## H. Cross-module integration

**Quotations** (costing ↔ quote; `pc_make_quote` seeds quote lines), **Recruitment**
(`pc_make_requisition` — per-role economics), **Cost Run/Profitability** (actuals side —
MOD-32), **Jobs** (man-day economics). **No code path reconciles bid budget vs cost-run
actuals** (GAP-PC-003). Idempotency: `pc_make_quote`/`_requisition` not transactional — a
mid-loop failure leaves a partial quote/requisition (GAP).

---

## I. Data integrity & audit

Line rollup ↔ header headline figures kept via restamp; locked states protect a submitted
bid. Migrations/restamp wrap errors silently (no logging — GAP). **TC-PC-int-010:** the
quote generated from a costing matches the costing line-for-line; **TC-PC-int-011:** a
frozen cost-run month is immutable.

---

## J. Reports & outputs

The printable costing sheet, the generated quotation, the requisition, and the cost-run
allocation. **TC-PC-out-001:** the costing print totals == screen; **TC-PC-out-002:** the
quote created carries the proposed rate × BOQ and the configured GST.

---

## K. Negative, edge & resilience

A percentage summing >100% (accepted silently — GAP); a target margin ≥100 (hidden
zero-margin); a bid with no lines (no quote); a partial to-quote failure; a frozen month
recalculated; a RATE-basis bid created (defaults COST, needs re-save).

---

## L. TPIA operational suitability

Models the real bid build-up a TPIA prices deputations on — per-role man-month/man-day
economics with loadings and negotiation — and separately allocates real monthly cost to
SBUs. The two halves (budget vs actual) are the right shape; wiring them together would
close the loop.

## M. Management usefulness

Bid margin visibility, per-SBU actual P&L from the cost run, quote/requisition generation.
Confirm the bid margin is meaningful (edge cases don't silently zero it) and reconcile
against actuals.

## N. UI/UX

Guided costing editor, one-click to-quote/to-requisition, printable sheet, cost-run
freeze. Terminology via `T*()`.

## O. Security

Edit/approve gated; **but no data scoping on bids** (GAP-PC-001 — salary-like head data
visible to any quotes/hiring viewer); cost run salary-gated + office-scoped; frozen months
immutable.

---

## P. Coverage scorecard

| # | Dim | Covered | Note |
|---|---|---|---|
| 1–4 | Y | §B–D; margin priority |
| 5 Statuses | Y | §E |
| 6 Validation | Partial | §C unclamped % |
| 7 Negative | Y | §K |
| 8 Roles | Y | §F |
| 9 Scope | **Gap** | §F no bid scoping |
| 10 Settings | Y | §G |
| 11 Workflow | Y | §E |
| 12 Integration | **Priority** | §H budget↔actual gap |
| 13 Data integrity | **Priority** | §I margin/rollup |
| 14 Audit | Partial | §I silent errors |
| 15 Outputs | Y | §J |
| 16 TPIA suitability | Y | §L |
| 17 Mgmt usefulness | Y | §M |
| 18 UI/UX | Y | §N |
| 19 Gap | Y | §Q |
| 20 Security | **Priority** | §O bid scoping |
| 21 Import | N-A | — |
| 22 Notifications | N-A | — |
| 23 Offline | N-A | — |
| 24 AI | N-A | — |
| 25 Licensing | N-A | — |
| 26 Terminology | Y | — |
| 27 Time/FY | Y | cost-run month |
| 28 Performance | Partial | — |
| 29 Backup | N-A here | — |

**Verdict:** Complete-with-defects.

---

## Q. Defects & gaps

| ID | Sev·Pri | Title → Recommendation |
|---|---|---|
| GAP-PC-001 | **Major (verify)** | Project costings have **no office/owner data scoping** — any user with quotes/hiring/inquiries view sees all bids (incl. salary-like head data). Add scoping or gate the money columns. |
| GAP-PC-002 | (verify) | The margin maths **silently falls back to zero-margin** when target_margin ≥100 (COST) or tm+load ≥100 (RATE) — surface an error instead of a hidden under-priced bid. |
| GAP-PC-003 | (verify) | **No budget-vs-actual reconciliation** — project costing (bid) and cost run (actual) never meet; the shown margin is never validated against realised job cost. |
| GAP-PC-004 | — | Clamp percentages (non-negative, sane sum); make `pc_make_quote`/`_requisition` transactional; surface the hard-coded 18% GST as a setting. |

---

## R. Traceability

RTM slice: `/project-costing*`, `/cost-run` × dims 1–29 → TC-PC-* → results → DEF/GAP.
**Verdict: Complete-with-defects** — margin-maths correctness, bid data scoping, and
budget-vs-actual reconciliation are the exit conditions.
