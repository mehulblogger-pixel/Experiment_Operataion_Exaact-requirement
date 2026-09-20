# Phase 6 · Batch 2 — Pre-implementation audit **R2** (re-audit)
## Person relationship integrity & conversion safety

*Read-only. No product code, schema, migration, route or permission was touched,
and no defect recorded here was fixed.*

---

## 0. Read this section first — why this file exists and is not the one you named

The instruction asked for `docs/phase6/P6-BATCH2-PREIMPLEMENTATION-AUDIT.md` and
`docs/phase6/P6-BATCH2-IMPLEMENTATION-PLAN.md`.

**Both already exist**, created in commit `c617a40`, and the batch they describe
was implemented in `d5aa8cc` and accepted. The programme record is:

| File | Commit | State |
|---|---|---|
| `P6-BATCH2-PREIMPLEMENTATION-AUDIT.md` | `c617a40` | shipped |
| `P6-BATCH2-IMPLEMENTATION-PLAN.md` | `c617a40` | shipped |
| `P6-BATCH2-COMPLETION-REPORT.md` + test / mutation / security / reconciliation results | `d5aa8cc` | **ACCEPTED** |
| Batch 3 and Batch 3 corrective | through `dc8d3c0` | **ACCEPTED / LOCKED** |

Overwriting those two files would erase an accepted record and would break the
standing rule that historical records are never silently rewritten. So the
originals are untouched and this re-audit is written beside them as **R2**.

**This is not a formality.** Re-running the audit against today's code turns out
to be worth doing, because the subject matter of the instruction is exactly what
is currently open and blocked: the conversion's `team_role` gap (Q32, now decided
by the owner) and inspector duplicate protection (R20 / RB-3, still open). This
document supplies the evidence those need and it records **eight findings that
did not exist in, or were not visible to, the original Batch 2 audit** — one of
them material and reproducible on the production engine.

**Baseline for this re-audit:** `7d485c6` (Batch 3 corrective accepted; Phase 7
entry audit and Q32 recorded). Working tree clean.

---

## 1. In plain words

Three sentences, each proved by running the shipped system, not by reading it:

1. **Hiring four different people at the same instant gives all four the same
   employee number.** Not a near-miss — four processes, four people, one number,
   `EMP01` four times, on MariaDB. Nothing in the database prevents it, because
   the `inspectors` table carries **no index at all** on any identifying column.
2. **Every person hired through recruitment is silently filed as a field
   inspector.** The conversion never sets `team_role`, so the column default
   decides who is deployable. The owner has now decided this must be deliberate
   (Q32), which makes it a live gap rather than a latent one.
3. **Nothing anywhere compares an applicant against the existing workforce.** The
   applicant duplicate check never looks at the staff table; the identity
   suggester only bridges marketplace professionals to staff. So "we already
   employ this person" is a question the system is not able to ask.

Against that, what Batch 2 built **holds under attack and was re-proved here**:
four processes converting *one* application at the same microsecond produced
exactly one team member, three deterministic `RACE_LOST` refusals, one ledger row
and zero orphans.

---

## 2. Method

| | |
|---|---|
| **Sweep** | every production path in `phpapp/lib`, `phpapp/views`, route dispatch, seeds, CLI and diagnostics that creates or changes a Candidate / Inspector / Professional / User relationship |
| **Probes** | behavioural, against **throwaway databases only** — a temp SQLite file and a scratch MariaDB schema. Probe scripts were written outside the repository and no file inside `phpapp/` was created or changed |
| **Concurrency** | real operating-system processes via `proc_open`, independent PDO connections, synchronised on one wall-clock microsecond barrier, one-time costs (login, branch resolution, migration, code generation) paid **before** the barrier |
| **Engines** | SQLite 3.45.1 for schema inspection, **MariaDB 10.11.14 for every concurrency claim** — MariaDB is authoritative and SQLite's transactional DDL and coarse locking hide exactly this class of defect |
| **Not done** | nothing was repaired, no migration was written, no historical record was modified |

---

## 3. What Batch 2 already closed — re-verified, not assumed

The instruction's §4–§5 ask questions the shipped code already answers. Re-run on
MariaDB rather than taken on trust:

**Probe — four real processes, ONE application, one barrier**

```
{"cand":1,"ok":false,"code":"RACE_LOST","insp":0}
{"cand":1,"ok":false,"code":"RACE_LOST","insp":0}
{"cand":1,"ok":false,"code":"RACE_LOST","insp":0}
{"cand":1,"ok":true ,"code":"CONVERTED","insp":2,"emp":"EMP01","role":"FIELD"}

inspectors rows created : 1
candidate.inspector_id  : 2
live conversion ledger  : 1
orphan team members     : 0
```

