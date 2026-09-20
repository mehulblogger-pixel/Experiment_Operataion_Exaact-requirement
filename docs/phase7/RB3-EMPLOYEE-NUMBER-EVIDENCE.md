# RB-3 · Step 1 — the employee number
## Implementation and evidence

**Owner decision 1 (2026-09-20):** *"An employee number must be permanently
unique and never re-issued to another person, even after the employee leaves."*

**Baseline:** `b2338df`. **Engines:** MariaDB 10.11.14 (authoritative) ·
SQLite 3.45.1 (supplementary).

---

## 1. In plain words

Before this change, four recruiters accepting four different people at the same
moment gave all four **the same employee number**, and all four screens said the
hire had succeeded. The table holding your staff had **no protection of any kind**
— not on the employee number, not on e-mail, not on mobile.

It now has one, and the database is the thing enforcing it, not a check in the
code that a second process can run straight past.

**The acceptance test:** four real processes, four different people, one
wall-clock microsecond, on MariaDB.

| | Employee numbers issued |
|---|---|
| **Before** | `EMP01 · EMP01 · EMP01 · EMP01` — 4 of 4 collided |
| **After** | `EMP03 · EMP04 · EMP05 · EMP06` — all four different |

---

## 2. What was built

| | Change | File |
|---|---|---|
| **1** | `EMP_CODE_KEY_EXPR` — the rule, written once so the database's key and any check in the code cannot drift apart | `lib/ops.php` |
| **2** | `emp_code_migrate()` — installs a **generated** key column the database computes, plus a unique index, through the existing `ensure_unique_generated_index()` | `lib/ops.php` |
| **3** | `emp_code_claim()` — the number is **claimed**, not guessed: if the database says somebody took it a microsecond ago, the next one is claimed and the write runs again | `lib/ops.php` |
| **4** | `emp_code_is_taken()` — deliberately narrow: only **our** key. A duplicate on any other unique key is re-thrown, never retried | `lib/ops.php` |
| **5** | `emp_code_taken_by()` — who holds a number, so the form refuses in a sentence rather than with a stack trace | `lib/ops.php` |
| **6** | `emp_code_collisions()` + two new finding kinds in the existing report | `lib/ops.php`, `lib/connect_identity.php` |
| **7** | The three production creators now claim: `rcv_convert()`, `team_member_create()`, the Inspector form | `lib/recruit.php`, `lib/ops.php` |
| **8** | Lock ordering in `rcv_convert()` — see §4 | `lib/recruit.php` |
| **9** | The demo seed is idempotent by employee number — see §5 | `lib/seed_demo.php` |

**Reused, not rebuilt:** `ensure_unique_generated_index()` and the `schema_guards`
ledger (Batch 3 corrective) · `identity_state_findings()` (Batch 2) · `act_log()`.
**No new engine, no new table, no new permission, no new status, no new route.**

### The rule

```sql
CASE WHEN TRIM(COALESCE(emp_code,'')) <> ''
     THEN UPPER(TRIM(emp_code)) ELSE NULL END
```

**No "is this person still employed" test.** Decision 1 is lifetime uniqueness, so
a retired person's number is constrained exactly like a live one — which is also
the simpler rule, because there is no liveness condition to get wrong.
`UPPER(TRIM(…))` so `emp01`, `EMP01 ` and `EMP01` cannot coexist. `NULL` for a
blank code, so the many historical rows that never had one stay unconstrained.

---

## 3. One assumption was measured before any code was written

The claim retries inside a transaction that `rcv_convert()` has already opened.
That is only safe if a unique-key rejection rolls back the **statement** and not
the **transaction** — so it was tested rather than believed:

```
=== SQLite  ===  still inTransaction() after the rejection : true
                 retry in the SAME transaction succeeded   : true
                 VERDICT : statement-level only — retry is SAFE
=== MariaDB ===  still inTransaction() after the rejection : true
                 retry in the SAME transaction succeeded   : true
                 VERDICT : statement-level only — retry is SAFE
```

Because nothing is reserved outside the row itself, **a rolled-back acceptance
consumes no employee number** — owner decision 5, proved by probe `C8`.

---

## 4. A defect this change introduced, found and fixed

**It must be recorded, because the first version of this work made things worse
in one specific case.**

With the employee number now protected by a unique index, four processes
converting **one** application contended for two resources — the application row
and the same employee-number key, which they all compute at the same instant —
and took them in whatever order they arrived in. InnoDB found a cycle and killed
one transaction outright.

