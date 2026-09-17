# PHASE 3 · M6 — ADVERSARIAL AUDIT

Two passes, both in throwaway copies, both against production functions rather
than against source text. **No product code was modified during either attack.**

---

## PASS 1 — against the accepted M1–M5 tree (discovery)

Run before a line of M6 code was written, with clean baselines on both engines so
that no finding could be an environment failure.

### Findings

| # | Finding | Class | Measured |
|---|---|---|---|
| **A1/A2** | an offer could be created, submitted, approved, **ISSUED** and accepted on a requisition whose approval M4 had invalidated; the candidate was moved to `OFFERED` | **BLOCKER** | `offer_issue()` returned *"Offer issued. Share the letter with the candidate."* |
| **A3** | an interview could be scheduled on the same blocked requisition | **MATERIAL** | interview row created |
| **A5** | **three people joined a two-seat requirement** | **BLOCKER** | authorised 2, joined 3 |
| **A7** | interviews and offers ran on a **CANCELLED** requirement | **MATERIAL** | both created |
| **X3/X4** | the **approval engine** wrote requisition statuses that are not in the requisition lifecycle (`'approved'`, `'on_hold'`), so an approved requirement vanished from open demand and the execution gate refused all work against it | **MATERIAL** | status `"approved"`; absent from the dashboard's demand |
| A6 | the dashboard agreed with the records | **HELD** | — |
| A8 | ADR-001 direct candidates still workable | **HELD** | — |
| **A4** | my own pipeline probe was **vacuous** | **TEST-HARNESS DEFECT** | it asserted "the stage did not change" on a candidate an earlier probe had already moved to `OFFERED` |

### The pattern

All five product findings are the same shape, and it is the shape this phase keeps
producing: **a rule applied where somebody remembered to apply it.** M4 connected
six execution paths; paths seven to ten — the pipeline engine, the interview, the
offer and the joining — were never asked. And the approval engine, asked to record
its own decision, wrote into a lifecycle it does not own.

### What was built in response

One composed question, `rexec_block_reason()`, asked at each unguarded path. It
re-decides nothing: approval is asked of M4, counting of M3. The only genuinely
new rule is the one nothing owned — **a joining may not take a seat that does not
exist** — expressed through M3's own counter and protected by a compensating
revert, because check-then-write is not atomic.

---

## PASS 2 — the same battery against the fixed tree (§57)

| Probe | Pass 1 | Pass 2 |
|---|---|---|
| A1 offer on a blocked requisition | **FAIL** | **PASS** — `offer_create()` returns 0, no row |
| A2 issue / accept while blocked | **FAIL** | **PASS** — refused |
| A3 interview while blocked | **FAIL** | **PASS** — no row |
| A4 pipeline advance while blocked (repaired probe) | vacuous | **PASS** — and the probe asserts its own starting state |
| A5 joining beyond the seats (repaired probe) | **FAIL** | **PASS** — authorised 2, joined 2 |
| A6 dashboard vs records | PASS | **PASS** |
| A7 cancelled requirement | **FAIL** | **PASS** — neither interview nor offer |
| A8 ADR-001 still works | PASS | **PASS** |
| X3–X6 approval chain statuses | **FAIL** | **PASS** — `OPEN`, on both branches |

**24 / 24.**

### Two probes of mine that were invalid in pass 2, reported rather than discarded

Both **A5** and **X3** performed the write *themselves* with raw SQL — one
`UPDATE candidates SET stage='ACCEPTED'`, the other the callback's old
`UPDATE requisitions SET status='approved'`. After the fixes they were therefore
asserting against **their own SQL**, not against the product: A5 was measuring
"can a direct database write bypass a PHP-level control", which is trivially yes
and says nothing; X3 was re-performing the very statement that had been removed.

Neither was deleted. Both were repaired to call the production path — A5 now asks
the gate, writes, and runs the compensating check exactly as the route does, and
X3 calls `appr_callback()` — and both then passed. That is the difference between
a probe that tests the product and a probe that tests itself.

---

## What neither pass could defeat

No path past approval, re-approval, the execution boundary, the seat ceiling,
recruiter accountability, entitlement, tenant, branch, permission or state. No
lost update. No phantom ledger row. No audit record claiming an operation that did
not happen. No dashboard figure the records do not support.


---

# PASS 3 — the independent gate, after M6 was declared accepted

Run against the committed, accepted tree in a throwaway copy, attacking the M6
gate itself rather than the defects it was written to close. **No product code
was modified during the attack.**

**Five defects found, all material. The M6 verdict issued before this pass was
premature and is withdrawn.** Three came from the attack battery (G1, G2, G3), one
from a probe written to pin a fix (G8), and one from my own suite failing
intermittently and being investigated rather than re-run (G7).

## G1 — MATERIAL. The gate decided *which* requirement by type conversion.

**Proved.** `rexec_block_reason()` cast its requirement id. An array became the
integer 1, a word became 0, a negative number fell through to the "no
requirement" path — and every one of those answers was **ALLOW**:

```
G1 · the lowest live requirement id is: 1
G1 · an ARRAY requirement id answers: *** ALLOWED ***
G1 · …and so is a word: ALLOWED
G1 · …and a negative id: ALLOWED
```

