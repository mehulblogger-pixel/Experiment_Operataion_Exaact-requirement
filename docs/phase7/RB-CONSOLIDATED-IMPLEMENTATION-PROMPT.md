# RB-1 + RB-2 + RB-3 — Consolidated implementation prompt

*Written against the owner's locked decision set of 2026-09-20 (Q32 §21) and the
evidence in `docs/phase6/P6-BATCH2-PREIMPLEMENTATION-AUDIT-R2.md`. This is the
prompt. **Nothing in it is implemented.***

**Baseline:** `672cb17`. Working tree clean; `git status` over `phpapp/` empty.

---

## 0. Two structural consequences the owner should see before this is issued

Both were found by reading the shipped code after the decisions were locked.
Neither changes a decision; both change how much work a decision is.

### 0.1 `team_role` does not exist on the requisition or the position

Decision 3 places the choice at the requisition/position. **Neither table has the
column.** `grep team_role` over `lib/position.php`, `lib/recruit.php` and
`lib/reqfulfil.php` returns nothing.

`requisitions` (`lib/ops.php:247`) and `positions` (`lib/position.php:20`) both
need one additive, nullable column. This is small, but it is a schema change on
two masters rather than the zero-schema change Model D′ originally promised, and
it should not be discovered mid-implementation.

**`NULL` must mean "not chosen", never `FIELD`.** That is the whole point of
decision 3, and a `DEFAULT 'FIELD'` on either new column would reintroduce the
defect in a new place.

### 0.2 Decision 5 is not a message change — it restructures the stage-move route

Today, at `lib/ops.php:5690`, acceptance is **two committed operations in a fixed
order**:

```
1  M4 / M6 gates
2  UPDATE candidates SET stage='ACCEPTED'        ← COMMITS
3  rkpi_stage_log()                              ← the KPI stage ledger
4  M6 rexec_join_enforce_after_write()           ← COMPENSATING revert
5  P4 rful_enforce_candidate()
6  reqf_sync()
7  rcv_convert()                                 ← may fail; the stage move STANDS
```

Decision 5 says the candidate must remain at the previous stage when conversion
fails. That collides with two deliberate, documented designs:

- **Phase 5 ordering.** `rkpi_stage_log()` is called at step 3 *on purpose* —
  the comment records that logging after the compensating checks left a reverted
  move with **no ledger entry at all**.
- **M6's compensating pattern.** `rexec_join_enforce_after_write()` reverts
  *after* the write because check-then-write is not atomic. It assumes the write
  committed.

**The design that honours decision 5 without breaking either** is to move every
refusable question **in front of** the write, so the only failures left inside the
transaction are a lost race or a genuine database error:

```
1  M4 / M6 gates                                        unchanged
2  RESOLVE AND REFUSE — nothing written yet
     · branch (BD1)
     · workspace capability classification (decision 4)
     · team_role (decision 3)
     · workforce match acknowledgement (decision 2)
     · employee number reservable
   → any refusal: candidate UNCHANGED, inline actionable message, audited
3  BEGIN
     UPDATE candidates SET stage='ACCEPTED' …
     INSERT inspectors  (team_role, emp_code)
     UPDATE candidates SET inspector_id  … WHERE inspector_id IS NULL OR =0
     INSERT cx_identity_link              (when entitled — BD2)
   COMMIT
4  rkpi_stage_log()        ← now AFTER the commit
5  M6 compensating revert · P4 credit · reqf_sync()      unchanged
```

**This reverses Phase 5's deliberate ordering for this one path**, and that must
be an owner-visible change rather than a silent design choice. The justification:
once the move is inside the transaction, a rolled-back move **genuinely did not
happen** and needs no ledger entry — and the refusal is separately audited as
`IDENTITY_REFUSED`, so nothing goes unrecorded. Phase 5's guarantee ("a reverted
move still leaves a ledger entry") is preserved for M6's *compensating* revert at
step 5, which still happens after a committed write.

**Containment.** This ordering change applies **only** to `ACCEPTED` with
conversion. Every other stage move — shortlist, interview, offer, reject,
withdraw, hold — keeps today's behaviour byte for byte, and a test must prove it.

> **OWNER CONFIRMATION REQUIRED — 0.2.** The design above is sound but it moves a
> Phase 5 write. Confirm, or say the stage move must stay outside the transaction
> and decision 5 be met another way.

---

## 1. Objective

Make the two locked invariants true in the code:

