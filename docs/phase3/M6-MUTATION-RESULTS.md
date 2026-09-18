# PHASE 3 · M6 — MUTATION RESULTS

Each mutation is applied to a **copy** of `phpapp/` in a scratch directory, the
deploy checksum is regenerated, and the `p3m6`, `p3m5` and `p3m4` suites run on
**MariaDB**.

**Baseline: 0 failures on both engines before the battery started.**
The harness **aborts** if the baseline is not clean — a battery that cannot prove
its own starting point proves nothing about its mutants.

**Attempted 36 · Caught 36 · Survived 0.**

| # | Mutation (mandatory target) | Result | Caught by |
|---|---|---|---|
| M1 | **entitlement guard** — `can()` stops asking the licence | **CAUGHT** | S6 |
| M2 | **tenant guard** — a requisition read leaks across workspaces | **CAUGHT** | S7.4 |
| M3 | **branch guard** — the actor's scope is not checked | **CAUGHT** | S2, M5 B2 |
| M4 | **permission guard** — the assignment band is removed | **CAUGHT** | S3.4, S4.1, M5 B6 |
| M5 | **approval guard** — an unapproved request becomes executable | **CAUGHT** | L9, C5.1 |
| M6 | **reapproval guard** — a pending re-approval no longer blocks | **CAUGHT** | 42 assertions across p3m6/p3m5/p3m4 |
| M7 | **execution boundary** — the gate stops asking M4 | **CAUGHT** | L3, L4 (16 assertions) |
| M8 | **quantity ceiling** — allocation may exceed the approved figure | **CAUGHT** | M4 suite |
| M9 | **recruiter assignment control** — the door lets anything through | **CAUGHT** | 36 M5 assertions |
| M10 | **target-requisition validation** — scope asked about the origin again | **CAUGHT** | M5 P4, S-matrix |
| M11 | **candidate / requirement state validation** — a dead requirement executes | **CAUGHT** | L5 |
| M12 | **offer state validation** — the offer path stops asking | **CAUGHT** | L3.5, L4.3 |
| M13 | **joining quantity validation** — the seat check is removed | **CAUGHT** | L2.3 |
| M14 | **stale-write protection** — an old screen overwrites again | **CAUGHT** | S5, M5 G |
| M15 | **concurrency** — the assignment swap becomes unconditional | **CAUGHT** | C4, M5 C |
| M16 | **dashboard scope** — the command centre loses its branch filter | **CAUGHT** | S2.7, M5 R3.3 |
| M17 | **export scope** — the export stops sharing the scoped builder | **CAUGHT** | M5 P6 |
| M18 | **audit integrity** — a reverted joining is not recorded | **CAUGHT** | L2.8 |
| M19 | **input-type validation** — a person id is cast again | **CAUGHT** | S1, M5 P1 |
| M20 | **dynamic write protection** — the ownership compensator is removed | **CAUGHT** | M5 N |
| M21 | **cross-module state** — the pipeline engine stops asking the gate | **CAUGHT** | L3.7 |
| M22 | **partial-failure protection** — the joining compensator is removed | **CAUGHT** | L2.6, C1 |
| M23 | **interview path** stops asking the gate | **CAUGHT** | L3.3 |

## M36 — the mutation that attacks the ratified policy (I21c)

| # | Mutation | Result | Caught by |
|---|---|---|---|
| M36 | a refused dead heat **consumes** the capacity it refused — the revert cancels a vacancy on its way out | **CAUGHT** | L2.10, L7.4 |

I21c says a refused dead heat must leave the capacity **usable by a subsequent
valid transaction**, never consumed by the refusal. That is easy to state and easy
to break by accident: a compensator that "tidies up" by cancelling the vacancy it
just refused would satisfy every other invariant and quietly destroy capacity the
business had approved. M36 does exactly that, and it is caught.

## The eight added by the third adversarial pass

| # | Mutation | Result | Caught by |
|---|---|---|---|
| M24 | the gate **casts** its requirement id instead of validating it | **CAUGHT** | S1 |
| M25 | a malformed *"except this candidate"* value releases a seat again | **CAUGHT** | S1 (seat-holder probe) |
| M26 | a negative id falls through to the ADR-001 **allow** path | **CAUGHT** | S1 |
| M27 | the interview route announces success again | **CAUGHT** | L10 |
| M28 | the offer route announces success again | **CAUGHT** | L10 |
| M29 | the gate casts the candidate id **before** the validated helper | **CAUGHT** | S1 |
| M30 | the decision goes back to check-then-write (two approvers both win) | **CAUGHT** | L11, C5 |
| M31 | the decision stops checking whether it matched a row | **CAUGHT** | L11 |

**M25 survived its first run**, and the reason was my probe, not the product: it
compared an array against 0, and an array casts to candidate #1 — who held no seat
on that requirement, so both answers matched and the probe passed with the
validation removed. The malformed values are now built to coerce **onto a real
seat-holder of that requirement**, so the probe fails deterministically whenever
the validation is missing. Re-run: caught.

## An invalid baseline, reported rather than worked around

One battery run aborted with a dirty baseline (3 failures in `p3m4`). It was not
reproducible — `p3m4` passed twelve consecutive runs alone, in reverse order, and
on both engines — and a stray worker process from an earlier concurrency run was
still on the machine. Classified **ENVIRONMENT FAILURE**, not a product defect and
not a survivor: the harness refused to measure anything against it, which is what
§3 requires. The battery was re-run on a quiet machine from a clean baseline, and
those numbers are the ones above.

## Two mutations survived the first battery. Neither was excusable, and neither was the product's fault.

**M2 — the tenant leak.** The mutation added a cross-workspace cache to the gate.
It survived because my tenant probe never asked the gate about that requirement
**while still in workspace A**, so the cache had nothing in it to leak. A leak
test that never fills the leak is not a test. The probe now asks the gate in its
own workspace first (`S0`) and then across the boundary (`S7.4`) — and the
mutation is caught.

**M5 — the approval guard.** The mutation deleted the approval-status line from
`hreq_is_executable()`. The M1 and M4 suites assert that a draft request is not
executable, but the battery ran M6's suites — and **M6's own suite never proved
the invariant M6 claims (I1)**. A milestone must prove the invariants it lists.
`L9` now walks `DRAFT`, `SUBMITTED`, `REJECTED` and `CANCELLED`, asserts none is
executable and that no requisition can be raised from any of them, and closes with
an **approved control** so the four checks cannot pass vacuously. The mutation is
caught.

Both were re-run individually against the strengthened suites, from a clean
baseline, and both are now **CAUGHT**. Nothing inherited from the M4 or M5
batteries is counted here.
