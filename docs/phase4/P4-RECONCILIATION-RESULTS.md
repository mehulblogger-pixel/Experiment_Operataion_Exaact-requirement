# Phase 4 — Reconciliation Results

*Does the arithmetic hold, at the sizes the business actually works at, in every
order of operations, and does every reported figure equal the rows it came from?*

Harness: `tests/test_p4_reconcile.php`. **454 assertions**, all through
production functions; fixtures are built through the real M4 approval path, never
by raw SQL, so no probe can pass against a requirement the product would refuse.

---

## The one check

Nothing in Phase 4 is stored as a total. `rful_summary()` derives every figure on
read, and `$r4check()` re-derives the same figures **straight from the tables**
and demands an exact match — nine assertions, applied at every stage of every
scenario:

```
allocated          == SUM(allocated_qty) over the rows
sourced_fulfilled  == COUNT(candidates in a filled stage carrying a link)
direct_fulfilled   == COUNT(candidates in a filled stage carrying none)
fulfilled          == COUNT(candidates in a filled stage on the requirement)
committed          == allocated + direct_fulfilled
sourced_fulfilled  <= allocated  <= authorised
fulfilled          <= authorised
unallocated >= 0 and remaining >= 0
```

If `rful_summary()` ever learns to cache, this is what catches the drift.

---

## R1 — every shape the business asked for (§12)

**10, 20 and 100 seats × 1, 2, 3 and 5 sources = 12 shapes.** For each: allocate
the split (remainder to the first source, as a coordinator would), verify the
totals, prove not one more seat can be promised, then **fill every source to the
brim** and prove the next arrival at each is refused.

| Seats | Sources | Split | Allocated | One more? | Filled | Result |
|---|---|---|---|---|---|---|
| 10 | 1 / 2 / 3 / 5 | 10 · 5+5 · 4+3+3 · 2×5 | 10 | `OVER_AUTHORISED` | 10 | **PASS** |
| 20 | 1 / 2 / 3 / 5 | 20 · 10+10 · 8+6+6 · 4×5 | 20 | `OVER_AUTHORISED` | 20 | **PASS** |
| 100 | 1 / 2 / 3 / 5 | 100 · 50+50 · 34+33+33 · 20×5 | 100 | `OVER_AUTHORISED` | 100 | **PASS** |

Every shape also proves the per-source ceiling: a source full to its promise
takes nobody more (`OVER_ALLOCATED`), and the refused person **keeps their seat**.

---

## R2 — the same total reached by a different route

Twenty seats, 12 + 8. Five arrive from the agency; the agency is cut to the five
it delivered (seven seats return); a sub-contractor takes the seven; own payroll
is cancelled (its eight undelivered seats return).

| Step | Allocated | Unallocated | Reconciles? |
|---|---|---|---|
| 12 + 8 | 20 | 0 | yes |
| five arrive | 20 | 0 | yes |
| agency cut to 5 | 13 | 7 | yes |
| sub-contractor takes 7 | 20 | 0 | yes |
| own payroll cancelled | 12 | 8 | yes |

**PASS** — and the twenty is reached from three sources instead of two without
the approved headcount ever moving.

---

## R3 — a hundred seats reorganised mid-flight

100 seats across five sources (30/25/20/15/10). Roughly half arrive (49). The
supplier walks away and is cancelled; the agency grows from 25 to 30 to absorb
the freed seats.

- After the cancellation, the supplier's **five undelivered** seats return and
  its **five delivered** people stay credited to it and stay in their seats.
- The allocation is pinned to exactly those five *(R3.9–R3.10)*.
- After the agency grows, the requirement is back to 100 promised, nothing spare,
  and not one more can be added.

**PASS**, with the full nine-assertion reconciliation at each step.

---

## R4 — a whole-workspace sweep

After everything above, `rful_bad_links()` walks **every** candidate in the
workspace carrying a link and asks whether that link is holdable; and every
allocation is asked whether it is over-credited.

| Check | Result |
|---|---|
| Candidates credited to a source that cannot hold them | **0** |
| Sources credited beyond their promise | **0** |

---

## Totals

| Battery | Assertions | SQLite | MariaDB |
|---|---|---|---|
| Invariants (`test_p4_allocation.php`) | 95 | 0 failed | 0 failed |
| Negative security matrix (`test_p4_security.php`) | 81 | 0 failed | 0 failed |
| Reconciliation (`test_p4_reconcile.php`) | 454 | 0 failed | 0 failed |
| Concurrency (`test_p4_concurrency.php`) | 37 | 0 failed | 0 failed |
| **Phase 4 total** | **667** | **0 failed** | **0 failed** |
