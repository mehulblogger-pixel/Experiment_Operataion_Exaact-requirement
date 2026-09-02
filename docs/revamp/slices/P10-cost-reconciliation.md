# Slice P10 — Cost Reconciliation Worklist

**Change-control record (directive Part 25). Classification: REFACTOR / detector
(read-only, additive, non-destructive). Status: DELIVERED (detector). Convergence
staged.**

The cost-side twin of **P9 (revenue reconciliation)**. P9 gave finance a worklist
for where a job's *legacy invoice* figure disagrees with the *books ledger*. This
gives the same for the cost side: where a job's *legacy sub-contractor cost*
disagrees with the *committed cost ledger*.

> Not to be confused with **P8 / R9 (cost dual-write detector)**, which is a
> different axis — reimbursables double-counted between the closure `expenses`
> door and the inspector `voucher` door, surfaced per-job on the job-detail Money
> fold. P10 reconciles a job's `subcon_cost` against the month-end cost run's
> committed ledger. The two detectors are complementary and independent.

## 1. Existing state / problem

A job's sub-contractor cost is written in **two places meant to agree**:

1. the **legacy figure** typed on the job — `jobs.subcon_cost` — which the per-job
   P&L (`ops.php`) and the order roll-up read directly; and
2. the **ledger figure** — the `SUBCON` row a committed monthly cost run
   (`costing_run`, `lib/costing.php`) writes into `cost_allocations` for that job.

`costing_run` **derives** the ledger row from `subcon_cost`, so at the moment a run
commits they are equal. They drift afterwards — `subcon_cost` is edited once the
run is closed (legacy moves, ledger stale), a job carries a `subcon_cost` but no
run was ever committed for its month (legacy-only), or a ledger `SUBCON` row
survives after the job's figure was cleared (ledger-only). **Nothing surfaced the
drift** — the revenue side had a reconciliation worklist (P9); the cost side did
not.

## 2. Solution (delivered — read-only detector)

`lib/costrecon.php` (new), mirroring `lib/revrecon.php`:
- **`costrecon_job($jobId)`** → `{legacy, ledger, diverges, legacy_only, ledger_only}`.
- **`costrecon_scan($limit)` / `costrecon_count()`** → the diverging worklist / count.
- **`costrecon_candidate_ids()` / `costrecon_summary()`** → candidates + a health
  summary whose `green` is true only when nothing diverges.
- **`costrecon_list($limit)`** → diverging jobs enriched with code / client / a
  plain-language reason.
- **`ops_costrecon()`** → the read-only worklist screen
  (`views/ops/cost_reconciliation.php`), gated the same as revenue reconciliation.

Surfaced at:
- **Money → Costs & margins → "Cost reconciliation"** rail tile (with the live
  count), route `/cost-reconciliation`.
- the **system-status attention band** (`ops.php`) beside the P9 revenue-recon
  signal, advisory only.
- CLI: **`tools/cost-reconciliation.php`** (`--list`) — a read-only report that
  exits non-zero while any job diverges.

Only the sub-contractor cost is reconciled — it is the one job-cost figure written
on both sides. Salary, idle time and office overheads are computed *into* the
ledger and were never typed on the job, so there is no second copy to disagree
with; reconciling them would invent drift, not find it.

It **changes no figure** and switches no reader.

## 3–8. Impact

- **DB / status / permission:** none. Pure read over `jobs` + `cost_allocations`.
  The screen reuses the existing finance gates (`can_see_salary` /
  `finance.reconcile` / master); the route is handler-gated. No permission-matrix
  or lifecycle change.
- **Behaviour:** additive screen + tile + attention signal + CLI; `costing_run`,
  `job_profit()` and every cost reader are unchanged.

## 9. Regression & validation

- `php -l` clean on all changed files.
- New `tests/test_costrecon_worklist.php` — **13/13** (a matching job reconciles;
  a disagree, a legacy-only and a ledger-only job diverge; summary counts +
  `green=false`; the worklist lists every diverging job, excludes the reconciled
  one, and carries client + reason; the detector leaves both the job figure and
  the ledger untouched — proven read-only).
- **Full suite: 5381 passed, 0 failed** (was 5368).
- **Auto-walk: 203 screens across every role render cleanly.**

## 10. Rollback

Remove the route + view + `lib/costrecon.php` + the Money tile + the attention
signal + the CLI. No data touched.

## Staged — the reader switch (needs a decision, deliberately NOT done)

Once `costrecon_summary()['green']` holds across the real dataset, the cost readers
could be pointed at the ledger as the single truth, one at a time, each validated
against the §30 frozen-cost snapshots. That *changes displayed cost/profit*, so it
is a deliberate step per reader — not part of this diagnostic slice. The worklist
is the gate that makes it safe.
