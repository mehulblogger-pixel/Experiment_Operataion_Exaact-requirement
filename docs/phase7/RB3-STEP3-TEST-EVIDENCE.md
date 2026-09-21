# RB-3 · Step 3 — acceptance is one transaction
## Implementation and evidence

**Working commit (before mutation testing):** `938c279`
**Engines:** MariaDB 10.11.14 (authoritative) · SQLite 3.45.1 (supplementary)

---

## 1. What changed

Accepting a candidate is now one transaction: the stage move, the KPI stage
ledger, the workforce record, the employee number, the requirement's standing
and the source credit either all happen or none of them do. Every refusable
question is asked before it opens.

**The defect this removes.** The seat ceiling used to be a *compensating* check —
two recruiters both passed it and the loser was put back afterwards. That was
only safe because the revert ran before the workforce record was created.
Wrapping acceptance in a transaction without touching it would have destroyed
that ordering and left the loser holding a real team member and a permanent
employee number for a hire that had been undone: **worse than the problem being
fixed.** The seat is now decided inside the transaction, under a lock on the
requirement, and the second recruiter is stopped at the save.

**Lock order:** requirement → candidate → workforce insert. One direction
everywhere. Step 1 paid for that lesson with a deadlock in 3 runs out of 3.

**Migrations.** Eight are reachable from this path and each runs DDL on a cold
process, which MariaDB commits implicitly. They are warmed before `BEGIN`, and
the warm-up refuses to run inside a transaction.

**Audit.** Outside, per invariant I41 — a failed note must not undo a completed
hire. What changed is that a lost entry is now counted and reported instead of
silently swallowed.

---

## 2. Regression

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **13,025 passed · 0 failed** |
| **MariaDB 10.11.14** | **13,037 passed · 0 failed** |

No skips. `php tools/make_deploy_check.php` re-run.

`tests/test_rb3_step3_atomic.php` — **44 assertions on SQLite, 53 on MariaDB**
(the extra nine are the three overlap probes that only the production engine can
stage).

## 3. Mutation — 9 of 9 caught

Whole-repository copy and a fresh database per mutant. **FATAL is not a catch.
ANCHOR-MISS is not a catch. A dirty baseline aborts.**

| | Mutant | Engine | Result | Killed by |
|---|---|---|---|---|
| **A1** | the lock on the requirement is removed | MariaDB | **CAUGHT** | T1 (10 assertions) |
| **A2** | the seat is no longer decided inside the transaction | MariaDB | **CAUGHT** | **T10** |
| **A9** | the seat check asks the wrong question | MariaDB | **CAUGHT** | **T10** |
| **A3** | there is no transaction at all | SQLite | **CAUGHT** | T1, T2 |
| **A4** | a failed acceptance commits instead of rolling back | SQLite | **CAUGHT** | T2, T8 |
| **A5** | a ledger this path cannot write no longer stops it | SQLite | **CAUGHT** | **T8** |
| **A6** | the migrations are no longer warmed before the transaction | MariaDB | **CAUGHT** | **T9** |
| **A7** | a refusal is no longer recorded | SQLite | **CAUGHT** | T2e |
| **A8** | the warm-up stops refusing to run inside a transaction | SQLite | **CAUGHT** | T5 |

**CAUGHT 9 · SURVIVED 0 · FATAL 0 · ANCHOR-MISS 0.**

---

## 4. The first battery left four survivors. What they actually were.

Reported openly at the time rather than presented as green. Each turned out to
be a different kind of mistake, and only one of them was the sort I expected.

### D1 · A2 and A9 — the protection was **doubled**, and my tests could not see mine

T1 races two real processes; T7 manufactures the overlap deterministically. Both
showed the loser being refused — so both passed, and both mutations survived.

The reason is that **`rcv_convert()` re-asks the same seat gate itself**
(`lib/recruit.php:1560`), and it runs inside the transaction too. Whenever a
conversion is requested the seat is checked twice, so deleting the check in the
route changed nothing either probe could observe.

The case that is **not** doubled is accepting somebody **without** creating a
workforce record — the hidden checkbox left unticked. There `rcv_convert` never
runs, and the check inside the transaction is the only thing between two
recruiters and one seat. Nothing exercised that. **T10** does, with the same
manufactured overlap, and both mutants die.

*This is the one that mattered. The gap was real, not cosmetic: on that path the
mutation produces two people in one approved seat.*

### D2 · A5 — nothing ever made the ledger fail

The owner named the KPI stage ledger as a write that must participate, which
means more than sitting inside the transaction: a ledger that **cannot** be
written must stop the acceptance. No probe made it fail.

**T8** takes the ledger table away *while an acceptance is in flight* — after the
child process has warmed its migrations, so the route's own warm-up cannot
quietly put it back — and asserts the acceptance does not happen. Restored
afterwards by renaming rather than dropping, so other tests' rows come back
untouched.

### D3 · A6 — my probe tested a **position**, not an effect

T5 proved the warm-up call exists and sits before the transaction. Disabling it
(`if (false)`) left the call text exactly where it was, so T5 passed regardless.

**T9** tests the effect: a cold process meets an absent ledger table and an
acceptance that will be refused *after* the ledger step. Done correctly the
migration runs before `BEGIN`; done wrongly it runs inside, MariaDB commits
implicitly, and the stage move survives a refusal that should have undone it.
The mutant leaves the candidate Accepted for a hire that was refused.

### D4 · …and A6 was also run on the wrong engine

Even after T9 existed, A6 still reported SURVIVED. T9 is MariaDB-only by design —
SQLite's DDL is transactional, so the implicit-commit hazard cannot occur there —
and **my harness was running A6 against SQLite**, where T9 skips itself.

A mutation run against an engine that cannot express the defect proves nothing.
The harness now runs A1, A2, A9 and A6 on MariaDB. *The bug was in the
instrument's instrument.*

---

## 5. Why several claims are proved only on MariaDB

**SQLite cannot stage a write race** — one database-wide lock, no busy timeout —
and **its DDL is transactional**, so the implicit-commit hazard does not exist
there. Asserting either on SQLite would be asserting the engine rather than the
behaviour. T7, T9 and T10 say so in their own names and skip deliberately;
MariaDB is where they run.

## 6. What is NOT done

RB-1's remaining items are untouched and deferred: `team_role` capture at the
requisition/position, workspace capability classification, and removal of the
hidden workforce checkbox. RB-2 is untouched. The Step 1 items (no SQLite busy
timeout; demo unload deleting by employee number) remain open.