| Instruction asks | Answer in today's code | Where |
|---|---|---|
| which function creates the Inspector | `rcv_convert()` — the single conversion door | `lib/recruit.php:1273` |
| which function links the Candidate | the same one, inside the same transaction | `lib/recruit.php:1345` |
| multiple writers? | **no** — one production caller, `lib/ops.php:5777` | — |
| transactional? | **yes** — `BEGIN → inspector → conditional link → ledger → COMMIT` | `lib/recruit.php:1322–1370` |
| inspector created but link fails | whole transaction rolls back; nothing survives | `:1357` |
| retried | second attempt is refused `ALREADY` on `candidates.inspector_id` | `:1281` |
| two processes at once | one wins, the rest get `RACE_LOST`; the conditional `UPDATE … WHERE inspector_id IS NULL OR inspector_id=0` is the ceiling, and `uq_cand_insp` (U4) is the database backstop | `:1345`, `lib/connect_identity.php:71` |
| already converted | refused before any write | `:1281` |
| candidate already has an identity relationship | conversion axis is separate from the candidate↔professional axis, so both may exist (invariant I1) | `connect_identity_keys()` |
| session invalid | `is_coordinator_level()` is asked **by the action**, not inherited from the route (I27) | `:1277` |
| requisition changes state mid-conversion | M4's `rexec_block_reason()` is re-asked inside the door | `:1288` |
| borrowed transaction | detected; on failure the exception is **re-thrown** so the caller unwinds, rather than reporting failure over committed work | `:1320`, `:1366` |

**Conclusion.** Requirement **R2 is closed** and invariant **I30 holds in the
shipped code**. See finding **N6** — the invariants register has not been told.

---

## 4. Audit areas A–V — sweep result

Only genuinely new observations are recorded. "As Batch 2" means the shipped
behaviour matches what the accepted Batch 2 documents describe and was
re-verified here.

| | Area | Finding |
|---|---|---|
| **A** | Candidate → Inspector conversion | As Batch 2, and sound. **`team_role` is absent from the INSERT column list** — see **N3** |
| **B** | Candidate → Professional | `connect_identity_candidate_link_create()`, U3-protected, coordinator-gated, reversible |
| **C** | Professional → Inspector | `connect_identity_link_create()`, U1/U2-protected. Unchanged |
| **D** | User → Inspector | `link_inspector_users()` (`lib/ops.php:1374`) is authorised, transactional and idempotent; the People form writes the column directly (`lib/ops.php:8937`, `:8986`) |
| **E** | `candidates.inspector_id` | Single production writer, conditional, inside the transaction |
| **F** | `users.inspector_id` | Three writers: reconciliation, user create, user edit. The edit path has **no `inspector_login_conflict()` re-check documented at the column write** — collisions are reported after the fact by `inspector_shared_login_count()` |
| **G** | `cx_identity_link` | Four axes, four live keys (U1–U4), generated-key discipline from Batch 3 not yet applied here — the keys are still **set by the writer** (`connect_identity_keys()`), which is the Batch 1 residual. See **§7** |
| **H** | `candidates.person_ref` | Additive grouping key, no unlink route (R21) |
| **I** | `person_link_rows()` | Closure-correct since Batch 2 — linking can no longer split a group (I43). Audited via `rcv_log()` |
| **J** | person grouping / suggestions | `connect_identity_suggestions()` bridges **professional ↔ inspector only**. See **N4** |
| **K** | hiring conversion functions | One: `rcv_convert()` |
| **L** | candidate-stage conversion paths | One: `lib/ops.php:5775`, gated on `$_POST['make_inspector']` |
| **M** | duplicate checks | `cand_find_duplicates()` (candidate↔candidate), `cand_submission_dupes()` (same client). **Neither queries `inspectors`.** Scan is capped at 500 rows — **N5** |
| **N** | inspector creation | **Four production creators** — `recruit.php:1332`, `ops.php:1429` (`team_member_create()`), `ops.php:4490` (the Inspector form), `trace_audit.php:49` (diagnostic). See **N7** |
| **O** | inspector duplicate detection | **None.** No index, no probe, no report. See **N1**, **N2** |
| **P** | identity history | `cx_identity_link` keeps every LINKED/UNLINKED row; the three column mechanisms keep none |
| **Q** | unlink / reversal | `connect_identity_unlink()` only. `person_ref`, `candidates.inspector_id` and `users.inspector_id` have no reversal (R21 / R18) |
| **R** | audit trails | `act_log()` via `rcv_log()` for conversion and person-link. The column mechanisms still write none (R18) |
| **S** | routes that trigger conversion/linking | Six — see **§11** |
| **T** | imports / seeds / CLI | Seeds create inspectors directly and bypass every gate; correct for seeds, but they are the reason `emp_code` collisions already exist in demo data |
| **U** | background paths | None. Nothing converts or links on a timer |
| **V** | direct internal callers | `rcv_convert()` has exactly one; `team_member_create()` has two; the raw INSERTs have none outside their own file |

