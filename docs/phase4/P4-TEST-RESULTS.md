# Phase 4 — Test Results

Every probe calls the **production** function a route or a crafted POST would
reach. Fixtures are built through the real M4 approval path, never by raw SQL, so
no probe can pass against a requirement the product would refuse. The route
battery drives the **actual routes** in their own processes and then reads the
**database** — nothing a route *says* is believed.

---

## The batteries

| File | What it proves | Assertions |
|---|---|---|
| `test_p4_allocation.php` | The invariants: the ceiling, the per-source ceiling, rebalancing, closing, cancelled vacancies, the ledger, clean refusal, stale expectations, corrupt rows | 140 |
| `test_p4_security.php` | The negative matrix: permission, branch scope, entitlement (incl. no master bypass), malformed input, source entities, TOCTOU, duplicate-requirement attacks, the execution boundary, refusal ordering | 81 |
| `test_p4_reconcile.php` | 10/20/100 seats × 1/2/3/5 sources; rebalance, release, cancel; every figure re-derived from the rows; a whole-workspace sweep | 454 |
| `test_p4_concurrency.php` | Real separate OS processes: contested allocation, contested credit, established-holder protection, colliding resizes, resize-vs-release, the whole arrival path raced, one person to two sources, six-way contention over four rounds, six-way credit contention over four rounds | 136 |
| `test_p4_routes.php` | The real `candidate-new` / `candidate-edit` / `candidate-stage` routes, driven in their own processes | 35 |
| **Phase 4 total** | | **840** |

---

## Results

| Engine | Phase 4 | Full regression |
|---|---|---|
| **SQLite 3.45.1** | 840 passed, **0 failed** | 12008 passed, **0 failed** |
| **MariaDB 10.11.14** | 840 passed, **0 failed** | 12009 passed, **0 failed** |

MariaDB is authoritative for production-oriented evidence. Both engines were run;
neither figure is inferred from the other.

---

## The final mutation gate — probes added, and why

The gate that classified T6/T18/T19 added seven probes and corrected two. Each is
here because a mutation proved nothing was checking the claim.

| Probe | The claim nothing was checking |
|---|---|
| **C10** | Eight arrivals against one promised seat: the surviving LINKS, not the reports |
| **C11** | One person, eight sources at once: every source carrying a credit must hold them now or record a matching DETACHED |
| **C12** | Eight resizes from one baseline: the ledger must be a path that ends where the allocation stands |
| **C13** | Eight planners, one whole headcount, nothing pre-allocated: standing promises must equal successful processes |
| **C14** | A **staggered** race: a loser must be told somebody changed it first, never that the approval is full |
| **RT2.4–RT2.7** | The create route runs the same defence in depth the edit and stage routes run |
| **D14–D18** | A FULFILLED source can be grown, trimmed to what it delivered, never below |

Two existing probes were corrected because they asserted properties the system
does not promise:

- **C12.3/C12.4 and C14.5/C14.6** demanded that ledger entries appear in write
  order. The CAS and the ledger INSERT are separate statements, so two concurrent
  *successful* resizes can record entries in the opposite order — each entry
  truthful, only the ordering inverted. They failed against the **real**
  implementation about one run in six. They now reconstruct the ledger as a path.
- **C6.6b** now reports the state it observed (status, quantity, delivered,
  direct, attempted trim) rather than only what it wanted, which is what made the
  FULFILLED-blocks-trimming defect diagnosable instead of merely intermittent.

## Every failure this work produced, classified

Per §45, no failure was fixed before it was classified.

