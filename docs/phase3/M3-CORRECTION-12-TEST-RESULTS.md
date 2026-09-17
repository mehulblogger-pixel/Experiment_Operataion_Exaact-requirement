# M3 CORRECTION #12 — TEST RESULTS

Suite: `phpapp/tests/test_p3m3c12_persist.php` — **54 assertions**.

| run | engine | result |
|---|---|---|
| focused X1/X2/X3 | SQLite 3.45.1 | **54 passed, 0 failed** |
| focused X1/X2/X3 | MariaDB 10.11.14 | **54 passed, 0 failed** |
| M3 suites (`p3m`) | SQLite | **1469 passed, 0 failed** |
| M3 suites (`p3m`) | MariaDB | **1469 passed, 0 failed** |
| **full regression** | SQLite | **10286 passed, 0 failed** |
| **full regression** | MariaDB | **10287 passed, 0 failed** |

No test was weakened, deleted or skipped. No skip was introduced. The MariaDB
count is one higher because one engine-specific assertion only exists there.

---

## C12.1 — X3: the workspace switch is real

`$enterWs()` repoints the connection and calls `db(true)`; `$whoAmI()` then asks
the database itself who it is. Asserted, not assumed:

- workspace A, B and C resolve to **three different database identities**
  (`SELECT DATABASE()` on MariaDB, `config.php`'s `sqlite_path` on SQLite);
- a row written in A is **not visible** in B, and vice versa — proving the
  switch moved the data, not just a label;
- six consecutive switches produce **six distinct epochs**, and none of them is
  a value this test wrote by hand.

## C12.2 / C12.3 — X1: `STORED` means actually persisted

- a normal write reports `STORED` **and the row really carries the key**;
- the **same key written again** still reports `STORED` — the idempotent
  re-write that `rowCount()` would have called `FAILED` on MariaDB;
- an **id that does not exist** reports `FAILED`, not `STORED`, with a reason
  naming the cause;
- an **id belonging to another workspace** reports `FAILED`, and the row in the
  owning workspace is confirmed **untouched**;
- a write that genuinely fails (forced by a `BEFORE UPDATE` trigger —
  `RAISE(ABORT)` on SQLite, `SIGNAL SQLSTATE '45000'` on MariaDB) reports
  `FAILED` and the column is *not* blamed.

## C12.4 — X2: four channels, each proved on its own

For every channel — `core`, `col`, `idx`, `write` — the same five steps:

1. A produces a **genuine** error in that channel, captured before any repair;
2. the error is asserted **still standing** at the moment of the switch;
3. B, genuinely active, reads **empty**;
4. B records its own; re-entering A reads **empty** — neither B's nor A's stale
   message;
5. only then is **each** workspace repaired, and both asserted whole again.

### Two false-green fixtures found while writing this section

Both are the family this project keeps meeting — a test that passes for the
wrong reason — and both were found by mutation, not by reading.

**1 · The fixture cleared the channel it had just filled.** Repair sat at step 1,
before the switch. A successful repair *clears* the error it just produced, so A
crossed the switch holding nothing and step 3 read empty no matter what the
scoping did. The `col` and `idx` mutations survived the entire M3 suite because
of it. Repair moved to step 5, and step 2 was added to assert the channel is
non-empty at the moment of the switch.

**2 · Only workspace A was ever repaired.** With repair at step 5, the `column`
iteration left **B** without its `cond_key` column. The `index` iteration that
followed then had nothing to index, produced no error in B at all, and the
fixture silently stopped testing. Caught by a real failure
(`C12.4 index · B records its own error in the same channel`), not excused. Both
workspaces are now repaired after step 4 — after the isolation claims, never
before.

The engine-specific blockers are genuine: on SQLite a table named
`idx_act_cond` blocks the index; on MariaDB long columns silently prefix-index,
so the **64-key limit** is filled to exactly 64 by counting the existing indexes
first.
