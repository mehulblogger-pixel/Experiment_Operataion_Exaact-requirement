# Phase 6 · Batch 2 — Implementation plan
## Person relationship integrity & conversion safety

*Plan only. No product code, schema, migration, route or permission is changed by
this document, and nothing below is implemented.*

**Contract:** `P6-BATCH2-PREIMPLEMENTATION-AUDIT.md` (this batch's audit) ·
`P6-CANONICAL-DOMAIN-MODEL.md` · `P6-DUPLICATE-AND-IDENTITY-RULES.md` ·
`P6-ACTION-PATH-MATRIX.md` · `P6-BUSINESS-INVARIANTS.md` ·
`P6-FOUNDATIONAL-IMPLEMENTATION-PLAN.md` · Batch 1 as shipped and **LOCKED**.

---

## 1. Objective

Make the **existing** Candidate / Inspector / Professional / User relationships
reliable enough for later convergence: safe, explicit, transactional, auditable
and — where the architecture already supports it — reversible.

Stated as one sentence: **hiring one person must produce one staff record, that
record must be connected to the identity ledger Batch 1 built, and declaring two
applications the same person must never quietly declare a third person
different.**

## 2. Scope

| # | Work | Finding | Requirement |
|---|---|---|---|
| **S1** | Make the candidate→inspector conversion **one transaction** | A | R2 |
| **S2** | Write the **candidate↔inspector edge into `cx_identity_link`** inside that transaction, so Batch 1's constraints apply to it | C | R1 / R5 |
| **S3** | Add the **candidate-axis inspector key (U4)** so the database, not a PHP check, stops a second conversion | A | R3 (extended) |
| **S4** | Set `emp_code` and `home_office_id` on the converted inspector | A, D | R2 |
| **S5** | Make `person_link_rows()` compute the **whole group**, so linking never splits one | B | new — see §16 |
| **S6** | **Audit** the conversion and the person link, through the existing engine | A, B | R18 (partial) |
| **S7** | **Detect and report** contradictory and dangling states — report only, repair nothing | E, F | R20-adjacent |

## 3. Out of scope — explicitly

- **No Person table and no Person hub.** Q3/Q7/Q13 untouched.
- **No merging** of Candidate, Professional, Inspector, Employee or User.
- **No general inspector uniqueness key (R20)** — §8 explains why, and it stays
  OPEN.
- **No automatic repair** of any contradictory state. Detection only.
- **No reversal** for `candidates.inspector_id` or `person_ref` (**R21**), and no
  audit for `users.inspector_id` or the per-application bridge (**R18** in full).
- **No taxonomy work (R23)**, no organisation work (**R4/R27**), no
  `partner_contacts` (**R30**), no portal e-mail uniqueness (**R31**).
- **No change to Batch 1**, unless a genuine regression is found.
- **No answer to Q1–Q18.**

## 4. Current architecture — what Batch 2 builds on

| Layer | State | Batch 2 uses it |
|---|---|---|
| `cx_identity_link` + `connect_identity_*` | Batch 1: axis-aware, entitlement- and scope-gated, U1/U2/U3, attributable audit, `W1` boundary | **REUSE** — the conversion becomes a caller |
| `scope_allows()` / `scope_office_allows()` / `scope_sbu_allows()` | Batch 1 | **REUSE unchanged** |
| `connect_identity_admin_can()` | Batch 1 | **REUSE unchanged** |
| `act_log()` + `ACT_ENTITIES` / `ACT_KINDS` | `IDENTITY_LINK` + three kinds registered in Batch 1 | **REUSE**; `CANDIDATE` and `INSPECTOR` are already registered |
| `system_status()` | two Batch 1 rows | **EXTEND** — one more row |
| `team_member_create()` / `next_emp_code()` | existing | **REUSE** |
| M4 execution boundary · M5 recruiter accountability · M6 seat gate · Phase 4 allocation | locked | **UNCHANGED, and still asked first** |

**No new engine of any kind is proposed.**

## 5. Candidate → Inspector conversion map

### Today

```
/candidate-stage  (POST, is_coordinator_level, module 'hiring')
  └─ M6 seat gate ─ Phase 4 credit ─ reqf_sync         (unchanged, runs first)
  └─ if ACCEPTED and make_inspector and inspector_id is empty:
         INSERT  inspectors                (raw, no emp_code, no office)
         UPDATE  candidates.inspector_id
         UPDATE  requisitions.hired_inspector_id
         reqf_sync
     ── no transaction · no constraint · no ledger row · no audit ──
```

### Proposed

```
  └─ if ACCEPTED and make_inspector and inspector_id is empty:
        ENTITLEMENT → PERMISSION → TENANT → SCOPE → STATE          (before the write)
        BEGIN
          A  INSERT inspectors            (+ emp_code, + home_office_id)
          B  UPDATE candidates.inspector_id  WHERE id=? AND inspector_id IS NULL
             └─ 0 rows affected → somebody else converted first → ROLLBACK, refuse
          C  INSERT cx_identity_link       (candidate ↔ inspector, live key U4)
             └─ uniqueness violation      → ROLLBACK, refuse truthfully
        COMMIT
        AUDIT (outside the transaction; a failed observation never undoes it)
        then, unchanged and outside: requisitions.hired_inspector_id, reqf_sync
```

**Called through one new function** in the recruitment layer — the conversion
stops being inline route code, so it can be tested and attacked directly
(invariant **I27**). The route keeps its own gate; the function asks its own.

## 6. Existing identity mechanisms — disposition

| Mechanism | Batch 2 does | Batch 2 does **not** |
|---|---|---|
| `cx_identity_link` | **gains the candidate↔inspector axis** and a fourth live key | change any Batch 1 rule |
| `candidates.inspector_id` | **keeps working, unchanged for every reader**; becomes transactional; gains an audit entry | get retired, reversed, or made canonical |
| `users.inspector_id` | untouched | gain a constraint or an audit (**R18/R20**, deferred) |
| `candidates.person_ref` | **stops splitting groups**; gains an audit entry | become a person identifier (**Q3/Q7/Q13**) |
| per-application bridge | untouched | anything |

> **Three axes, one ledger.** Batch 1 established that the ledger carries
> independent relationship axes distinguished by predicate. Batch 2 adds the
> third — candidate↔**inspector** — the same way: a typed column combination, not
> a new table and not a merge.

## 7. Contradictory state matrix — what Batch 2 does about each

Full matrix in the audit §6. Disposition here:

| States | Batch 2 |
|---|---|
| 1, 2, 4, 5, 9, 11, 14, 15 — valid | nothing |
| **8** — a group split by the old `person_link_rows()` | **repaired mechanically**, because recomputing a closure needs no business decision. Reported as it happens |
| 3, 12, 13 — dangling references | **detected and reported.** Not repaired |
| **6, 7, 10, 16** — two inspectors apparently for one person | **detected and reported for human review.** Never merged, never auto-unlinked |
| new conversions | **prevented** by U4 + the transaction |

**No existing data is silently changed** other than case 8, which restores a
grouping the system itself broke, and says so in the audit trail.

## 8. R20 assessment — recommendation

> **R20 stays OPEN. Do not adopt an inspector business identity key in Batch 2.**

The audit (§4) tested every candidate field against the shipped code:
`emp_code` is not set by one live creation path and its generator is raceable;
e-mail and mobile are ruled out as resolvers by the locked duplicate rules and
are legitimately shared in this business; there is no external identity column;
name is not a resolver. And at least three cases of *legitimate* multiple
inspector rows for one human exist (re-hire on different terms, two agencies,
diagnostic namespaces).

**What Batch 2 does instead:** stops the duplication this batch is actually
about. **U4** — one live candidate-axis inspector link per candidate — plus the
transaction means **one candidate cannot produce two inspectors**, without
anybody deciding what makes two inspectors the same human.

**OWNER DECISION REQUIRED** for general inspector uniqueness: it needs a business
identifier every creation path populates. That is a data-and-process decision,
not a schema one.

## 9. Transaction boundary

**The smallest correct business boundary**, and no more:

| In | Why |
|---|---|
| `INSERT inspectors` | the staff record |
| `UPDATE candidates.inspector_id` | the relationship this conversion exists to create |
| `INSERT cx_identity_link` | the same relationship, in the ledger that constrains it |

| Out | Why |
|---|---|
| M6 seat gate, Phase 4 credit, `reqf_sync` | they already ran, are locked, and belong to other engines |
| `requisitions.hired_inspector_id` | a downstream denormalisation; failing it must not undo a hire |
| `rkpi_stage_log` and every audit write | **a failed observation is never a failed transaction** (**I41**) |
| e-mail, notifications | never inside a business transaction |

**Failure paths**

| Failure | Result |
|---|---|
| A fails | ROLLBACK · no inspector · candidate unchanged · refusal |
| B affects 0 rows (another process won) | ROLLBACK · **no orphan** · "already converted" |
| C violates U4 | ROLLBACK · **no orphan** · "already converted", read back from the winner |
| commit fails | ROLLBACK · nothing written · refusal |
| audit fails after commit | **the hire stands**; the failure is visible |

## 10. Duplicate strategy

Four classes, exactly as §6 of the instruction requires, and **no automatic merge
in any of them**:

| Class | Evidence | Batch 2 behaviour |
|---|---|---|
| **Proven same person** | an existing `cx_identity_link` already connects them | reuse the existing inspector; **offer**, never act |
| **Possible same person** | the existing `connect_identity_suggestions()` / `candpool` signals (e-mail, mobile) | **suggest** at the point of conversion; the recruiter decides |
| **Different person** | no signal | convert normally |
| **Ambiguous** | more than one candidate counterpart | **refuse to guess**; present the conflict |

**The human confirmation point already exists** — `/connect-identity` and the
candidate screen's link/unlink actions, both secured in Batch 1. Batch 2 routes
the suggestion there rather than building a second confirmation surface.

> **"AI may suggest. AI must NOT silently merge."** Batch 2 adds a suggestion and
> a refusal. It adds no merge.

## 11. Concurrency strategy

Database constraints are the final protection; the PHP checks are the courtesy
that gives a good message. Real OS processes, independent connections,
synchronised on one wall-clock microsecond — never sequential simulation.

| # | Race | Expected |
|---|---|---|
| **A** | two simultaneous conversions of one candidate | exactly **one** inspector; the loser is told "already converted"; **no orphan** |
| **B** | the same candidate converted twice, sequentially | second is a no-op refusal |
| **C** | two candidates converting to the same inspector identity | both succeed — **legitimate** (I3 analogue); U4 constrains the *candidate* side only |
| **D** | conversion while another process links the professional | both succeed; the axes are independent (Batch 1) |
| **E** | retry after a rolled-back transaction | succeeds cleanly; nothing left behind |
| **F** | stale browser posting a conversion for an already-converted candidate | refused by B's `WHERE inspector_id IS NULL`, not by a re-read |

## 12. Tenant / scope strategy

**Reuse Batch 1 entirely. No new scope engine.**

| Check | Helper |
|---|---|
| tenant | structural (`db()`), proved by Batch 1's two-tenant probe |
| candidate | `connect_identity_scope_ok('candidate', …)` → `scope_sbu_allows()` |
| actor permission | `is_coordinator_level()` + module `hiring` (unchanged) |
| recruitment entitlement | M4/M6 gates, unchanged |
| Connect entitlement | `connect_identity_admin_can()` — **required, because the conversion now writes the ledger** |
| **resulting state** | the inspector the conversion is about to create must itself be in the actor's scope — hence `home_office_id` is set from the actor's/candidate's branch rather than left NULL |

> **§11 of the instruction is explicit that the RESULTING state must be
> evaluated.** Leaving `home_office_id` NULL today means every converted inspector
> silently lands in Ahmedabad's scope. Setting it is both the scope fix and the
> resulting-state check.

**OWNER DECISION REQUIRED (small):** which branch a converted inspector belongs
to — the candidate's requisition branch, or the converting actor's home branch.
The plan proposes **the requisition's branch, falling back to the actor's**, and
will not implement until this is confirmed. Relationship-level branch scope
remains **Q5/Q11, open**.

**Entitlement consequence to note:** once the conversion writes the ledger, a
workspace without Connect cannot complete it. **OWNER DECISION REQUIRED** —
either (a) the ledger write is skipped, with the conversion still transactional
and U4 still applying via `candidates.inspector_id`, or (b) Connect becomes
required for hiring conversion. **The plan recommends (a)**: recruitment must not
become unsellable without the marketplace.

## 13. Authorisation

The Batch 1 order, unchanged, asked by the **function**:

```
ENTITLEMENT → PERMISSION → TENANT → SCOPE → STATE →
RELATIONSHIP OWNERSHIP → DUPLICATE → WRITE → AUDIT
```

Authorisation is evaluated **before any information-revealing refusal**. No new
permission. The population that may convert a candidate is unchanged.

## 14. Audit and reversal

**Reuse `act_log()`. No new audit engine.**

| Event | Entity | Kind |
|---|---|---|
| conversion requested / refused | `CANDIDATE` | `IDENTITY_REFUSED` |
| conversion succeeded | `CANDIDATE` + `INSPECTOR` | `IDENTITY_LINKED` |
| identity link created / removed | `IDENTITY_LINK` | Batch 1 kinds |
| rollback | `CANDIDATE` | `IDENTITY_REFUSED`, naming the cause |
| duplicate prevented | `CANDIDATE` | `IDENTITY_REFUSED` |
| human confirmation of a suggested match | `IDENTITY_LINK` | Batch 1 kinds |
| person group linked **or repaired** | `CANDIDATE` | `IDENTITY_LINKED` |

**Reversal:** the new `cx_identity_link` row is reversible through Batch 1's
unlink. **`candidates.inspector_id` is NOT made reversible in Batch 2** — undoing
a hire means deciding what happens to the staff record, the requisition seat and
the money already recorded against it. **R21, deferred, stated.**

**No historical business record is rewritten**, with the single stated exception
of the person-group repair in §7, which restores a grouping the system broke.

## 15. Action-path matrix — Batch 2

| Path | Actor | Entitlement | Permission | Tenant | Scope | State | Identity validation | Duplicate | Transaction | Concurrency | Audit | Rollback | Expected refusal |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `/candidate-stage` + `make_inspector` | coordinator+ | hiring (+ Connect for the ledger row, per §12) | `is_coordinator_level()` | `db()` | candidate SBU + resulting inspector branch | ACCEPTED, not already converted | suggestion only | **U4** | **yes** | DB-decided | yes | yes | generic before the record may be named |
| `/candidate-link-person` | coordinator+ | hiring | `is_coordinator_level()` | `db()` | both anchors (Batch 1) | — | closure computed | n/a | **yes** | last-writer-wins on the group | **yes (new)** | n/a | "no such application for this record" |
| `/candidate-link-pro` · `/candidate-unlink-pro` · `/connect-identity` | unchanged | unchanged | unchanged | unchanged | unchanged | unchanged | unchanged | U1/U2/U3 | unchanged | unchanged | unchanged | unchanged | unchanged |
| people form (`ops_inspectors`) | admin/coordinator | ops | existing | `db()` | existing | — | **none today** | **none** | no | none | no | no | — **recorded as a gap, deferred** |
| `/users` save · reconcile · org import | unchanged from Batch 1 | | | | | | | | | | | | |
| `trace_audit_seed()` | master only | — | `is_master()` | `db()` | — | — | none | none | no | none | no | no | **recorded, diagnostic namespace, deferred** |
| DEMO seeds + CLI | master / CLI | — | master | `db()` | — | — | via canonical writers (S06) | — | — | — | — | — | S01/S02/S03 still raw — **NOT A DEFECT**, namespaced |
| cron / background | — | — | — | — | — | — | — | — | — | — | — | — | **ABSENT — confirmed, not assumed** |
| AJAX / API | — | — | — | — | — | — | — | — | — | — | — | — | **ABSENT — confirmed, not assumed** |

## 16. Business invariants

| Invariant | Batch 2 effect |
|---|---|
| **I1 · I2** | I2 moves toward HOLDS: the candidate→inspector edge finally exists, so one person resolves across all three pools |
| **I6** | PARTIAL → **less partial**: `candidates.inspector_id` and `person_ref` gain audit entries. Still PARTIAL (`users.inspector_id`, the bridge) |
| **I7** | unchanged — ambiguity still refuses to resolve itself |
| **I15 · I16** | unchanged. I16 stays **PARTIAL** (Q5/Q11) |
| **I22 · I42** | **PARTIAL → HOLDS for the conversion path**, which is the case that made them PARTIAL. Still PARTIAL overall while R20 is open and dangling states exist |
| **I23 · I25 · I27** | unchanged — Batch 2 adds no read-path write and asks every gate at the function |
| **I28 · I29** | extended to the new axis by **U4** |
| **I30** | **VIOLATED → HOLDS** — this is the invariant Batch 2 exists to close |
| **I32** | unchanged — "keep separate" is still unrecordable (**R21**) |
| **I33** | partially improved: the new ledger row is reversible; `candidates.inspector_id` is not |
| **I39** | unchanged — still NOT ESTABLISHED, and cannot be established until convergence actually happens |
| **I41** | unchanged — audits stay outside the transaction, by design |

**One new invariant proposed, and only one:**

> **I43 — Declaring two records the same person must never make a third record a
> different person.**
> *Owning domain:* Identity.
> *Testable condition:* link A+B, link C+D, then link B+C; assert all four share
> one reference and no group is left behind.
> *Status today:* **VIOLATED** — proved in the audit §2.

No other invariant is added. The register is not inflated.

## 17. File / function / route changes proposed

| # | File | Change | Finding | Risk | Regression surface |
|---|---|---|---|---|---|
| 1 | `lib/recruit.php` *(or a small new recruitment-side function)* | the conversion, lifted out of the route: gates, transaction, ledger write, audit | A, C, D | **High** — the hire path | Recruitment, Operations, Money, Phase 4/5 |
| 2 | `lib/ops.php:5392–5427` | route calls that function; keeps its own gate | A | High | as above |
| 3 | `lib/connect_identity.php` | **U4** — a fourth live key for the candidate↔inspector axis, its resolver, and the axis predicate | A, C | Medium | Marketplace, Recruitment |
| 4 | `lib/recruit.php` `person_link_rows()` | compute the whole group; audit; repair a split on encounter | B | Medium | Recruitment |
| 5 | `lib/ops.php` | one `system_status()` row for contradictory/dangling identity states | E, F | Low | Dashboard |
| 6 | `lib/ops.php` | a read-only reconciliation report behind the existing People/identity screens | E, F | Low | — |
| 7 | `docs/02-permission-matrix.md` | same commit, if §12's entitlement decision changes who can convert | — | — | — |
| 8 | `tests/test_p6_batch2.php` + workers | the battery | — | — | — |

**New components proposed: one** — the conversion function. It is not an engine;
it is the route's own code, moved somewhere it can be tested and attacked.

## 18. Database changes proposed

| Object | Change | Destructive? |
|---|---|---|
| `cx_identity_link.uq_cand_insp` | new `INT NULL` live key (**U4**) | no — additive |
| `ux_cx_idlink_cand_insp` | new UNIQUE index | no |

**No new table. No column removed or retyped. No row deleted. No business field
written.** Nothing is added to `inspectors`, `candidates` or `users`.

> **U4 constrains the candidate side only** — one live candidate↔inspector link
> per candidate. It deliberately does **not** constrain the inspector side, so a
> re-hire (a second candidate record converting to the same person) is not
> forbidden by a technical rule that no business decision has made.

## 19. Migration strategy

Identical to Batch 1's, which is proven on both engines:

- **Forward-only, additive, idempotent, non-destructive**, inside
  `connect_identity_migrate()`, behind `db_epoch()`.
- `ensure_column()` for the key; a plain `CREATE UNIQUE INDEX` inside `try/catch`.
- **Back-fill**: existing `candidates.inspector_id` pairs are written into the
  ledger as historical links. **Read-only on `candidates`** — the column is not
  modified.
- **Legacy duplicates**: if one candidate already has two conversions (possible
  only through the race in Finding A), the index is **skipped** and the duplicate
  **reported**, exactly as U1/U2/U3 do. Nothing merged.
- Multiple NULLs in a UNIQUE index on both engines; **no driver branch**.
- **Deployment cannot fail**; the worst case degrades to today's behaviour.

## 20. Test plan — written **before** implementation, baselined against current code

| | Test | Asserts |
|---|---|---|
| A1 | **Conversion race** — 3 real processes, 1 candidate | exactly **one** inspector, no orphan, loser told the truth |
| A2 | Sequential retry | no second inspector |
| A3 | Rollback on B affecting 0 rows | **no orphan inspector** |
| A4 | Rollback on U4 violation | no orphan |
| A5 | `emp_code` and `home_office_id` populated | read back from the row |
| A6 | Ledger row created | `connect_person_resolve('candidate', X)` reaches the inspector |
| A7 | Downstream unaffected | `requisitions.hired_inspector_id`, `reqf_sync`, Phase 4 credit unchanged |
| B1 | **A+B, C+D, then B+C** | all four share one reference; **no group left behind** (invariant **I43**) |
| B2 | Repair on encounter | a pre-split group is restored, and audited |
| C1–C4 | Entitlement · permission · scope · refusal ordering | as Batch 1 |
| D1 | Resulting-state scope | a converted inspector lands in the right branch |
| E1 | Cross-tenant | conversion cannot use another tenant's ids |
| F1–F3 | U4 by direct SQL · history unconstrained · re-hire allowed | database-enforced |
| G1 | Two candidates → one inspector | **allowed** (the negative that proves U4 is not over-tight) |
| H1 | Legacy duplicate migration | index skipped, both rows kept, reported |
| I1 | Audit attributable for conversion, refusal and person link | registered kinds, real entity ids |
| J1 | Detection report | every contradictory state in the audit §6 that is marked detectable, is detected |
| N–S | Full regression | Operations · Workforce · Recruitment · Marketplace · Reporting · Money · Dashboard **+ the locked Phase 5 KPI battery** |

Every assertion reads business state. **Both engines; MariaDB authoritative.**

## 21. Mutation plan

| # | Mutation | Must be caught by |
|---|---|---|
| N1 | remove the transaction from the conversion | A1, A3 |
| N2 | restore the read-then-write guard as the only protection | A1 |
| N3 | drop `WHERE inspector_id IS NULL` from write B | A1, A3 |
| N4 | skip the ledger write | A6 |
| N5 | never build U4 | F1 |
| N6 | set U4 on unlinked rows too | F2 |
| N7 | **add an inspector-side key to U4** (forbidding a legitimate re-hire) | G1 |
| N8 | swallow the U4 conflict and report success | A1, A4 |
| N9 | leave `home_office_id` NULL again | A5, D1 |
| N10 | leave `emp_code` blank again | A5 |
| N11 | `person_link_rows()` reverts to updating only the named ids | **B1** |
| N12 | the group repair silently changes data without auditing | B2, I1 |
| N13 | the detection report auto-repairs a contradictory state | J1 |
| N14 | conversion audit reverts to no entry | I1 |
| N15 | conversion stops asking entitlement / scope | C1–C4, D1 |
| N16 | cross-tenant id accepted | E1 |

**A FATAL is not a catch.** Clean baseline mandatory. No test weakened, deleted
or skipped. Own database and workspace per mutant.

## 22. Regression plan

Every surface the audit names as a consumer: **51 files read `inspectors`**.
Operations (allocation, schedule, timesheet, equipment, utilisation) ·
Workforce · Recruitment (M4 boundary, M5 accountability, M6 seat gate, Phase 4
allocation, Phase 5 KPI) · Marketplace · Reporting · Money · Dashboard.

**The locked Phase 5 KPI battery must be numerically identical.** Full suite on
SQLite, then on **MariaDB restarted between batteries**.

## 23. Risks

| Risk | Mitigation |
|---|---|
| The hire path is the most business-critical route in the product | Lift it into a function first, with the battery green, before changing behaviour |
| A transaction around the hire could interact with M6/Phase 4 compensators | They run **before** and **outside** the boundary; unchanged, and asserted by A7 |
| Back-filling historical conversions into the ledger could surface duplicates | The Batch 1 protocol: skip the index, report, merge nothing |
| Requiring Connect for the ledger write would make recruitment unsellable alone | §12 recommends skipping the ledger row instead — **owner decision** |
| Repairing split person groups touches existing data | It is the one mechanical repair, restores a grouping the system broke, and is audited |
| Scope creep into convergence | Scope is fixed at S1–S7; everything else is listed in §3 |

## 24. Deferred items

**R20** (general inspector uniqueness) · **R21** (reversal, "keep separate") ·
**R18** in full (`users.inspector_id`, the per-application bridge) · **R23**
(taxonomy) · **R4/R27** (organisation) · **R30** (`partner_contacts`) · **R31**
(portal e-mail) · repair of contradictory states 3, 6, 7, 10, 12, 13, 16 ·
`inspector_login_conflict()` coverage and index · the people form's missing
duplicate check · **Q1–Q18**, all still open.

## 25. Business decisions required

| # | Decision | Recommendation |
|---|---|---|
| **BD1** | **Which branch does a converted inspector belong to?** | the requisition's branch, falling back to the converting actor's |
| **BD2** | **Must Connect be held to convert a candidate to an inspector**, now that the conversion writes the ledger? | **No** — skip the ledger row without Connect; the conversion stays transactional and U4 still applies. Recruitment must not become unsellable alone |
| **BD3** | **General inspector uniqueness (R20)** | **Leave OPEN.** No viable key exists today (§8) |
| **BD4** | May Batch 2 **mechanically repair** person groups the old code split? | **Yes** — it restores a grouping the system broke, needs no judgement about who anyone is, and is audited |
| **BD5** | Does `person_ref` become a canonical person identifier? | **NOT ASKED HERE** — Q3/Q7/Q13 stay open. Batch 2 only stops it corrupting itself |

**None of BD1–BD5 is decided by this plan.** Implementation does not begin until
BD1, BD2 and BD4 are answered; BD3 and BD5 stay open by design.

## 26. Acceptance criteria

Batch 2 may be marked ACCEPTED only when:

- One candidate cannot produce two inspector records — **proved by real
  concurrent processes**, not sequentially.
- A failed conversion leaves **no orphan**.
- The converted inspector carries an `emp_code`, a branch, and a **ledger row**.
- `connect_person_resolve()` reaches the inspector from the candidate.
- **U4** is enforced by the database; legacy duplicates are reported, never merged.
- A re-hire (two candidates, one person) is **still allowed** — the negative.
- **Invariant I43 holds**: linking never splits a group.
- Conversion, refusal, rollback and person linking are **attributably audited**.
- Contradictory and dangling states are **reported**; nothing is auto-repaired
  except the split-group case, which is audited.
- Full regression green on SQLite **and MariaDB**, with the Phase 5 KPI battery
  numerically identical.
- **All mandatory mutations caught**; no FATAL counted as a catch; no test
  weakened, deleted or skipped.
- **I30 moves to HOLDS. I22/I42 move to HOLDS for the conversion path** and are
  stated as still PARTIAL overall.
- **I16 stays PARTIAL. Q1–Q18 stay open. R20 stays deferred.**
- Known limitations documented and **not rounded up**.

**Phase 6 will not be complete after Batch 2.**

---

## What this plan did NOT do

- **No product code, schema, migration, route or permission was changed.**
- **No defect from the audit was fixed.**
- **No Q1–Q18 was answered**; no Person hub, no universal person key, no branch
  ownership of relationships, no automatic merge, no identity uniqueness on
  e-mail or mobile, no automatic candidate→inspector merge, no organisation
  convergence.
- **Batch 1 was not touched.**
- Five decisions are marked **OWNER DECISION REQUIRED** rather than assumed.
