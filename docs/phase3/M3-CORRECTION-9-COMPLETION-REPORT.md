# Phase 3 · M3 CORRECTION #9 — COMPLETION REPORT

**Scope: T1 and T2, plus U1 — a defect this correction introduced and fixed inside
itself.** S2, S3, H1 and H3 untouched. No M4 work.

---

## 1 · T1 — the evidence proved the wrong thing on the authoritative engine

`C8.6` forced a "core INSERT failure" by replacing `activities` with a view.

| | SQLite | MariaDB |
|---|---|---|
| `INSERT` into the view | **throws** | **SUCCEEDS** — a view over one table is updatable |

So on MariaDB the assertion passed because `act_migrate()`'s index creation threw
first: the row never reached the INSERT. The claim was true; the mechanism named in
it never ran.

**Replacement — a `BEFORE INSERT` trigger** (`RAISE(ABORT)` on SQLite,
`SIGNAL SQLSTATE '45000'` on MariaDB) carrying a marker. The table, column and
index are asserted healthy first, so none of them can be the cause, and the marker
is read back out of `act_last_error()` — the failing operation is **named**, with
the row count as corroboration only.

## 2 · T2 — a performance structure was a correctness precondition

`act_migrate_optional()` ensured the **column** (correctness) and the **index**
(performance) and returned one boolean, so a failed `CREATE INDEX` stopped
`cond_key` being written into a column that existed and worked.

**A USABLE COLUMN IS NOT AN INDEXED COLUMN.** Two states, two bounded retries, two
error strings; the write path depends on the column alone.

## 3 · U1 — found by this correction's own adversarial audit, and fixed in it

`act_optional_state()` exists so a health check can **ask** whether metadata is
usable — and each call spent one of the three DDL attempts reserved for
**repairing** it. Four polls during an outage left the feature unrepairable.

The rule, written into the code rather than left implicit:

> **ONLY AN ATTEMPT TO REPAIR MAY CONSUME THE REPAIR BUDGET.**

Fixed inside this correction because it was introduced by it, sits in its scope
(§12), and the evidence was already in hand.

## 4 · Three MariaDB fixtures tried and rejected

Widening `cond_key` to `VARCHAR(2000)`, to `VARCHAR(4000)`, and changing it to
`TEXT` — MariaDB **silently prefix-indexes** in every case. The fixture is the hard
**64-key limit**, filled to exactly the limit by counting the indexes the table
already carries; filling blindly overshoots and then the *fixture* is what fails.

## 5 · Two defects in the first draft of the split

Both found by tests, not by reading: the index check **spent the column's retry
budget**, and there was **no cheap existence probe**, so an already-present
structure still consumed a DDL attempt.

## 6 · Evidence

| | |
|---|---|
| **New suite `p3m3c9_optional`** | **61 / 0 on SQLite · 61 / 0 on MariaDB** |
| **Full regression · SQLite** | **10 148 passed, 0 failed** |
| **Full regression · MariaDB 10.11.14** | **10 149 passed, 0 failed** |
| **Mutations** | **10 attempted, 10 caught, 0 survivors**, clean baselines on both engines |

`T1-M1` is the load-bearing one: restoring the old view fixture is judged **only on
MariaDB**, where it proves nothing — and there the new test dies. That is the
direct evidence T1 is fixed.

Two mutations survived the first run and are reported in full: `T2-M2` (a column
failure filed on the index's channel, concealed by the index's own later message)
and `T2-M6` (an already-present structure recognised without spending a DDL
attempt). Both are now caught.

## 7 · §13 acceptance criteria

All met: T1 corrected with a genuine core-INSERT failure on **both** engines and
the mechanism explicitly identified; zero rows never used as sole proof; column
availability independent of index availability; index failure does not disable
storage; both retries work and remain bounded; core audit operational without
`cond_key`; S1 green; both engines green; mutations honestly reported.

## 8 · What this correction did NOT do

**S2, S3, H1, H3 remain open and untouched.** **U2** — without the index,
`appr_condition_seen()` runs an unindexed lookup, a full scan of the application's
largest table once per permanent-condition write — is **recorded as a cost, not
fixed**. The trade-off is deliberate: a slow correct answer beats a fast wrong one.

**V1 was found by the second adversarial pass of this correction and is fixed by
correction #10**, not here.

---

**PHASE 3 — M3 CORRECTION #9 COMPLETE**

M3 itself remains **NOT ACCEPTED**.