| Probe | Classification | What happened |
|---|---|---|
| D5 | **PRODUCT DEFECT** | A full-but-open source told the user its allocation was *closed*. The refusal was right; the reason a human reads was false. FULFILLED is a live state, not a closed one |
| F12 | **PRODUCT DEFECT** | Closing an allocation stopped counting the people it had delivered, so a requirement could report *one joined against zero allocated* — FULFILLED > ALLOCATED |
| F5, F6 | **TEST DEFECT** | Written against the defective behaviour above and asserting it. Corrected, and an explicit invariant assertion added at every stage of that section |
| F12c | **PRODUCT DEFECT** (found through an imprecise invariant) | The allocation ceiling ignored people already found directly, so ten seats could be promised to an agency when three were already filled. See the adversarial audit — restating the invariant alone would have hidden this |
| F12, F12a | **TEST DEFECT** | Expectations written before the committed model was correct |
| C4.4 | **PRODUCT DEFECT** | The resize pre-check mixed a stale quantity with a fresh total, so the loser of a race was refused with a false reason. Found only by real concurrent processes |
| C6.7 | **TEST DEFECT** | Asserted a property the system never promises (that nobody may hold a credit before joining). Exposed by MariaDB; SQLite passed it by accident of interleaving. Replaced with the claims that *are* invariants |
| S3.1 | **INVALID PROBE, disclosed** | Reported "NOT APPLICABLE" when the licence could not be switched off — which is calling *environment unavailable* a pass. Rewritten to switch the module off in the workspace's own **paid ceiling** and read the answer through the product's own choke point, plus a superuser probe for no-master-bypass |
| S5.2, S5.3 | **TEST DEFECT** | The supplier fixture used a column that does not exist (`is_active`; the table has `status`) and the failure was swallowed by a `try/catch`, so two probes vanished silently. The fixture now has no `try/catch`: if a supplier cannot be put on file, the battery fails loudly |
| S5.3 | **TEST DEFECT** | Inherited a requisition that section S4 had deliberately exhausted, so it tested S4's leftovers rather than its own claim. Given its own requirement |
| RT3.1–RT3.7 | **TEST DEFECT** | The route worker posted `to` where the stage route reads `to_stage`, so it drove nothing |
| N4–N6 | **TEST DEFECT** | Asserted an event sequence that omitted the legitimate derived `STATE` entry |

| C8.2–C8.4 | **PRODUCT DEFECT** | The allocation compensator withdrew only the *latest* over-allocation. Six racers left five standing and the requirement over-promised by four. Surfaced as an intermittent dirty baseline, and **first misdiagnosed as MariaDB table-cache thrashing** — an explanation written before the failing assertions had been read |
| T38 survived | **TEST GAP** | The probe that found the defect above caught it roughly one run in six, so it did not guard against its return. The allocate path was still paying M6's gate unwarmed after the barrier |
| T31 anchor-miss | **HARNESS DEFECT** | The mutation was never applied — its target moved — and an unapplied mutant reads as absence of a problem |
| C14.5/C14.6 | **TEST DEFECT** | Demanded a ledger ordering the system never promised; failed against the real implementation about one run in six; and were the assertions that appeared to catch T26, whose result was withdrawn |
| C6.6b | **PRODUCT DEFECT** | FULFILLED treated as closed by the resize gate, so a coordinator could not trim a promise a source had not delivered — the correction refused. The same defect already fixed on the seat path |
| D15/D16 | **TEST DEFECT** | Grew an allocation on a requisition with no headroom, so they measured the ceiling rather than the state gate |
| P7 | **CAUGHT MY OWN BAD FIX** | The first correction to the resize gate ("refuse only CLOSED") would have let a status in no lifecycle through. A probe written in the earlier adversarial pass failed within a minute |

No test was weakened, skipped or deleted to obtain a green result, and no product
code was changed to satisfy an incorrect test.

---

## What the mutation battery changed about these tests

The first mutation run caught 23 of 34; the final run catches **34 of 37**. Every
survivor was investigated rather than excused; **seven were genuine test gaps**
and produced new probes —
`test_p4_routes.php` in its entirety (the three `ops.php` wiring points nothing
had ever exercised), section **L** (cancelled vacancies lowering the ceiling),
section **M** (a refusal happening *before* the write rather than being written
and withdrawn), section **N** (the ledger asserted by name rather than by count),
section **O** (a malformed stale expectation on the create path), and
concurrency **C7**, **C8** and **C9**, and **J15–J18** (that "still to be sourced"
reads zero, not a negative number, while a requirement is over-committed).
Three race-dependent mutations still survive and are reported as survivors, with
their evidence, in `P4-MUTATION-RESULTS.md`.