---

## 5. New findings

### N1 · **MATERIAL** — four people hired at once get one employee number

`next_emp_code()` (`lib/ops.php:1546`) reads the highest existing code and adds
one. There is no reservation, no lock and no unique constraint behind it.

**Probe, SQLite — two calls, no insert between:**

```
first call  : EMP01
second call : EMP01
```

**Probe, MariaDB — four real processes, four different applications, one barrier:**

```
{"cand":1,"ok":true,"code":"CONVERTED","insp":3,"emp":"EMP01","role":"FIELD"}
{"cand":2,"ok":true,"code":"CONVERTED","insp":4,"emp":"EMP01","role":"FIELD"}
{"cand":3,"ok":true,"code":"CONVERTED","insp":2,"emp":"EMP01","role":"FIELD"}
{"cand":4,"ok":true,"code":"CONVERTED","insp":1,"emp":"EMP01","role":"FIELD"}

emp_codes issued : EMP01 , EMP01 , EMP01 , EMP01
distinct         : 1 of 4
VERDICT          : COLLIDED
```

All four conversions reported success. Each is individually correct — Batch 2's
per-application ceiling did its job, one team member per application, no orphan.
The collision is on the **business identifier**, which Batch 2 never protected
because Batch 2's ceiling is per-candidate.

**Why it matters.** `emp_code` is the number Operations, attendance, punch,
vouchers and payroll export use to mean *this person*. Four people sharing it is
not a cosmetic clash.

**Why it has not been seen.** It needs simultaneity. A busy recruitment day with
several coordinators accepting offers is exactly when it happens, and exactly
when nobody is looking.

### N2 · **MATERIAL** — the `inspectors` table carries no index at all

```
PRAGMA index_list('inspectors')  →  (empty)
```

Not on `emp_code`, not on `email`, not on `mobile`. A direct `INSERT` of a second
row with an identical employee code, e-mail and mobile is **accepted**:

```
second row with identical code+email : ACCEPTED (ids 1, 2)
rows now sharing emp_code EMP-X1     : 2
```

Every protection that exists on this table today is a PHP check, which invariant
**I28** exists to forbid. Batch 1 and Batch 3 closed exactly this shape of hole
on `cx_identity_link`, `partner_contacts` and the portal accounts. `inspectors`
was never in scope and is the last one standing.

### N3 · **MATERIAL** — every recruited hire is silently a field inspector

`rcv_convert()`'s INSERT column list is
`name, first_name, middle_name, last_name, email, mobile, trade_id, skill_ids,
sbus, sbu, designation, staff_kind, emp_code, home_office_id, agency_id,
roll_type, agency_name, agency_cost, placement_fee, fee_status, guarantee_upto,
status, created_at` — **`team_role` is not in it**, so the column default
`'FIELD'` decides.

Confirmed in the concurrency probe: every converted row came back `"role":"FIELD"`.
By contrast `team_member_create('…','COORD',…)` returns `COORD` correctly, and the
Inspector form validates the value explicitly (`lib/ops.php:4470`). Only the
recruitment door is silent.

`team_role` is not decoration. `inspectors_list()` ranks `FIELD` above `COORD`
above `OFFICE`, so it drives who appears at the top of every allocate picker.

The owner decided on 2026-09-20 (Q32) that `team_role` must be deliberately
selected or derived, never silently defaulted. This finding is that decision's
implementation target.

### N4 · **MATERIAL** — nothing compares an applicant against the existing workforce

The instruction's §6 asks what happens when an Inspector already exists for the
human being hired. The answer is: **the system never asks.**

- `lib/recruit.php` contains **no `FROM inspectors` query anywhere.**
- `cand_find_duplicates()` compares candidates to candidates only.
- `connect_identity_suggestions()` joins `cx_professionals` to `inspectors` — an
  applicant who was never a marketplace professional is invisible to it.