```
round 1 : RACE_LOST CONVERTED RACE_LOST FAILED
   · Conversion rolled back — SQLSTATE[40001]: Deadlock found when trying to get lock
round 2 : RACE_LOST RACE_LOST CONVERTED FAILED
round 3 : FAILED CONVERTED RACE_LOST RACE_LOST
```

**Three rounds out of three.** Before the change the same scenario gave three
clean `RACE_LOST` refusals, so this was a regression, and the recruiter saw a
bare "could not be completed" instead of the truth.

**The fix is lock ordering, not relabelling.** `rcv_convert()` now takes the
application's row **first** (`SELECT … FOR UPDATE` on MySQL; SQLite serialises
writers already and needs none). Everybody queues on the same lock in the same
order, so there is no cycle to find — and the three who lose discover it before
creating anything, which is also three team members and three employee numbers
no longer made only to be thrown away.

```
round 1 : RACE_LOST CONVERTED RACE_LOST RACE_LOST
round 2 : CONVERTED RACE_LOST RACE_LOST RACE_LOST
round 3 : RACE_LOST RACE_LOST CONVERTED RACE_LOST
```

A new refusal, `BUSY` — *"Somebody else was saving at the same moment, so nothing
was changed. Please try again."* — now covers a deadlock or lock-wait timeout if
one ever occurs, because that aborts the whole transaction and no retry inside it
can help. It is not a defect in the hire and must not read like one.

---

## 5. A second defect this change introduced, found and fixed

The full regression caught it; the isolated test did not.

The demo pack hardcodes `EMP01`, `EMP02`, `EMP03` — **exactly the numbers a
workspace's first real hires are issued**. With the rule in force, loading the
demo into such a workspace was refused, and because the whole seed is one
transaction it took the offices, the clients and everything else down with it,
**silently**. The visible symptom was a test three files later reporting zero
Ahmedabad offices.

The demo seed now **reuses** the row that already holds the number instead of
inserting a second one — the same lesson the offices in that same function had
already learned, and now for a stronger reason.

---

## 6. Evidence

### Regression — both engines, no skips

| Engine | Result |
|---|---|
| **SQLite 3.45.1** | **12,891 passed · 0 failed** |
| **MariaDB 10.11.14** (authoritative) | **12,894 passed · 0 failed** |

`php tools/make_deploy_check.php` re-run; the shipped-code checksum matches.

### `tests/test_rb3_emp_code.php` — 57 assertions, both engines

| Group | What it establishes |
|---|---|
| **A1–A13** | the key and index really exist (asked of the schema, not of "CREATE did not throw") · a raw duplicate `INSERT` is refused **with no application code in the path** · a **leading**-space duplicate is refused · a lower-case duplicate is refused · **a leaver's number cannot be re-issued** · blank codes stay unconstrained |
| **C1–C10** | the generator can be asked for the next free number · a hire whose number is taken still succeeds, with a different one · **a rolled-back hire frees its number** · a duplicate on somebody else's key is re-thrown, never retried |
| **X1** | **the acceptance test** — four people, one microsecond, four different numbers |
| **X2** | Batch 2's guarantee intact — one application, four processes, one team member, no orphan, three `RACE_LOST` |
| **B1–B11** | an install that already carries a collision **boots**, records `DIRTY`, does **not** build the index, **renumbers nothing**, reports the clash, and installs itself once a person has resolved it |
| **X3** | three processes installing the rule at once all succeed, and at least one genuinely saw the index missing |
| **X7** | a typed number that somebody holds is refused in a sentence, names who, says if they have left, and does not clash with itself on edit |
| **J1–J4** | the register, the allocate picker, the identity ledger's own keys and `team_role` all behave as before |

### Why the leading space, and why MariaDB decides

**MySQL ignores trailing spaces when comparing strings**, so a trailing-space
probe proves nothing on the production engine. Every whitespace probe here uses a
**leading** space.

**SQLite cannot stage the race at all.** It takes one database-wide write lock and
sets no busy timeout, so the second, third and fourth writers are refused before
they reach the employee number. One winner and three refusals is SQLite being
SQLite — and a one-of-one "all distinct" would be a **vacuous pass**. The test
says so in its own name, runs the four hires sequentially there instead, and the
race itself is proved on MariaDB.

*The instrument caught this: the first version of X1 passed on SQLite with a
single number. The guard assertion `X1c` failed and exposed it.*

---

## 7. Findings recorded, not fixed

