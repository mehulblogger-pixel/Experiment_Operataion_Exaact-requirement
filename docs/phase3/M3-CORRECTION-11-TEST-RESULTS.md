# Phase 3 · M3 CORRECTION #11 — TEST RESULTS

## New suite — `tests/test_p3m3c11_workspace.php`

**36 assertions, 0 failed — on SQLite AND on MariaDB.**

Workspace switching is **genuine and in-process** (§3): the suite moves
`db_epoch()`, the same boundary every migration guard, settings cache and the
attempt ledger already uses, and the same one `db(true)` moves during "Log in as",
provisioning and cron tenant sweeps. No child processes are used for the primary
test.

| Section | §§ | What it holds |
|---|---|---|
| **C11.1 · CASE A** | 2 | A `BEFORE INSERT` trigger makes A's core write genuinely fail, and **A sees A's own error** |
| **C11.2 · CASE B** | 2, 5, 10 | **The defect.** B is asked **before it has ever failed at anything** — it sees nothing, and no trace of A reaches it: not A's marker, `SQLSTATE`, `activities`, `RAISE`, `45000` or `23000`. The column and index channels are checked too |
| **C11.3 · CASE C** | 2 | B then fails on its own account: B sees **B's** error, and **A's is not returned** |
| **C11.4 · CASE D** | 2, 4 | `A → B → C → A`. C, a third workspace, sees nothing from either; back in A, **B's error is never returned**; B, the most recent writer, still has B's, and A's never became B's |
| **C11.5 · W2** | 6, 7, 8, 9 | The writer's four outcomes, each by the path actually taken |

### §5 — the leak test uses a marker that could only come from A

Each workspace's forced failure carries a string unique to it
(`C11 WORKSPACE A ONLY SECRET`). A leak is therefore unmistakable rather than
inferred, and the assertion also rejects the generic fragments a leak would carry
(`SQLSTATE`, the table name, the raise mechanism, the error codes).

### §7 — the writer's four outcomes

| Case | Setup | Result |
|---|---|---|
| **B** | column present, UPDATE succeeds | `STORED` — **and the value is read back out of the column**, so STORED is not merely claimed |
| **C** | column present, a `BEFORE UPDATE` trigger makes the write fail | `FAILED`, with the real reason on the **write's own** channel, and the column still `READY` (§8) |
| **A** | nothing has attempted the column, and it can be made | **not `FAILED`** — the writer creates it and reports `STORED`, so `FAILED` would have been a lie |
| **D** | column absent **and** the migration genuinely cannot run | `UNAVAILABLE` — never `STORED`, and never `FAILED`, because the write never ran |

### §9 — the core row is primary

After the metadata write fails, the core audit row is asserted **still present**.
Mutation `W2-M4` attacks that line directly and is caught.

## §10 — false-green defence

* **W1:** B is asked **before** it is ever made to fail. A test that made B fail
  first and then checked B's error would pass whether or not the leak existed.
* **W2:** an absent column is never used as proof of a failed write. Each of the
  four outcomes is produced by a distinct, constructed path, and `CASE A` proves
  its own counterfactual by going on to **succeed**.

## One assertion of mine that was wrong, and was corrected

`C11.4 CASE D` first demanded that returning to A still shows A's error — **per
workspace retention**, a property I had not built and that §4 discourages, since
one entry per workspace is the *"global historical error store"* it forbids.

Rather than build a historical store to satisfy my own test, the test now asserts
the **safety** property that matters — A never sees B's, B never sees A's — and
documents the retention choice: the channel is single-slot, so a workspace whose
entry has been superseded reads **empty**, never stale and never somebody else's.

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | **10 232 passed, 0 failed** |
| **Whole suite · MariaDB 10.11.14** | **10 233 passed, 0 failed** |
| `p3m3c11_workspace` | **36 / 0** on both engines |
| all M3 suites (`p3m`) | **1379 / 0** |

Particular attention per §12: "Log in as", provisioning, cron tenant switching,
multi-workspace tests and the migration guards all pass — they are the flows the
epoch boundary belongs to, and an earlier correction in this sequence broke exactly
those by mishandling it.

No test was skipped, disabled or weakened. Four assertions changed from a boolean
to the specific status they now check, which is strictly more precise.
