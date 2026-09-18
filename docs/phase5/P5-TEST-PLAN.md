# Phase 5 — Test Plan

*What is proved, how, and what would count as a failure.*

## The principle

Every probe calls the **production function a screen calls**, and then reads the
**database**. Nothing a function says about itself is believed. Where a claim is
about a *route* rather than an engine, the route is driven in its own process —
because routes call `redirect()`, which exits, and because a route is exactly
where a control gets forgotten.

## Sections

| § | What it proves | How a failure would look |
|---|---|---|
| **A** | The screen and the records agree | The fixture from the audit — ten approved, four given up, three joined, two promised — asked of M3, Phase 4 and the KPI engine. All three must give the same answer. A failure is the seven-vs-three gap returning |
| **B** | Reconciliation (§23) | The set-based aggregate is compared against the sum of `reqf_counts()` **row by row**, over a set deliberately containing an **over-filled** requirement and a **partly cancelled** one. A failure means the aggregate has an opinion of its own |
| **C** | Canonical ageing (K5) | Calendar and business days over the same span must differ; a bad date, a missing date and an unknown basis must each return NO DATA; the same day must return 0, not null |
| **D** | The target date (K3, K11) | A requirement raised through a hiring request inherits the needed-by date unchanged and says where it came from. One raised on the direct path has **no target**, and its days-late is **null** |
| **E** | The stage ledger is complete (K12) | An offer is created, submitted, approved and issued through the production path; the ledger must gain an entry with a stage code. A joining is reverted by the execution gate; the ledger must **end at OFFERED**, marked REVERT, with both the move and its undoing kept |
| **F** | Credit survives reassignment (K4) | Ann delivers a hire; the record is then handed to Bob. Ann must keep it and Bob must not gain it |
| **G** | Stage durations (K6) | A revert and a workflow switch must each close **no** step; the in-progress step is reported separately; asking for one ladder returns only that ladder |
| **H** | Entitlement and scope (K7, K8) | Every recruitment metric declares a method and a lineage that maps to *People & hiring*; a period with no joinings gives NO DATA; a user scoped to another branch sees less, and no source promise leaks across |
| **J** | The real route (K1, K12) | The `candidate-stage` route is driven in its own process; the requirement must be recomputed to PARTIALLY_FILLED, the KPI engine must see the joining, and the ledger must carry the coded entry |
| **K** | The clamps that defend against legacy data | Figures that the normal paths cannot produce but that imported or corrupted data can — more people joined than the requirement has seats, more vacancies cancelled than were ever requested |

## Regression

The **whole** suite, on **both** engines, to completion:

- SQLite 3.45.1
- MariaDB 10.11.14 — **authoritative for production-oriented evidence**. No
  MySQL claim is inferred from a SQLite run

## Mutation testing

Thirty-six mutations, each breaking exactly one control in a **copy** of the
application, plus three that break a control **and its partner** to prove the
pairing is real rather than assumed. The battery aborts if the baseline is not
clean, because a battery run against a dirty baseline measures nothing.

A surviving mutation is not excused. It is classified as either

1. a real gap in the probes — which is fixed by adding a probe, or
2. an independent protection — which must be **proved**, by naming the assertion
   that detects it, not asserted.

## What is NOT claimed

- No concurrency battery. Phase 5 adds no compare-and-swap, no compensator and
  no contended write: its writes are ledger entries appended beside a change that
  has already committed. There is nothing here for two processes to race over,
  and inventing a race to have one would be theatre.
- No performance benchmark beyond the design constraint that a dashboard is one
  query and a recruiter table is one scan, not one per person.