> **ACCEPTED (Hired)** = a workforce record exists · the employee number is valid
> and permanently unique · the classification is explicitly known.

> **Inspector** = an Operations-enabled workspace **and** a person explicitly
> classified `FIELD`.

---

## 2. Scope — three requirements, one batch

| | Requirement | What it closes |
|---|---|---|
| **RB-1** | Conversion is unconditional, atomic and explicitly classified | the hidden checkbox · the silent `FIELD` default (**N3**) · non-atomic acceptance (decision 5) |
| **RB-2** | Requisition fulfilment reads one truth | `filled` and `joined` converge once conversion is unconditional |
| **RB-3** | Workforce duplicate protection | employee-number collisions (**N1**, **N2**) · no workforce-match question (**N4**) |

---

## 3. Out of scope — do not build these

- **No Person table, no Person Hub, no universal person key.** Q3 / Q7 / Q13 untouched.
- **No merging** of Candidate, Professional, Inspector, Employee or User.
- **No new workforce table**, no parallel workforce engine.
- **No uniqueness on e-mail, mobile or name.** Two live team members may be one
  human — a re-hire, or supply through two agencies. Detection only.
- **No automatic merge, no automatic repair, no automatic renumbering, no
  automatic unlink.** A human decides, always.
- **No new engine** — no duplicate engine, no identity engine, no audit engine,
  no approval engine, no KPI engine, no notification engine.
- **No new permission, no new status, no new lifecycle transition.** If one seems
  necessary, **stop and ask**.
- **No changes to** Hiring Request · Approval · Re-Approval · Requisition raising
  · Recruiter Assignment · Interview · Offer. M4's execution boundary and M5's
  recruiter accountability remain authoritative.
- **No changes to** scheduling · allocation · timesheet · equipment · reporting ·
  utilisation · any existing workforce screen.
- **No changes to** Marketplace identity, Phase 4 fulfilment, `cx_professionals`.
- **No answers to Q1–Q18.** No decision on "one person = one inspector".
- **No rewriting of historical records** to satisfy a constraint or a test.
- **The live-host workspace incident stays out.** No application change for it.

---

## 4. RB-1 — conversion

### 4.1 The hidden checkbox goes

`views/ops/candidate_detail.php:379` (`make_inspector`) and its gate at
`lib/ops.php:5775` are removed. Conversion is no longer a choice. **What replaces
it is not a checkbox but a classification** — the inherited `team_role`, shown
and confirmable.

### 4.2 Classification — Model D′, resolved before any write

```
LEVEL 1 — workspace
    connect_cap_modules(workspace) contains 'operations'?
      NO                → recruitment-only. Workforce record created.
                          Inspector concept does not apply. No role is asked.
      YES               → continue to Level 2
      NOT CONFIGURED    → decision 4. DO NOT INFER.
                          Refuse with NEEDS_CLASSIFICATION and say what to set.
                          Navigation behaviour elsewhere is UNCHANGED.

LEVEL 2 — the person
    team_role ← requisition.team_role, else position.team_role
      present  → shown to the recruiter, confirmable subject to permission
      absent   → refuse NO_TEAM_ROLE. NEVER default to FIELD.
```

### 4.3 The transaction

Per §0.2. Inside: the stage move · the inspector · the candidate link · the
ledger row (when entitled). Outside: `act_log()` (I41) · `rkpi_stage_log()` ·
M6's compensating revert · P4 credit · `reqf_sync()`.

**Borrowed transactions unchanged:** join, never commit or roll back, **re-throw**
on failure. A reported failure that commits a row is the defect Batch 2 removed.

### 4.4 Refusal codes — decision 5

Every one leaves the candidate **at the previous stage**, writes **nothing**, and
says what to correct.

| Code | Message shape |
|---|---|
| `NO_BRANCH` | *"Candidate could not be accepted. The workforce record needs a branch, and neither the requirement nor the recruiter has one. Set a branch on the requirement and try again."* |
| `NO_TEAM_ROLE` | *"… The requirement does not say what kind of workforce member this is. Set it on the requirement and try again."* |
| `NEEDS_CLASSIFICATION` | *"… This workspace has not said whether it runs Operations, so we cannot tell whether this person is an Inspector. Set the workspace capabilities and try again."* |
| `WORKFORCE_MATCH` | decision 2 — see §6.2 |
| `EMP_CODE_UNAVAILABLE` | *"… An employee number could not be issued. Try again; if it repeats, contact support."* |
| `RACE_LOST` | *"Somebody else accepted this application a moment ago."* |
| `BLOCKED` · `NOT_ALLOWED` · `ALREADY` | unchanged |

