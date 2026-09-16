# Phase 3 · M3 CORRECTION #9 — AUDIT (before any code was written)

## 1 · T1 — why the old fixture proved the wrong thing

`C8.6` forced a "core INSERT failure" by replacing `activities` with a **view of
itself**. Probed directly, engine by engine:

| | SQLite | MariaDB |
|---|---|---|
| `INSERT` into the view | **throws** — *cannot modify … because it is a view* | **SUCCEEDS** — a view over one table is updatable |
| `ALTER` on the view | throws | throws — *not of type 'BASE TABLE'* |
| `CREATE INDEX` on the view | throws | throws — *not of type 'BASE TABLE'* |

So on the **authoritative** engine the audit row never reached the INSERT at all:
the assertion passed because `act_migrate()`'s index creation threw first. The
claim was true; the mechanism named in it was not the one that ran.

**Requirement for the replacement (§1, §2, §9):** the failure must be *at the
INSERT*, with the table, the column and the index all demonstrably healthy, and the
failing operation must be **identified** rather than inferred from a row count.

**Mechanism chosen — a `BEFORE INSERT` trigger:**

| | |
|---|---|
| SQLite | `CREATE TRIGGER … BEGIN SELECT RAISE(ABORT, '<marker>'); END` |
| MariaDB | `CREATE TRIGGER … FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '<marker>'` |

A trigger touches nothing but the INSERT. The schema, column and index stay in
place and are asserted healthy first, so none of them can be the operation that
failed; and the marker travels into `act_last_error()`, so the test reads the
failing operation instead of guessing it.

## 2 · T2 — what was conflated

```php
function act_migrate_optional() {
    ensure_column('activities', 'cond_key', …);   // CORRECTNESS — can we store it?
    act_index('activities', 'idx_act_cond', …);   // PERFORMANCE — can we find it fast?
    // … one boolean for both
}
```

A failed `CREATE INDEX` therefore reported the whole feature unavailable, and
`act_set_cond_key()` refused to write into a column that existed and worked.

**A USABLE COLUMN IS NOT AN INDEXED COLUMN.**

Two states, two bounded retries, two error strings. The write path depends on the
**column alone**; `act_optional_state()` reports both side by side so neither has to
be inferred from the other.

## 3 · Three MariaDB fixtures tried and REJECTED

`C9.4` needs `CREATE INDEX` to fail while the column stays usable. MariaDB resists:

| Attempt | What MariaDB actually did |
|---|---|
| widen `cond_key` to `VARCHAR(2000)` | index **created** — the table is not utf8mb4, so the key fitted |
| widen to `VARCHAR(4000)` | index **created** — silently prefixed |
| change to `TEXT` | index **created** — auto-prefixed as well |
| **fill the table to its 64-key limit** | **"Too many keys specified"** — deterministic |

And the fill must **count the indexes the table already carries**. Filling blindly
overshoots, and then the *fixture* is what fails rather than the thing under test —
which is how the first attempt died.

## 4 · Two defects in the first draft of the split

Both were found by tests, not by reading:

1. **The index check spent the COLUMN's retry budget.** `act_cond_index_ready()`
   called `act_cond_column_ready()` to check its prerequisite, consuming an attempt
   each time. The budget was exhausted before a repaired schema could be noticed —
   `C8.5`'s retry case went red. The prerequisite is now asked with a **cheap
   metadata read**.
2. **There was no cheap existence probe at all**, so a structure that was already
   present still consumed a DDL attempt. Both readiness functions now short-circuit
   on a metadata read and spend budget only on a real attempt.

## 5 · What this correction does NOT change (§12)

S2, S3, H1 and H3 are untouched. The approval engine, scheduler, dashboard and the
Operations / Quality / Marketplace architectures are untouched. The correction #8
core-write guarantee is unchanged and remains covered by its own suite.
