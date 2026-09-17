# PHASE 3 · M5 — CONCURRENCY RESULTS

Suite `phpapp/tests/test_p3m5_concurrency.php` with `tests/_m5_worker.php`.
Every race runs in **genuinely separate php processes** on independent database
connections, launched together and released at the same moment. Nothing is a
sequential simulation and nothing is defended in the browser: two managers
pressing Save at the same instant is a server-side problem.

| # | Race | Expected | Result |
|---|---|---|---|
| C1 | two processes assign the same unowned requirement to **different** people | exactly one wins; the loser is told; the column holds the winner's person | **PASS** |
| C2 | **five** processes, two of them naming the **same** person | exactly one winner, one ledger row | **PASS** |
| C3 | two processes **reassign** from the same existing owner | one wins; the ledger records who it was taken from | **PASS** |
| C4 | two **browser form** saves, both carrying the same baseline | one winner; the form path is no weaker than the service path | **PASS** |
| C5 | a baseline-carrying save against a baseline-less service call | the final owner is one of the two asked for; the ledger is an unbroken chain ending where the column stands | **PASS** |
| C6 | two assignments racing a requirement that is being **cancelled** | both refused by the state, not by the race; still unowned | **PASS** |

## The defect C2 found — and the first version of my own test that hid it

Five processes contended for one unowned requirement, and **two reported
success**. The cause was in the writer: it performed the compare-and-swap and
then read the column back, and finding the value it had asked for, called it a
win.

Reading back the value you wanted does **not** mean you wrote it. Two of the five
processes named the same person; the loser's swap matched no row, it read the
column, saw that person there — put there by the winner — and reported a
successful assignment. Two winners, **two ledger rows, one actual move**. A test
that only asserted the final owner would have called that correct.

**Fixed** by asking both halves of the question: how many rows the swap matched,
**and** what the column holds afterwards. The matched-row count is safe to read
here because the no-change case (`$from === $to`) returns before the swap, so any
row the statement matches is a row it genuinely changed — which is the one case
MariaDB's `rowCount()` would otherwise report as 0.

Because a race is a **probabilistic** detector, the same rule is pinned
deterministically in `p3m5_assign` section **M**, and mutation **M10** removes the
matched-row test and is caught.

## A defect in my own probe, which MariaDB exposed

C5's first version asserted *"exactly one of the two wrote, baseline or no
baseline"*. That is the right invariant for two callers holding the **same**
baseline — C1, C2, C3 and C4 all assert it — but a caller carrying **no** baseline
is not a stale screen. It is a service saying *"make Z the owner, whatever it is
now"*, so when it re-reads and finds Y it moves Y → Z, and both moves are real.
The ledger showed `279 → 280 → 281`: an unbroken chain of two genuine
assignments, not a lost update.

SQLite passed the wrong assertion only because it serialises writers, so the
second process usually read before the first committed. **The engine difference
exposed a defective test, not defective behaviour.** The probe now asserts what
must actually hold: the final owner is somebody who was asked for, and the ledger
is a truthful chain in which every row hands over from the row before it and the
last handover is what the column holds. It was re-run three times on MariaDB.

## Engine note

MariaDB is authoritative. Both defects above — the product one and the probe one —
were invisible on SQLite, which serialises writers with a database-level lock.
This is the second milestone running in which the real concurrency finding came
only from MariaDB.