- `identity_state_findings()` reports `TWO_INSPECTORS_ONE_PERSON` only when the
  link travels through a marketplace professional.

So the instruction's four-way classification — proven same / possible same /
different / ambiguous — has **no engine on the candidate→inspector axis at all**.
Not a weak engine; no engine.

The production path that feeds it is ordinary: the public careers intake
(`lib/careers.php:124`) de-dupes **per requisition only**, so one human applying
to two openings is two applications by design (invariant I3, correct), and both
can be accepted and both converted.

### N5 · **MODERATE** — duplicate detection silently stops at 500 applications

`cand_find_duplicates()` scans `… ORDER BY id DESC LIMIT 500`. In a tenant with
more than 500 applications, a duplicate older than the newest 500 is not found,
and the screen says nothing about having looked at only part of the register. A
check that quietly becomes partial is worse than one that is absent, because it
is trusted.

### N6 · **MODERATE** — the invariants register disagrees with the shipped code

`docs/phase6/P6-BUSINESS-INVARIANTS.md` still reads:

- **I30** — *"**VIOLATED** — two inspector rows accepted, candidate points at the
  second, first stays live and orphaned (§2 audit; **R2**)"*
- **I22** / **I42** — *"The hiring conversion (**R2**) … **deferred**"*
- crosswalk line 220 — *"R2, R20 … **deferred**"*

§3 above proves R2 is closed in the shipped code. The register was never updated
when Batch 2 shipped. `CLAUDE.md` requires that the docs and the code never
disagree, so this is a defect in its own right. **Recorded, not fixed.**

### N7 · **MINOR** — four inspector creators, three different rulebooks

| Creator | `team_role` | `emp_code` | Branch |
|---|---|---|---|
| Inspector form · `ops.php:4490` | validated against FIELD/COORD/OFFICE | auto, **but an operator may type any value** | from the form |
| `team_member_create()` · `ops.php:1429` | parameter, validated, default FIELD | auto `next_emp_code('ASSET')` | parameter |
| `rcv_convert()` · `recruit.php:1332` | **absent → column default** | auto `next_emp_code($kind)` | BD1 resolution |
| `trace_audit.php:49` | absent | **fixed constant** `TRACE_AUD_INSP_EMP` | none |

Four doors, three answers. The operator-typed `emp_code` on the form is a second,
non-concurrent route to the same collision as N1.

### N8 · **NOT A DEFECT, recorded for completeness**

The careers intake creating two applications for one human across two openings is
**correct** and is invariant I3. It is listed only because it is the supply line
for N4, and a future reader will otherwise mistake it for the bug.

---

## 6. The five identity mechanisms — current state

| | Mechanism | Purpose | Writer | Authorisation | Scope | Audit | Reversal | DB uniqueness | Keep? |
|---|---|---|---|---|---|---|---|---|---|
| 1 | `cx_identity_link` | the one ledger — professional ↔ inspector ↔ candidate | `connect_identity_*`, `rcv_convert()` | own gate + entitlement | per-end visibility (Q5/Q11 open) | **yes**, attributable | **yes**, `UNLINKED` + history kept | **yes** — U1–U4 | **yes**, the target |
| 2 | `candidates.inspector_id` | "this application became this team member" | `rcv_convert()` only | conversion gate | candidate SBU | via `rcv_log()` | **no** | conditional UPDATE + U4 | keep; readers depend on it |
| 3 | `users.inspector_id` | "this login is this team member" | reconciliation + user form ×2 | People right | branch | **no** (R18) | **no** | **none** — collisions reported only | keep; it is the login model |
| 4 | `candidates.person_ref` | "these applications are one human" | `person_link_rows()`, `person_group_repair()` | coordinator + per-end scope | candidate SBU | via `rcv_log()` | **no** (R21) | none — it is a grouping key, not a constraint | keep |
| 5 | per-application bridge (`cx_applications.inspector_id` + `applicant_professional_id`) | the original marketplace bridge | marketplace | marketplace gate | tenant-global | no | n/a | none | keep; historical |

**They are not contradictory today.** `identity_state_findings()` reports six
contradiction kinds across them (§7 below). What is missing is a **sixth**
detector covering the workforce axis, because mechanism 3 and the `inspectors`
table have no uniqueness and no similarity probe at all.

---

## 7. Contradictory state matrix

`R` = reported by `identity_state_findings()` today.

