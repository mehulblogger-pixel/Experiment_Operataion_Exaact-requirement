# Phase 6 · Batch 2 — Security results

*Every attack the owner's gate requires, what it did, and what the database said
afterwards.*

**Rule throughout:** *a return code is never evidence.* Every verdict below is
read back from the database after the attack.

---

## 1. Direct route · forged POST

The real `/candidate-stage` route, dispatched in its own OS process with a forged
`make_inspector` POST:

| Attack | Result |
|---|---|
| Convert an application the actor may not open (wrong business unit) | **Refused**, nothing created (`D1 · D2`) |
| Convert on a **cancelled** requirement | **Refused by M4/M6 first**, nothing created (`D3 · D4`) |
| Convert an application **already converted** | Refused; the original relationship untouched (`A5–A7`) |
| A **stale browser** re-posting after somebody else converted | Creates nothing (`A8`) |

## 2. Direct internal function

The conversion is a function now, so it was attacked as one — not only through a
route (invariant **I27**):

| Attack | Result |
|---|---|
| `rcv_convert()` called by field staff (no recruitment right) | **Refused**, and **no team member created** — asserted on row counts, not on the return value (`A14–A16`) |
| `rcv_convert()` on an out-of-scope application | Refused with the **same words** as "no such application", so the refusal cannot be used to enumerate other people's records (`D1`) |

## 3. Real-process concurrency

**Three separate OS processes, independent connections, synchronised on one
wall-clock microsecond**, all converting the same application:

```
team members created ......... 1     (A1)
processes reporting success .. 1     (A10)
orphans left behind .......... 0     (A3)
false successes .............. 0     (A9)
```

**`A9` is the assertion that matters most here.** Every process that said it
converted must name a team member that really exists and really is this
application's. A loser told "done" is worse than a loser told "you lost": the
screen then shows a hire nobody has.

The same race with the **marketplace add-on switched off** still produces exactly
one (`C10`) — the protection does not depend on an entitlement the customer may
not hold.

### What actually stops it

```
BEGIN
  A  INSERT inspectors
  B  UPDATE candidates SET inspector_id=? WHERE id=? AND inspector_id IS NULL
     └─ 0 rows → another process won → ROLLBACK
  C  INSERT cx_identity_link   (U4: one live conversion per application)
     └─ constraint violation   → ROLLBACK
COMMIT
```

Write **B** is a conditional update, so the database — not a prior `SELECT` —
decides the winner: the second transaction blocks on the row lock, then matches
nothing once the first commits. **U4** is the second line of defence on the
ledger. Neither is `SELECT → check → INSERT`.

## 4. Transaction integrity — no false success, no false failure

| Attack | Result |
|---|---|
| A conversion that committed | reports **success**, and the record it names is really there (`A12–A13`) |
| A conversion that rolled back | reports **failure**, and nothing is in the database (`A1–A3`) |
| A failure **inside a caller's transaction** | the failure **reaches the caller** (`A17`), the caller's transaction is still its own to unwind (`A18`), and after it rolls back **nothing was committed** (`A19–A20`) |

> The last row is there because the first implementation got it wrong, and the
> adversarial pass caught it: a reported *failure* left a committed orphan for
> the caller to commit. See `P6-BATCH2-ADVERSARIAL-AUDIT.md`, Finding A.

## 5. Branch — the attack of the silent default (BD1)

| Attack | Result |
|---|---|
| Convert with a requirement branch | takes the **requirement's** branch (`B2`) |
| Convert with no requirement branch | falls back to the **recruiter's** (`B4`) |
| Convert with **no branch anywhere** | **REFUSED** — no team member, no partial relationship, and the reason is named (`B5–B8`) |
| Any converted team member in Ahmedabad by default | **none** (`B9`) |

The platform's generic *"no office means Ahmedabad"* rule is right for reading a
register and wrong for creating a person. It filed every hire under a branch
nobody chose. **Mutant M7 restores it, and `B9` kills it.**

## 6. Entitlement — and the attack of the hidden dependency (BD2)

| Attack | Result |
|---|---|
| Convert with the marketplace add-on **off** | **succeeds** — recruitment is not held hostage (`C4–C5`) |
| Does it claim an identity link it does not have? | **No** — it reports `NOT_ENTITLED` (`C6`) and writes **no ledger row** (`C7`) |
| Is the state recoverable? | **Yes** — reported as `CONVERTED_NO_LEDGER`, linkable later through the authorised path (`C8–C9`) |
| Is the race still safe without it? | **Yes** (`C10`) |

**Mutant M11** makes the marketplace a requirement; **M12** writes a false ledger
row when it is unavailable. Both are caught.

## 7. Tenant isolation

Unchanged from Batch 1 and still enforced: `db()` is this tenant's database and
no identity path can reach another. The conversion adds no cross-tenant surface —
it reads the application, the requirement and the recruiter through the same
connection. Batch 1's two-tenant probe (`E0–E5` there) continues to pass in the
full regression on both engines.

## 8. Structural — the second-writer attack

Batch 1 was defeated once by a file that wrote the identity ledger directly. The
same attack was run against the conversion:

| | |
|---|---|
| **W1** | **no file outside the recruitment layer writes `candidates.inspector_id` — seeds included.** A seed may create namespaced demo *team members*; writing the conversion is performing a hire, and a hire written straight to the database skips the transaction, the branch rule, the ledger and the audit |
| **W2** | the conversion is a function, so every gate can be attacked directly |
| **W3** | the raw conversion INSERT is gone from the route |

**Mutant M15** re-introduces a seed writer; **M16** adds a raw writer to an
unrelated library. Both are caught by `W1`.

## 9. The one repair, and its limits

The only data repair in this batch is the person group the old linker split
(owner decision BD4). It was attacked as a merge engine:

| Attack | Result |
|---|---|
| Two intact, unrelated groups, **live** repair run | both **untouched** (`G8b–G8d`) |
| Two records that **share a mobile and an e-mail**, never linked | **not merged** (`G8e`) |
| A dry run | changes nothing (`G7`) |
| Every repair | writes an audit entry (`G4`) |

**Mutant M13 makes the repair reach for a shared e-mail when the recorded
evidence is absent. `G8e` kills it.** That is the line between restoring the
system's own record and inventing a person decision.

---

## Result

| Attack class | Verdict |
|---|---|
| Direct route · forged POST | **held** |
| Direct internal function | **held** |
| Real-process concurrency (with and without the add-on) | **held** — one hire, truthful answers |
| No false success / no false failure | **held**, including inside a caller's transaction |
| Silent branch default | **held** — refusal, never Ahmedabad |
| Hidden marketplace dependency | **held** — recruitment stands alone, and says which state it is in |
| Second writer | **held** — seeds included |
| Repair-as-merge | **held** — never fuzzy, never silent |

**Engines:** every probe ran on **SQLite 3.45.1** and **MariaDB 10.11.14**.

**Not claimed:** general staff uniqueness (**R20**, open by decision BD3), repair
of the contradictory states other than the split group, reversal of a completed
hire (**R21**), and any answer to **Q1–Q18**.
