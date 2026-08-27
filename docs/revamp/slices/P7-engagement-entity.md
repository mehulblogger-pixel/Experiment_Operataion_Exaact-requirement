# Slice P7 — Engagement Entity (additive groundwork)

**Change-control record (directive Part 25). Classification: BUILD (additive,
non-destructive, dual-read). Status: DELIVERED (groundwork).**

The item RT3 parked "until after P4" (`00-program.md` D3). P4 is delivered, so the
first-class Engagement entity is now introduced — **without** abandoning the
`contract_number` string thread.

---

## 1. Existing state

The whole spine is threaded by the **`contract_number` string** (quotations,
calls, jobs, invoices; reports hang off jobs). `engagement($contractNumber)`
(`lib/engagement.php`, §25) is a read-only grouping over it. §80 deliberately
**deferred** a first-class entity ("use the string today").

## 2. Problem

A string thread can't be a stable, FK-able handle, can't carry engagement-level
attributes, and can't be renamed without touching every row. The directive's
operational spine wants a real Engagement object — but replacing the string is a
large, risky migration.

## 3. Solution (delivered — additive, dual-read)

`lib/engagement.php` (extends the existing read-view, does not replace it):
- **`engagements` table** — one row per `contract_number` (`engagement_key`
  unique), with `partner_id`, `title`, `opened_at`. **No status column**, so no
  new lifecycle is introduced (nothing needing sign-off).
- **`engagement_id`** — a nullable additive column on `calls`, `jobs`,
  `quotations`, `invoices` (via `ensure_column`; a bootstrap probe upgrades live
  DBs). Never replaces `contract_number`.
- **`engagement_ensure()` / `engagement_id_for()`** — idempotent get-or-create /
  lookup, keyed by the contract number (unique-index race handled).
- **`engagement_backfill()`** — creates an engagement per distinct
  `contract_number` and stamps `engagement_id` onto the records that carry that
  number but have none yet. Idempotent and self-guarded; runs nightly from cron.
- **`engagement_by_id()`** — dual-read: resolves a stable id back to the same
  spine `engagement()` groups by string, so callers can hold the id and still get
  the identical read-view.

The string still links everything (`engagement()` is unchanged); the id is a
parallel, additive handle. **`contract_number` is never dropped.**

## 4–8. Impact

- **DB:** one additive table + one nullable column on four tables + a bootstrap
  probe; `engagement_migrate()` in `boot()` (preceded by `books_migrate()` so the
  invoices table exists before it is stamped). No column dropped or repurposed.
- **Status / permission:** **none** — no status column, no new permission;
  `docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` unchanged.
- **Behaviour:** nothing reads `engagement_id` for control yet — it is groundwork.
  Existing string-based reads are untouched, so no behaviour changes.
- **cron:** `engagement_backfill()` added as a nightly backstop.

## 9. Regression & validation

- `php -l` clean; cron boots and runs to exit 0.
- New `tests/test_engagement_entity.php` — **16/16** (columns exist; ensure
  idempotent; backfill stamps calls/jobs/invoices and is idempotent on re-run;
  dual-read id↔string returns the same spine; the string is preserved).
- **Full suite: 3902 passed, 0 failed.**

## 10. Rollback

The `engagements` table and `engagement_id` columns are inert additive objects
(nothing reads them for control). Drop them to fully revert; no existing data or
behaviour is affected.

## Next steps (future slices, not required now)

1. **Stamp on write:** set `engagement_id` at the point a record is assigned a
   `contract_number` (call/job/quote/invoice creation), so new rows are linked
   without waiting for the nightly backfill.
2. **Dual-read in features:** let `engagement()` accept an id directly and let
   360/rollup surfaces prefer the id, still falling back to the string.
3. **Engagement-level attributes / a `/engagement` view** once there is a reason
   to hold data the string can't.

Only when the id path is proven everywhere would the string become a pure
back-compat column — never dropped.
