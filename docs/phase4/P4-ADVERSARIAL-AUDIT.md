# Phase 4 — Adversarial Audit

*Attacking my own completed work. Nothing was fixed while attacking; the attack
battery is a throwaway copy of the application and no product code was modified
during the pass. Everything below was found after Phase 4 was, by its own
account, finished and green.*

---

## The two attack surfaces that actually produced findings

### 1. The invariant that could not be stated as written

§11 states **FULFILLED ≤ ALLOCATED ≤ AUTHORISED**. A probe asserting it over the
*total* fulfilled count failed, because a person found directly (ADR-001) has no
source and so is never in ALLOCATED.

The obvious move — restate it over *sourced* fulfilment — makes the probe pass
immediately. **That is the trap**, and it is worth recording because a green test
would have closed the investigation:

> Restating the invariant alone would have hidden a real control gap underneath
> it. The allocation ceiling was **AUTHORISED**, ignoring people who had already
> arrived directly. Ten approved, three walk in off the street, and a coordinator
> could still promise all ten to an agency — which then believes it owes ten
> seats that only seven exist for. One approved demand, promised twice: the
> precise failure this phase exists to prevent, reached by a path nobody had
> looked at because the headline number still looked right.

**Fixed**: the ceiling measures **COMMITTED** (allocated + already arrived
directly), and the invariant is now recorded as two honest lines rather than one
unsatisfiable one. Section **J/K** of the invariant battery tests it in both
orders, including the case where the direct arrival comes *after* the promise —
where Phase 4 refuses to remove anybody or to trim a supplier silently, and
**shows** the over-commitment instead.

### 2. A corrupt row could invent capacity

`rful_summary()` added each row's `allocated_qty` straight into the total. The
door refuses a negative quantity — but a manual database fix, a bad migration, or
a writer added in two years' time could still produce one, and a negative made
ALLOCATED negative and pushed UNALLOCATED **above** the approved headcount. The
engine would then promise more people than were ever approved, reached entirely
by a path the door does not guard.

The same pass found the mirror problem in states: `rful_seat_block()` refused only
RELEASED and CANCELLED, so a row carrying a status in **no lifecycle at all**
could still be credited with people — while `rful_reallocate()`, asking the open
states, refused to resize that very same row. Two answers to one question, and
the permissive one was the security-relevant one.

**Fixed**: every row contributes at least nothing, and anything outside the
lifecycle fails closed. Section **P** is the permanent probe.

---

## What the mutation battery said about my own tests

The first run caught **23 of 34**. Every survivor was investigated; **seven were
genuine test gaps**, and they are more interesting than the defects:

| Survivor | What nothing was testing |
|---|---|
| T10, T27, T30 | The three `ops.php` wiring points. Every probe had called the **engine**; nothing drove the **routes**. All three controls could be deleted silently. → `test_p4_routes.php`, which drives the real routes in their own processes and then reads the database |
| T4 | Cancelling vacancies lowering the allocation ceiling. The seam to M3 existed and was correct, and was untested → section **L** |
| T23 | The compensator's keep-list. Every probe refused at the *door*, so the code that decides **who keeps a credit** when a source is over-credited had never run → **RT4**, which writes four links past the door and runs the compensator newest-first, so a list built the wrong way round would visibly keep the newcomers |
| T20 | A malformed stale expectation on the **create** path. The resize path was covered; the create path takes a different value through a different helper → section **O** |
| T29 | The ledger. Probes counted entries, so an **extra** one was invisible. Now asserted **by name and in sequence** → section **N** |
| T1, T3, T5, T6 | Whether a refusal happens **before** the write or is written and then withdrawn. Both controls existed, each covered the other, and nothing could tell them apart → section **M** |
| T18, T19 | The two compare-and-swaps. The race harness slept a fixed interval after start, and PHP's boot time varies by hundreds of milliseconds — so the processes **queued instead of colliding** and the races were never races. Workers now spin to a shared wall-clock instant, and C8/C9 run four rounds each |

---

## Probes of mine that were wrong, and what they nearly cost

