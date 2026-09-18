# Phase 4 — Concurrency Results

*Genuinely separate operating-system processes on independent database
connections. Nothing here is a sequential simulation, and nothing is defended in
the browser.*

Harness: `tests/test_p4_concurrency.php` launches `tests/_p4_worker.php` with
`proc_open()`. Each worker attaches to the same database as the parent, sleeps
the **same** interval before acting so the processes collide rather than queue,
and reports a JSON verdict. The worker deliberately does **not** use
`tests/bootstrap.php`, which drops every table on the MySQL path; it re-requires
the libraries from `index.php`'s own require list.

---

## The rule being tested

The ratified Phase 3 capacity rule, carried forward unchanged (Phase 3 invariant
I21, restated here as P22):

> Recruitment must never exceed approved capacity and must never displace an
> established holder merely to manufacture a concurrency winner. Where
> simultaneous claims cannot be deterministically resolved without risking
> displacement, the system may refuse the contested claims and leave the capacity
> available for a subsequent valid transaction.

**"Somebody must win" is not an invariant of this system.** The invariants are
*nobody is overfilled* and *nobody is displaced*. A test that demanded exactly
one winner would be asserting a property the product does not promise — and in
M6 such a test drove two attempted fixes that each let an arriving candidate
displace an established one.

---

## The scenarios

| # | Scenario | What must hold | Result |
|---|---|---|---|
| **C1** | Two coordinators promise the last four seats of a ten-seat requirement at the same moment | Never over-promised; at most one succeeds; the loser is told the seats are gone; whatever is left is still usable by a later valid transaction | **PASS** |
| **C2** | Two people arrive for one remaining seat at a source | The source is credited with at most its one; **both people keep their seats** | **PASS** |
| **C3** | Three arrivals race for a single credit that an established holder already owns | The established credit is **untouched**; all three arrivals are refused rather than displacing anybody | **PASS** |
| **C4** | Two processes resize the same allocation (to 9 and to 7) from the same baseline | The allocation holds **one** of the values asked for, never a blend; at most one accepted; the loser is told somebody changed it first | **PASS** |
| **C5** | A resize and a release collide on the same allocation | Whatever happens, the source is not credited beyond its promise and keeps the person it delivered | **PASS** |
| **C6** | Three people run the **whole arrival path** at once against a two-seat requirement whose only source was promised two | Never more than two seats filled; the source never credited beyond two; nothing promised twice; every one of the three is either in a seat or plainly not | **PASS** |

Run on SQLite and on MariaDB. Repeated three times on each engine after the final
fix; all six runs clean.

---

## What the races found

**C4 — the resize pre-check mixed two moments.** `rful_reallocate()` took the
allocation's current quantity from a read at the top of the function and the
requirement's total from a read further down. Under a real race these described
two different instants, so the arithmetic *total − mine + new* subtracted a
quantity that was no longer mine. The compare-and-swap still protected the
**write**, so nothing was ever corrupted — but the loser was told *"that would
promise more people than the requirement is authorised for"* when the truth was
*"somebody resized it while you were looking"*.

A false reason is a defect: it sends the user to fix the wrong thing. Both
figures now come from one read, and a disagreement between them **is** the race,
reported as `LOST_RACE`.

This is recorded because it is the only failure in this battery that the
single-process tests could never have found. SQLite and MariaDB both reproduced
it once the race was real.

**C6.7 — a probe that asserted something the system never promised.** The
original probe demanded that nobody hold a source credit without being in a
filled stage. That is not an invariant and must not become one: a candidate the
agency sent is legitimately linked to that agency from intake onwards, long
before they join, and such a link consumes nothing. When M6 reverted a contested
joining, the person kept a perfectly correct link and the probe called it a leak.
SQLite passed it by accident of interleaving; **MariaDB is what made the bad
assertion visible**. Had the product been "fixed" to satisfy it, the system would
have learned to delete a true fact about where a candidate came from every time a
race was lost. The probe was replaced with the claims that *are* invariants — the
credit equals the people in filled stages, never exceeds the promise, every racer
ends either seated or plainly not, and a whole-workspace sweep finds no
unholdable link.

---

## Why no new fairness mechanism was invented (§27)

Phase 4 reuses the Phase 3 shape exactly: **check, write, compare-and-swap,
compensate** — and the compensator asks only whether there was room for the
**arriving** claim. It never re-ranks the people already there. Two attempts in
M6 to guarantee a winner under a dead heat (ranking by decision time, then by id)
each let an arriving candidate displace an established one; both were abandoned,
and that lesson is inherited here rather than re-learned.