No partial record · no retry-created duplicate · no misleading success.

---

## 5. RB-2 — requisition fulfilment

`lib/reqfulfil.php:107–110` computes `filled` (stage ∈ `REQF_FILLED_STAGES`) and
`joined` (same, plus `inspector_id IS NOT NULL`). **`joined` is computed and never
used for status** (`reqf_derive_status()` reads `filled` only).

Once conversion is unconditional and atomic, `ACCEPTED` implies
`inspector_id IS NOT NULL`, so **the two converge by construction**.

| | Action |
|---|---|
| `filled` | **unchanged** — remains the status input |
| `joined` | **keep and keep computing it.** It is the reconciliation signal that catches a historical row where the two disagree. Removing it destroys the only detector of pre-decision data |
| `PARTIALLY_FILLED` | **already exists.** No new status value, no new transition |
| `reqf_derive_status()` | **no logic change** |

**RB-2 is therefore mostly a test and a report**, not a rewrite. Assert the
convergence; report the historical rows where it does not hold. **Do not modify
historical records to make them agree.**

---

## 6. RB-3 — workforce duplicate protection

### 6.1 The employee number — decision 1, lifetime and tenant-wide

Reuse `ensure_unique_generated_index()` (`lib/db.php:397`) — a **generated**
column the database computes, plus its unique index, recorded in `schema_guards`,
returning `OK` / `ABSENT` / `DIRTY` / `FAILED`. No new engine.

```
key = CASE WHEN TRIM(COALESCE(emp_code,'')) <> ''
           THEN UPPER(TRIM(emp_code)) ELSE NULL END
```

- **No "is this row live" predicate** — decision 1 is lifetime uniqueness, and
  this is the rule's whole simplification. Retired and inactive rows count.
- `NULL` for blank codes, so historical rows without a number are unconstrained.
- `UPPER(TRIM(…))` so `emp01`, `EMP01 ` and `EMP01` cannot coexist.
- Probe with a **LEADING** space. MySQL ignores trailing spaces in string
  comparison, so a trailing-space probe proves nothing.

**And a reserving generator.** `next_emp_code()` (`lib/ops.php:1546`) currently
reads the highest code and adds one, with no reservation — four concurrent hires
all received `EMP01`. It must reserve, and retry under the constraint. The index
is the backstop; the generator is what users experience.

**Dirty data is expected and must not fail a boot.** Report, leave the index off,
install on a later boot once a human has resolved it. **Nothing is renumbered
automatically** — renumbering a person is precisely the historical ambiguity
decision 1 exists to prevent.

### 6.2 The workforce match — decision 2

```
workforce_matches($cand) → [ { inspector_id, name, emp_code, email, confidence, basis } … ]
```

Reuses `cand_find_duplicates()`'s confidence scale so the desk sees one
vocabulary: same mobile **96** · same e-mail **94** · same first+last name **72**
· same surname + first initial **46**. Below 46 is not offered.

- **Live team members only.** A person who left is history, not a duplicate.
- **Scope-filtered.** Matches the actor may not open are **counted, never named** —
  a record id is never proof of authorisation.
- **No `LIMIT` ceiling, ever.** `cand_find_duplicates()` has one (`LIMIT 500`) and
  says nothing about it (**N5**); this must not repeat that.
- **It never links and never merges.** It returns a list.

**The gate:** matches found and not acknowledged → refuse `WORKFORCE_MATCH`,
nothing written, candidate unchanged, audited. The acknowledgement must name
**which** matches were shown, and a forged acknowledgement for matches never
shown must be refused. **The tick is an acknowledgement, not a merge.**

### 6.3 The report

A seventh finding kind in the existing `identity_state_findings()`
(`lib/connect_identity.php:690`) for shared employee numbers. **Extend the report;
do not build a second one.**

---

## 7. Database changes — the complete list

| | Change | Destructive? |
|---|---|---|
| 1 | `requisitions.team_role` — `VARCHAR(10) NULL`, **no default** | no |
| 2 | `positions.team_role` — `VARCHAR(10) NULL`, **no default** | no |
| 3 | one **generated** key column on `inspectors` over `emp_code` | no — derived |
| 4 | one **unique index** over it | no |
| 5 | one `schema_guards` row | no |