An array posted as the requirement id got the verdict for **requisition #1**,
which happened to be live. A word or a negative number got the ADR-001 answer —
*"there is no requirement, so there is nothing to respect"* — which is the most
permissive answer the gate can give.

**This is the same defect M5's adversarial audit found in the ownership door, one
milestone later, in the gate I wrote after fixing it.** The lesson was recorded
and then not applied to the next thing built. That is the honest finding here: not
that the rule was unknown, but that it was not carried forward.

**Fix.** `rexec_id()` validates before anything looks anything up, and is
deliberately **stricter** than the ownership door: there "nothing" means
*unassign*, a legitimate choice; here "nothing" means *no requirement*, and that
answer is **allow**. So only a genuinely empty value counts as no record. A
negative number, a word, an array or anything else is invalid, and invalid fails
closed.

## G2 — MATERIAL. A malformed value conjured a seat.

**Proved.** `rexec_seats($rq, $except)` cast its "don't count this candidate"
argument. An array became candidate #1; because candidate #1 held a seat on that
requirement, the seat was released:

```
G2 · both seats are taken
G2 · seats with an ARRAY "except" id: remaining=1
```

Of everywhere the coercion appeared, this is the one that mattered most: every
other bad value produced a **refusal**, and this one produced **capacity** — a
free seat on a requirement that had none, which is precisely what the ceiling
exists to prevent.

**Fix.** The same validation. A value that is not an id releases nothing.

## G3 — MATERIAL. A refused operation told the person it had succeeded.

**Proved.** The interview route ran `iv_schedule($id, $_POST); flash('Interview
scheduled.');` and the offer route `offer_create($id, $_POST); flash('Offer
drafted.');` — both discarding the result. Once M6's gate began refusing, a
coordinator working on a blocked requirement was told the interview was booked and
the offer drafted **while nothing had been written**.

This is worse than the gap it replaced. An unblocked write at least leaves a
record; a false success leaves the person acting on a meeting that does not exist
and a promise that was never made. It is M6's own invariant **I18** — *audit
history never claims an operation that did not happen* — failing at the one place
a human actually reads.

**Fix.** Both routes keep the result, branch on it, and show the gate's own reason
for refusing.

## G7 — MATERIAL. Two approvers, one request, two recorded decisions.

Found not by a probe I wrote for it but by **my own suite failing intermittently**
— two runs in five on MariaDB, never on SQLite:

```
C5.1 · *** only one decision is recorded ***  (want 1, got 2)
```

`hreq_apply_decision()` checks the request's state and then writes with
`WHERE id=?`. Check-then-write is not atomic, so two approvers deciding the same
re-approval at the same moment both passed the gate, **both wrote, and both were
told their decision was recorded** — leaving one request carrying two
contradictory decisions in its audit trail, and the final state decided by
whichever process happened to write last. SQLite hid it by serialising writers.

**"Flake" is not a root cause.** An intermittent failure in a race test is the
test doing its job: a race is a probabilistic detector, and an intermittent red is
what a real race looks like.

**Fix.** The decision is now a compare-and-swap: the state the gate checked is
added to the WHERE, so only the process that still finds it there may write, and
the loser is told plainly and writes nothing — no state, no audit, no claim. Six
consecutive MariaDB runs clean afterwards, and pinned deterministically by **L11**
(both writes carry their state, both check that they matched a row, and the refused
decision adds nothing to the audit trail).

## G8 — MATERIAL. My own fix, not carried to its caller.

While pinning G2, the new probe caught something the attack had not: the gate
**cast the candidate id before handing it to the function that validates it**:

```php
$seats = rexec_seats($rq, (int) $candidateId);      // the helper validates; the caller already destroyed the evidence
```

So `"57x"` — malformed — arrived at the validation as the clean integer 57 and
released seat-holder 57's seat. The helper had been fixed and the caller had not,
which is the defect family this phase is named for, appearing **inside the repair
for the same defect**. The cast is gone; `rexec_seats()` validates what it is
actually given.

## What held under this pass

- **G4** — an offer already issued cannot be **accepted** into a seat somebody
  else took in the meantime: *"All 1 approved position on this requirement has
  already been filled."*
- **G5** — the compensating revert puts a candidate back to a real, non-terminal
  stage even when the prior stage is unknown, and leaves exactly the approved
  number of seats filled.
- **G6** — a requirement cut below the people already in it never reports free
  seats, and accepts no further joining.

## Probe defects of my own in this pass, disclosed

1. **The M6 security suite accepted "allowed" as a pass** for a malformed
   requirement id (`$r === ''` was one of its permitted answers). That is the
   dangerous answer, and the suite was written to tolerate it. It now demands a
   refusal.
2. **`'007'` was listed as an attack.** It is the number seven written oddly and
   must resolve to seven; demanding a refusal would have been demanding the wrong
   answer. Moved out of the malformed list and asserted positively.
3. **Two G3 assertions encoded the old code's shape** (the absence of two
   substrings) and went stale the moment the defect was fixed — the success branch
   legitimately still says *"Interview scheduled"*. Both now assert that the route
   keeps the result and branches on it.

## Verification after the fixes

The full attack battery, re-run against the fixed tree: **31 / 31**.
The concurrency suite, six consecutive MariaDB runs: **21 / 21** each time.
Complete regression: **SQLite 11151 / 0 · MariaDB 11152 / 0**.
