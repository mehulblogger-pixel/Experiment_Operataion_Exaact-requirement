# Phase 5 — Test Results

## The Phase 5 battery

**135 assertions, 0 failed**, on both engines.

| § | Section | What it establishes |
|---|---|---|
| A | The screen and the records agree | The audit's own fixture — ten approved, four given up, three joined, two promised — now gives **one** answer from M3, Phase 4, the KPI engine and the dashboard |
| B | Reconciliation | The set-based aggregate equals the sum of `reqf_counts()` row by row, and **B4** re-checks every live requirement in the suite against `rful_summary()` |
| C | Canonical ageing | Calendar and business days differ; bad dates, missing dates and an unknown basis each give NO DATA; the same day is 0, not null |
| D | The target date | Inherited unchanged from the hiring request; **absent** on the direct path, with lateness null |
| E | The stage ledger is complete | An offer issued through the production path now leaves a coded entry; a reverted joining leaves the ledger ending at OFFERED, marked REVERT, with both the move and its undoing kept |
| F | Credit survives reassignment | Ann delivers, the record goes to Bob, Ann keeps it |
| G | Stage durations | Reverts and switches close no step; the in-progress step is separate; one ladder at a time |
| H | Entitlement and scope | Lineage declared and mapped; NO DATA on an empty period; a Branch B user sees less, and no promise leaks |
| J | The real route | `candidate-stage` driven in its own process; requirement recomputed, engine agrees, ledger written |
| K | States the ordinary paths cannot reach | Over-filled, over-cancelled, released-after-delivering, dashboard identity, pipeline keys, pre-ledger records, ambiguous outcomes, scope on the credit query, the M4 boundary, and the absence of a stale cache |
| L | What the adversarial pass found | Negative quantities, negative cancellations, corrupt promises, foreign allocations, **a ledger running backwards**, ageing at the edges, dangling links, unknown metrics, a dead heat in the ledger |

## Full regression

| | SQLite 3.45.1 | MariaDB 10.11.14 |
|---|---|---|
| Whole suite, to completion | see the completion report | see the completion report |

MariaDB is authoritative for production-oriented evidence. Neither figure is
inferred from the other.

## What the batteries said about my own work

Three defects in **my** code were found by testing rather than by reading it:

1. **`rkpi_target()` read the `hiring_requests` table directly**, breaking the M4
   boundary that only its own layer may touch it. Caught by M4's existing suite.
2. **`rkpi_settled_rows()` cached its query in a static.** A caller reading after
   a write in the same process got the state from before it. In production each
   request is its own process and this would have looked harmless for a long
   time; in one long-running process it was immediately wrong.
3. **The allocation sum counted only live allocations.** Found by the mutation
   battery — see `P5-MUTATION-RESULTS.md`.

And two defects in **my own probes**:

- The probe for released allocations released a promise that had delivered
  nobody, whose pinned quantity is zero under both the right rule and the wrong
  one. It compared two numbers that agree either way.
- Sections J and K were each written after a `$_SESSION` restore, so they ran
  with no scope and their fixtures were built against requirement id 0. Both
  produced confident passes and confident failures for reasons unrelated to what
  they claimed to test.

## One Phase 3 file was touched

`test_m3_multi_vacancy.php` located the raw `INSERT INTO candidate_events` in
`ops.php` and checked that `reqf_sync` appeared within 700 characters of it.
Phase 5 routes every stage movement through one canonical writer, so that INSERT
no longer exists, `strpos` returned `false`, and **the probe reported the absence
of a problem** — the most dangerous result a test can give.

It is re-anchored on the canonical writer and bounded by **the branch itself**
rather than by a count of bytes, because a byte window has to be widened every
time anything is inserted between the two points and each widening quietly
weakens the probe. The claim it stands for is additionally proved end to end, in
`test_p5_kpi.php` section J, by driving the real `candidate-stage` route in its
own process and reading the database.

**No product code was changed to make it pass**, and the probe asserts the same
thing it always did. It touches a Phase 3 file and is therefore the owner's to
review.
