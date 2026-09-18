# Phase 4 — Completion Report

## Multi-Source Fulfilment Engine

---

## What was asked for, in business terms

Twenty welders are approved. Ten come off our own payroll, five through a
manpower agency, five freelance. That is **one** approved requirement fulfilled
from several sources — and it must stay one requirement, because the moment
sourcing creates a second one, the business is committed to forty people.

Phase 4 answers the single question nobody owned: **of the authorised headcount,
how much has been promised to which source, and how much of that has arrived?**

## What was built

**One new table.** `requisition_allocations` — one row per source's promise
against one requirement — plus an append-only event ledger and **one additive
nullable column** on `candidates` recording which source a person arrived
through.

**Nothing else was built.** The source vocabulary **extends the existing
configurable `req_sourcing_model` lookup** already registered in Masters, so a
workspace can add its own sources without a line of code. Source entities are
**existing records**: a supplier from `business_partners`, a marketplace
requirement from `cx_requirements`, a professional from `cx_professionals`, a
person from `inspectors`. No new master of anything.

**One screen.** A panel on the requirement — one bar, one row per source, one box
to add another — and one field on the candidate form. The panel answers the
question a coordinator asks next, in the words they already use: *"twenty
approved, fifteen promised to three sources, five not yet sourced."*

## What Phase 4 is forbidden to do

Nothing Phase 3 owns is re-decided. M4 still owns the approved ceiling; M6 still
owns whether recruitment may execute **and whether anybody holds a seat**; M3
still owns what "filled" means; M5 still owns who is accountable. Phase 4 decides
only which source is **credited** with a person.

The practical consequence, tested from several directions: **the worst outcome of
any Phase 4 refusal is that a credit returns to the direct path.** It can never
remove anybody from a job.

## The defects this work found and fixed

Six, all in Phase 4's own code, all found by the batteries or by attacking the
finished work:

1. A full-but-open source told the user its allocation was **"closed"**. The
   refusal was right; the reason a human reads was false.
2. Closing an allocation stopped counting the people it had **delivered**, so a
   requirement could report *one person joined against zero allocated*.
3. **The allocation ceiling ignored people already found directly.** Ten
   approved, three walk in, and a coordinator could still promise all ten to an
   agency — which then believes it owes ten seats that only seven exist for. One
   approved demand, promised twice: the exact failure this phase exists to
   prevent, reached by a path nobody had looked at because the headline number
   still looked right.
4. A resize under contention **mixed a stale quantity with a fresh total**, so
   the loser of a race was refused with a false reason and sent to fix the wrong
   thing.
5. A **corrupt row could invent capacity**: a negative quantity made ALLOCATED
   negative and pushed UNALLOCATED *above* the approved headcount.
6. A status **in no lifecycle at all** could still be credited with people, while
   the same row could not be resized — two answers to one question, and the
   permissive one was the security-relevant one.

Defects 3, 5 and 6 were found by attacking work that was already, by its own
account, finished and green.

## Evidence

| | Assertions | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|---|
| Phase 4 batteries | 840 | 0 failed | 0 failed |
| Full regression | — | **12008 passed, 0 failed** | **12009 passed, 0 failed** |

The two engines differ by one assertion because one probe is driver-conditional.
MariaDB is authoritative for production-oriented evidence; neither figure is
inferred from the other, and both were run to completion.

**Mutation testing: 34 of 37 caught.** Each mutation breaks exactly one control
in a copy of the application; four of them break a control **and** its partner,
to prove the pairing is real rather than assumed. The three survivors are the
attach compensator and two compare-and-swaps, which execute only when several
processes get past a pre-check before any of them writes. They are reported as
**survivors, not as caught**; each has been caught in earlier runs of the same
battery, and removing either half of the pair **together** with its partner is
caught with 46 failures. `P4-MUTATION-RESULTS.md` gives the evidence, and states
plainly why this container cannot force that interleaving and why a test hook in
product code was not acceptable as a way to manufacture it.

**Concurrency:** real separate operating-system processes on independent
connections, synchronised on a shared wall-clock instant, with the contended
scenarios run four rounds each. Nothing is simulated sequentially and nothing is
defended in the browser.

## What the batteries said about my own tests

The first mutation run caught 23 of 34, and **seven survivors were genuine test
gaps** — most importantly that every probe had been calling the *engine* while
nothing drove the *routes*, so three controls in `ops.php` could have been
deleted silently. That produced `test_p4_routes.php`, which drives the real
`candidate-new`, `candidate-edit` and `candidate-stage` routes in their own
processes and then reads the **database**: nothing a route *says* is believed.

