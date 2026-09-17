# PHASE 3 · M4 — CONCURRENCY RESULTS

Suite `phpapp/tests/test_p3m4_concurrency.php` with worker `tests/_m4_worker.php`
— **24 assertions**, **24 PASS / 0 FAIL** on **SQLite and MariaDB**.

Every race uses **genuinely separate `php` processes on independent database
connections**, each sleeping the same interval so they reach the operation
together. Nothing here is a sequential simulation.

| Test | Engine | Processes | Operation | Initial state | Concurrent action | Final state | Invariant | Result |
|---|---|---|---|---|---|---|---|---|
| **C1** last seat | SQLite · MariaDB | 2 | `hreq_to_requisition(1)` | approved 10, allocated 9 | both claim the last seat | allocated **10**, one process won | allocation ≤ approved; never negative | **PASS** |
| **C2** simultaneous increase | SQLite · MariaDB | 2 | quantity 4→6 on two requisitions | approved 10, allocated 8 | both would make 12 | allocated **10** | total never exceeds approved | **PASS** |
| **C3** duplicate re-approval | SQLite · MariaDB | 2 | material change on one request | approved, no open chain | both start re-approval | **1** open chain | never two open chains | **PASS** |
| **C4** concurrent decision | SQLite · MariaDB | 2 | one approves, one rejects | re-approval open | both decide | one answer (`REAPPROVED`), boundary agrees | no double decision, no contradiction | **PASS** |
| **C5** change vs execution | SQLite · MariaDB | 2 | material change ‖ execution attempt | approved, executable | race | blocked state **and** blocked boundary agree | execution never bypasses the boundary | **PASS** |

---

## A REAL DEFECT, FOUND ONLY ON MariaDB

**C1 failed on MariaDB on its first run: two processes both took the last seat and
the allocation reached 11 against an approved 10.**

`hreq_to_requisition()` was check-then-insert, which is not atomic. **SQLite hid
it** — it serialises writers with a database-level lock, so the race could not
materialise there and the test passed. MariaDB permits true concurrency and the
over-allocation happened immediately.

This is exactly why MariaDB is the authoritative engine, and it is the single most
valuable result in this gate.

**Fix.** The edit path already re-checked the ceiling after its write; creation now
does the same. The row is inserted, the total is re-read, and a requisition that
broke the ceiling is deleted again and refused with *"Another user took the last of
the approved headcount while this was being raised."* Two racing processes may both
revert — refusing an allocation that could in principle have succeeded, which is
the safe direction for a headcount control and **never an over-allocation**.

Re-run after the fix: **24/24 on MariaDB**, C1 included, with the losing process
failing cleanly and a reason.
