# PHASE 3 · M5 — DATABASE CHANGES

## One new table. No column added, none altered, none dropped.

```sql
CREATE TABLE recruiter_assignments (
    id            INTEGER PRIMARY KEY AUTOINCREMENT | INT AUTO_INCREMENT PRIMARY KEY,
    subject       VARCHAR(20),   -- REQ_RECRUITER | REQ_MANAGER | CAND_RECRUITER
    entity_id     INT NULL,      -- the requisition or candidate
    from_user_id  INT NULL,      -- who held it (NULL = nobody)
    to_user_id    INT NULL,      -- who holds it now (NULL = unassigned)
    actor         VARCHAR(150),  -- display name — never an identity
    actor_id      INT NULL,      -- the identity
    source        VARCHAR(30),   -- which production path
    reason        VARCHAR(255),
    created_at    VARCHAR(30)
);
CREATE INDEX idx_rasg_entity ON recruiter_assignments (subject, entity_id, id);
CREATE INDEX idx_rasg_to     ON recruiter_assignments (to_user_id);
```

**Append-only.** Nothing updates or deletes a row. It answers the question the
columns cannot: a column holds one value, and an overwrite destroys the previous
one — which is precisely how "who was accountable last quarter?" became
unanswerable (invariants **I4** and **I12**).

Two indexes, for the two questions actually asked: one record's history, and one
person's.

## Existing columns — unchanged, now controlled

| Column | Table | Change |
|---|---|---|
| `recruiter_id` | `requisitions` | **none to the column.** It left the blind field list; only `rasg_assign()` writes it |
| `manager_id` | `requisitions` | same |
| `recruiter_id` | `candidates` | same |

## Migration

`rasg_migrate()` is wired into `boot()`'s migrate chain (`lib/db.php`), beside
`appr_migrate()`, and is guarded by the existing boot-migration test — a
table-creating migration that is not wired in fails the suite.

It is idempotent (`CREATE TABLE IF NOT EXISTS`, index creation inside
`try`/`catch`) and epoch-guarded (`static $doneAt === db_epoch()`), so a
workspace switch re-runs it and a repeat call does not.

## Existing data

No backfill is performed, and that is deliberate. Ownership written before M5 was
never validated, so some of it points at people who no longer exist or are
deactivated. Inventing ledger history for those rows would fabricate a record of
decisions nobody made. Instead `rasg_phantoms()` **reports** them — with the
reason — and they keep their value until a human decides what to do, because the
value is the only evidence of who was recorded (probe **L3**).