| State | Verdict | Repairable | Human review | Detected |
|---|---|---|---|---|
| Candidate, no Inspector | **valid** | — | — | n/a |
| Candidate → Inspector | **valid** | — | — | n/a |
| Candidate → Professional | **valid** | — | — | n/a |
| Candidate → both | **valid** (I1) | — | — | n/a |
| Candidate → different Professional and Inspector | **ambiguous** | no | **yes** | **R** `TWO_INSPECTORS_ONE_PERSON` |
| Candidate has `person_ref` | **valid** | — | — | n/a |
| One human, several candidate rows | **valid** (I3) | — | — | n/a |
| Candidate → Inspector that no longer exists | **invalid** | no | **yes** | **R** `CANDIDATE_INSPECTOR_MISSING` |
| Login → Inspector that no longer exists | **invalid** | no | **yes** | **R** `USER_INSPECTOR_MISSING` |
| Converted, no ledger row | **valid** (BD2 STATE B) | **yes**, later | no | **R** `CONVERTED_NO_LEDGER` |
| Column and ledger name different inspectors | **invalid** | no | **yes** | **R** `CONVERSION_DISAGREES` |
| One Inspector, two active logins | **invalid** | no | **yes** | **R** `INSPECTOR_TWO_LOGINS` |
| Link is `UNLINKED`; a live link also exists | **valid** — history | — | — | n/a |
| **Two Inspectors sharing one `emp_code`** | **invalid** | **no — the number must be re-issued by a human** | **yes** | **NOT DETECTED — N1/N2** |
| **Two Inspectors sharing one e-mail or mobile** | **ambiguous** — a re-hire or two agencies is legitimate | no | **yes** | **NOT DETECTED — N4** |
| **An applicant who is already on staff** | **ambiguous** | no | **yes** | **NOT DETECTED — N4** |

The last three rows are the whole of R20.

---

## 8. R20 assessment — should inspector duplicate protection be implemented now?

Batch 2 deferred R20 (owner decision BD3) because a business identity key could
not be established safely. That reasoning was right and **is still right for
"one person = one inspector"**. But it was applied to the whole of R20, and the
re-audit shows R20 is two different questions wearing one number.

**Split it.**

### R20-a · the employee number — **implement**

`emp_code` is not an inference about who somebody is. It is an **administrative
identifier the business itself issues, controls and already treats as unique** in
every downstream consumer. Making the database enforce what the business already
believes is not a new business rule; it is the removal of a lie.

It also has the only property that makes a constraint safe to add: a collision is
**always** wrong. There is no legitimate reason for two live team members to
share one employee number — unlike an e-mail, which two records may share for a
genuine re-hire.

Two halves, and both are needed:

1. a **uniqueness constraint** over the live employee number, enforced by the
   database; and
2. a **generator that reserves**, so the constraint is a backstop rather than the
   thing users collide with. A unique index alone converts N1 from a silent
   collision into a visible failed hire, which is better but not good.

The Batch 3 corrective already built the tool: `ensure_unique_generated_index()`
(`lib/db.php:397`) installs a generated live-key column plus a unique index, with
a schema-guard ledger, a dirty-data refusal path and a reconcile hook — and it is
proven on both engines. R20-a is a **reuse**, not a new engine.

**Existing data must be assumed dirty.** Seeds and the operator-typed form have
had years to create collisions. The guard's `DIRTY` path exists for exactly this:
report and leave the index off rather than fail a tenant's boot.

### R20-b · "is this applicant already on our staff?" — **detect only, do not constrain**

E-mail, mobile and name are **evidence about a human**, not identifiers the
business issues. Two live inspector rows sharing an e-mail may be:

- one person re-hired on new terms — **legitimate**;
- one person supplied through two agencies — **legitimate**;
- a shared family or site mailbox — **legitimate**;
- the same person entered twice — **a duplicate**.

A constraint cannot tell these apart, and choosing between them is deciding who a
person is. Batch 2's BD3 reasoning holds exactly here.

What is missing is not a constraint but the **question**: the conversion should
say *"three people already on your team share this mobile number — is this one of
them?"* and let a human answer. That is `find_duplicate_partner()`'s pattern
applied to people, and it is the engine finding **N4** says does not exist.

### Verdict

| | Recommendation |
|---|---|
| **R20-a** — live `emp_code` uniqueness + reserving generator | **IMPLEMENT** — reuse `ensure_unique_generated_index()`; no new business rule, no identity inference |
| **R20-b** — applicant-vs-workforce similarity | **IMPLEMENT AS DETECTION ONLY** — surface, never block, never merge |
| **"one person = one inspector"** | **REMAINS OPEN.** Nobody has decided it, and it is not decidable from the evidence the system holds |

