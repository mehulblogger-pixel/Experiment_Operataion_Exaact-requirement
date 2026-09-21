# RB-3 · Step 3 — Completion

*Acceptance is one transaction, or it is nothing.*

---

## 1. What changed

Accepting a candidate used to be a sequence of separate saves. If anything went
wrong part-way, the business was left with half an event: a candidate marked
Accepted with no employee behind them, or an employee holding a permanent staff
number for a hire that was undone.

It is now **one save**. The stage move, the stage history, the workforce record,
the employee number, the requirement's standing and the source credit either all
happen together or none of them happen. Every question that could cause a refusal
is asked **before** the save begins, so a refusal costs nothing and the candidate
stays exactly where they were.

Two recruiters racing for the last approved seat are now settled *at the save*,
under a lock on the requirement, instead of both being let through and the loser
being put back afterwards.

## 2. Files changed

| File | Change |
|---|---|
| `phpapp/lib/ops.php` | the `candidate-stage` route restructured into one transaction; the old post-commit conversion block deleted |
| `phpapp/lib/recruit.php` | `rcv_refusal_before_transaction()`, `rcv_prewarm_migrations()`, `rcv_audit_or_report()`; `rcv_convert()` locks the candidate row and re-throws on a borrowed transaction |
| `phpapp/tests/test_rb3_step3_atomic.php` | the T / X / E / J battery |
| `phpapp/tests/_rb3s3_worker.php` | the real-process child used for overlaps |
| `phpapp/tests/test_m3_multi_vacancy.php` | source probe re-aimed at both regions and made to assert it found them |
| `phpapp/deploy-check.php` | regenerated |

## 3. Engines reused — nothing new was built

Step 2's duplicate matching and acknowledgement, Step 1's employee-number key,
the seat engine (`rexec_block_reason`), the KPI ledger, the fulfilment engine and
the audit trail are all **called**, not re-implemented.
`rcv_refusal_before_transaction()` is composition only.

## 4. Database changes

**None.** No new table, no new column, no migration. Idempotency reuses the
existing `candidates.inspector_id`, which already records what an application
produced. The §25 requirement to prove a new table is genuinely needed was met by
not needing one.

## 5. The transaction boundary

Full trace in `RB3-STEP3-TRANSACTION-MAP.md`. Nine persistent writes participate;
one — the audit entry — deliberately does not, per invariant **I41**.

**No write was found that cannot participate.** The two hazards that could have
forced a STOP were investigated and resolved rather than worked around:

- **Migrations run DDL, and MariaDB commits implicitly on DDL.** They are warmed
  before the transaction opens, and the warm-up refuses to run inside one.
- **Helpers that might commit internally or use a second connection.** Every one
  was checked; none does.

## 6. The two owner decisions, as implemented

1. **The seat ceiling moves inside the transaction**, under a lock on the
   requirement. The second recruiter is stopped at the save, and leaves nothing
   behind. Lock order is fixed: requirement → candidate → workforce insert.
2. **The audit stays outside** (I41), *and* a lost entry is **counted and
   reported** instead of silently swallowed — surfaced as the
   `AUDIT_WRITES_LOST` finding.

## 7. Security

A forged or replayed POST gains nothing. The acknowledgement tick carries a
signed, time-limited token bound to the applicant, the matches and the actor;
`rcv_convert()` re-asks its own gates (invariant **I27**), so bypassing the route
does not bypass the rules; an application id from another tenant is refused by
the route **and** by the action (**I25** — a record id is never proof of
authorisation).

## 8. Audit

Every refusal is recorded, including one that rolls back — written outside the
transaction that vanished, which is what **X11b** asserts. A successful
acceptance's entry is written **after** the commit (**T4g**).

## 9. Concurrency

Proved with real processes and a wall-clock barrier, on MariaDB:
two recruiters and one seat (**T1**), a deterministic overlap (**T7**), the same
overlap on the path with no conversion (**T10**), and three simultaneous
submissions of the *same* acceptance producing one record and one number
(**X13**).

## 10. Test results

| Engine | Full suite | Step 3 battery |
|---|---|---|
| **SQLite 3.45.1** | **13,064 passed · 0 failed** | 83 · 0 |
| **MariaDB 10.11.14** | **13,076 passed · 0 failed** | 92 · 0 |

The engines are run **one after the other**, never at once — they share one
control plane. The 12-assertion difference in the full suite is accounted for
assertion by assertion in `RB3-STEP3-TEST-EVIDENCE.md` §2: nine of them are
T7, T9 and T10, which SQLite cannot express and which say so in their own names.
MariaDB's total is not stable between runs, for reasons named there.

## 11. Mutation results

**15 targets · CAUGHT 13 · SURVIVED 2 · FATAL 0 · ANCHOR-MISS 0.**

This is **not** reported as zero survivors. **A12** and **A15** survive, and both
were shown empirically — not argued — to be unreachable backstops: nothing after
the workforce insert can fail, and `rcv_convert()` cannot return a failure inside
a borrowed transaction because it re-throws. The **E-section** of the battery
asserts those facts directly, so the equivalence claim withdraws itself
automatically if the code ever changes. Both guards stay in place.
Detail: `RB3-STEP3-TEST-EVIDENCE.md` §5.

## 12. Known limitations

- The overlap proofs are MariaDB-only by necessity: SQLite takes one
  database-wide write lock and sets no busy timeout, so it cannot stage a write
  race, and its DDL is transactional, so the implicit-commit hazard does not
  exist there.
- Under heavy contention a recruiter can be told the record is busy and asked to
  try again. That is an honest refusal with nothing written, not a silent
  failure.
- The audit entry remains the one write that can be lost without failing the
  hire. That is the owner's decision, and the loss is now visible.

## 13. Deferred — untouched

Exactly as instructed, none of these was implemented, redesigned or "cleaned up
while I was here":

1. `team_role` capture at the requisition / position
2. Workspace capability classification
3. Removal of the hidden `dup_ack` checkbox (RB-1)
4. RB-2 lifecycle / status correction
5. SQLite busy timeout
6. Demo unload deleting records by employee number

## 14. Commits

| Commit | What |
|---|---|
| `938c279` | acceptance is one transaction, or it is nothing |
| `857124f` | the four mutation survivors closed, 9 of 9 caught |
| `da64ffc` | §7 refusal hoist, the X / E matrix, 15-target mutation battery, the three documents |

## 15. Status

**Step 3 is complete and awaiting owner approval.** Nothing from Step 1 or
Step 2 was rebuilt, redesigned or reopened.
