# Slice P4 — Billable Event Ledger

**Change-control record (directive Part 25). Classification: BUILD (the one
genuine new table; additive, non-destructive). Status: DELIVERED (P4a + P4b
inline job-close hook). Remaining P4b sources (report/timesheet/candidate) staged.**

Priority 4 / the headline differentiator in `03-target-architecture.md` §4 & §8;
directive Part 16 (the Billable Event Engine). Lifecycle recorded in
`docs/03-object-lifecycles.md`.

---

## 0. Revisit-trigger check (RT1)

This *is* the slice RT1 escalates to. Delivered on the roadmap's own order.

## 1. Existing state

EXAACT knows when work is done (job close, report issue) and has a real books
ledger (`invoices`/`invoice_lines`/`receipts`/`credit_notes`). `finevent.php`
projects the money stream but its earliest event is `INVOICE_ISSUED`. "Billable"
was a **value on the call/job** (`calls.billable_value/rate/qty/basis`), derived
on the fly by `books_billable_jobs()` at invoice time — with **no place to hold**
"approved to bill but not yet invoiced", disputes, or non-job sources.

## 2. Problem

The directive's core promise — *operational work must never disappear before
reaching billing* — needs a **persisted bridge** between an approved operational
occurrence and its commercial charge. That did not exist.

## 3. Solution (delivered)

New engine `lib/billable.php` + one additive table `billable_events`:
- **Idempotent record** per occurrence, keyed `(source_module, source_kind,
  source_id)` with a unique index; `billable_event_upsert()` refreshes derived
  fields only while PENDING (never overwrites a human decision).
- **Lifecycle** PENDING → APPROVED → BILLED (+ CANCELLED / DISPUTED), enforced by
  `billable_can_transition()`. **BILLED is reconciliation-only** — never a manual
  click.
- **`billable_events_sync()`** — (1) derives PENDING events from closed,
  not-yet-invoiced billable jobs (`books_billable_jobs()`); (2) reconciles any
  event whose source job is now invoiced (`books_invoices_for_job()`) to BILLED,
  taking the amount from the invoice so **the books ledger wins on money**.
- **Board** `/billable-events` (the Commercial cockpit) under Money → tile with a
  pending-count badge: rollup chips (unbilled / approved / billed / disputed),
  status filter, and approve / dispute / re-approve actions + a "Sync from closed
  work" button. Read gate `billable_can()` (finance.reconcile / data.credit /
  master); manage gate `billable_can_manage()` (finance.reconcile / master).

## 4–8. Impact

- **DB:** one additive table `billable_events` + a unique index; a bootstrap probe
  in `index.php`; `billable_migrate()` added to `boot()`. No existing column
  touched.
- **Routes:** `/billable-events`, `/billable-sync`, `/billable-approve`,
  `/billable-cancel`, `/billable-dispute` → `ops_billable()`, mapped to the
  existing `invoicing` module gate.
- **Permission:** **none added** (D1 — reuse `finance.reconcile`);
  `docs/02-permission-matrix.md` unchanged. New **status lifecycle** recorded in
  `docs/03-object-lifecycles.md` (same commit).
- **Dependencies:** reads `books_billable_jobs()` / `books_invoices_for_job()`;
  the job-close and invoicing write paths are **not modified** in P4a.

## 9. Regression & validation

- `php -l` clean on all changed files.
- New `tests/test_billable_events.php` = **25/25** (transition matrix, derive,
  idempotency, approve + stamp, no-manual-BILLED, reconcile-to-BILLED with invoice
  link + amount-from-invoice, terminal states, rollup, list).
- One brittle pre-existing test (`test_simplify_costs.php`) sliced the money area
  by a fixed 2600-char window; a new tile pushed `/call-profit` out of it. Fixed
  the test to slice to the real case boundary (more robust, same intent) — not a
  weakening; all four cost-route assertions still hold.
- **Full suite: 3807 passed, 0 failed.**

## 10. Rollback

Turn the `invoicing` module gate off to hide the board; the `billable_events`
table is inert additive data (drop it to fully revert). No existing data touched.

## Delivery record & staged P4b

**Shipped (P4a):** the ledger, lifecycle, sync (derive + reconcile), and the
Commercial-cockpit board — populated from the `JOB_CLOSED` source.

## Delivery record (P4b — inline job-close hook)

**Shipped (P4b):**
- **`billable_on_job_closed($jobId)`** (`lib/billable.php`) — an idempotent,
  fully self-guarded hook (a `try/catch` that swallows every error) so queuing a
  billable candidate can **never** affect whether a job closes. Reuses
  `billable_event_upsert()`; skips a job already invoiced.
- Wired into the **job-close success path** (`lib/ops.php`, right after the cost
  snapshot) so the candidate appears the moment work is closed — no wait for a
  sync run.
- **Cron call** to `billable_events_sync()` (`cron.php`) as a backstop derive +
  hands-off reconciliation to BILLED.
- Report-issue is **deliberately NOT** a separate money-bearing source: the
  job/call is the billable unit, so a per-report event would double-count.

**Validation:** new `tests/test_billable_hook.php` = **10/10** (no-op on bad id,
never throws, queues one PENDING candidate with the right value/client,
idempotent re-fire, never overwrites an approved decision, skips an
already-invoiced job). php -l clean; **full suite 3817 passed, 0 failed.**

> **Pre-existing cron bug — now FIXED (follow-up commit).** `cron.php`'s hardcoded
> require list had drifted to 27 of index.php's 135 libs, so `boot()` and every
> nightly task failed on this instance (`Undefined constant ASSET_TYPES`, then
> `PRODUCT_MODULES`). This predated the revamp and killed *all* cron tasks, not
> just the billable sync. **Fix:** `cron.php` now derives its library list from
> `index.php` at load time (the same mechanism the test bootstrap uses), so it can
> never drift again. Verified end-to-end: cron boots and runs every task (exit 0),
> including retention/audit/competence tasks that were also silently dead, and the
> billable `sync` now runs nightly. (The billable feature never depended on cron —
> the inline hook creates events in real time — but the sync backstop now works.)

**Delivered (P4b — Customer-360 unbilled figure):** `billable_party_rollup($pid)`
+ a Customer-360 "Unbilled work" qcard (₹ pending+approved, linking to the
board filtered by client via a new `?party=` filter). Read-only, additive.
Test: `tests/test_billable_party.php` (6 assertions). Full suite 3870/0.

**Remaining P4b — needs a design decision (blocked, flagged for sign-off):**
Adding non-job sources (`TIMESHEET_APPROVED`, `CANDIDATE_JOINED`/placement fee)
is **not** a safe drop-in: reconciliation to BILLED currently matches per-**job**
invoices (`books_invoices_for_job`), and invoices carry no
inspector/timesheet-period/placement linkage — so those events would have **no
path to BILLED** and would sit forever in PENDING/APPROVED. Closing this cleanly
needs one of:
1. **Generalize invoicing to consume billable events** — let finance pick the
   billable event(s) a line bills and stamp `invoice_line_id` at
   `books_line_add()`; then reconciliation works for every source. (Touches the
   money write-path — its own validated slice.)
2. **A manual "billed, invoice #…" action** for non-job events — which changes the
   "BILLED is reconciliation-only" rule and so needs your sign-off.

Until one is chosen, the timesheet/placement sources are held so the ledger never
strands events. The `JOB_CLOSED` source (real-time hook + sync) is complete.

**RT3 note:** the first-class `Engagement` entity was parked "until after P4"
(`00-program.md` D3). P4 is now delivered — Engagement can be revisited if the
`contract_number` string proves limiting.
