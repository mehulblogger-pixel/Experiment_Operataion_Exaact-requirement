# M3 FINAL STABILISATION — TEST RESULTS

Suite: `phpapp/tests/test_p3m3fs_stabilise.php` — **78 assertions**, plus
`tests/_fs_worker.php`, a **real separate process** used for the concurrency proofs.

| run | engine | result |
|---|---|---|
| final stabilisation suite | SQLite 3.45.1 | **78 passed, 0 failed** |
| final stabilisation suite | MariaDB 10.11.14 | **78 passed, 0 failed** |
| **complete regression** | SQLite | **10534 passed, 0 failed** |
| **complete regression** | MariaDB | **10535 passed, 0 failed** |

No test was weakened, deleted or skipped; no skip was introduced.

## Engine differences

**None in any final-stabilisation behaviour.** Two implementation details differ
and are handled rather than hidden: the upsert syntax (`ON CONFLICT` vs
`ON DUPLICATE KEY`, already branched in the product) and the failure fixtures
(`RAISE(ABORT)` vs `SIGNAL SQLSTATE '45000'`). The query plans differ by engine
and are reported separately below rather than averaged into one claim.

---

## §C · concurrency — REAL processes, REAL connections

`tests/_fs_worker.php` is a separate `php` process that attaches to the same
database and writes one condition. It deliberately does **not** use
`tests/bootstrap.php`, which drops every table on the MySQL path.

| | proof |
|---|---|
| **FS.1** two processes, two conditions | both workers report success; **A survives and B survives**; exactly two rows |
| **FS.2** reverse order | both survive — order makes no difference |
| **FS.2** eight processes at once | **all eight survive**, eight rows |
| **FS.3** four processes, same condition | one row — the upsert is atomic, not duplicated |
| **FS.4** §H cache | this process holds a loaded cache, a worker writes, and **this process sees the worker's condition**; writing a third **does not erase** the worker's |

The final persisted state is asserted, not the shape of the code.

## §D · no cap
`FS.5` stores the oldest condition first, then fills to **199, 200, 201, 500 and
1000**. At every size the count matches exactly, the **oldest unresolved condition
is still present**, and it still knows its row, so it is still recoverable. All
1000 are reported as active faults; none is silently dropped.

## §E · corruption isolation
`FS.6` writes two good records and one unparsable one. All three remain readable
*as records*; the bad one is reported **`CORRUPT`**, not absent; the other two are
untouched; **the active-fault count does not become zero**; reconciliation reports
it (`corrupt: 1`) rather than ignoring it; a diagnostic is written; and **the
dashboard renders** rather than crashing, leaking no internals.

## §F · no MAX(activities.id)
`FS.7` leaves a condition unarmed, then deletes the newest activity rows so
`MAX(id)` is pushed back down — the exact motion that used to strand a condition
for ever. **Recovery still works**, the marker is genuinely persisted, and the
warning clears. `FS.4`'s record is separately asserted to carry **no `maxid`**.

## §G/§K · lifecycle and idempotency
`FS.8`: the condition never recurs, reconciliation recovers it anyway, and **45
further passes** add no activity row, no duplicate marker and no duplicate
warning. The condition ends with **one authoritative state**. When it occurs again
it is **suppressed** — no duplicate permanent record.

## §L · partial failure
`FS.9` runs A (recoverable), B (malformed), C (blocked), D (recoverable):
**A and D recover**, B is reported corrupt, C stays truthfully unarmed, and
**B did not stop A or D**. There is no global "everything succeeded" result —
only counts.

## §N · migration
`FS.10`: three entries move out of the old document; the **active UNARMED one
survives**; the document is deleted only after everything was written; **repeating
the migration is a no-op**; and an **unreadable** old document migrates nothing and
is **left in place** rather than silently discarded.

## §I · tenant isolation
`FS.11`: real `db(true)` switching, the database naming itself, **DB A ≠ DB B**.
With A holding two active faults (one of them corrupt), B cannot **count**, **see**
or **read** them, B's settings hold none of them, **reconciling in B changes
nothing of A's**, and B's attempt to delete A's condition — plus a real B worker
process writing its own — leave A with **both of its own** and B's condition never
reaches A.

---

## §J · performance, measured on 20 000 activity rows and 500 stored conditions

| query | SQLite plan | SQLite | MariaDB plan | MariaDB |
|---|---|---|---|---|
| #14 dashboard (scan of the spine) | `SCAN activities` + temp B-tree | 1.477 ms | `type=ALL key=NULL rows=20000` | 5.796 ms |
| FINAL dashboard (condition rows) | `SCAN settings USING COVERING INDEX` | 0.039 ms | `type=range key=PRIMARY rows=500 Using index` | 0.214 ms |
| FINAL single-condition read | `SEARCH settings USING INDEX (skey=?)` | 0.005 ms | `key=PRIMARY`, const | 0.068 ms |

Stated honestly: MariaDB examines **500 rows — the conditions themselves — not
20 000**, and it is an index range on the PRIMARY KEY, not a table scan. In a
healthy workspace there are **zero** condition rows, so the dashboard reads
nothing. `appr_cond_unarmed_count()` costs 0.576 ms (SQLite) / 1.060 ms (MariaDB)
**with 500 open faults**; that cost is the JSON decode and it scales with the
number of unresolved faults, not with the size of the activity spine. No index
was added.

---

## Defects in my own test work, found and reported

1. **`M-F1` survived the first battery, and the mutation was at fault.** It
   restored a read-modify-write that re-read *fresh* from the database — which is
   not what #15 did. The defect was the **stale snapshot**, not the rewrite. The
   corrected mutation caches the snapshot as #15's cache did, and is now caught by
   `FS.2 · eight processes … all eight survive` (want 8, got 5) and
   `FS.4 · writing a third does NOT erase the other process's` (want 3, got 2).
2. **The suite's fixture omitted a privileged session**, so `appr_rule_save()`
   returned 0 and eight assertions read `NO_SUBJECT` — a fixture failure that
   looked exactly like a product one.
3. **A fresh workspace has no `settings` table**; the tenant-isolation section had
   to build B the way the application builds a tenant.
4. **The migration fixture used SQLite-only `ON CONFLICT` syntax on both engines**
   and died on MariaDB.