Recorded because a wrong probe that is quietly deleted teaches nothing.

**C6.7** demanded that nobody hold a source credit without being in a filled
stage. That is not an invariant and must never become one — a candidate the
agency sent is legitimately linked to that agency from intake. MariaDB produced
the interleaving that exposed it; SQLite had passed it by accident. *Had the
product been "fixed" to satisfy it, the system would have learned to delete a
true fact about where a candidate came from every time a race was lost.*

**C6.6** demanded `over_committed == 0` after a three-way arrival race. If a
racer's **credit** loses but their **joining** wins — correct, because Phase 4
must never refuse a joining — they fill an approved seat directly and the
requirement is genuinely over-committed by one. Satisfying the probe would have
required either refusing a joining or silently cutting a supplier's promise, both
forbidden. Replaced with what always holds.

**C7.7** demanded that the source a person did not end on carry no credit entry.
That confuses *lost the race* with *never had it*: when the two attaches
serialise, both succeed and the person legitimately **moves**, and both entries
are true history.

**S3.1** reported "NOT APPLICABLE" when the licence could not be switched off —
which is calling *environment unavailable* a pass. Rewritten to switch hiring
out of the workspace's own **paid ceiling** and read the answer through the
product's own choke point, with a superuser probe for no-master-bypass.

**S5.2/S5.3** used a column that does not exist and swallowed the failure in a
`try/catch`, so two probes vanished from the output without a word. The fixture
now has no `try/catch`: if a supplier cannot be put on file, the battery fails
loudly. **S5.3** additionally inherited a requisition that section S4 had
deliberately exhausted, so it tested S4's leftovers rather than its own claim.

**RT3** posted `to` where the stage route reads `to_stage`, so it drove nothing
and reported failures that were entirely the fixture's fault.

---

## A Phase 3 probe that contradicts the ratified policy

`test_p3m4_concurrency.php` C1 asserted `$won === 1` — *exactly one* process
takes the last seat. It failed once, on MariaDB, under full-suite load.

This is **not a Phase 4 regression**, and the evidence is specific: no Phase 4
code is on that path (`hiringreq.php` and `reqfulfil.php` contain no reference to
the Phase 4 engine), the pre-Phase-4 tree behaves identically, and the M4
implementation's **own source comment**, written during Phase 3, says:

> *"Two racing processes may both revert, which refuses an allocation that could
> in principle have succeeded — the safe direction for a headcount control, and
> never an over-allocation."*

So zero winners is documented, intended behaviour, and it is also exactly what
the ratified capacity rule permits. The probe asserted a stronger property than
either the implementation or the policy promises.

**The probe was corrected — no product code was changed.** It now asserts what
the system guarantees: at most one winner, never an over-allocation, allocated
plus remaining always exactly the approval, and — the point of the rule — that a
dead heat leaves the seat **usable by the next valid transaction**, which the
probe now goes on to demonstrate. This is flagged prominently because it touches
a Phase 3 file.

---

## Attacks that found nothing, listed so the coverage is visible

Re-closing a closed allocation to leak capacity · syncing a closed allocation to
resurrect it · an allocation whose requisition was deleted underneath it · a
candidate credited while belonging to no requirement · SQL injection through the
ledger reason · an over-long source label · moving a person between sources and
checking the old source's history · the picker offering a source it would then
refuse · a released allocation being credited by somebody who joins afterwards ·
`rful_summary()` called with an array, a string, a negative, a float and a
boolean · cancelling vacancies underneath a full promise.

---

## What I would attack next

1. **The `connect` seam.** Marketplace and professional sources are refused
   without the module, but a workspace that *buys* connect mid-flight inherits
   allocations that were refused before. Nothing is wrong today; the transition
   is untested.
2. **Cost.** The source vocabulary is shared with the requisition's cost model
   and deliberately does not feed it. The first person who wires them together
   will need to decide what a released allocation costs.
3. **Scale.** The reconciliation battery goes to 100 seats and five sources. The
   derivation is `O(allocations × candidates)` per summary; a requirement with
   hundreds of sources has not been measured.