| | Finding | Why not now |
|---|---|---|
| **F1** | **No SQLite busy timeout is configured anywhere.** Two simultaneous writers mean one is refused outright with "database is locked" | Pre-existing, and production is MariaDB. Changing it affects every write path in the application and belongs in its own change, not inside RB-3 |
| **F2** | **The demo unload deletes by employee number** (`DELETE FROM inspectors WHERE emp_code IN ('EMP01',…)`), so unloading the demo from a workspace whose real staff hold those numbers would delete real people | Pre-existing and independent of this change. Fixing it properly needs a decision about how demo data marks its own rows — an owner decision, not an inference |

---

## 8. Mutation — 11 of 11 caught, no survivors

Fresh copy of the whole repository and a fresh database per mutant. **FATAL is
not a catch. ANCHOR-MISS is not a catch. A dirty baseline aborts the run.**
Baseline verified clean on all three configurations first: SQLite targeted
57/0 · SQLite full 12,891/0 · MariaDB targeted 57/0.

| | Mutant | Result | Killed by |
|---|---|---|---|
| **M1** | the unique index is never created | **CAUGHT** | A4, A5, A7 (12 assertions) |
| **M2** | the key stops folding case | **CAUGHT** | A9, B11 |
| **M3** | the key stops trimming whitespace | **CAUGHT** | A8, C5, B11 |
| **M4** | blank employee codes are pulled into the key | **CAUGHT** | A13 |
| **M5** | uniqueness restricted to live rows — **decision 1 reversed** | **CAUGHT** | A12, B11 |
| **M6** | a `DIRTY` install builds the index anyway | **CAUGHT** | B3, B5 |
| **M8** | the generator reverts to read-max | **CAUGHT** | C2, C4, C5 (10 assertions) |
| **M9** | the claim gives up instead of retrying | **CAUGHT** | C4, C5, C6 (9 assertions) |
| **M10** | the lock ordering is removed — the deadlock returns | **CAUGHT** (MariaDB) | X2d |
| **M11** | any duplicate is treated as ours | **CAUGHT** | C10 |
| **M12** | the demo seed reverts to its hardcoded numbers | **CAUGHT** (full suite) | the AMD-office assertion |

**CAUGHT 11 · SURVIVED 0 · FATAL 0 · ANCHOR-MISS 0.**

### Two defective instruments the battery exposed — published, not quietly repaired

**D1 · `C3`–`C5` proved nothing.** The first version let somebody take the number
the generator was about to issue, then asserted the next hire got a different
one. It passed — and tested nothing, because `next_emp_code()` re-reads the
highest code every time, so it had already moved past the squatter on its own and
**the retry never ran**. Mutant **M9** (`$tries = 1`, never retry) survived it
untouched.

Reaching the retry needs a squatter the generator **cannot see** but the database
**can**. A **leading space** does both: `' EMP07'` does not match the generator's
`emp_code LIKE 'EMP%'` scan, and the key normalises it to `EMP07`. New assertion
`C3b` now proves the generator still offers the taken number, so the first attempt
*must* be refused — the probe states it has a subject before it states a result.

**D2 · a test that could only die, not fail.** With M8 and M9 applied, `C4` and
`C5` correctly failed — and then an **unguarded** `team_member_create()` at `C6`
threw and killed the run before the result line. The harness read that as
**FATAL**, which is not a catch. `C6` is now guarded, and both mutants are caught
properly. *A test must be able to fail, not only to die.*

**D3 · the harness itself was wrong once.** The first battery copied only
`phpapp/`, but `test_books_receiver.php` requires a sibling directory outside it,
so the full-suite mutant died for a reason that had nothing to do with the
mutation. The harness now copies the whole repository. The FATAL verdict it
produced was **not** evidence about M12 and was not counted as one.

---

## 9. What is NOT done yet

This is **step 1 of RB-3** and nothing beyond it was touched.

- **Not done:** the applicant-vs-workforce match and its acknowledgement tick
  (decision 2) — RB-3 step 2.
- **Not done:** the atomic acceptance flow, `team_role` capture, workspace
  classification (decisions 3, 4, 5) — RB-1.
- **Not done:** the fulfilment status correction — RB-2.
- **Not done:** the mutation battery.
- **Unchanged:** the hidden workforce checkbox still exists; `rcv_convert()` still
  does not set `team_role`; every recruited hire is still silently `FIELD`.

**No new workforce table · no Person Hub · no historical employee number
rewritten · no destructive cleanup of existing team members · no new permission,
status, transition, engine or route.**
