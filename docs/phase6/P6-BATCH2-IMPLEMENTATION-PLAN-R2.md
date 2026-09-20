# Phase 6 · Batch 2 — Implementation plan **R2**
## Person relationship integrity & conversion safety — the remainder

*Plan only. No product code, schema, migration, route or permission is changed by
this document, and nothing below is implemented.*

**Contract:** `P6-BATCH2-PREIMPLEMENTATION-AUDIT-R2.md` (this plan's audit) ·
`P6-BATCH2-IMPLEMENTATION-PLAN.md` and `P6-BATCH2-COMPLETION-REPORT.md` (the
shipped batch, **accepted**) · `P6-CANONICAL-DOMAIN-MODEL.md` ·
`P6-DUPLICATE-AND-IDENTITY-RULES.md` · `P6-BUSINESS-INVARIANTS.md` ·
Batch 1 and Batch 3 as shipped and **LOCKED** · the owner's Q32 decisions.

> **Naming.** `P6-BATCH2-IMPLEMENTATION-PLAN.md` exists and describes the batch
> that shipped in `d5aa8cc`. It is not overwritten. This is the plan for what
> that batch deliberately left open, written against today's code.

---

## 1. Objective

Batch 2 made **the conversion** safe. This makes **the person it creates** safe.

One sentence: **hiring must issue an employee number that belongs to one human
and to no other, must say out loud which team a person joins, and must ask
whether we already employ them — and then stop and let a person answer.**

Nothing converges. No representation is merged. No Person hub is created.

---

## 2. Scope

| # | Work | Finding | Requirement | Owner decision |
|---|---|---|---|---|
| **S1** | Live **`emp_code` uniqueness enforced by the database**, via the existing `ensure_unique_generated_index()` | N1, N2 | **R20-a** | B1 |
| **S2** | A **reserving** employee-number generator, so the constraint is a backstop and not the user's experience | N1 | R20-a | — |
| **S3** | `rcv_convert()` sets **`team_role`** deliberately | N3 | RB-1 | **Q32 · decided** · B3 |
| **S4** | **Workforce-match detection** at the conversion — applicant vs existing team members | N4 | **R20-b** | B2 |
| **S5** | A **seventh finding kind** in `identity_state_findings()` for shared employee numbers | N1, N2 | R20-a | — |
| **S6** | Audit every new refusal and every acknowledged match through the **existing** engine | — | R18 (partial) | — |
| **S7** | Correct `P6-BUSINESS-INVARIANTS.md` where it contradicts shipped code | N6 | — | — |

S1–S2 are one transaction's worth of work. S4 is detection only and writes
nothing on its own.

---

## 3. Out of scope — explicitly

- **No Person table and no Person hub.** Q3 / Q7 / Q13 untouched.
- **No merging** of Candidate, Professional, Inspector, Employee or User.
- **No uniqueness on e-mail, mobile or name.** §10 explains why, and this is the
  same reasoning that kept R20 open in Batch 2 — it has not been overturned, only
  narrowed.
- **"One person = one inspector" stays OPEN.** Nobody has decided it.
- **No automatic merge, no automatic repair, no automatic unlink.** Detection
  only; a human decides.
- **No new engine** — no duplicate engine, no identity engine, no audit engine,
  no approval engine, no notification engine, no KPI engine.
- **No answers to Q1–Q18.**
- **No changes to** Hiring Request · Approval · Re-Approval · Requisition ·
  Recruiter Assignment · Candidate Pipeline · Interview · Offer · Joining.
  M4's execution boundary and M5's recruiter accountability remain authoritative.
- **No changes to** inspectors' operational behaviour, scheduling, allocation,
  timesheet, equipment, reporting, utilisation or any workforce screen.
- **No changes to** Marketplace identity, Phase 4 fulfilment, or
  `cx_professionals`.
- **No rewriting of historical records** to satisfy a new constraint.
- **The live-host workspace incident stays out.** It is not addressed here and
  must not be worked around through application changes.

---

## 4. Current architecture — what is being built on

- **Tenancy is structural.** One database per tenant, no `tenant_id` column.
  Nothing here weakens that.
- **The conversion is one door.** `rcv_convert()` (`lib/recruit.php:1273`), one
  production caller (`lib/ops.php:5777`), asking its own permission, scope,
  state, execution-boundary and branch gates, inside one transaction.
- **The ledger is `cx_identity_link`**, with four live keys U1–U4 enforced by
  unique indexes over NULL-able columns — identical semantics on both engines.
- **The uniqueness tool already exists.** `ensure_unique_generated_index()`
  (`lib/db.php:397`) installs a **generated** key column the database computes,
  plus its unique index, recorded in a `schema_guards` ledger, with an `OK /
  ABSENT / DIRTY / FAILED` outcome and a reconcile hook. Built and proven on both
  engines in the Batch 3 corrective. **S1 reuses it verbatim.**
- **The reconciliation report already exists.** `identity_state_findings()`
  (`lib/connect_identity.php:690`) reports six contradiction kinds. **S5 adds a
  seventh to it rather than building a second report.**
- **The audit engine already exists.** `act_log()` via `rcv_log()`.

Nothing in this plan introduces a mechanism that is not already in the codebase.

---

## 5. Candidate → Inspector conversion map — after this batch

```
POST /candidate-stage   (to=ACCEPTED)
  │
  ├─ M4  rexec_block_reason()            execution boundary            unchanged
  ├─ M6  seat gate                                                     unchanged
  ├─ P4  rful_enforce_candidate()        source ceiling                unchanged
  ├─ M3  reqf_sync()                     requisition standing          unchanged
  │
  └─ rcv_convert()
       1 permission   is_coordinator_level()                           unchanged
       2 tenant       structural                                       unchanged
       3 scope        connect_identity_scope_ok('candidate', …)        unchanged
       4 state        already converted → ALREADY                      unchanged
       5 boundary     rexec_block_reason() re-asked in the action      unchanged
       6 branch       rcv_branch_for()  — BD1, no silent Ahmedabad     unchanged
       7 entitlement  connect_identity_conversion_allowed()  — BD2     unchanged
     ▸ 8 WORKFORCE MATCH  ── NEW (S4) ──────────────────────────────────────────
          workforce_matches($cand)  → [] | [ {inspector, confidence, basis} … ]
          matches AND no acknowledgement on the request
              → refuse WORKFORCE_MATCH, audited, nothing written
              → the desk is shown who, and may acknowledge and retry
          (Behaviour on a match is OWNER DECISION B2. The refusal shape above is
           the SAFEST of the three and is what this plan assumes.)
     ▸ 9 TEAM ROLE       ── NEW (S3) ──────────────────────────────────────────
          Q32 Model D' — two levels, both reusing what already exists:
            L1  connect_cap_modules(workspace) contains 'operations'?
                  NO  → recruitment-only workspace: workforce record, no
                         Inspector concept; team_role = OFFICE
                  YES → continue
            L2  team_role from the captured value (B3 decides where);
                  FIELD → deployable Inspector · COORD/OFFICE → workforce only
          No new table, column, engine or migration.
      10 TRANSACTION
          BEGIN
            A  INSERT inspectors  …, team_role, emp_code, …   ← A′ S3 · A″ S2
                  emp_code comes from the RESERVING generator and is written
                  under the S1 constraint; a collision fails HERE, inside the
                  transaction, and the whole thing goes back
            B  UPDATE candidates SET inspector_id=? WHERE id=? AND (… IS NULL OR =0)
                  0 rows → RACE_LOST → rollback
            C  INSERT cx_identity_link            (only when entitled — BD2)
          COMMIT
      11 AUDIT  act_log() OUTSIDE the transaction  (I41)                unchanged
```

Every gate above line 8 is unchanged. Two of the three new writes land inside a
statement that already exists.

---

## 6. Existing identity mechanisms

All five stay. None is merged, renamed or retired.

| | Mechanism | Change in this batch |
|---|---|---|
| 1 | `cx_identity_link` | **none** |
| 2 | `candidates.inspector_id` | **none** |
| 3 | `users.inspector_id` | **none** |
| 4 | `candidates.person_ref` | **none** |
| 5 | per-application marketplace bridge | **none** |

The only new protection is on the **`inspectors` table itself**, which is not an
identity mechanism — it is the record the mechanisms point at, and it is the one
record with no protection at all today.

---

## 7. Contradictory state matrix — what changes

| State | Today | After |
|---|---|---|
| Two team members sharing a live `emp_code` | **undetected, and creatable** | **impossible** to create; pre-existing ones **reported** (S5), repaired by a human |
| Two team members sharing an e-mail / mobile | undetected | **reported** and **surfaced at the conversion** (S4). Still allowed — it may be a re-hire |
| An applicant already on staff | never asked | **asked, before the write** (S4) |
| Every other row of the audit's §7 matrix | as today | **unchanged** |

Nothing that is valid today becomes invalid. Nothing valid is repaired away.

---

## 8. R20 assessment — the decision this plan takes

Batch 2's **BD3** deferred R20 because a business identity key could not be
established safely. **That reasoning is not overturned.** It is narrowed, because
R20 turns out to be two questions:

| | Question | Verdict |
|---|---|---|
| **R20-a** | may two live team members share an **employee number**? | **No, never.** `emp_code` is an administrative identifier the business issues and already treats as unique everywhere downstream. A collision is wrong under every reading. **Constrain it.** |
| **R20-b** | may two live team members be the **same human**? | **Sometimes yes** — a re-hire, a two-agency supply. Evidence cannot separate the legitimate case from the duplicate. **Detect and ask. Never constrain, never merge.** |
| **R20-c** | is "one person = one inspector" the rule? | **OPEN.** Not decided, not decidable from what the system holds, not inferred here |

**Rejected keys and why** — login (not everyone has one; the reverse direction is
already covered by `inspector_login_conflict()`) · e-mail (legitimately shared) ·
mobile (legitimately shared, more so in this industry) · external identity (not
held for internal staff) · name (never an identifier).

---

## 9. Transaction boundary

| Write | Inside? | Reason |
|---|---|---|
| `INSERT inspectors` | **yes** | the team member |
| `team_role` on that INSERT | **yes** | same statement (S3) |
| `emp_code` reserved under the constraint | **yes** | part of creating the person; outside it, a rollback burns a number (S2) |
| `UPDATE candidates.inspector_id` | **yes** | the relationship this exists to create |
| `INSERT cx_identity_link` | **yes**, when entitled | the ledger row that constrains it |
| `act_log()` | **no** | a failed observation is never a failed transaction (I41) |
| requisition `hired_inspector_id` + `reqf_sync()` | **no** | downstream consequence; already correctly outside |
| workforce-match detection | **no** | runs **before** `BEGIN`; it reads, and produces a question |
| e-mail / notification | **no** | never |

**Borrowed transactions.** Unchanged and non-negotiable: when a caller already
holds a transaction, `rcv_convert()` joins it, neither commits nor rolls back,
and **re-throws** on failure so the caller unwinds. A reported failure that
commits a row is the defect Batch 2 removed and must not return.

**Failure paths**

| Failure | Result |
|---|---|
| workforce match, unacknowledged | refused **before** `BEGIN`; nothing written; audited |
| `emp_code` constraint rejects the insert | rollback; `FAILED`; audited; the number is not consumed |
| candidate link matches 0 rows | rollback; `RACE_LOST`; audited |
| ledger insert refused | rollback; `RACE_LOST`; audited |
| audit write fails | **the hire stands** (I41) |
| borrowed transaction, any failure | re-thrown; caller unwinds |

---

## 10. Duplicate strategy

**Two mechanisms, deliberately different in kind.**

**A · The employee number — a constraint.**
A generated live-key column over `emp_code`, computed by the **database**, plus a
unique index, installed through `ensure_unique_generated_index()`. Because it is
generated, no writer can set, forget or bypass it — the Batch 1 residual is
closed by construction, exactly as it was for `partner_contacts.uq_primary`.

Shape, subject to **B1**:

```
key = CASE WHEN <row is live> AND TRIM(COALESCE(emp_code,'')) <> ''
           THEN UPPER(TRIM(emp_code)) ELSE NULL END
```

- `NULL` for blank codes, so historical rows without one are never constrained.
- `UPPER(TRIM(…))` so `emp01`, `EMP01 ` and `EMP01` cannot coexist.
- **"live"** is what **B1** decides. If a retired person's number may be
  re-issued, `live` excludes retired rows; if not, the key is over all rows.
  **This must be answered before the expression is written** — it is the whole
  semantics of the key.
- **Probe with a LEADING space**, never a trailing one: MySQL ignores trailing
  spaces in string comparison and a trailing-space probe proves nothing.

**Dirty data is expected.** Seeds and the operator-typed form have had years to
create collisions. The guard's `DIRTY` path reports and leaves the index off
rather than failing a tenant's boot. **Nothing is merged, renumbered or deleted
to make the constraint fit** — re-issuing somebody's employee number is a
decision about a person's record and a human makes it.

**B · The human — a question.**

```
workforce_matches($cand) → [ { inspector_id, name, emp_code, confidence, basis } … ]
```

Deterministic and explainable, reusing `cand_find_duplicates()`'s existing
confidence scale so the desk sees one vocabulary, not two:

| Basis | Confidence |
|---|---|
| same mobile (last 10 digits) | 96 |
| same e-mail (case- and space-normalised) | 94 |
| same first + last name | 72 |
| same last name + same first initial | 46 |

- **Live team members only.** A left employee is history, not a duplicate.
- **Scope-filtered.** Only team members the actor may open are named; the rest
  are counted, never identified — a record id is never proof of authorisation.
- **No LIMIT ceiling**, and it must not acquire one. N5 exists because
  `cand_find_duplicates()` has one (`LIMIT 500`) and says nothing about it.
- **It never writes, never links, never merges.** It returns a list.

---

## 11. Concurrency strategy

Real OS processes, independent connections, wall-clock microsecond barrier, every
one-time cost paid **before** the barrier, **MariaDB authoritative**.

| | Test | Must show |
|---|---|---|
| **X1** | 4 processes, 4 different applications, one barrier | **4 distinct employee numbers**, or a deterministic refusal — never a silent duplicate. *This is the test that fails today, 4 out of 4.* |
| **X2** | 4 processes, **one** application | 1 team member · 3 × `RACE_LOST` · 1 ledger row · **0 orphans** — the Batch 2 guarantee, unchanged |
| **X3** | 3 concurrent boots installing the `emp_code` key | all 3 succeed, **none reports failure**, exactly one index exists (the `CG13–CG18` shape) |
| **X4** | raw `INSERT` of a duplicate employee number, **no application code** | refused by the database on **both** engines |
| **X5** | raw `INSERT` with a **leading** space (` EMP01`) | refused — the key normalises |
| **X6** | conversion racing a professional↔inspector link | both succeed; separate axes |
| **X7** | form-typed `emp_code` colliding with a generated one | refused |
| **X8** | boot on a database that **already** carries a collision | boot succeeds · index **not** installed · guard state `DIRTY` · the collision is **reported** · nothing is renumbered |

**The database provides final protection.** X4 and X5 must pass with no
application code in the path, or S1 has not been built.

---

## 12. Tenant / scope strategy

Reuses Batch 1. **No new scope engine.**

| | |
|---|---|
| tenant isolation | structural; unchanged and re-asserted by regression |
| candidate scope | `connect_identity_scope_ok('candidate', …)` — unchanged |
| inspector scope | `connect_identity_scope_ok('inspector', …)` — applied to **S4's match list**, so a match outside the actor's scope is counted but never named |
| actor permission | asked by the action (I27) |
| recruitment entitlement | M4, unchanged |
| Connect entitlement | BD2, unchanged |
| **resulting-state scope** | **left open.** Whether the actor must be able to open the **branch the new team member lands in** is Q5/Q11. Not decided here, not implemented here, and no back door is opened toward it |

---

## 13. Authorization

No new permission. Not one.

| Action | Right |
|---|---|
| convert | `is_coordinator_level()` — unchanged |
| acknowledge a workforce match | the **same** right as converting. An acknowledgement is part of the conversion, not a separate authority |
| see the shared-employee-number report | the existing data-integrity board right |
| resolve a shared employee number | the existing People/Inspector-edit right |

If any of this seems to need a new permission, **stop and ask** — per `CLAUDE.md`,
no role gains a right that is not in `docs/02-permission-matrix.md`.

---

## 14. Audit and reversal

Through `act_log()` / `rcv_log()`. **No new audit engine.**

| Event | Kind | Subject |
|---|---|---|
| conversion refused on an unacknowledged workforce match | `IDENTITY_REFUSED` | candidate — names how many matched and on what basis |
| conversion proceeding on an **acknowledged** match | `IDENTITY_LINKED` | candidate — records that the desk was shown the matches and went ahead, **and which ones** |
| `team_role` on conversion | folded into the existing `IDENTITY_LINKED` line | candidate — names the role and where it came from (L1 or L2) |
| employee-number constraint rejects a hire | `IDENTITY_REFUSED` | candidate |
| the key is installed / found dirty | `schema_guards` ledger | the guard's own record, already built |

**Reversal.** Nothing new becomes reversible and nothing that is reversible
stops being so. R18 and R21 remain open. **No historical record is rewritten.**

---

## 15. Action-path matrix — after

Changes only; every other path in the audit's §11 is untouched.

| # | Path | Change |
|---|---|---|
| 1 | Accept + convert | **+ workforce-match gate** (S4) · **+ deliberate `team_role`** (S3) · **+ constrained, reserved `emp_code`** (S1/S2) |
| 7 | Add a team member (form) | **+ the `emp_code` constraint applies** — a typed collision is refused. No other change |
| 6 | Reconcile unlinked logins | `team_member_create()` inherits the reserving generator; **no behavioural change** |
| — | **New:** shared-employee-number finding | appears in the existing reconciliation report; **read-only** |

**Still absent, deliberately:** no bulk import, no API, no CLI, no background job
creates or changes these relationships — and this batch adds none.

---

## 16. Business invariants

| | After this batch |
|---|---|
| **I28** — uniqueness protected at database level | **HOLDS for `inspectors`** — a generated key the database computes; a raw `INSERT` cannot bypass it (X4, X5) |
| **I29** — concurrent creators produce one relationship | **widened to the person record** — 4 concurrent hires produce 4 distinct numbers (X1) |
| **I30** — a person representation cannot be duplicated by an ordinary action | **already HOLDS**; the register must be corrected (S7 / N6) |
| **I22**, **I42** | **improve** — the last un-reconcilable state on this axis becomes reportable |
| **I7** — ambiguous matches are never auto-merged | **reinforced** — S4 suggests and refuses; it never links |
| **I41** — a failed observation is never a failed transaction | **unchanged** — audit stays outside |
| **I1**, **I3** | **unchanged** — one human may still hold several applications, and several representations |
| **I32** (R21), **I33**, **I6**, **I39**, **I16** | **unchanged**; stay open |

**No new invariant is proposed.** Everything here is covered by I28, I29 and I7.

---

## 17. File / function / route changes proposed

| File | Change | Kind |
|---|---|---|
| `lib/ops.php` · `next_emp_code()` | reserve rather than read-max; retry on collision under the constraint | **modify** |
| `lib/recruit.php` · `rcv_convert()` | add `team_role` to the INSERT; call the workforce-match gate before `BEGIN`; one new refusal code | **modify** |
| `lib/recruit.php` | `workforce_matches($cand)` — new, read-only | **add** |
| `lib/recruit.php` · `RCV_CODES` | one entry: `WORKFORCE_MATCH` | **modify** |
| `lib/ops.php` · inspector migration | one `ensure_unique_generated_index()` call | **modify** |
| `lib/connect_identity.php` · `identity_state_findings()` | a seventh finding kind | **modify** |
| `views/ops/candidate_detail.php` | show the matches and the acknowledgement; capture `team_role` if **B3** puts it here | **modify** |
| `docs/phase6/P6-BUSINESS-INVARIANTS.md` | correct I30 / I22 / I42 / the crosswalk (**N6**) | **modify** |
| `docs/02-permission-matrix.md` | **no change** — no new permission |
| `docs/03-object-lifecycles.md` | **no change** — no new status or transition |

**No new file, no new library, no new engine, no new route.**

---

## 18. Database changes proposed

| | Change | Destructive? |
|---|---|---|
| 1 | one **generated** column on `inspectors` carrying the live employee-number key | **no** — derived from a column that stays; dropping it destroys nothing |
| 2 | one **unique index** over that column | **no** |
| 3 | one row in the existing `schema_guards` ledger | **no** |

**No table is created. No column is dropped. No data is modified.** No existing
column changes type, width or nullability. No row is renumbered, merged or
deleted to make the constraint fit.

---

## 19. Migration strategy

1. Epoch-guarded, through the existing `ensure_unique_generated_index()`.
2. **MariaDB commits implicitly on any DDL**, so the installation must never run
   inside a borrowed transaction — the guard already refuses that, and the test
   must prove it refuses rather than assume it.
3. Outcome is one of `OK` · `ABSENT` · `DIRTY` · `FAILED`, recorded in
   `schema_guards`, never thrown at a booting tenant.
4. **`DIRTY` is the expected outcome on real installs.** Report, leave the index
   off, let a human resolve it, and install on a later boot.
5. **Concurrent boots must all succeed** (X3).
6. `PRAGMA table_info` does **not** list generated columns — `table_xinfo` does,
   and `table_columns_incl_generated()` already handles both engines.
7. `php tools/make_deploy_check.php` re-run after any source change.
8. **No back-fill that changes a business value.** Existing blank employee codes
   stay blank and stay unconstrained.

---

## 20. Test plan

`phpapp/tests/test_p6_batch2_r2.php`, both engines, no skips.

| Group | Covers |
|---|---|
| **A · the key exists and is real** | the generated column is present on both engines · the unique index exists · `schema_guards` records it · a raw duplicate `INSERT` is refused with **no application code in the path** · a **leading**-space duplicate is refused · a blank code is not constrained |
| **B · dirty data** | a database seeded with a collision boots · the index is **not** installed · state is `DIRTY` · the collision is **reported** · **nothing is renumbered** |
| **C · the generator** | two calls with no insert between return **different** numbers · a collision under the constraint is retried, not surrendered · a rolled-back conversion does **not** burn a number |
| **D · `team_role`** | conversion in a workspace **without** `operations` → workforce, not Inspector · **with** `operations` → the captured role is honoured · **never** silently `FIELD` · `inspectors_list()` ranking is unchanged for existing rows |
| **E · workforce match** | exact mobile → 96 · exact e-mail → 94 · same name → 72 · similar name → 46 · below 46 → not offered · a **left** employee is not a match · a match outside the actor's scope is **counted, never named** · **no LIMIT ceiling** |
| **F · the gate** | unacknowledged match → refused, **nothing written**, audited · acknowledged → converts, and the audit names which matches were shown · a forged acknowledgement for matches never shown is refused |
| **G · concurrency** | X1–X8 of §11, real processes, MariaDB |
| **H · the Batch 2 guarantee** | the §3 re-verification re-run **as a permanent test** — 4 processes, one application, 1 team member, 3 × `RACE_LOST`, 0 orphans |
| **I · nothing else moved** | `cx_identity_link` U1–U4 unchanged · `person_link_rows()` closure unchanged · tenant isolation on two real databases · the six existing finding kinds still fire |

**Every probe must assert it had a subject before it asserts behaviour.** Sixteen
defective instruments were recorded in the Batch 3 corrective, **eleven of them
the same static-epoch-marker trap**, and two of those were inside corrections
written to fix earlier ones. A migration whose `static $doneAt === db_epoch()`
marker is already warm is a **no-op**, and a probe that calls it and then measures
nothing will pass while testing nothing. Every test here that depends on a
migration running must **observe that it ran**.

---

## 21. Mutation plan

Fresh copy and fresh database per mutant. **FATAL is not a catch. ANCHOR-MISS is
not a catch. A dirty baseline aborts the run.** Every survivor must become CAUGHT
or **PROVEN EQUIVALENT** — zero unexplained survivors.

| | Mutant | Must be caught by |
|---|---|---|
| **R1** | drop the unique index creation | A |
| **R2** | make the key ignore case-folding (`TRIM` only) | A |
| **R3** | make the key ignore whitespace-trimming | A (leading space) |
| **R4** | include blank codes in the key | A |
| **R5** | let `DIRTY` install the index anyway | B |
| **R6** | make `DIRTY` renumber the loser | B |
| **R7** | revert the generator to read-max | C, G/X1 |
| **R8** | consume a number before the transaction commits | C |
| **R9** | drop `team_role` from the INSERT | D |
| **R10** | default `team_role` to `FIELD` regardless of level 1 | D |
| **R11** | ignore the workspace capability at level 1 | D |
| **R12** | drop the match gate entirely | F |
| **R13** | make the gate warn instead of refuse | F |
| **R14** | accept an acknowledgement for matches never shown | F |
| **R15** | include left employees in matches | E |
| **R16** | name out-of-scope matches | E |
| **R17** | add a `LIMIT` to the match scan | E |
| **R18** | let the match gate **link** as well as report | E, I7 |
| **R19** | move `act_log()` inside the transaction | H, I41 |
| **R20** | swallow the borrowed-transaction re-throw | H |
| **R21** | remove the conditional `WHERE inspector_id IS NULL` | H/X2 |
| **R22** | install the key inside a transaction | §19.2 |

**Do not weaken production code to make a mutant fail.** If a mutant survives,
fix the invariant or prove equivalence experimentally, with the backstop
genuinely installed, on both engines — and publish the reasoning either way.

---

## 22. Regression plan

| | |
|---|---|
| **SQLite** | full suite, `php tests/run.php`, **0 failures** |
| **MariaDB** | full suite, `DB_DRIVER=mysql`, **0 failures** |
| **Baseline** | recorded **before** any change, on both engines, so a delta is a delta and not a memory |
| **Deploy check** | `php tools/make_deploy_check.php` re-run |
| **Targeted** | every Batch 1, Batch 2 and Batch 3 identity test re-run explicitly and named in the evidence |
| **Operations consumers** | allocation · scheduling · attendance · punch · vouchers · expenses · equipment · competence · reporting · utilisation — every one reads `inspectors`, and **§15's protection must be invisible to all of them** |
| **Recruitment consumers** | requisition standing (M3 multi-vacancy) · M4 boundary · M5 accountability · P4 allocation credit — none may change |

---

## 23. Risks

| | Risk | Mitigation |
|---|---|---|
| **1** | **A real tenant's data is already dirty** and the index cannot be installed | Expected, not exceptional. `DIRTY` reports and leaves the index off; the tenant keeps working; a human resolves it. **Nothing is renumbered automatically** |
| **2** | The match gate becomes an obstacle a busy recruiter clicks through without reading | **B2** is the owner's decision precisely because of this. Acknowledgement must name **which** matches were shown and record it — a gate nobody reads is worse than none |
| **3** | A reserving generator serialises hiring under load | The reservation is one short statement inside a transaction already open. Measure it; do not assume it |
| **4** | Constraining `emp_code` breaks an Operations consumer that relies on collisions | No consumer can rely on a collision as a **feature**; §22 tests every one of them |
| **5** | **B1 is answered after the key is built** and the expression is wrong | **Do not build the key before B1 is answered.** The answer *is* the expression |
| **6** | The static-epoch-marker trap produces a test that passes while testing nothing | §20's standing rule: observe that the migration ran before measuring what it did |
| **7** | Scope creep toward "one person = one inspector" | §3 forbids it; mutant **R18** exists to catch the drift |

---

## 24. Deferred items

| | Why |
|---|---|
| **R20-c** — "one person = one inspector" | not decided, not decidable from held evidence |
| **R18** — audit for the three column mechanisms | unchanged, open |
| **R21** — recording "keep these separate" | unchanged, open |
| **R23** — taxonomy creation on a search path (I24) | unrelated |
| **Q5 / Q11** — does a relationship carry its own branch? | open; resulting-state scope waits on it |
| **Q1–Q18** | not answered |
| **N5** — the 500-row candidate duplicate ceiling | folded into S4's discipline rather than fixed separately |
| **Person Hub · universal person key · organisation convergence** | out of scope, permanently, unless the owner decides otherwise |
| **The live-host workspace incident** | separate; no application change |

---

## 25. Business decisions required

> **B1 · OWNER DECISION REQUIRED — may an employee number ever be re-issued?**
> If a person leaves and their record is retired, may their number be given to
> somebody new? **Yes** → the key covers live rows only. **No** → the key covers
> every row that has a number. *This is the key's entire meaning and it cannot be
> inferred. Nothing is built until it is answered.*

> **B2 · OWNER DECISION REQUIRED — what happens when an applicant matches
> somebody already on the team?**
> (a) show and continue · (b) show and require an acknowledgement before the hire
> · (c) refuse until a human links or dismisses. *This plan assumes (b) as the
> safest. It is an assumption, not a decision.*

> **B3 · OWNER DECISION REQUIRED — where is `team_role` captured?**
> The stage-move form, the offer, or the requisition/position. (Q32 item 2.)

> **B4 · OWNER DECISION REQUIRED — the default for a workspace that has not
> configured capabilities.** `connect_cap_configured()` false shows everything
> today. Is such a workspace Level 1 = yes, or asked to configure first?
> (Q32 item 3.)

> **B5 · OWNER DECISION REQUIRED — what a recruiter sees when acceptance is
> refused.** Atomicity is decided; the wording and recovery path are not.
> (Q32 item 4.)

**Nothing here is answered by inference.** No Person hub, no universal person
key, no branch ownership of relationships, no automatic merging, no uniqueness on
e-mail or mobile, no automatic candidate→inspector merge, no organisation
convergence.

---

## 26. Acceptance criteria

This batch is complete when **all** of the following are true — and not when the
code merely compiles.

1. **SQLite** full regression: **0 failures**.
2. **MariaDB** full regression: **0 failures**.
3. `php tools/make_deploy_check.php` re-run after the final source change.
4. **X1 passes**: four real processes hiring four people at one barrier produce
   **four distinct employee numbers** on MariaDB. *This is the acceptance test.*
5. **X2 still passes**: four real processes, one application → 1 team member,
   3 × `RACE_LOST`, 1 ledger row, **0 orphans**. Batch 2's guarantee is intact.
6. **X4 and X5 pass**: a raw duplicate `INSERT`, and a leading-space duplicate,
   are refused **by the database** with no application code in the path, on both
   engines.
7. **X8 passes**: a database that already carries a collision boots, the index is
   not installed, the state is `DIRTY`, the collision is reported, and **nothing
   was renumbered**.
8. No converted hire is `FIELD` unless that was **chosen or derived** — never
   defaulted.
9. The workforce-match gate **never links and never merges**, proved by mutant
   **R18**.
10. **Zero unexplained mutation survivors.** Every survivor is CAUGHT or PROVEN
    EQUIVALENT, with published reasoning.
11. **No new permission**, **no new status**, **no new transition**, **no new
    engine**, **no new route**.
12. Every Operations and Recruitment consumer in §22 behaves identically before
    and after.
13. **No historical record was modified** to satisfy any constraint or any test.
14. `docs/` and the code agree — including the **N6** correction.
15. **B1 was answered before the key was written.**

*Nothing in this document has been implemented. Batch 2's shipped work is
untouched.*

---

## Addendum — §25's five decisions are LOCKED (2026-09-20)

| | Question | Locked answer | Effect on this plan |
|---|---|---|---|
| **B1** | employee-number re-issue | **NO — never. Lifetime, tenant-wide** | §10's key loses its "is this row live" predicate entirely. `key = CASE WHEN TRIM(COALESCE(emp_code,''))<>'' THEN UPPER(TRIM(emp_code)) ELSE NULL END`. Simpler and stronger; **`DIRTY` becomes more likely**, because retired rows now count |
| **B2** | applicant matches staff | **Show + require an explicit acknowledgement tick** | §10B's assumption (b) is confirmed. The tick is an acknowledgement, **not a merge** |
| **B3** | where `team_role` is chosen | **Requisition / position, confirmed at acceptance** | Needs an additive column on **two** masters — neither has one today |
| **B4** | unconfigured capabilities | **Do not infer.** Preserve navigation; require explicit classification | A new refusal, `NEEDS_CLASSIFICATION` |
| **B5** | acceptance refused | **Candidate stays at the previous stage**, inline actionable message | Restructures the stage-move route's write ordering — see the consolidated prompt §0.2 |

**Superseded by** `docs/phase7/RB-CONSOLIDATED-IMPLEMENTATION-PROMPT.md`, which
carries this plan forward together with RB-1 and RB-2. This plan remains the
record of how R20 was split and why.
