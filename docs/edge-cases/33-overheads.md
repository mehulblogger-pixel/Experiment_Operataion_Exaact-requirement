# Module 33 — Overheads · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: two overhead models that never meet, and no recovery figure

The app carries two parallel overhead models: **Model A** — a per-office `overhead_pct` + `contingency_pct`
baked into loaded labour and applied per job/contract by the canonical engine (`job_profit`,
`boss_profit`); and **Model B** — the real monthly office-expense pool (`office_expenses`) allocated to
SBUs by a month-end cost run. They share nothing at runtime, and — the headline gap — the **actual
overhead pool is never reconciled against the overhead recovered** through the per-job oh%. There is
no under/over-recovery figure anywhere. It is the estimate-vs-actual gap from Module 20, one level up.
The whole real-cost engine also had **zero test coverage**.

---

## 1. Built (additive, read-only, one canonical engine)

1. `overhead_recovery($officeId, $yr, $mon)` — composes two numbers that already exist:
   **pool** = `office_expense_total()` (the actual overhead entered); **recovered** = the sum of the
   `overhead` + `contingency` lines the **canonical `job_profit`** already loads onto that office's
   jobs whose work fell in the month. **No second cost formula** — variance is subtraction
   (`recovered − pool`) + a % of pool, exactly like `pc_estimate_vs_actual`.
2. An **"Overhead recovery"** panel on the `/cost-run` screen (already per office+month and
   salary-gated): pool, recovered, and over/under-recovery with a plain-English read.
3. **Confidentiality:** the recovered figure is salary-derived (from CTC), so it is **withheld** from
   a viewer without `can_see_salary()` (shown as "salary hidden"); the pool (office running costs —
   salary is forbidden as an expense head) is always shown.
4. The **first tests** over the overhead engine (it had none).

---

## 2. Edge cases handled

1. No jobs in the month → recovered 0, under-recovered by the whole pool (correct — no work recovered
   anything).
2. A job in a different month → not counted; each month has its own pool and jobs.
3. A job with no inspector → contributes 0 recovered overhead (no salary → no oh%).
4. Viewer without cost access → recovered/variance null (withheld); pool still shown.
5. Job-to-month matching uses `inspection_start_date` → `scheduled_date` → `created_at` (first
   non-empty), scoped to `executing_office_id` — the office that bears the cost.
6. `pool = 0` → no % shown (no divide-by-zero); the panel still renders pool vs recovered.

## 3. Guardrails (green)

`job_profit`/`boss_profit`, the oh%/contingency% settings, the cost run, `office_expenses`, and the
SBU P&L — all unchanged. No new permission (reuses the `/cost-run` salary gate); no schema change; no
data mutated; no second cost formula; nothing deleted. It makes the two-model divergence **visible
and measurable** for the first time without changing either model.

## 4. Deferred (noted)

Reconciling or retiring one of the two overhead models (the divergence this panel now surfaces); the
OVERHEAD/CONTINGENCY expense-head vs contingency% double-count; a per-office overhead-as-%-of-revenue
trend. Each is a deliberate finance decision, not an additive read.

## 5. Tests

`tests/test_module33_overheads.php` (14 assertions): pool = office_expenses total; recovered visible
to a cost-permitted viewer and withheld from others; variance = recovered − pool; no-jobs →
under-recovered by the pool; a month's jobs are scoped correctly; the engine reuses `job_profit`; the
panel renders.
