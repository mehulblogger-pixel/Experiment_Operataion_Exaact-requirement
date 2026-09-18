# Phase 4 — Final Mutation Verification (T6 · T18 · T19)

*The three mandatory survivors from the Phase 4 battery, conclusively classified.
This document records what each mutation actually removes, what remains to
protect the business state, and the evidence for the classification.*

---

## Why they survived, and why that was a harness defect rather than a product one

All three controls execute **only** when several processes get past a pre-check
before any of them writes. Every earlier battery ran the racers through a
wall-clock barrier and they still refused to collide. Measuring the workers
rather than guessing showed why:

| Phase, per worker | Time | Spread across workers |
|---|---|---|
| Boot (227 libraries) | ~145 ms | ±4 ms |
| Arrive at the barrier | — | **1–2 µs** (the barrier was accurate) |
| First production read → **lazy schema check** | **7.5–9.8 ms** | **±2.4 ms** |
| Candidate read + gate + allocation read + seat check | ~1.0 ms | ±0.5 ms |
| **Seat check → write (the window being raced)** | **~0.25 ms** | — |

The per-process one-time schema check cost **nine milliseconds** and varied by
**2.4 ms** — an order of magnitude wider than the 0.25 ms window it was supposed
to be racing inside. The processes were arriving nine milliseconds apart and
queueing politely. They were never racing.

**The fix is entirely test-side** (`tests/_p4_worker.php`): the worker performs
that idempotent migration and the lookup/licence reads **before** the barrier, for
exactly the reason it opens the database connection there. No production logic,
timing, SQL or isolation level is altered; no test hook exists in product code.

Measured afterwards, with eight workers against a one-seat source: **all eight
passed the seat check and all eight wrote**, and the compensator withdrew every
one of them — zero winners, which is the ratified capacity rule working, and
direct proof that the contested path now executes.

---

## §8 — What each mutation removes, and what remains

| Mutation | What is removed | Expected invariant | How production still protects it | Business outcome if unprotected | Result |
|---|---|---|---|---|---|
| **T6** — attach compensator | The post-write withdrawal of an over-credit in `rful_attach()` | A source is never credited beyond its promise (P1) | `rful_seat_block()` before the write (single-process only); `rful_enforce_candidate()` on all three `ops.php` routes — but **not** for a direct `rful_attach()` call | **Over-credit persists**: eight people credited to a one-seat source | **CAUGHT** |
| **T18** — resize CAS | The `rowCount()` check after the guarded `UPDATE` | The ledger records only changes that happened | The SQL predicate `WHERE allocated_qty = <what I read>` still protects the **data**; the same-read pre-check (`$now !== $was → LOST_RACE`) refuses most losers **before** the CAS | **False success and a broken ledger chain**: several entries each claiming to start from a figure that had already moved | **CAUGHT** |
| **T19** — attach CAS | The `rowCount()` check after the guarded `UPDATE` | Every credit in the ledger is one the person actually held | The SQL predicate `WHERE allocation_id = <what I read>` still protects the **data** | **False attribution**: sources credited with a person who was never on them | **CAUGHT** |

