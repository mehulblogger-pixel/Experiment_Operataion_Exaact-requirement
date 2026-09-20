# RB-3 · Step 3 — transaction-boundary investigation
## STOP REPORT — three items need an owner decision before any code

*No code was written. The instruction was: all refusable checks before the
transaction; every acceptance-related persistent write inside it **wherever
technically possible**; and **if any required acceptance write cannot
participate, STOP and report it before coding**. One cannot, one must not, and
one can only if something is built first.*

**Baseline:** `5178d69` (RB-3 Step 2 approved). Working tree clean, no changes.

---

## 1. The complete list of persistent writes on the acceptance path

Traced from the route at `lib/ops.php:5690` through to the end of the hire.

| # | Write | Where | Same database? | Can it join one transaction? |
|---|---|---|---|---|
| 1 | `UPDATE candidates SET stage, decided_at, drop_point, drop_reason` | `ops.php:5728` | yes | **yes** |
| 2 | KPI stage ledger — `INSERT INTO candidate_events` | `recruit_kpi.php:90` | yes | **yes**, once its migration is pre-warmed (§4) |
| 3 | `INSERT INTO inspectors` — the workforce record | `recruit.php:1332` | yes | **yes** (already is) |
| 4 | employee-number allocation | inside #3, via `emp_code_claim()` | yes | **yes** — it is part of that INSERT, and a rolled-back hire consumes no number |
| 5 | `UPDATE candidates SET inspector_id` | `recruit.php:1345` | yes | **yes** (already is) |
| 6 | `INSERT INTO cx_identity_link` | `recruit.php:1357` | yes | **yes** (already is) |
| 7 | `UPDATE requisitions SET hired_inspector_id` | `ops.php:5790` | yes | **yes** |
| 8 | `reqf_sync()` → `UPDATE requisitions SET status` | `reqfulfil.php:153` | yes | **yes**, once its migration is pre-warmed |
| 9 | P4 source credit — `UPDATE candidates SET allocation_id=NULL` | `recruit_fulfil.php:803` | yes | **yes**, once its migration is pre-warmed |
| **10** | **audit — `act_log()` via `rcv_log()`** | `recruit.php`, `activity.php:467` | yes | **technically yes — but it must not.** See **§2** |
| **11** | **M6 compensating revert — `UPDATE candidates SET stage` back, plus a REVERT ledger row** | `recruit_exec.php:305`, `:317` | yes | **NO — it is designed to run after the commit.** See **§3** |

**Nothing on this path writes to another database.** `licence_blocks()`
(`licence.php:337`) is read-only, and there is no e-mail, outbox or registry
write on the acceptance path. So there is no cross-database write to worry about
— the only obstacles are the three below.

---

## 2. ITEM 1 — the audit trail conflicts with a LOCKED invariant

**Technically it can join the transaction.** It is an ordinary INSERT into
`activities` in the same database.

**But locked invariant I41 says it must not:**

> **I41 — A failed observation is never a failed transaction.** *Force an
> audit/ledger write to fail; assert the business action still stands and the
> failure is visible.*

Putting the audit inside means **a failed audit write rolls back a completed,
valid hire**. The person was really hired, the number was really issued, and the
whole thing is undone because we could not write a note about it.

`rkpi_stage_log()` carries the same rule in its own comment: *"A ledger write
must never lose a business action that already succeeded."*

> **OWNER DECISION REQUIRED — 1**
>
> **(a) Keep the audit OUTSIDE the transaction.** I41 stands. The audit is the
> one acceptance-related write that does not participate. **Consequence:** a hire
> can in principle commit with no audit row — which is today's behaviour and is
> exactly what I41 was written to allow.
>
> **(b) Bring it INSIDE.** "The hire is only real if it is recorded." I41 must
> then be amended for this path, and a database hiccup while writing a note can
> cost a recruiter a completed hire.
>
> **(c) Outside, but never silent.** Keep I41, and make a failed audit write a
> reported reconciliation finding rather than a swallowed `return false`, so the
> gap is visible instead of invisible.
>
> **My recommendation: (c).** It honours I41, keeps the hire safe, and removes
> the thing that actually makes (a) uncomfortable — that the failure is silent
> today. It is also a small, contained change.

---

## 3. ITEM 2 — M6's seat ceiling would leave a team member behind. **This is the blocker.**