### What is explicitly rejected, and why

| Candidate key | Rejected because |
|---|---|
| **login / username** | not every team member has a login; `inspector_login_conflict()` already covers the reverse direction |
| **e-mail** | legitimately shared; identity inference the owner has not authorised |
| **mobile** | same, and more so — site and family numbers are common in this industry |
| **external identity** | not held for internal staff |
| **name** | never an identifier |

---

## 9. Transaction boundary — current and recommended

**Today, and it is correct:**

```
BEGIN                                        (only if the caller has not already)
  A  INSERT inspectors                       the team member
  B  UPDATE candidates SET inspector_id=?    WHERE id=? AND (inspector_id IS NULL OR =0)
        └─ 0 rows matched → RACE_LOST → rollback
  C  INSERT cx_identity_link                 only when the workspace may record identity
        └─ refused → RACE_LOST → rollback
COMMIT
  D  act_log()                               OUTSIDE — a failed observation is never a failed
                                             transaction (I41)
```

**Recommended, and the boundary does not grow:**

| Write | In? | Why |
|---|---|---|
| A · inspector | **yes** | unchanged |
| A′ · **`team_role` on that same INSERT** | **yes** | same statement, no new write — N3 |
| A″ · **reserve `emp_code` under the uniqueness constraint** | **yes** | it is part of creating the person; outside the transaction a rollback would burn a number — N1 |
| B · candidate link | **yes** | unchanged |
| C · ledger | **yes** | unchanged |
| D · audit | **no** | I41 |
| requisition `hired_inspector_id` + `reqf_sync()` | **no** | downstream consequence; currently at `ops.php:5790`, correctly outside |
| duplicate **detection** (R20-b) | **no** | it runs **before** the transaction and produces a question, not a write |

Two of the three new writes land in statements that already exist. The boundary
is unchanged in shape and is still the smallest correct one.

**Failure paths:** unchanged — every failure rolls back to no team member, no
link, no ledger row, and is audited as `IDENTITY_REFUSED` against the candidate.
On a **borrowed** transaction the exception is re-thrown so the caller unwinds,
because a reported failure that commits a row is the exact defect Batch 2 removed.

---

## 10. Concurrency — what must be tested

Real OS processes, independent connections, wall-clock microsecond barrier, all
one-time costs paid before the barrier, **MariaDB authoritative**.

| | Case | Status today | Expected after |
|---|---|---|---|
| **A** | two conversions, two different applications | **COLLIDES on `emp_code`** — proved, 4/4 | distinct numbers, or a deterministic refusal; never a silent duplicate |
| **B** | same application converted twice | **PASSES** — 1 team member, 3 × `RACE_LOST`, 0 orphans | unchanged |
| **C** | two applications resolving to the same human | creates two team members, **undetected** | still two — but the desk was asked first (R20-b) |
| **D** | conversion while another process links a Professional | separate axes, both succeed | unchanged |
| **E** | retry after a failed transaction | clean retry | unchanged |
| **F** | stale browser re-posting a conversion | `ALREADY` | unchanged |
| **G** | concurrent boots installing the R20-a key | untested — the key does not exist | all succeed, none reports failure (the Batch 3 corrective `CG13–CG18` shape) |
| **H** | form-typed `emp_code` colliding with a generated one | **accepted today** | refused by the constraint |

Case A is the new one and it is the one that already fails.

---

## 11. Action-path matrix

Every production path that can create or change a Candidate / Inspector /
Professional / User relationship.

