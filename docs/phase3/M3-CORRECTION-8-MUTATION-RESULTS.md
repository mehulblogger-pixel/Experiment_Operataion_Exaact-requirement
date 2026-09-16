# Phase 3 · M3 CORRECTION #8 — MUTATION RESULTS

Clean baseline, executed against the final source, no anchor misses.

**10 attempted · 10 caught · 0 survivors.**

| # | §12 requirement | Result | Failures |
|---|---|---|---:|
| S1-M1 | make `cond_key` mandatory for the core INSERT | **CAUGHT** | 33 |
| S1-M2 | restore silent exception swallowing around the core INSERT | **CAUGHT** | 1 |
| S1-M3 | latch the migration guard before successful migration | **CAUGHT** | 2 |
| S1-M4 | prevent migration retry after failure | **CAUGHT** | suite died |
| S1-M4b | latch a *failed* optional migration as **success** | **CAUGHT** | suite died |
| S1-M5 | make CRM audit fail when `cond_key` is absent | **CAUGHT** | 11 |
| S1-M6 | make quotation audit fail when `cond_key` is absent | **CAUGHT** | 3 |
| S1-M7 | make Recruitment audit fail when `cond_key` is absent | **CAUGHT** | 1 |
| S1-M8 | make Operations audit fail when `cond_key` is absent | **CAUGHT** | 3 |
| S1-M9 | the optional write claims a success it did not achieve | **CAUGHT** | 1 |

S1-M4b and S1-M9 are additions of mine. §12 asks for "prevent retry"; the opposite
error — a **failed** migration recorded as **successful** — is the one that would
hide the problem for ever, and nothing in the list covered it. S1-M9 covers the
same shape one layer out: the optional write returning `true` when it stored
nothing.

---

## The first run is reported, not hidden: three of these survived

| | First run | After |
|---|---|---|
| S1-M2 core failure silent again | **SURVIVED** | CAUGHT |
| S1-M3 guard latches before success | **SURVIVED** | CAUGHT |
| S1-M9 optional write claims false success | **SURVIVED** | CAUGHT |

All three survived for one reason: **the suite never forced a genuine CORE
failure.** It tested the column being absent, but never the audit write itself
failing — so "is the failure observable?" was never actually asked, and
`act_set_cond_key()` was never reached while the optional migration was genuinely
unavailable.

### How the real failure is produced — no mock, no injected flag

`C8.6` replaces the table with a **VIEW of itself**. A view cannot be inserted into
and cannot be indexed, so the core INSERT and the index creation both fail exactly
as a broken or half-restored schema makes them fail.

Two details of that test carry as much weight as the test itself:

* **`cond_key` is dropped first**, so after the repair it can only reappear if
  `act_migrate()` genuinely retried. Without that, the assertion would be checking
  a column that had never gone away.
* **The epoch is deliberately not bumped on the way out.** Bumping it resets the
  guard, which would let an early-latching implementation pass. That is the same
  trap that had to be removed from `C8.2` earlier in this correction — there,
  bumping the epoch let the optional migration quietly re-add the column, modelling
  a host that *can* run DDL, which is the opposite of the condition under test.

## Scope of the run

Suites per mutation: `p3m`, `recruit_approval`, `m4_`, `activity`. A suite that
dies without printing `RESULT:` counts as a detection. Mutations are applied to a
copy of `phpapp/` in the scratchpad; the repository is never mutated.