M6 (`rexec_join_enforce_after_write()`, `recruit_exec.php:264`) is the approved
seat ceiling. It is a **compensating** check: check-then-write is not atomic, so
two recruiters can both pass the pre-check, and the loser is put **back** after
the write by an `UPDATE candidates SET stage=<previous>` plus a REVERT ledger row.

**Today that ordering protects us.** The revert runs *before* the conversion and
`redirect()`s out, so a reverted joining never reaches the workforce creation at
all.

**Move the conversion inside the transaction and that protection is gone.** The
order becomes:

```
BEGIN … stage=ACCEPTED · create team member · issue number · link … COMMIT
then M6 sees the seat was taken and UPDATEs the stage BACK
```

The revert only undoes the **stage**. It does not — and must not — delete a
person. The result is a **new contradictory state that does not exist today**:

> a candidate sitting at OFFER, with `inspector_id` set and a real team member,
> holding a permanent employee number, for a hire that was undone.

That is the precise condition your two locked invariants exist to prevent, and
the naive reading of "put everything in one transaction" *creates* it.

**So acceptance cannot simply be wrapped. The seat ceiling has to be decided
inside the same transaction, or the transaction is not actually atomic.**

> **OWNER DECISION REQUIRED — 2**
>
> **(a) Move the seat ceiling INSIDE the transaction.** Lock the requisition row
> first (`SELECT … FOR UPDATE`), count the filled seats under that lock, and
> refuse there. Two recruiters then queue on the requisition instead of both
> passing and one being undone, and the compensating revert becomes unnecessary
> **on this path only** — it stays exactly as it is for every other stage move.
> **This changes M6, which is locked and authoritative, so it needs your word.**
> Lock ordering would be: **requisition row → candidate row → workforce insert**,
> single direction, documented and tested. *(Step 1 already showed what
> inconsistent lock ordering costs: a deadlock in 3 runs out of 3.)*
>
> **(b) Leave M6 as it is.** Acceptance is then atomic only until M6 reverts it a
> moment later, and the contradictory state above becomes reachable. **I do not
> recommend this.**
>
> **(c) Let the revert also undo the workforce record.** This means deleting a
> person and releasing — or worse, orphaning — a permanent employee number.
> **Against your Decision 1 and against "no destructive cleanup". I do not
> recommend this.**
>
> **My recommendation: (a).**

---

## 4. ITEM 3 — migrations cannot run inside the transaction (solvable, but it must be built)

**MariaDB commits implicitly on any DDL.** A migration firing inside the
acceptance transaction would **silently commit a half-finished acceptance** — the
exact defect Batch 3 was created to remove.

Eight migrations are reachable from the acceptance path, and every one of them
runs DDL on a process whose epoch marker is not yet warm — that is, **the first
acceptance after any restart or deploy**:

| | |
|---|---|
| `rkpi_migrate()` | called by `rkpi_stage_log()` — write #2 |
| `reqf_migrate()` | called by `reqf_sync()` — write #8 |
| `rful_migrate()` | called by `rful_enforce_candidate()` — write #9 |
| `asg_migrate()` · `person_migrate()` · `connect_identity_migrate()` · `emp_code_migrate()` · `act_migrate()` | reachable from the conversion and the audit |

**This is not a blocker and needs no decision** — every one must simply be called
**before `BEGIN`**, and the transaction must assert it is not running DDL. But it
means the single transaction has a **mandatory pre-warm step**, and that step has
to be tested on a genuinely cold process, not a warm one. A test that pre-warms
and then measures nothing is the trap that produced eleven defective instruments
in Batch 3.

---

## 5. What I am NOT asking about

These are settled and need no further input: the refusable checks (branch,
capability, `team_role`, workforce match, employee number) all move **before**
`BEGIN`, exactly as you directed. `rcv_convert()`'s borrowed-transaction contract
already does the right thing — it joins a caller's transaction, never commits or
rolls back what it does not own, and **re-throws** on failure so the caller
unwinds. Writes #1, #3–#9 all participate.

---

## 6. Summary

| | Item | Status |
|---|---|---|
| **1** | audit vs locked invariant **I41** | **decision required** — recommend (c), outside but never silent |
| **2** | **M6's compensating revert leaves a team member behind** | **BLOCKER — decision required**, recommend (a), seat ceiling inside the transaction with a documented lock order |
| **3** | migrations must be pre-warmed | no decision needed; must be built and tested cold |

**Nothing is implemented. No production file has been touched.**
Coding starts when items 1 and 2 are answered.
