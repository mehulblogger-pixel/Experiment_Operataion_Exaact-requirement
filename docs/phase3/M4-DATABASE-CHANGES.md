# PHASE 3 · M4 — DATABASE CHANGES

All changes are **forward-only, additive, idempotent and non-destructive**, applied
through the existing `ensure_column()` inside `hreq_migrate()`. No table is created,
no column dropped, no historical approval evidence rewritten, no activity deleted.

## Columns added to `hiring_requests`

| Column | SQLite | MariaDB | Purpose |
|---|---|---|---|
| `reapproval_state` | `VARCHAR(20)` default `'NONE'` | `varchar(20)` default `'NONE'` | the additive re-approval attribute — **no lifecycle status was added** |
| `approved_snapshot_json` | `TEXT` | `text` | the immutable record of **what the approver approved**, captured at the decision |
| `approved_snapshot_at` | `VARCHAR(30)` default `''` | `varchar(30)` default `''` | when that snapshot was taken |
| `reapproval_started_at` | `VARCHAR(30)` default `''` | `varchar(30)` default `''` | when a re-approval was opened |

All four are nullable, so an existing row is valid the moment the column appears.
`hreq_reapproval_state()` maps `NULL` or anything unrecognised to `NONE`, which is
how every pre-existing approved request keeps working unchanged (§26).

## Idempotency, measured

The migration was run **three further times** on each engine: **38 columns before,
38 after**, no error on either. Repeat-safe.

## Existing structures reused, not duplicated

`requisitions.hiring_request_id` (already additive, `NULL` for a direct
requisition), the approval tables (`recruit_approval_rules/levels/requests/steps`),
the activity spine, the `settings` store, and the offices/scope model. **No new
table was created by M4.**

## Indexes

None added. The M4 reads are by primary key (`hiring_requests.id`,
`requisitions.id`) or by `requisitions.hiring_request_id` on a table already
filtered to one request.
