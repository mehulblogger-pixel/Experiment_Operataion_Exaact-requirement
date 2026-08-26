# Module 20 — Project Costing · Edge-case analysis (pre-build)

**Status:** ✅ BUILT (2026-08-26). Decision: (A) estimate-vs-actual reconciliation + first estimate-
math tests; margin-floor guard (§5-B) and CTC-derived manpower (§5-C) deferred. Implemented the link
**derive-on-read** (matching `boss_number` = `quotations.contract_number`) — even more non-destructive
than the spec's stored column: **zero schema change**, works retroactively. `pc_for_boss()` +
`pc_estimate_vs_actual()` (reusing `boss_profit`) + a panel on the `data.profitability`-gated
profitability detail. Asserted by `tests/test_module20_costing.php`. P1.

---

## 0. Headline: a bid estimate that never learns whether it was right

There are **two** "costing" subsystems, and they never meet:

- **Project costing** (`lib/projcosting.php`) — a **pre-award estimate** (bid build-up): a header
  (`project_costings`) + per-role lines (`project_costing_lines`) with **typed** cost heads,
  loadings, a per-line `target_margin` that sets the rate, and stamped rollups
  `exp_revenue / exp_cost / exp_profit` (`pc_rollup` / `pc_restamp`). Attachable to a **quote**,
  **opportunity** or **recruitment requisition**. Gated by the broad `pc_can()`.
- **The actuals / profitability engine** (`ops.php` — `boss_profit()`, `job_profit()`,
  `ops_profitability()`) — post-facto **actual** margin from real CTC + vouchers + expenses +
  subcon, gated tightly by `data.profitability` / `data.salary`.

**The #1 gap:** project costing is **estimate-only**. Nothing rolls **actual** cost up against the
estimate — you can bid a 22% margin and **never learn whether the project delivered it**. The two
sides share **no key and no code path**: `project_costings` carries `quote_id / opportunity_id /
requisition_id` but **no `boss_id` / `contract_number`**, so even a report *couldn't* join the
estimate to the contract where the actuals land. `boss_profit()` never reads the estimate.

Secondary, deliberately-not-touched observations (flag, don't "fix"): the estimate's cost/margin is
visible to any `mod.quotes.view` / `mod.hiring.view` holder (`pc_can()`) — defensible for a
sell-side bid, but an asymmetry vs the `data.*`-gated actuals engine; there is **no margin floor**;
the manpower figure is typed, not derived from real CTC; and the estimate math has **zero unit
tests** despite a seed that advertises "hand-checkable numbers".

---

## 1. What is deliberately NOT in scope (program rules)

- **No second margin formula.** Actuals stay `boss_profit()`; the estimate stays `pc_rollup()`. The
  new panel only *displays both and subtracts* — honouring the "one canonical profitability engine"
  rule.
- **No widened exposure.** The reconciliation lives **inside** the `data.profitability`-gated
  profitability screen. `pc_can()` is **not** touched; no cost/margin reaches a role that can't
  already see contract profitability.
- **No hard control.** No margin-floor gate on submit/approve (that would change who-can-do-what and
  needs a separate decision). No change to the estimate's own visibility.
- **No deletion / no rename.** The `lib/costing.php` vs `lib/projcosting.php` naming confusion stays
  as-is; both engines are untouched.

---

## 2. Proposed additive layer (recommended = §5-A)

**Link each approved costing to its contract, and show Estimated vs Actual where profitability is
already reviewed.**

1. **One additive column** `boss_id INT NULL` on `project_costings` (via the `ensure_column` pattern
   already in `pc_migrate()`), plus a read-only helper **`pc_for_boss($bossId)`** mirroring the
   existing `pc_for_quote()`.

2. **Opportunistic, non-destructive write-through** — when a quote that carries a costing is won
   into a contract (the existing quote→contract registration path), stamp the costing's `boss_id`
   with the resulting contract's boss id. Best-effort, never blocking the win; a costing with no
   contract simply stays `boss_id = NULL` (pre-award, as today). No retroactive rewrite of anything
   else.

3. **`pc_estimate_vs_actual($bossId)`** — a read-only helper that returns, side by side:
   **Estimated** = the linked costing's `exp_revenue / exp_cost / exp_profit` + margin (already
   computed by `pc_rollup` / stamped in `pc_restamp`), and **Actual** = the numbers already returned
   by `boss_profit($bossId)` (the sole actuals source). Variance is computed in the helper/view only
   — no new formula, just subtraction.

4. **An "Estimated vs Actual" panel** on the profitability detail view
   (`views/ops/profitability_detail.php`, rendered by the `data.profitability`-gated
   `ops_profitability()`): estimate, actual, and variance (₹ and margin-points), with a plain-English
   "made / missed its bid margin by N points" line. Shown only when a linked costing exists; a clean
   "no bid estimate linked" note otherwise. Salary/labour components stay behind `can_see_salary()`
   exactly as `boss_profit` already gates them.

