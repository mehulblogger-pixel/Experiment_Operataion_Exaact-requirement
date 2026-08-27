# Slice P8 — Cost Dual-Write Detector (R9)

**Change-control record (directive Part 25). Classification: REFACTOR / detector
(read-only, additive, non-destructive). Status: DELIVERED (detector). Convergence
staged.**

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

## Staged — convergence (needs a decision, deliberately NOT done)

Making the cost single-truth is the risky part and remains open:
- **Option A — one authoritative door:** treat the voucher as the system of record
  for the engineer's own reimbursables and stop counting closure `expenses` for
  the same category (or vice-versa); migrate readers after a reconciliation pass
  proves the totals.
- **Option B — net the overlap:** keep both doors but subtract a detected
  duplicate at read time in `job_profit()`.
Either changes displayed profit, so it must be chosen deliberately and validated
against the §30 frozen-cost snapshots — its own slice.
