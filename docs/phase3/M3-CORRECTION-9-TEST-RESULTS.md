# Phase 3 · M3 CORRECTION #9 — TEST RESULTS

## New suite — `tests/test_p3m3c9_optional.php`

**61 assertions, 0 failed — on SQLite AND on MariaDB.**

| Section | §§ | What it holds |
|---|---|---|
| **C9.1 · T1** | 1, 2, 3, 9 | A `BEFORE INSERT` trigger makes the **core INSERT** fail. The table, column and index are asserted healthy first, so none of them can be the cause; the trigger's marker is read back out of `act_last_error()`, so the failing operation is **named**; `act_log()` returns 0; the row count is corroboration, never the proof |
| **C9.2 · T1** | 1 | Records *why* the old fixture was replaced, engine by engine: a view is not insertable on SQLite and **is** insertable on MariaDB |
| **C9.3 · T2 CASE A** | 5 | column YES · index YES → metadata stored |
| **C9.4 · T2 CASE E** | 5, 6 | **The primary acceptance test.** Index creation genuinely blocked, column present: the column still reports available, the index reports unavailable **on its own account**, the core row is written, **the metadata IS stored**, and correction #7's suppression still works |
| **C9.5 · retry** | 7 | The index migration succeeds on retry once the obstacle is gone, with the column unaffected throughout |
| **C9.6 · T2 CASE C** | 5, 8 | Column absent: the core row is still written, the metadata is correctly reported **not** stored, and an index is never claimed available without its column |
| **C9.7** | — | A **column** failure arrives on the **column's** channel and names it; and a structure that is already present is recognised without spending a DDL attempt |
| **C9.8 · U1** | — | `act_optional_state()` is polled **six times — twice the whole budget** — during an outage, and the repair still succeeds afterwards |

## §8 — the behaviour matrix, as exercised

| Column | Index | Core audit | `cond_key` storage | Where |
|---|---|---|---|---|
| YES | YES | works | works | C9.3 |
| YES | **NO** | works | **works** | **C9.4** |
| NO | (impossible) | works | unavailable, and the index is never claimed | C9.6 |
| Core INSERT fails | any | **observable failure, operation named** | irrelevant | C9.1 |

## §9 — false-green defence

The T1 mechanism is stated per engine — `RAISE(ABORT)` on SQLite,
`SIGNAL SQLSTATE '45000'` on MariaDB — and the assertion is that the recorded error
**contains the trigger's marker**. *"Core INSERT failed"* is therefore read from the
error, not inferred from zero rows.

### Three MariaDB fixtures tried and rejected

Widening `cond_key` to `VARCHAR(2000)`, to `VARCHAR(4000)`, and changing it to
`TEXT` — MariaDB silently creates a **prefix index** in every case, so none of them
blocks anything. The fixture is the hard **64-key limit**, filled to exactly the
limit by counting the indexes the table already carries: filling blindly overshoots
and then *the fixture* is what fails.

### One consequence of the U1 fix, faced rather than papered over

An observer that never attempts cannot produce a fresh error. `C9.4` had been
reading an error that its own observation caused. It now makes the attempt through
the path the application really uses and observes what that attempt left behind —
**an error only a health check can conjure is not evidence.**

## Regression — both engines, identical source, run serially

| | |
|---|---|
| **Whole suite · SQLite** | see completion report |
| **Whole suite · MariaDB 10.11.14** | see completion report |
| `p3m3c9_optional` | **61 / 0** on both engines |
| all M3 suites (`p3m`) | **1325 / 0** |

No test was skipped, disabled or weakened.
