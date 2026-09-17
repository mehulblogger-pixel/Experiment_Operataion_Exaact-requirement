# PHASE 3 · M5 — MUTATION RESULTS

Each mutation is applied to a **copy** of `phpapp/` in a scratch directory, the
deploy checksum is regenerated, and the M5 and recruitment suites are run on
**MariaDB**. A suite that dies without printing a result counts as a detection.

**Baseline: 0 failures. Attempted 22 · Caught 22 · Survived 0.**

| # | Mutation | Result | The assertion that caught it |
|---|---|---|---|
| M1 | a deactivated or unknown person may be given the work | **CAUGHT** | B3 *(want `RECRUITER_INACTIVE`, got `OK`)*, B4 |
| M2 | the recruiter's own branch scope is not checked | **CAUGHT** | B1 *(want `RECRUITER_OUT_OF_SCOPE`, got `OK`)* |
| M3 | the **actor's** scope over the record is not checked | **CAUGHT** | B2 *(want `OUT_OF_SCOPE`, got `RECRUITER_OUT_OF_SCOPE`)* |
| M4 | ownership may be moved in any state | **CAUGHT** | D · HIRED *(want `BAD_STATE`, got `OK`)* |
| M5 | the M4 execution boundary is not asked | **CAUGHT** | E4 *(want `M4_BLOCKED`, got `OK`)* |
| M6 | the permission check is removed | **CAUGHT** | B6 *(want `NO_PERMISSION`, got `OK`)* |
| M7 | entitlement is asked **after** permission instead of first | **CAUGHT** | C1 / C2 |
| M8 | a stale screen silently overwrites the newer owner | **CAUGHT** | G1 *(want `STALE`, got `OK`)* |
| M9 | the compare-and-swap becomes an unconditional write | **CAUGHT** | C4 *(two browser saves, two winners)* |
| M10 | a value somebody else wrote counts as this process's win | **CAUGHT** | M2 (the deterministic pin) |
| M11 | the ownership ledger is not written | **CAUGHT** | F1 *(want 3 rows, got 0)* |
| M12 | the recruitment dashboard drops its branch scope again | **CAUGHT** | R3.3 |
| M13 | partly-filled requirements fall out of live demand again | **CAUGHT** | R1.2 *(want 2, got 1)* |
| M14 | the candidate save writes ownership blindly again | **CAUGHT** (see below) | J5d / J5e |
| M15 | the public careers intake inherits without asking | **CAUGHT** | J4, J7 *(sweep names `careers.php`)* |
| M16 | the compensating check after a save is removed | **CAUGHT** | N3 / N4 |
| M17 | the compensator trusts the caller's map instead of the subject list | **CAUGHT** | N7 / N10 |

## M14 survived the first battery. What that meant, and what changed.

The mutation put the ownership column straight back into the candidate save's
blind field list — `$fields[] = 'recruiter_id';` — and emptied the mapping that
routes it through the door. **Nothing failed.**

That is not an excusable survivor. It was a real gap, and it was in the tests:

- the probes asserted on the field list's **literal text**, and the mutation
  appended to the list **at runtime**;
- the repository-wide sweep looks for SQL that names an ownership column, and the
  operations-layer write is built dynamically from `$fields`, so there is no such
  text to find;
- and no test can drive the route itself — `ops_candidates()` ends in
  `redirect()`, which calls `exit`.

A cleverer probe alone would have been the wrong answer, because the next path
written by somebody who has never read this document would slip past it in a new
way. So **the save paths stopped trusting themselves**:

- `rasg_authorised_now()` snapshots what the door left, driven by the **service's
  own subject list** rather than by anything the caller keeps;
- after the rest of the save is written, `rasg_enforce_table()` puts any other
  value back, tells the person, and writes the attempt to the audit spine —
  **without** recording it in the ownership ledger, because it was never an
  assignment;
- and creation enforces "unowned" before the door assigns, so an INSERT that
  carried an owner is stripped back.

This is the same shape as M4's headcount compensator, and it closes the **class**:
a save path invented next year that quietly carries an ownership column cannot
change ownership, whether or not anybody remembers the rule. Mutations **M16** and
**M17** were then added to attack the new protection itself; both are caught.

M14 is now caught by J5d and J5e, and the compensator means the mutated code would
not have changed ownership even if the probes had missed it again.

## The five added by the adversarial audit

| # | Mutation | Result | Caught by |
|---|---|---|---|
| M18 | a person id is **cast** instead of validated (an array becomes user #1) | **CAUGHT** | P1 / P1c |
| M19 | the scope question is asked about the **origin**, not the destination | **CAUGHT** | P4 |
| M20 | authorization is answered **after** the stale-screen test again | **CAUGHT** | C6–C8 |
| M21 | moving a candidate across branches ignores who holds it | **CAUGHT** | P5 |
| M22 | the workload counter keeps its **own** candidate scope rule | **CAUGHT** | P6 |

## An invalid run, reported rather than discarded

The first re-run after the audit's fixes reported **21 survivors**. It measured
nothing: the database server had gone down, so the **baseline itself was FATAL**
and every mutant "survived" against a broken run. One anchor was also stale,
because a fix had changed the line it matched.

The harness now **aborts when the baseline is not clean** — a battery that cannot
prove its own starting point proves nothing about its mutants — the anchor was
corrected, and the numbers above are from the re-run.

## Nothing inherited is claimed as newly caught

All twenty-two are M5 mutations against M5 code paths. The M4 battery is not
re-counted here.
