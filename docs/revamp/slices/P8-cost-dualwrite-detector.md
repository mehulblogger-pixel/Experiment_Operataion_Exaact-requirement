# Slice P8 — Cost Dual-Write Detector (R9)

**Change-control record (directive Part 25). Classification: REFACTOR / detector +
worklist + estimate toggle (additive; the toggle is off by default and reversible).
Status: DELIVERED (detector + Option C worklist + Option B netting toggle). The
authoritative-door policy (Option A) deliberately deferred.**

`02-gap-and-reuse-map.md` §3 / gaps register R9. Detector-first, as agreed — the
safe step before any risky data-model convergence.

## 1. Existing state / problem

Reimbursables (travel / lodging / food) have **two data-entry doors** for one job:
the coordinator's closure `expenses` and the inspector's monthly `voucher`.
`job_profit()` (`ops.php`) **sums both** — correct when they record *different*
costs, but if the **same trip** is keyed on both sides it is **double-counted**.
Nothing surfaced the overlap.

## 2. Solution (delivered — read-only detector)

`lib/costing.php`:
- **`cost_sources($jobId)`** → `{expenses, voucher, total, both_sided, overlap}`,
  where `overlap = min(expenses, voucher)` (the likely double-counted amount).
- **`cost_dualwrite_flag($jobId)`** → true when reimbursables sit on both doors.
- **`cost_dualwrite_scan($limit)`** → the reconciliation worklist (closed jobs with
  both-sided reimbursables, largest overlap first).
- **`cost_dualwrite_render($jobId)`** → a warning on the job-detail **Money fold**
  (managers/coordinators only) naming both figures and the amount at risk.

It **changes no figure** — it only surfaces jobs a human should reconcile.

## 3–8. Impact

- **DB / status / permission:** none. Pure read over existing `expenses` /
  `voucher_entries`. The Money fold is already managers/coordinators-only.
- **Behaviour:** additive warning only; `job_profit()` and every cost reader are
  unchanged.

## 9. Regression & validation

- `php -l` clean.
- New `tests/test_cost_dualwrite.php` — **11/11** (reads both sides; flags a
  both-sided job; ignores one-sided/none; overlap = min; scan includes the
  both-sided job and excludes one-sided; warning renders only when both-sided).
- **Full suite: 3913 passed, 0 failed.**

## 10. Rollback

Remove the one view line + the `costing.php` functions. No data touched.

## Convergence — DECIDED "C + B" and DELIVERED (owner's call)

The owner chose **C as the standing rule, with B available as an off-by-default
estimate** (the decision brief laid out A/B/C). Both are now built:

- **Option C — reconcile per job (the rule):** a finance worklist screen,
  `ops_reimbursable_dedup()` at `/reimbursable-dedup` (route + Money → Costs &
  margins tile + system-status signal), lists every both-sided closed job largest
  overlap first, each linking to the job so a coordinator removes the duplicate at
  source. `cost_dualwrite_count()` / `cost_dualwrite_summary()` drive the tile and
  the KPIs. This is the accurate path — no figure is ever silently wrong.
- **Option B — net the overlap (off-by-default estimate):** `reimbursable_dedupe_mode()`
  / `_set_mode()` (setting `reimbursable_dedupe`, default `off`), and
  `reimbursable_dedupe_amount($expenses,$voucher)`. When set to `net`, `job_profit()`
  subtracts `min(expenses,voucher)` for a both-sided job (`$dedupe`, exposed in the
  return; no extra query — it uses the figures already computed). Mirrors the P9/P10
  reader switches: it changes displayed profit only when a human turns it on, and it
  is reversible. It is **not** the default because the overlap is an estimate and
  over-removes when the two doors hold genuinely different trips — the worklist banner
  and the toggle both say so.

**Option A — one authoritative door** remains deferred: its category dimension and
migration are a large separate slice that only pays off once C shows how many jobs
truly need it.

Validated by `tests/test_reimbursable_dedupe.php` (15/15): default `off` nets
nothing; `net` nets `min(expenses,voucher)` and one-sided jobs net nothing;
`job_profit`'s `dedupe` is 0 under off and the overlap under net, and the cost drop
equals the overlap plus its contingency; the worklist lists the both-sided job and
excludes the one-sided one; an invalid mode is rejected; and the toggle **never
mutates the source expense or voucher rows**. Full suite 5478 passed, 0 failed;
auto-walk 207 screens render cleanly under the default.
