# Phase 3 · M3 CORRECTION #11 — COMPLETION REPORT

**Scope: W1 and W2.** H1, H3, S2, S3 and U2 untouched. No M4 work.

---

## 1 · W1 — an error belongs to the workspace that produced it

The audit found **four** error channels in the spine, not the one W1 named — the
core INSERT failure, the column migration, the index migration and the metadata
write — all plain process globals, while this application switches workspace **in
process**. A failure recorded against company A was returned after the switch to
company B: a false diagnosis in B, and A's database error text handed to B.

All four now go through **one workspace-keyed channel**, on `db_epoch()` — the
boundary every migration guard, settings cache and the attempt ledger already uses.

**Keyed, not cleared, and deliberately single-slot.** Entering a workspace wipes
nothing; it simply cannot see what belongs to another. One entry *per* workspace
was considered and rejected as the *"global historical error store"* §4 forbids, so
a workspace whose entry has been superseded reads **empty** — never stale, never
somebody else's.

## 2 · W2 — the writer answers the same question as the observer

`act_set_cond_key()` returned one boolean for *"nothing has tried to create the
column"* and *"the write was attempted and failed"* — V1's sentence, **ABSENT IS
NOT FAILED**, on the writer side.

Four outcomes, keeping the migration's result distinct from the write's (§8):

| | |
|---|---|
| `STORED` | the UPDATE ran and succeeded |
| `FAILED` | the UPDATE ran and failed |
| `UNAVAILABLE` | the column was attempted and cannot be made |
| `NOT_ATTEMPTED` | nothing was attempted at all |

A **status constant, not a boolean** — a truthy `'FAILED'` would be a trap for any
future `if (act_set_cond_key(...))`. The single production caller and all four
existing assertions compare explicitly against `ACT_COND_STORED`. The 74 ordinary
`act_log()` callers are untouched (§6).

## 3 · Evidence

| | |
|---|---|
| **New suite `p3m3c11_workspace`** | **36 / 0 on SQLite · 36 / 0 on MariaDB** |
| **Full regression · SQLite** | **10 232 passed, 0 failed** |
| **Full regression · MariaDB 10.11.14** | **10 233 passed, 0 failed** |
| **Mutations** | **9 attempted, 9 caught, 0 survivors** |

## 4 · §14 acceptance criteria

All met: W1 reproduced and fixed with **genuine in-process** switching; A's error
cannot appear in B; workspace-local semantics documented; W2 reproduced;
`NOT_ATTEMPTED`, `FAILED` and `STORED` all distinguishable; the core audit row
survives optional metadata failure (`W2-M4`); V1, U1, T1, T2 and S1 remain green;
both engines green; the suite restores the epoch it moved; tree clean.

## 5 · Two things I got wrong inside this correction, both corrected

**An assertion demanding a design I had not built.** `C11.4 CASE D` first required
that returning to A still shows A's error — per-workspace retention, which §4
discourages. Rather than build a historical store to satisfy my own test, the test
now asserts the safety property and documents the retention choice.

**A malformed mutation reported as a survivor.** `W2-M5` appended a comment instead
of removing the `UPDATE`, so it changed nothing and "survived". That is a broken
experiment, not a finding. Rewritten to genuinely skip the write, it is caught with
20 failures.

## 6 · What this correction did NOT do

**H1, H3, S2 and S3 remain open and untouched**, and **U2** remains a documented
cost. None is affected by this change: correction #11 is about *whose* error is
reported and *what* the writer's answer means, not about approvals, the dashboard,
or the unindexed lookup.

---

**PHASE 3 — M3 CORRECTION #11 COMPLETE — HARD STOP — READY FOR ADVERSARIAL AUDIT**

M3 itself remains **NOT ACCEPTED** until **H1, H3, S2 and S3** are separately
resolved and the final M3 acceptance suite passes.
