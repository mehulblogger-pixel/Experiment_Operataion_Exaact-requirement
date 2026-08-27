# Slice P4 — Billable Event Ledger

**Change-control record (directive Part 25). Classification: BUILD (the one
genuine new table; additive, non-destructive). Status: DELIVERED (P4a). P4b
(inline hooks + more sources) staged.**

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

**Staged (P4b):**
1. **Inline hooks** so events are created the moment work is approved (job close,
   report/IRN issue, timesheet/OT approval, candidate joined) instead of only by
   the sync pass — reusing `billable_event_upsert()`. (Touches those write paths,
   so done as its own validated slice.)
2. **More sources** (`REPORT_ISSUED`, `TIMESHEET_APPROVED`, `CANDIDATE_JOINED`…).
3. **Generalize `books_billable_jobs()` → `books_billable_events()`** so the
   existing invoice flow lists all billable candidates, and stamp `invoice_line_id`
   at `books_line_add()` for line-level reconciliation.
4. A cron call to `billable_events_sync()` for hands-off freshness.

**RT3 note:** the first-class `Engagement` entity was parked "until after P4"
(`00-program.md` D3). P4 is now delivered — Engagement can be revisited if the
`contract_number` string proves limiting.
