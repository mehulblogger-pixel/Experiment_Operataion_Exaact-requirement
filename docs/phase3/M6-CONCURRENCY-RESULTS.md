# PHASE 3 · M6 — CONCURRENCY RESULTS

Genuinely separate php processes on independent database connections, released
together. Nothing is a sequential simulation; nothing is defended in the browser.

| # | Race | Expected | Result |
|---|---|---|---|
| C1 | two joinings for the **one remaining seat** | exactly one joins; the loser is gated or reverted; never more than the approved seats | **PASS** |
| C2 | **four** processes, a one-seat requirement | exactly one winner | **PASS** |
| C3 | a joining racing a **material change** that blocks the request | a consistent outcome — either the joining landed before the block or it was refused; never a joining recorded against an already-blocked request | **PASS** |
| C4 | two recruiter assignments during the lifecycle | one winner; the column holds the winner's person; one ledger row | **PASS** |
| C5 | two **decisions** on one re-approval | one decision recorded; execution agrees with it | **PASS** |
| C6 | recruiter **deactivation** racing an assignment | one of the two consistent outcomes, and from that moment a deactivated person cannot be given the work | **PASS** |

## How the seat is actually protected

Check-then-write is not atomic, so two processes recording a joining at the same
instant both pass the gate. The seat therefore has two defences:

1. **the gate**, which refuses when no seat remains; and
2. **the compensating revert**, which runs immediately after the write, asks
   whether there was a seat for this person, and if not puts them back to their
   prior stage, re-syncs the requirement's status, and writes
   *"Joining reverted — no approved seat remained"* to the audit spine —
   **without** recording it as a joining anywhere.

This is the same shape M4 used for the headcount ceiling and M5 for ownership,
and it is the only thing that holds under real concurrency without wrapping every
caller in a transaction it does not own.

## A probe defect of my own, disclosed

**C4.2** originally read the owner column and compared it with… the owner column,
because the worker did not report what it had asked for. It compared a value with
itself and would have passed whatever happened. The worker now returns the person
each process asked for, and the assertion checks the column against the winner's
choice, plus that the winner is one of the two candidates for it.