**The data is never corrupted by T18 or T19** — their SQL predicates survive the
mutation, so a losing `UPDATE` still matches no row. What they destroy is
**truthfulness**: the caller is told a change succeeded, and the audit ledger
records it. Under §12 ("audit/ledger must represent actual committed business
outcomes; no phantom success") that is an incorrect business state, and it is what
the new probes assert.

**T6 is different, and worse.** It is the only thing standing between a genuine
race and a persisting over-credit for any caller that does not go through an
`ops.php` route — which §15 explicitly includes ("direct function call").

---

## §14 — The required classification

```
T6  = CAUGHT   (was a REAL GAP: an over-credit persisted, with no second line
                for a direct caller. Closed by C10.)
T18 = CAUGHT   (was a REAL GAP in the tests: the data was safe, the ledger was
                not. Closed by C12.)
T19 = CAUGHT   (was a REAL GAP in the tests: the data was safe, the attribution
                was not. Closed by C11.)
```

**Final battery: 38 of 38 caught, clean baseline, zero survivors, zero unapplied
mutants.** Measured on a freshly restarted MariaDB, on the code as it now stands.

None of the three is classified *independently protected*. Each allows an
incorrect business state that the remaining controls do **not** prevent — an
over-credit for T6, a false ledger for T18 and T19 — so the only honest
classification was a real gap, and each is now closed by a probe that fails
against the mutation and passes against the real implementation.

**Reliability, measured over three independent batteries on a freshly restarted
MariaDB — 9 runs, 9 caught, 0 survived, 0 dirty baselines:**

| Mutant | Round 1 | Round 2 | Round 3 |
|---|---|---|---|
| T6 | CAUGHT (26 assertions) | CAUGHT (26) | CAUGHT (26) |
| T18 | CAUGHT (1) | CAUGHT (1) | CAUGHT (2) |
| T19 | CAUGHT (4) | CAUGHT (2) | CAUGHT (2) |

T18 was caught every time, but by only one or two assertions, and the internal
round that caught it varied — so `C12` was raised from two rounds to four before
the final battery. More attempts at the same claim, not a weaker claim.

---

## The probes, and why each asserts the database rather than the report

Believing what a process *reported* is precisely how a mutation that fakes
success escapes: the report and the ledger inflate together. Each probe therefore
reconciles the **ledger against the database**.

**C10 — `rful_attach()`'s compensator (T6).** Eight processes, one promised seat.
Asserts the surviving **links in the table**, the fulfilment count, that no
established holder was displaced, that all eight remain in their seats, and that
the capacity is still usable afterwards — including the dead-heat case where all
eight are refused, which the ratified rule permits.

**C11 — the attach CAS (T19).** Eight processes move the *same* person to eight
*different* sources. Asserts that **every source carrying a credit for that person
either holds them now or has a matching `DETACHED` recording that they left** —
so a serialised run, where each move is real history, passes, while a phantom
credit for a source the person never touched fails. Deliberately not "one winner".

**C12 — the resize CAS (T18).** Eight processes resize one allocation from the
same baseline. Asserts the ledger is a **continuous chain**: each entry's
`from_qty` must equal the previous entry's `to_qty`, and the chain must end
exactly where the row now stands.

---

## §6 — The resize pre-check versus the resize CAS, proved rather than asserted

`rful_reallocate()` carries **two** guards in front of the write, and it matters
which one does what.

**What the pre-check protects.** After reading `$was` at the top of the function,
the code re-reads the requirement and compares the allocation's quantity as the
summary sees it:

```php
$sum = rful_summary($rq);
foreach ($sum['sources'] as $srcRow) if ((int) $srcRow['id'] === (int) $a['id']) $now = (int) $srcRow['allocated'];
if ($now === null || $now !== $was) return $fail('LOST_RACE');
```

This catches a loser whose **summary read happened after the winner committed**.
It exists because the two figures came from different instants (mutant T26), and
it converts what would have been a false `OVER_AUTHORISED` into a truthful
`LOST_RACE`.

**What the CAS protects.** The window *between* that summary read and the
`UPDATE`. A loser whose summary read happened **before** the winner committed
passes the pre-check honestly — at the moment it looked, the value really was
still 4 — and only the compare-and-swap predicate then stops it writing.

**Is the pre-check alone sufficient?** No, and the proof is the measurement, not
the reading. The interleaving that defeats it is:

```
A: reads $was = 4
A: reads summary  → now = 4        ✔ pre-check passes
B: reads $was = 4
B: reads summary  → now = 4        ✔ pre-check passes  (B looked before A wrote)
A: UPDATE … WHERE allocated_qty = 4   → 1 row, 4 becomes 9
B: UPDATE … WHERE allocated_qty = 4   → 0 rows
   with the CAS check    → LOST_RACE, nothing recorded
   without it (mutant)   → reports success, writes REALLOCATED(from 4 → to 7)
```

**If the pre-check alone were sufficient, T18 could never be caught** — every
loser would return `LOST_RACE` before reaching the mutated line, and the suite
would stay green. T18 has been caught in **5 of 5** runs since the harness was
fixed, each time by `C12.3` observing a ledger entry that starts from a figure
which had already moved. Every one of those catches is a process that passed the
pre-check and reached the CAS. The window is real, reachable, and the CAS is the
only thing covering it.

**Why one catching assertion is enough here.** `C12.3` is not a weak proxy for
the invariant; it *is* the invariant. The ledger is a chain — each entry records
what it moved **from** and **to** — and a chain in which two entries both claim to
start from the same figure is a direct statement that a change was recorded which
never happened. That is precisely and only what removing the CAS causes. `C12.4`
(the chain must end where the row actually stands) catches the same defect from
the other end and has fired alongside it. The probe was nevertheless raised from
two rounds to four, because the *round* that caught it varied.

---

## One control gap found while classifying, and fixed

The candidate **create** route ran `rful_enforce_candidate()` *before*
`rful_apply_posted()` — where it could only ever be a no-op, because the INSERT
column list carries no `allocation_id` and there was never anything for it to
find. The **edit** and **stage** routes both run it *after* the write. So the
create path was the one route with no second line behind the engine's own
compensator.

Not a live defect while T6 is present, but the same "a rule applied where somebody
remembered to apply it" family this programme keeps meeting. The call was moved
after the attach (`lib/ops.php`), and **RT2.4–RT2.7** now prove a second person
cannot be created onto a full source while the established credit is untouched.

---

## §10 — Final evidence table

Every cell below is backed by a measured run, not by reading the code.

| Mutant | Removed control | Real business risk | Independent protection | Catching test | Reliability | Classification |
|---|---|---|---|---|---|---|
| **T6** | The attach compensator's withdrawal of an over-credit | A source credited with people it never had room for — eight against a one-seat promise | None for a direct `rful_attach()` caller. The seat check covers single-process only; `rful_enforce_candidate()` covers the three routes | **C10**, C2.2–C2.4 | 3/3 + battery | **CAUGHT** (was a real gap) |
| **T18** | The resize compare-and-swap's `rowCount()` check | A change recorded in the ledger that never happened; the caller told it succeeded | The SQL predicate still protects the DATA; the same-read pre-check refuses most losers earlier | **C12.3/C12.4** | 3/3 + battery | **CAUGHT** (was a real gap) |
| **T19** | The attach compare-and-swap's `rowCount()` check | Sources credited with a person who was never on them — seven of them, measured | The SQL predicate still protects the DATA | **C11.3** | 3/3 + battery | **CAUGHT** (was a real gap) |
| **T22** | The resize state gate | A released or cancelled allocation resized back to life | None | F8, F12a | 2/2 | **CAUGHT** |
| **T26** | The resize same-read pre-check | A loser told "the approval is full" when the truth is "somebody changed it" — sending a coordinator to fix the wrong thing | The CAS still protects the DATA, but not the REASON | **C14.4** | 2/2 + battery | **CAUGHT** |
| **T31** | The allocation ceiling **and** its compensator together | The approved headcount promised more than once | None — that is the point of the pair | B1, B2, B3 (73 assertions) | 1/1 | **CAUGHT** (was never applied — see below) |
| **T38** | Withdrawing anything but the latest over-allocation | Five over-allocations standing, requirement over-promised by four | None | **C13.4**, C8.2–C8.4 | 3/3 + battery | **CAUGHT** (was a real gap) |

### The two that were not what they first appeared

**T31 was an ANCHOR-MISS — it had never been applied at all.** The compensator fix
moved the code it targeted, so the mutation silently failed and the battery
reported nothing for it. An unapplied mutant looks exactly like absence of a
problem. It was visible only because the harness prints `ANCHOR-MISS` explicitly;
printing nothing would have turned 36/38 into an apparent 37/37.

**T26's first "catch" was an artefact of a flaky assertion of mine** and was
withdrawn rather than carried forward. See below.

## §14 — The final adversarial question

> **With the final Phase 4 implementation, can any real user, concurrent process,
> direct function call, POST, AJAX path, API path, import path, background path,
> candidate workflow, allocation workflow, fulfilment workflow, or sibling
> workflow violate the Phase 4 quantity, source-credit, ownership, attachment,
> allocation, or concurrency invariants?**

**Answer: no path found, and the search is documented below rather than
asserted.** What follows is what was actually exercised, not what was assumed.

### Every writer of the Phase 4 tables, enumerated

`requisition_allocations` has exactly three writers — `rful_allocate()`,
`rful_reallocate()`, `rful_close()` — plus `rful_sync_state()`, which writes only
a derived status. `candidates.allocation_id` has exactly one door,
`rful_attach()`. Verified by grep across `lib/` and `views/`: no other file
writes either. The route `/requisition-allocations` is a thin shell that decides
nothing; a crafted POST reaches the same three functions the form does.

### How each surface was exercised

| Surface | How it was tested | Evidence |
|---|---|---|
| **POST / AJAX / the routes** | The real `candidate-new`, `candidate-edit` and `candidate-stage` routes driven in their own processes, then the **database** read | `test_p4_routes.php`, 39 assertions |
| **Direct function call** | Every probe in the invariant, security and reconciliation batteries calls the production function with no screen in the way | 877 assertions |
| **Concurrent processes** | Real separate OS processes on independent connections, synchronised to a wall-clock instant, warmed so they genuinely collide | C1–C12 |
| **Raw SQL past the door** | Links and quantities written straight to the tables, then the compensators run | E3–E4, RT1.7–RT1.13, RT3.4–RT3.9, RT4, section P |
| **Import / background** | No Phase 4 importer or background job exists; the cron touches nothing in these tables | grep of `cron.php`, `api.php` |
| **Sibling workflows** | Offers, interviews, the configured pipeline and the careers intake were checked for writes to either table — there are none; they are gated by M6, which Phase 4 asks rather than duplicates | `P4-ACTION-PATH-SWEEP.md` |

### The invariants, and what proves each

| Invariant | Proof |
|---|---|
| ALLOCATED ≤ AUTHORISED | B1–B6, C1, C8 (six-way), mutants T1/T3/T31/T38 |
| COMMITTED ≤ AUTHORISED (a seat filled directly cannot be promised) | J1–J7, mutant T2 |
| SOURCED-FULFILLED ≤ ALLOCATED | D5–D8, C2, C9, C10, mutants T5/T6/T32 |
| No phantom credit or false attribution | C11, mutant T19 |
| Ledger equals committed state | C12, N1–N6, mutants T18/T29 |
| One allocation, one requirement | E1–E4, S7.4, mutants T8/T9/T34 |
| No established holder displaced | C3, C10.6, RT4, mutant T23 |
| No negative figure | G6, J15–J18, section P, mutants T35/T36 |
| Entitlement, permission, scope, executability | S1–S3, S8, mutants T11/T12/T13/T14 |

### What I am *not* claiming

This says no violating path was **found** by the work recorded here. It does not
say none exists. Two specific limits are worth stating plainly:

1. **Scale is unmeasured beyond 100 seats and eight concurrent processes.** The
   derivation is `O(allocations × candidates)` per summary.
2. **The `connect` entitlement transition is untested.** A workspace that buys the
   marketplace module mid-flight inherits allocations that were refused before it
   did. Nothing is wrong today; the transition has no probe.

Neither is a known defect. Both are named so that the next person attacking this
starts where the evidence stops rather than where it looks complete.

---

## What this gate actually found

Five distinct problems, each visible only after the previous one was fixed. Two
were defects in the product; two were defects in my own tests; one was a mutation
that had never run. They are listed in the order they surfaced, because the order
is the point.

**1. A real over-allocation defect.** `rful_allocate()`'s compensator withdrew an
over-allocation only when its row was the *latest* live row. With two racers that
looks correct. With six it left **five over-allocations standing, each reporting
success, and the requirement over-promised by four** — the ceiling this phase
exists to defend, broken by the control meant to defend it.

**2. A guard that was not guarding.** Mutant T38 reinstates exactly that defect,
and it **survived**. The probe that had found the defect caught it roughly one run
in six. Fixing a defect and leaving its guard ineffective would have been the
worst outcome of this gate, because the fix would have looked finished.

**3. A mutation that was never applied.** T31, above.

**4. An assertion of mine that agreed with a bug.** The ledger chain probes walked
entries in id order and demanded continuity. The CAS and the ledger INSERT are
separate statements, so two concurrent *successful* resizes can record entries in
the opposite order from the writes — each entry truthful, only the ordering
inverted. The probes failed against the **real** implementation about one run in
six, and they were **the assertions that appeared to catch T26**. That result was
withdrawn. The replacement reads the ledger as a *path*: every recorded change
must lead to the next, each used once, ending where the allocation stands.

**5. A derived state that blocked the correction it should permit.**
`rful_seat_block()` had already been taught that FULFILLED is a live state, not a
closed one — the lifecycle document says so explicitly. `rful_reallocate()` was
left asking the old question. Because FULFILLED is *derived* and can be left stale
under concurrency, a coordinator trying to trim a promise a source had **not**
delivered was told *"that allocation is closed"*. The same defect, on the one path
the earlier fix was not carried to. My first correction was itself wrong — it
would have let a status in no lifecycle through — and probe **P7**, written in the
earlier pass for precisely that property, failed within a minute.

### The common thread

None of these was visible until the concurrency harness was made to genuinely
collide. Measurement showed why it had not been: the workers paid **7.5–9.8 ms**
of one-time cost inside a window of **0.25 ms**, so eight processes released at the
same microsecond still arrived nine milliseconds apart and queued politely.

And then the opposite lesson, immediately: tightening the barrier **concealed**
T26, whose symptom requires a stale re-read that a perfect collision never
produces. A tighter race is not a better race — it is a different one, and a
harness needs both the tight case and the staggered one.

**A mutation score is only as trustworthy as the assertions behind it and the
interleavings the harness can actually produce.** Two of the five findings here
were faults in the measuring instrument, and one of those was actively agreeing
with a bug.
