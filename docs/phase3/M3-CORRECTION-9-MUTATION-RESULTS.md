# Phase 3 · M3 CORRECTION #9 — MUTATION RESULTS

Clean baselines on **both** engines, executed against the final source, no anchor
misses.

**9 attempted · 9 caught · 0 survivors.**

```
baseline [sqlite]: 0 (no failures)
baseline [mysql] : 0 (no failures)
```

| # | §10 requirement | Engine | Result | Failures |
|---|---|---|---|---:|
| T1-M1 | restore the old updatable-view fixture | **mysql** | **CAUGHT** | suite died |
| T1-M2 | a failed core INSERT reports apparent success | sqlite | **CAUGHT** | 4 |
| T1-M3 | suppress the core INSERT failure | sqlite | **CAUGHT** | 2 |
| T2-M1 | index failure stops `cond_key` storage | sqlite | **CAUGHT** | 3 |
| T2-M2 | a column failure appears as an index failure only | sqlite | **CAUGHT** | 2 |
| T2-M3 | column success made to depend on index success | sqlite | **CAUGHT** | 3 |
| T2-M4 | index failure falsely marks the column unavailable | sqlite | **CAUGHT** | 4 |
| T2-M5 | index migration failure latches as success | sqlite | **CAUGHT** | 3 |
| T2-M6 | the cheap existence probe removed | sqlite | **CAUGHT** | 2 |

## T1-M1 is the whole point, and it had to run on MariaDB

`T1-M1` restores the fixture that correction #8 used. On **SQLite** that fixture
still forces a core INSERT failure, so the mutation would be "caught" there for
entirely the wrong reason. It is therefore judged **only on MariaDB**, where the
view is updatable and the fixture proves nothing — and there the new test dies.

That is the direct evidence that T1 is fixed: **the new test fails when the core
INSERT is no longer forced to fail.** The battery was extended to run a mutation on
a chosen engine specifically for this.

`T2-M6` is mine rather than the brief's. Nothing in §10 attacks the cheap existence
probe, and without it a repaired schema can never be noticed once the retry budget
is spent — the mechanism that fixed one of this correction's own first-draft
defects would have been unprotected.

## The first run is reported, not hidden: two of these survived

| | First run | After |
|---|---|---|
| T2-M2 column failure filed as an index failure | **SURVIVED** | CAUGHT |
| T2-M6 cheap existence probe removed | **SURVIVED** | CAUGHT |

**T2-M2** survived because nothing asserted that a **column** failure arrives on
the **column's** error channel. Filing it under the index passed every test,
because the index's own *"the column is unavailable"* message overwrote it a moment
later — a misattributed error concealed by a later correct one.

**T2-M6** survived because nothing asserted that an **already-present** structure
is recognised **without spending a DDL attempt**. `C9.7` now spends the entire
bounded budget while the table is unreachable, then restores the table with the
column still on it, and requires immediate recognition.

## Scope of the run

SQLite mutations run `p3m`, `recruit_approval`, `m4_`, `activity`. The MariaDB
mutation runs `p3m3c9` and `p3m3c8` against a dedicated database. A suite that dies
without printing `RESULT:` counts as a detection. Mutations are applied to a copy of
`phpapp/` in the scratchpad; the repository is never mutated.
