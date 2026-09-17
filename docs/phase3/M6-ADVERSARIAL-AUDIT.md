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
