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
