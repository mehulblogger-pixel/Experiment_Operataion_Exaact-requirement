# Phase 3 · M3 CORRECTION #10 — MUTATION RESULTS

Clean baseline, executed against the final source, no anchor misses.

**9 attempted · 9 caught · 0 survivors.**

| # | §11 requirement | Result | Failures |
|---|---|---|---:|
| V1-M1 | treat an absent column as FAILED | **CAUGHT** | 6 |
| V1-M2 | make observation execute migration | **CAUGHT** | 19 |
| V1-M3 | make observation consume retry budget | **CAUGHT** | 20 |
| V1-M4 | make observation increment the attempt count | **CAUGHT** | 24 |
| V1-M5 | index error claims column failure with no column attempt | **CAUGHT** | 3 |
| V1-M6 | make FAILED appear as NOT_ATTEMPTED | **CAUGHT** | 4 |
| V1-M7 | make column READY depend on index READY | **CAUGHT** | 6 |
| **V1-M8** | *(mine)* a NOT_ATTEMPTED state described as "unavailable" | **CAUGHT** | 4 |
| **V1-M9** | *(mine)* the attempt ledger shared across workspaces | **CAUGHT** | suite died |

## The two that are mine, and why

**V1-M8** plants the literal V1 string — *"cond_key index: the column is
unavailable"* — into the message layer. §11 attacks the *states*; nothing in it
attacks the *words*. Since half of V1 was a message asserting a failure that had
not happened, the wording needed a guard of its own.

**V1-M9** shares the attempt ledger across workspaces, so one company's failed
migration makes another company's read `FAILED`. Making the ledger observable is
what allows `FAILED` to be distinguished from `NOT_ATTEMPTED` at all — and an
observable ledger that is not per-tenant would turn a diagnostic improvement into
cross-tenant bleed. Nothing in §11 covers it.

## Scope of the run

Suites per mutation: `p3m`, `recruit_approval`, `m4_`, `activity`. A suite that
dies without printing `RESULT:` counts as a detection. Mutations are applied to a
copy of `phpapp/` in the scratchpad; the repository is never mutated.