**No table created. No column dropped. No column changes type, width or
nullability. No row renumbered, merged or deleted.** `inspectors.team_role`
already exists and its `DEFAULT 'FIELD'` **stays** — existing rows depend on it.
What changes is that the conversion stops relying on it.

---

## 8. Migration

1. Epoch-guarded, via `ensure_unique_generated_index()`.
2. **MariaDB commits implicitly on any DDL** — the installation must never run
   inside a borrowed transaction. The guard refuses this; the test must prove it
   refuses rather than assume it.
3. `DIRTY` is the **expected** outcome on real installs — more so under decision 1,
   because retired rows now count. Report, do not fail the boot.
4. Concurrent boots must all succeed, none reporting failure.
5. `PRAGMA table_info` does **not** list generated columns; `table_xinfo` does.
   `table_columns_incl_generated()` already handles both engines.
6. **No back-fill changes a business value.** Blank employee codes stay blank.
7. `php tools/make_deploy_check.php` re-run after the final source change.

---

## 9. Test plan — `tests/test_rb_workforce.php`, both engines, no skips

| Group | Must show |
|---|---|
| **A · the key** | generated column present on both engines · unique index exists · `schema_guards` records it · raw duplicate `INSERT` refused **with no application code in the path** · **leading**-space duplicate refused · blank code unconstrained · **a retired person's number cannot be re-issued** (decision 1) |
| **B · dirty data** | a database seeded with a collision boots · index **not** installed · state `DIRTY` · collision **reported** · **nothing renumbered** |
| **C · the generator** | two calls with no insert between return **different** numbers · a collision is retried, not surrendered · a rolled-back acceptance does **not** burn a number |
| **D · classification** | no `operations` → workforce, no Inspector, no role asked · `operations` + role → honoured · **not configured → `NEEDS_CLASSIFICATION`, never inferred** (decision 4) · missing role → `NO_TEAM_ROLE`, **never silent `FIELD`** · navigation for an unconfigured workspace **unchanged** |
| **E · matches** | 96/94/72/46 exactly · below 46 not offered · a **left** employee is not a match · out-of-scope matches counted, never named · **no `LIMIT`** |
| **F · the tick** | unacknowledged → refused, nothing written, **candidate at the previous stage** · acknowledged → converts, audit names which matches were shown · **forged acknowledgement refused** · the tick **never links or merges** |
| **G · atomicity (decision 5)** | every refusal leaves stage unchanged, no inspector row, no ledger row, no burnt number, an actionable message · **and every non-ACCEPTED stage move behaves exactly as before** |
| **H · concurrency** | §10 |
| **I · RB-2** | after conversion `filled == joined` · `PARTIALLY_FILLED` still derived from `filled` · a historical disagreement is **reported, not repaired** |
| **J · nothing else moved** | `cx_identity_link` U1–U4 unchanged · `person_link_rows()` closure unchanged · tenant isolation on two real databases · the six existing finding kinds still fire · M4 boundary · M5 accountability · M6 seat gate · P4 credit |

**Every probe must assert it had a subject before asserting behaviour.** Sixteen
defective instruments were recorded in the Batch 3 corrective, **eleven the same
static-epoch-marker trap**, two of them inside corrections written to fix earlier
ones. A migration whose `static $doneAt === db_epoch()` marker is already warm is
a **no-op**; a probe that calls it and measures nothing will pass while testing
nothing. Any test depending on a migration running must **observe that it ran**.

---

## 10. Concurrency — real OS processes, MariaDB authoritative

Independent connections, wall-clock microsecond barrier, every one-time cost paid
**before** the barrier.

| | Test | Must show |
|---|---|---|
| **X1** | 4 processes, 4 different applications | **4 distinct employee numbers**. *Fails today, 4 of 4* |
| **X2** | 4 processes, **one** application | 1 workforce record · 3 × `RACE_LOST` · 1 ledger row · **0 orphans** · **stage moved exactly once** |
| **X3** | 3 concurrent boots installing the key | all succeed, none reports failure, exactly one index |
| **X4** | raw duplicate `INSERT`, no application code | refused on **both** engines |
| **X5** | raw ` EMP01` (leading space) | refused |
| **X6** | acceptance racing a professional↔inspector link | both succeed; separate axes |
| **X7** | form-typed `emp_code` colliding with a generated one | refused |
| **X8** | boot on a database already carrying a collision | boots · index off · `DIRTY` · reported · **nothing renumbered** |
| **X9** | 2 processes, same candidate, one acknowledges and one does not | the unacknowledged one is refused; **no partial state either way** |