| # | Path | Route / entry | Actor gate | Tenant | Scope | Identity check | Dup protection | Txn | Concurrency | Audit | Rollback |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Accept + convert | POST `/candidate-stage` → `rcv_convert()` | `is_coordinator_level()` **in the action** | structural | candidate SBU | U4 + conditional UPDATE | per-candidate ✔ · **per-person ✘** | **yes** | **safe** | ✔ | ✔ |
| 2 | Link two applications | POST `/candidate-link-person` | coordinator | structural | **both ends** checked | closure-correct | n/a | single UPDATE | safe | ✔ | none needed |
| 3 | Link applicant ↔ professional | POST `/candidate-link-pro` | coordinator + entitlement | structural | ledger's own | U3 | ✔ | ledger | safe | ✔ | unlink |
| 4 | Unlink applicant ↔ professional | POST `/candidate-unlink-pro` | coordinator | structural | ledger's own | — | — | ledger | safe | ✔ | n/a |
| 5 | Link professional ↔ inspector | `/connect-identity` | admin + entitlement | structural | per-end | U1/U2 | ✔ | ledger | safe | ✔ | unlink |
| 6 | Reconcile unlinked logins | `link_inspector_users()` | `users.manage.*` **in the action** | structural | branch | — | idempotent | **yes** | safe | partial | ✔ |
| 7 | Add a team member | POST `/m/inspectors` | inspector-screen gate | structural | branch | **none** | **none** — `emp_code` may be typed | single INSERT | **unsafe — N1/N2** | ✔ | n/a |
| 8 | Create / edit a login | POST users form | `users.manage.*` | structural | branch | `inspector_login_conflict()` | reported after the fact | single | not re-checked at write | ✔ | n/a |
| 9 | Public application | POST careers | **none — public** | structural | requisition's | per-requisition only | **by design** (I3) | single | safe | ✔ | n/a |
| 10 | Marketplace application | `cx_applications` | marketplace gate | structural | tenant-global | bridge columns | n/a | single | safe | ✔ | n/a |

**Absent paths, recorded explicitly:** there is **no** CSV/bulk import of team
members, **no** API, **no** CLI and **no** background job that creates or changes
any of these relationships. Seeds and `trace_audit.php` write directly and bypass
every gate — correct for their purpose, and the reason demo data already carries
`emp_code` collisions.

---

## 12. Tenant, scope and authorisation

Reuses Batch 1 unchanged. No new scope engine, and none needed.

| | State |
|---|---|
| tenant isolation | **structural** — one database per tenant, no `tenant_id`; proven in Batch 1 against two real databases on both engines (I15) |
| candidate SBU scope | `connect_identity_scope_ok('candidate', …)` — SBU-only, because a candidate carries no branch |
| inspector branch/SBU scope | `connect_identity_scope_ok('inspector', …)` — `scope_allows()` |
| actor permission | asked by the action (I27) |
| recruitment entitlement | M4 `rexec_block_reason()` inside the door |
| Connect entitlement | `connect_identity_conversion_allowed()`, decided **before** the write, never a blocker (BD2) |
| **resulting-state scope** | **the gap.** The conversion checks the candidate the actor may open, and BD1 chooses the branch — but nothing asserts the actor may open the **branch the new team member lands in**. A recruiter scoped to Branch A converting an application against a Branch B requirement creates a Branch B person |

That last row is the instruction's §11 demand — *"evaluate the RESULTING state,
not merely the original candidate state"* — and the answer is that today it does
not. Whether it should is **Q5/Q11** (does a relationship carry its own branch?),
which is open. **Recorded, not decided.**

---

## 13. Audit and reversal

| Event | Audited today | Where |
|---|---|---|
| conversion requested | implicitly, by its outcome | — |
| conversion succeeded | **yes** — `IDENTITY_LINKED`, names the team member, the branch and its source, and whether the ledger row was written | `rcv_log()` |
| conversion refused | **yes** — `IDENTITY_REFUSED` with the reason | `rcv_log()` |
| conversion rolled back | **yes**, distinguishing "another process got there first" from a genuine failure | `rcv_log()` |
| identity link created / removed | **yes**, attributable | `connect_identity_*` |
| duplicate prevented | **partial** — `RACE_LOST` is audited; **`emp_code` collision is not, because it is not detected** |
| human confirmation | **no mechanism** — R20-b does not exist |
| ambiguous match | **no mechanism** — N4 |

**Reversal.** Only `cx_identity_link` reverses. `candidates.inspector_id`,
`users.inspector_id` and `person_ref` have no reversal path (R18 / R21, both
open). Nothing proposed here changes that, and no historical record should be
rewritten to create one.

---

## 14. Invariants

