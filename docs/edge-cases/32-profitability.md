# Module 32 — Profitability (canonical engine) · Edge-case analysis

**Status:** ✅ BUILT (2026-08-26). Autonomous run — recommended additive option built.

---

## 0. Headline: one canonical engine, several screens quietly re-derive a partial profit

`job_profit($job, $officeId)` (`lib/ops.php:1629`) is the canonical per-job P&L. Its `profit`/`cost`
account for **labour + overhead + expenses + vouchers + subcon + other − client-recovered +
contingency**. Two dashboards, fed by that same engine, throw the engine's `profit` away and rebuild
it inline:

- **MIS** (`lib/mis.php:118-119, 140-141`): `profit = credit − labour − expenses − subcon`,
  `cost = labour + expenses + subcon` — drops overhead, voucher, other, contingency and recovered.
  Feeds the totals and every breakdown (by SBU / office / **client** / boss / inspector).
- **SBU-P&L contract table** (`costing_boss_lines`, `lib/costing.php:754-762`): the same partial
  formula, then sorts by it.

So on the *same jobs*, MIS and SBU-P&L show a **systematically higher** profit than `/profitability`
and the management-report financial total (`lib/ops.php:6784`), which read `$p['profit']` correctly.
This is the exact drift a "canonical engine" module exists to catch.

**Why surfaced, not silently rewritten:** changing the MIS / SBU-P&L / home-dashboard figures alters
existing displayed financial numbers that managers rely on — outside the standing "additive; existing
business logic must remain available; nothing minimized" rule. So, consistent with how this program
has handled every behaviour/control change, the corrections are **flagged for an explicit decision**,
and the additive deliverable is a *consistency check* that quantifies the gap — the profitability
analogue of the sealed-audit chain-verify.

**Noted, deferred (flagged, not built — each alters an existing figure or is its own feature):**
- **DRIFT A/B** — reconcile MIS + SBU-P&L to `job_profit()['profit']` (changes displayed profit).
- **DRIFT C** — `boss_profit` vs Σ`job_profit` are two contract formulas (loaded-vs-split labour,
  voucher scope, omitted other/recovered) that can disagree; unifying them changes contract margins.
- **DRIFT D** — home dashboard "Revenue booked (FY)" sums raw `expected_credit` (reads zero for
  same-office jobs — the documented old bug); route it through `job_revenue_for`.
- **DRIFT E** — reports byClient chart uses an `invoice_amount`-first revenue variant.
- Canonical **by-client / by-SBU** profitability on `/profitability` (today only on MIS, via the
  partial formula); **job-level and office-level estimate-vs-actual**; **loss flagging** on the
  by-boss / SBU / client lists — all additive features for later.

---

## 1. Built (additive, read-only; measures every job against the one engine)

`profit_reconciliation($F = null)` (`lib/mis.php`, beside the partial formula it measures):
- Enumerates the same job population MIS uses (`mis_jobs($F)`, so it honours office scope), defaulting
  to all-time / all-permitted-scope.
- For each job computes the **canonical** `job_profit()['profit']/['cost']` and the **partial**
  `credit − labour − expenses − subcon`, accumulating both plus the omitted components
  (overhead, voucher, other, contingency, recovered).
- Returns `jobs`, `drifting` (count where the two disagree), `canonical_profit`, `partial_profit`,
  `overstatement = partial − canonical`, the `omitted` breakdown, and a `consistent` verdict.

Surfaced as a **"Profit-figure consistency"** panel on `/profitability` (the canonical screen,
`data.profitability`-gated), shown only to salary-cleared viewers (it exposes overhead/contingency,
which are labour-derived). When consistent it says so; when not, it names the two screens, the omitted
cost lines, and the exact ₹ overstatement — and states plainly that reconciling them is a pending
decision, so the check never masquerades as having fixed them.

---

## 2. Edge cases handled

1. The overstatement identity is anchored to `job_profit`'s own fields: `partial − canonical` equals
   the omitted net cost (overhead + voucher + other + contingency − recovered) by construction.
2. The partial formula can only **overstate** (drops net cost), never understate.
3. An all-consistent population (e.g. jobs with no overhead/voucher/contingency) reports zero
   overstatement and `consistent = true` — no false alarm.
4. Office scope flows through `mis_jobs`, so a branch viewer's check covers only their branch.
5. Guarded: a missing MIS lib leaves `$reconcile = null` and the panel simply does not render.
6. Salary-gated — a `data.profitability`-only viewer (no `data.salary`) does not see the panel.

## 3. Guardrails (green)

`job_profit`, `boss_profit`, `job_money`, `office_expense_total`, the MIS partial formula, the SBU-P&L
table, `/profitability`, `/mis`, `/sbu-pl` — **all unchanged**. No displayed figure is altered; the
check is purely additive. No new permission; no schema change; nothing deleted.

## 4. Tests

`tests/test_module32_profitability.php` (20 assertions): the overstatement-equals-omitted-net identity
and the overstate-never-understate property; the reconciliation's shape and invariant
(`overstatement == partial − canonical`); consistent ⇒ zero overstatement; salary-gating and the
drift surface are wired; and — importantly — the existing MIS partial figure is asserted **unchanged**
(drift surfaced, not silently rewritten). Suite 3039 passing (only the 3 pre-existing baseline
failures remain).