5. **Fill the estimate-math test gap** — add the first unit tests over `pc_line_calc` / `pc_rollup`
   (direct → loaded → rate → revenue → cost → profit → margin), so the "hand-checkable" seed numbers
   are finally checked.

Reuses: `pc_for_quote` pattern, `pc_rollup` / `pc_restamp`, `boss_profit()`, `ops_profitability` +
its gate, the `ensure_column` migration pattern. **No new permission; one nullable additive column;
no new margin formula; no widened exposure.**

---

## 3. Edge cases

1. **Costing never won into a contract** → `boss_id = NULL`; it stays a pure pre-award estimate,
   exactly as today; it never appears in the reconciliation.
2. **Contract with no linked costing** (a direct win, or a pre-costing legacy contract) → the panel
   shows "no bid estimate linked", never a fabricated or zero estimate.
3. **Multiple costings historically attached to a quote chain** → link the **approved / current**
   one; if ambiguous, prefer the latest approved, and the helper returns a single estimate (never
   double-counts).
4. **Estimate in a different basis (LONG/DAILY/FIXED)** → the rollup `exp_*` are already normalised
   money totals; the panel compares money-to-money, not basis-to-basis.
5. **Actual not yet incurred** (contract just opened) → actual cost ≈ 0; the panel shows the estimate
   vs a low/early actual and labels it "actuals still accruing", not "huge profit".
6. **Viewer without `can_see_salary()`** → the labour component of `boss_profit` is already withheld
   for them by the engine; the panel inherits that — it never reveals salary to someone the actuals
   screen wouldn't.
7. **Viewer without `data.profitability`** → never reaches the screen at all (the existing route
   gate); the panel adds no new entry point.
8. **Estimate margin negative / zero** → shown honestly; no floor is enforced (out of scope).
9. **Currency** → both sides are INR (the estimate is INR-hardwired; actuals are INR); no
   cross-currency comparison is attempted.
10. **Write-through idempotency** → stamping `boss_id` is a single UPDATE guarded to only set it when
    empty (or to the same contract), so re-winning / re-registering doesn't thrash it.
11. **Performance** → one costing lookup + one `boss_profit` call per contract-detail view (already
    computed for that screen); no per-row storm.

---

## 4. Guardrails (must stay green)

- The estimate engine (`pc_line_calc`, `pc_rollup`, `pc_restamp`, the builder/approval routes,
  `pc_make_quote` / `pc_make_requisition`), the actuals engine (`boss_profit`, `job_profit`,
  `ops_profitability`), and their gates — **all unchanged**. The module adds a nullable column, a
  read helper, a write-through stamp, and a view panel.
- `test_profitability_visibility`, `test_simplify_costs`, `test_perms_no_lockout`,
  `test_no_dead_permission_gates` — untouched.
- No route/table/column/permission removed or narrowed; `pc_can()` unchanged; no cost/margin exposed
  to a role that couldn't already see contract profitability.

---

## 5. DECISION (recommended option built in this autonomous run)

- **(A) Estimated-vs-Actual reconciliation + estimate-math tests (recommended, P1) — BUILDING:**
  `boss_id` link + `pc_for_boss` + opportunistic write-through on win + a read-only estimate-vs-actual
  panel on the profitability-gated screen (reusing `boss_profit`), plus the first unit tests on the
  estimate math. Closes the "did it make its bid margin?" gap with no new formula and no widened
  exposure.
- **(B) Also add a margin-floor / negative-margin warning** on costing submit/approve — advisory
  first (a loud warning), a hard block only on explicit request. A separate decision (changes the
  approval flow). Deferred.
- **(C) Also derive/validate the manpower estimate against real CTC** (`users.monthly_ctc` /
  `inspectors.salary_ctc`) — ties the typed figure to reality, but needs the `data.salary` gate and
  touches the builder. Larger; deferred.

---

## 6. Tests

1. `pc_line_calc` / `pc_rollup`: a known role line (typed heads + loadings + target margin) produces
   the hand-checkable direct → loaded → rate → revenue → cost → profit → margin (the first coverage
   of the estimate math).
2. `pc_for_boss($bossId)`: returns a costing linked to a contract; null when none is linked.
3. Write-through: winning a costing-bearing quote into a contract stamps `boss_id` once (idempotent);
   a re-register does not thrash it.
4. `pc_estimate_vs_actual`: returns estimate (`exp_*`) and actual (`boss_profit`) with a correct
   variance; "no estimate linked" when `boss_id` is null; never invents a second margin number.
5. Preservation: `pc_can()`, the estimate/actual engines and the profitability gate are unchanged; no
   new permission; a viewer without `data.profitability` still cannot reach it.