| | Status in the register | Status in the shipped code | Action |
|---|---|---|---|
| **I1** | HOLDS | HOLDS | — |
| **I2** | PARTIAL | **improved** — the conversion writes the ledger edge (Batch 2) | register stale (**N6**) |
| **I6** | PARTIAL | PARTIAL | — |
| **I7** | PARTIAL | PARTIAL | R20-b must not weaken it — **detect, never merge** |
| **I15** | HOLDS | HOLDS | — |
| **I16** | PARTIAL | PARTIAL | resulting-state scope is Q5/Q11 |
| **I22** | PARTIAL, *"R2 deferred"* | **R2 closed**; R20 still open | register stale (**N6**) |
| **I23** | HOLDS | HOLDS | — |
| **I25** | HOLDS | HOLDS | — |
| **I27** | HOLDS | HOLDS | — |
| **I28** | HOLDS | **does not hold for `inspectors`** — no index exists (**N2**) | R20-a closes it |
| **I29** | HOLDS | holds per-candidate; **fails per-person on `emp_code`** (**N1**) | R20-a closes it |
| **I30** | **VIOLATED** | **HOLDS** — re-proved §3 | register stale (**N6**) |
| **I32** | VIOLATED | VIOLATED (R21) | stays open |
| **I33** | PARTIAL | PARTIAL | — |
| **I39** | NOT ESTABLISHED | NOT ESTABLISHED | — |
| **I41** | PARTIAL | PARTIAL | audit stays outside the transaction |
| **I42** | PARTIAL | PARTIAL | R20-a + the new detector improve it |

**No new invariant is proposed.** Everything found is covered by I28, I29 and I7.
The register does not need inflating; it needs correcting (**N6**).

---

## 15. Classification

### MUST FIX

| | Finding | Why |
|---|---|---|
| **M1** | **N1 + N2** — live `emp_code` uniqueness, enforced by the database, plus a reserving generator | Four people sharing one employee number is wrong under every reading of the business. Invariants I28 and I29 both fail on this table |
| **M2** | **N3** — `team_role` set deliberately on conversion | Owner-decided (Q32). A one-column change to an INSERT that already exists |

### SAFE TO DEFER

| | Finding | Why |
|---|---|---|
| **D1** | **N5** — the 500-row duplicate scan | Real, but it degrades an advisory check. Fix it with the R20-b detector rather than on its own |
| **D2** | **N7** — four creators, three rulebooks | Converges once M1 and M2 land |
| **D3** | R18 · R21 · Q5/Q11 · resulting-state scope | Genuinely open questions, not defects to fix by inference |

### BUSINESS DECISION REQUIRED

| | Question |
|---|---|
| **B1** | **Is an existing employee number ever legitimately re-issued** — to a re-hire, or after a record is retired? The answer sets whether the key is over all rows or only live ones |
| **B2** | **What should the conversion do when an applicant matches somebody already on staff?** Warn and continue · require an acknowledgement · refuse. **Do not infer this** |
| **B3** | **Where is `team_role` captured** — the stage-move form, the offer, or the requisition/position? (Q32 item 2, still unanswered) |
| **B4** | **Default when a workspace has not configured capabilities** (Q32 item 3, still unanswered) |
| **B5** | **What a recruiter sees when acceptance is refused** (Q32 item 4, still unanswered) |

### NOT A DEFECT

| | |
|---|---|
| **X1** | **N8** — one human, several applications. Invariant I3, deliberate |
| **X2** | Seeds bypassing the gates. Correct for seeds |
| **X3** | BD2 STATE B — converted without a ledger row. Legitimate and already reported |
| **X4** | Two live team members for one human. **Not a defect until somebody decides it is** — a re-hire and a two-agency supply are both real |

---

## 16. Owner decisions required

> **OWNER DECISION REQUIRED — B1** · employee-number re-issue
> **OWNER DECISION REQUIRED — B2** · what the conversion does on a workforce match
> **OWNER DECISION REQUIRED — B3** · where `team_role` is captured
> **OWNER DECISION REQUIRED — B4** · default for an unconfigured workspace
> **OWNER DECISION REQUIRED — B5** · the wording and recovery path of a refused acceptance

B3–B5 are Q32's outstanding items, repeated here so this document stands alone.

**Not answered here, by instruction:** Q1–Q18. **Not invented here:** no Person
hub, no universal person key, no branch ownership of relationships, no automatic
merging, no identity uniqueness on e-mail or mobile, no automatic
candidate→inspector merge, no organisation convergence.

---

## 17. Verdict

Batch 2 as shipped is **sound and holds under attack**. Its ceiling is
per-application, and that ceiling works.

What the re-audit adds is that **the ceiling is in the wrong place for the
workforce**: nothing protects the person record itself, on any engine, at any
level. Four people can share an employee number, two team members can be the same
human with nothing to say so, and every recruited hire is silently a field
inspector.

Two of those are mechanical and should be fixed. The third is a question the
system has never been able to ask, and it should learn to ask it — and then stop,
and let a person answer.

*Nothing in this document has been implemented.*