Seven probes of my own were wrong and are documented rather than quietly
deleted — including two that asserted properties the system never promises, and
which, had the product been "fixed" to satisfy them, would have taught it to
delete true history or to refuse a legitimate joining.

## One Phase 3 file was touched

`test_p3m4_concurrency.php` C1 asserted that **exactly one** process takes the
last seat. That contradicts the ratified capacity rule *and* the M4
implementation's own source comment ("two racing processes may both revert… the
safe direction for a headcount control"). It failed once, on MariaDB, under
full-suite load.

It is **not a Phase 4 regression** — no Phase 4 code is on that path, and the
pre-Phase-4 tree behaves identically. **The probe was corrected; no product code
was changed.** It now asserts what the system guarantees, and goes on to
demonstrate that a dead heat leaves the seat usable by the next valid
transaction. This is flagged here because it touches a Phase 3 file and is
therefore the owner's to review.

## The final mutation gate

Phase 4 was reported complete once before, at 34 of 37 mutations caught with
three survivors classified as *independently protected*. That classification was
wrong, and the gate that re-examined it is the most productive thing in this
phase.

**The three survivors were not protected.** They survived because the test
harness was not racing. Measuring the workers rather than reasoning about them
showed each was paying **7.5–9.8 ms** of one-time cost inside the **0.25 ms**
window it was supposed to be contending in — so eight processes released at the
same microsecond still arrived nine milliseconds apart and queued politely. The
remedy was entirely test-side: pay that cost before the barrier, exactly as the
database connection already was.

With the processes genuinely colliding, the gate produced **two real product
defects, two defects in my own tests, and one mutation that had never run**:

| # | Finding | Kind |
|---|---|---|
| 1 | The allocation compensator withdrew only the *latest* over-allocation. Six racers left **five standing and the requirement over-promised by four** | **product** |
| 2 | The probe that found (1) caught it about one run in six — it would not have stopped the defect returning | test gap |
| 3 | A combined mutant had never been applied; its target moved when (1) was fixed | harness |
| 4 | My ledger probes demanded an ordering the system never promised. They failed against the **real** implementation one run in six — and were the assertions that appeared to catch T26 | **my test, agreeing with a bug** |
| 5 | FULFILLED treated as closed by the resize gate, so a coordinator could not trim a promise a source had not delivered — the correction itself refused. The same defect already fixed on the seat path | **product** |

And the inverse lesson, immediately after: tightening the barrier **concealed**
T26, whose symptom needs a stale re-read that a perfect collision never produces.
A tighter race is not a better race. The harness now runs both the tight case and
a staggered one.

**What was corrected in the product:** the compensator now withdraws its own row
rather than only the latest (which can leave no winner — the ratified rule, and
the same choice M4 made at its headcount ceiling); the resize gate now allows
only LIVE states, character for character the test the seat gate makes; and the
candidate create route runs the same defence in depth the edit and stage routes
already ran.

**What was corrected in the tests:** the ledger is read as a *path* rather than a
sequence of rows; the workers warm their one-time costs before the barrier; the
races run staggered as well as tight; and a failing assertion now reports the
state it observed rather than only what it wanted — which is what turned an
intermittent failure into a diagnosable one.

Full detail, including the §10 evidence table and the §14 answer, is in
`P4-MUTATION-VERIFICATION.md`.

## Documents

`P4-PREIMPLEMENTATION-AUDIT.md` · `P4-TERMINOLOGY.md` · `P4-DATA-MODEL.md` ·
`P4-BUSINESS-INVARIANTS.md` · `P4-SOURCE-MATRIX.md` · `P4-STATE-MATRIX.md` ·
`P4-NEGATIVE-SECURITY-MATRIX.md` · `P4-ACTION-PATH-SWEEP.md` ·
`P4-INTEGRATION-MATRIX.md` · `P4-CANDIDATE-TRACEABILITY.md` · `P4-UI-UX.md` ·
`P4-RECONCILIATION-RESULTS.md` · `P4-CONCURRENCY-RESULTS.md` ·
`P4-MUTATION-RESULTS.md` · `P4-TEST-RESULTS.md` · `P4-ADVERSARIAL-AUDIT.md` ·
`P4-ANSWERS.md` · this report.

`docs/02-permission-matrix.md` and `docs/03-object-lifecycles.md` were updated in
the same commit as the code, as the repository's rules require.

## Backward compatibility

A workspace that upgrades and never touches sourcing is unchanged: ALLOCATED is
zero, everybody who joins is a direct arrival, the panel does not render on
requirements with nothing to show, and every pre-Phase-4 count reports exactly
what it did before. All schema changes are additive, forward-only, idempotent and
non-destructive.