---

## 11. Mutation plan

Fresh copy and fresh database per mutant. **FATAL is not a catch. ANCHOR-MISS is
not a catch. A dirty baseline aborts the run.** Every survivor CAUGHT or **PROVEN
EQUIVALENT** — zero unexplained survivors.

| | Mutant | Caught by |
|---|---|---|
| M1 | drop the unique index | A |
| M2 | key without case-folding | A |
| M3 | key without trimming | A (leading space) |
| M4 | key includes blank codes | A |
| M5 | **key restricted to live rows** | A (decision 1) |
| M6 | `DIRTY` installs anyway | B |
| M7 | `DIRTY` renumbers the loser | B |
| M8 | generator reverts to read-max | C · X1 |
| M9 | number consumed before commit | C |
| M10 | `team_role` dropped from the INSERT | D |
| M11 | `team_role` falls back to `FIELD` | D |
| M12 | unconfigured workspace inferred as Operations | D (decision 4) |
| M13 | Level 1 ignored entirely | D |
| M14 | match gate removed | F |
| M15 | gate warns instead of refusing | F |
| M16 | acknowledgement accepted for matches never shown | F |
| M17 | left employees included in matches | E |
| M18 | out-of-scope matches named | E |
| M19 | a `LIMIT` added to the match scan | E |
| M20 | the match gate **links** as well as reports | E · I7 |
| M21 | stage move moved back outside the transaction | G (decision 5) |
| M22 | refusal leaves the stage moved | G |
| M23 | `act_log()` moved inside the transaction | J · I41 |
| M24 | borrowed-transaction re-throw swallowed | X2 |
| M25 | conditional `WHERE inspector_id IS NULL` removed | X2 |
| M26 | key installed inside a transaction | §8.2 |
| M27 | `joined` deleted from `reqf_counts()` | I |

**Do not weaken production code to make a mutant fail.** A survivor means fix the
invariant or prove equivalence experimentally, with the backstop genuinely
installed, on both engines — and publish the reasoning either way.

---

## 12. Regression

SQLite full suite **0 failures** · MariaDB full suite **0 failures** · baseline
recorded on both engines **before** any change · `make_deploy_check.php` re-run ·
every Batch 1 / 2 / 3 identity test re-run and named.

**Consumers that must behave identically:** allocation · scheduling · attendance ·
punch · vouchers · expenses · equipment · competence · reporting · utilisation ·
requisition standing (M3 multi-vacancy) · M4 boundary · M5 accountability ·
P4 allocation credit · careers intake · marketplace fulfilment.

---

## 13. Acceptance criteria

Complete when **all** are true — not when the code compiles.

1. SQLite full regression **0 failures**.
2. MariaDB full regression **0 failures**.
3. `make_deploy_check.php` re-run.
4. **X1 passes** — four real processes hiring four people at one barrier produce
   **four distinct employee numbers** on MariaDB. *This is the acceptance test.*
5. **X2 passes** — Batch 2's guarantee intact, and the stage moves exactly once.
6. **X4, X5 pass** — refused **by the database**, no application code in the path.
7. **X8 passes** — dirty install boots, reports, **renumbers nothing**.
8. **A retired person's number cannot be re-issued** (decision 1).
9. No accepted hire is `FIELD` unless chosen or inherited — **never defaulted**.
10. An unconfigured workspace is **never inferred** as Operations (decision 4).
11. Every refusal leaves the candidate **at the previous stage** with an
    actionable message, and **every other stage move is unchanged** (decision 5).
12. The match gate **never links and never merges** — mutant **M20**.
13. **Zero unexplained mutation survivors.**
14. **No new permission · no new status · no new transition · no new engine · no
    new route · no new workforce table.**
15. Every §12 consumer behaves identically before and after.
16. **No historical record modified** to satisfy any constraint or any test.
17. `docs/` and the code agree — including the **N6** correction to
    `P6-BUSINESS-INVARIANTS.md` (I30, I22, I42 and the crosswalk).

---

## 14. Outstanding before this prompt is issued

| | Item |
|---|---|
| **0.1** | `team_role` needs an additive column on **two** masters — noted, not a decision |
| **0.2** | **OWNER CONFIRMATION REQUIRED** — decision 5 moves a Phase 5 write. Confirm the ordering in §0.2, or say the stage move must stay outside the transaction |

*Nothing in this document has been implemented.*
