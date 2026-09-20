# Q32 — Workforce vs Inspector · Business Decision

**Status: OWNER HAS ANSWERED — model revised. Still analysis only; nothing implemented.**
See §19 (owner decisions) and §20 (the revised model) at the end, which supersede
the recommendation in §12 where they differ.
No production code, schema, migration, test or data was changed.

Baseline `8793b91` · 2026-09-20

Throughout, four labels are used and kept apart:
**FACT** (read from source) · **INFERENCE** (reasoned from fact) ·
**RECOMMENDATION** (mine) · **OWNER DECISION** (yours).

---

## 1 · Executive summary

**The single most important finding: EXAACT already has the classifier this
decision needs, and the recruitment conversion ignores it.**

`inspectors.team_role` holds `FIELD`, `COORD` or `OFFICE` and defaults to
`FIELD`. `rcv_convert()` — the only path from candidate to workforce — does not
set it. So **every person recruited through EXAACT is silently classified as
field technical staff**, whatever role they were hired for.

Two further facts reshape the question:

- **`inspectors` *is* the workforce table.** It carries `designation`,
  `home_office_id`, `reports_to_id`, `weekly_working_days`, `salary_ctc`,
  `leave_balance` — an employment record, not a technical-capability record. The
  name is historical.
- **There is no separate Workforce/Employee creation path.**
  `back_office_staff` exists but **no candidate path reaches it**; it is a
  standalone admin master.

**INFERENCE:** the choice is not "which new table do we build". It is
**"should the existing conversion set `team_role` deliberately instead of
defaulting it"**. That reframes Q32 from an architecture decision into a data-
quality decision, and makes the fix small.

## 2 · Current behaviour — the sixteen questions

| # | Question | Answer | Evidence |
|---|---|---|---|
| 1 | What does **ACCEPTED** mean? | The final positive candidate stage, labelled **"Accepted (Hired)"**. It is the only stage counted as filling a seat | `lib/ops.php:70` `CAND_STAGES`; `lib/reqfulfil.php` `REQF_FILLED_STAGES = ['ACCEPTED']` |
| 2 | What does **HIRED** mean? | Two different things. On a **candidate** it is not a state at all — ACCEPTED carries the word in its label. On a **requisition** `HIRED` = "Hired (filled)" | `CAND_STAGES`; `REQ_STATUS` |
| 3 | What does **JOINED** mean? | **Nothing. There is no JOINED state anywhere.** It exists only as a computed count: candidates in a filled stage that also have an `inspector_id` | `lib/reqfulfil.php:109` |
| 4 | What creates an Inspector? | Three paths: two manual (`lib/ops.php` — the inspector form/import) and one from recruitment (`rcv_convert()`) | `lib/ops.php`, `lib/recruit.php:1273` |
| 5 | What does `make_inspector` do? | It is the sole gate on running `rcv_convert()` when a candidate moves to ACCEPTED | `lib/ops.php:5775` |
| 6 | What if it is not selected? | **Nothing happens.** The candidate is ACCEPTED, the requisition counts the seat as filled, and no workforce record exists. No error, no warning, no report | `ops.php:5775`; checkbox is `display:none` at `views/ops/candidate_detail.php:379` |
| 7 | Is there a genuine Workforce/Employee creation path? | **No separate one — `inspectors` IS the workforce table.** It holds designation, home office, reports-to, working days, CTC and leave balances | `lib/ops.php:136` + `ensure_column('inspectors', …)` |
| 8 | What is `back_office_staff` for? | A standalone admin master ("Back-office staff"), reachable from the generic master screen. Read by `orgadmin`, `reset`, seed. **No recruitment path reaches it** | `lib/ops.php:131`, `:2552`; `lib/orgadmin.php:1204` |
| 9 | What does `staff_kind` represent? | **Engagement type**, not technical classification: `ASSET`, `FREELANCER`, `SUBCON` | `lib/ops.php` validation list |
| 10 | What field identifies a technical role? | **`inspectors.team_role`** — `FIELD` / `COORD` / `OFFICE`, default `FIELD`. Secondary signals: `trade_id`, `skill_ids` | `ensure_column('inspectors','team_role', "VARCHAR(10) DEFAULT 'FIELD'")`; `ops.php:4470` |
| 11 | Does **Department** identify it? | **No.** Department is a canonical controlled vocabulary (Phase 2 M3) with no technical flag | `lib/vocab.php` |
| 12 | Does **Designation** identify it? | **No.** Free text / lookup-driven on both `inspectors` and `back_office_staff`; no technical flag | schema |
| 13 | Does the **requisition** carry a role category? | **No.** It carries `department`, `grade`, `position_id`, `manager_id`, `recruiter_id` — none classifies technical | `ensure_column('requisitions', …)` |
| 14 | Does **Marketplace taxonomy** carry it? | **Partly.** `cx_iti_trades` is a technical trade taxonomy, and `inspectors.trade_id` / `req_trade_id` reference trades | `lib/connect_source.php:38` |
| 15 | Is there an existing field to reuse? | **Yes — `team_role`.** It exists, is captured on the inspector form, and is validated against the three values | `ops.php:4470` |
| 16 | If not, smallest place to define the rule? | Not needed. **FACT:** `team_role` already exists. **INFERENCE:** the smallest place for the *rule* is the conversion point, which already exists as a single function | `rcv_convert()` |

### The one line that matters

`rcv_convert()`'s INSERT column list (`lib/recruit.php:1332`) does **not** include
`team_role`. Every recruited person therefore takes the column default, `FIELD`.

## 3 · Existing data model

```
candidates ──(inspector_id)──► inspectors ◄── the workforce spine
                                  │  team_role  FIELD | COORD | OFFICE   ← the classifier
                                  │  staff_kind ASSET | FREELANCER | SUBCON  (engagement)
                                  │  trade_id, skill_ids                  (technical capability)
                                  │  designation, home_office_id, reports_to_id,
                                  │  weekly_working_days, salary_ctc, leave_balance  (employment)
                                  ├──► jobs.inspector_id
                                  └──► attendance.inspector_id

back_office_staff  ── standalone admin master · NO candidate path reaches it
```

## 4 · Recruitment → Hire → Inspector flow, as built

```
candidate  ──stage move──►  ACCEPTED ("Accepted (Hired)")
                               │
                               ├── make_inspector ticked ──► rcv_convert() ──► inspectors row
                               │                              (atomic, branch-resolved,           
                               │                               identity-ledger written — Batch 2) 
                               │                              team_role NOT SET → defaults FIELD  
                               │
                               └── not ticked ─────────────► nothing. Seat still counts as filled.
```

## 5 · The Workforce/Employee gap

**FACT:** there is no third state. A person is either an `inspectors` row or is
not represented in the workforce at all.

**INFERENCE:** "Workforce" and "Inspector" are not two tables in EXAACT — they
are **one table and one column**. Any model that assumes two tables would be
building something the architecture does not have and does not need.

## 6 · Q32 — the business question

> When a person is genuinely hired, what must exist — and does being an
> "Inspector" mean *employed by us* or *technically qualified to inspect*?

## 7 · Model A — every hire becomes an Inspector

| Dimension | Effect |
|---|---|
| Operational meaning | Employment and technical capability are the same thing |
| Recruitment | Simplest: one outcome, no branch |
| Workforce | Everyone appears; accountants and coordinators appear as "Inspectors" |
| Inspector / Operations | The deployment picker fills with people who cannot inspect |
| Attendance / Timesheet | Work correctly (they are employment records) |
| Deployment | **Degraded** — coordinators offered for field jobs |
| Utilisation | **Wrong** — non-field staff dilute the denominator |
| Billing | Unaffected |
| Reporting | "We have 300 inspectors" becomes untrue |
| Existing data | **No migration** — matches today's behaviour when the box is ticked |
| Complexity | Lowest |
| Migration risk | None |
| Confusion risk | **High** — the word "Inspector" stops meaning anything |

## 8 · Model B — every hire becomes Workforce; technical roles additionally become Inspector

| Dimension | Effect |
|---|---|
| Operational meaning | Employment ≠ capability; the second is derived from the role |
| Recruitment | Needs a rule deciding which roles are technical |
| Workforce | Everyone appears, correctly classified |
| Inspector / Deployment | Picker shows only deployable people |
| Utilisation | **Correct** — denominator is field staff |
| Attendance / Timesheet / Billing | Unaffected |
| Reporting | Headcount and inspector count become separable |
| Existing data | Existing rows default to `FIELD`; correcting them is a **decision**, not an automatic migration |
| Complexity | Medium — needs the classification source decided |
| Migration risk | **Low if nothing is auto-reclassified**; medium if history is rewritten |
| Confusion risk | Low |

## 9 · Model C — every hire becomes Workforce; Inspector is a separate optional operation

| Dimension | Effect |
|---|---|
| Operational meaning | Employment is automatic; capability is granted deliberately by a human |
| Recruitment | Conversion always runs; no classification rule needed at hire time |
| Workforce | Always exists — **RB-1 closes completely** |
| Inspector / Deployment | A person becomes deployable only when somebody says so |
| Utilisation | Correct, provided the second step is done |
| Reporting | Clean separation |
| Existing data | No reclassification required |
| Complexity | Medium — needs a "make deployable" action somewhere |
| Migration risk | Low |
| Confusion risk | **Medium** — reintroduces a second manual step, which is how RB-1 arose |

## 10 · Model D — the model the architecture already implements *(RECOMMENDED)*

**Workforce and Inspector are one record with a role marker. Joining always
creates the record; `team_role` says what kind of person it is.**

```
Candidate → Selected → Offer → ACCEPTED (hired)
                                   │
                                   ▼   always, never optional
                            inspectors row  (the employment record)
                                   │
                          team_role ├── FIELD  → deployable, counts as an Inspector
                                    ├── COORD  → workforce, not deployable
                                    └── OFFICE → workforce, not deployable
```

| Dimension | Effect |
|---|---|
| Operational meaning | Employment is a fact; deployability is an attribute of it |
| Recruitment | Conversion always runs. The only new question is *which* `team_role` |
| Workforce | **RB-1 closes** — the record always exists |
| Inspector / Deployment | Picker filters on `team_role='FIELD'` — a field that already exists |
| Utilisation | **Correct** once the denominator filters on `FIELD` |
| Attendance / Timesheet / Billing | Unaffected — all key on `inspector_id`, which still exists for everyone |
| Reporting | Headcount vs deployable headcount become separable **with no new field** |
| Existing data | **Nothing is reclassified.** Existing rows keep `FIELD`; a workspace corrects them at its own pace |
| Complexity | **Lowest of B/C/D** — one column already in the schema |
| Migration risk | **None** if nothing is auto-reclassified |
| Confusion risk | Low — and the word "Inspector" recovers its meaning |

**Model D is Model B implemented through the field that already exists, with no
second table and no migration.**

## 11 · Comparative impact

| | A | B | C | **D** |
|---|---|---|---|---|
| Closes RB-1 | Partly | Yes | Yes | **Yes** |
| New table required | No | Maybe | Maybe | **No** |
| New column required | No | Maybe | Maybe | **No** |
| Migration of existing data | None | Possible | None | **None** |
| Utilisation becomes correct | No | Yes | Yes | **Yes** |
| Reintroduces a manual step | No | No | **Yes** | No |
| "Inspector" keeps its meaning | **No** | Yes | Yes | **Yes** |
| Implementation size | Smallest | Medium | Medium | **Small** |

## 12 · Recommendation

**RECOMMENDATION: Model D**, with `team_role` as the classifier and conversion
made unconditional.

**Why it follows from the evidence, not from preference:**

1. `team_role` already exists, is already validated against three values, and is
   already editable on the inspector form. This is **REUSE**, the first step of
   the reuse order.
2. `inspectors` is already the employment record — it carries CTC, leave, office
   and reporting line. Treating it as "the workforce table" describes what it is.
3. No table is created, no column is added, **no existing row is reclassified**.
4. It closes RB-1 by removing the optionality, not by adding machinery.
5. It gives RB-2 the honest denominator it needs (§14).
6. It contradicts none of the locked architectural principles: no Person Hub, no
   identity engine, no merged tables, no new engine.

**The one thing it needs from you:** what sets `team_role` at hire time.

## 13 · RB-1 consequences *(if Model D is chosen)*

**Do not implement until Q32 is confirmed.** The shape would be:

| Aspect | Position |
|---|---|
| **Invariant** | If a candidate reaches ACCEPTED, the workforce record must exist |
| **Trigger** | The existing stage move to ACCEPTED — no new trigger |
| **Validation** | The existing `rcv_convert()` gates: authority, branch, duplicate, identity |
| **Transaction** | Already atomic (Batch 2). The stage move and conversion must **succeed or fail together**, which they currently do not |
| **Error behaviour** | Today the stage move stands and only the conversion is refused (`ops.php:5783`). **OWNER DECISION:** should an un-convertible candidate be blocked from ACCEPTED entirely? |
| **Rollback** | Existing transaction boundary |
| **Audit** | `rcv_log()` already records refusals and outcomes |
| **Duplicate protection** | See §15 — this is where RB-3 attaches |
| **Permissions** | Unchanged — `is_coordinator_level()` |
| **Tenant / branch scope** | Unchanged — branch already resolved deliberately (Batch 2, BD1) |
| **Existing identity links** | Unchanged — the ledger write already happens |
| **Existing candidate history** | Untouched. Past ACCEPTED candidates without a workforce record are **reported, not repaired** |

## 14 · RB-2 consequences

**FACT:** `reqf_counts()` already computes `requested`, `filled`, `joined`,
`remaining`. Only `filled` drives status.

**Smallest truthful change, reusing the existing status architecture:**

- `remaining` should be driven by people who have actually joined.
- The existing status `PARTIALLY_FILLED` ("Partly filled (still hiring)") already
  exists for the in-between state — **no new status value is required.**

**INFERENCE:** under Model D, `filled` and `joined` converge, because conversion
is no longer optional. RB-2 then largely closes as a consequence of RB-1, and
what remains is presentational: showing both numbers.

**OWNER DECISION:** should a requisition be "filled" when the offer is accepted,
or when the person actually starts? Model D makes either answer implementable;
it does not choose.

## 15 · RB-3 consequences

**If conversion becomes unconditional, the conversion point becomes the single
highest-volume creator of inspector records** — which makes duplicate prevention
more important, not less.

| Position | Detail |
|---|---|
| Reuse | `rcv_convert()` already refuses when `candidates.inspector_id` is set (`ALREADY`). That prevents converting the *same candidate* twice — **not** the same *person* arriving as two candidates |
| Minimum prevention rule | A person already on file must not become a second workforce record. The natural key is the one the probe broke: **employee code, and e-mail** |
| Mechanism | The Batch 3 guard pattern — a generated column plus a unique index — **reusing `ensure_unique_generated_index()`**, which already exists and is mutation-tested |
| Do not build | A second identity resolver. `find_duplicate_partner()` is for organisations; the person-side mechanisms are `cx_identity_link` and the conversion gates |
| Sequencing | **Detection first** (RB-3a, a release blocker), prevention second (RB-3b), because prevention over existing dirty data needs a reconciliation decision |

## 16 · Data and migration risk

**Read-only assessment script:** `scratchpad/p7/q32_impact.php`. It counts
ACCEPTED candidates with and without a workforce record, inspectors sharing an
employee code / e-mail / name, the `team_role` distribution, inspectors with no
candidate history, and the volume of `jobs` / `attendance` history that any change
would touch.

**It was run and returned all zeros, because the throwaway test database carries
no seeded population.** It therefore establishes **nothing** about real data.

**This is stated plainly rather than presented as a clean bill of health.** The
script must be run against a real workspace before any implementation. That is a
read-only operation and safe to run in production.

**Structural risks, independent of data volume:**

| Risk | Assessment |
|---|---|
| Existing rows default to `FIELD` | **Do not auto-reclassify.** Any bulk change to `team_role` rewrites history about what people are |
| ACCEPTED candidates with no workforce record | **Report, do not repair.** Creating them retrospectively invents a joining date |
| Duplicate inspectors already present | Must be surfaced before any unique constraint; Batch 3's DIRTY pattern applies |
| `jobs` / `attendance` history | **Untouched by Model D** — `inspector_id` still exists for every employed person |
| `back_office_staff` | **Leave alone.** Whether it is deprecated is a separate owner decision, not part of Q32 |

## 17 · Explicitly deferred

Person Hub · universal identity convergence · merging candidate/workforce/inspector
tables · a second identity resolver · deprecating `back_office_staff` ·
reclassifying existing `team_role` values · R23 taxonomy · Q1/Q2.

---

## 18 · OWNER DECISION FORM

### Q32 — Which business rule should EXAACT enforce?

```
[ ]  A — Every hire becomes an Inspector.
         (today's behaviour when the box is ticked; "Inspector" loses its meaning)

[ ]  B — Every hire becomes Workforce; technical roles additionally become Inspector.

[ ]  C — Every hire becomes Workforce; Inspector creation is a separate optional operation.

[ ]  D — RECOMMENDED. Every hire becomes a workforce record (an `inspectors` row);
         `team_role` (FIELD / COORD / OFFICE) says whether they are a deployable
         Inspector. No new table, no new column, no migration.

[ ]  Other: ______________________________________________
```

### 1 · What determines whether a role is technical?

```
[ ]  Department
[ ]  Designation
[ ]  Recruitment Role / Position
[ ]  Product / Technical Category (trade)
[ ]  Explicit role classification  ← RECOMMENDED: the existing `team_role`,
                                     chosen by the recruiter at hire time
[ ]  Other: ______________________________________________
```

### 2 · Should joining create the Workforce record automatically?

```
[ ]  YES   ← RECOMMENDED. This is what closes RB-1.
[ ]  NO
```

### 3 · Should a technical-role joining create the Inspector automatically?

```
[ ]  YES
[ ]  NO
[ ]  Only when explicitly required
```

*Under Model D this question changes shape: the record is always created, and
the answer decides whether `team_role` defaults to FIELD or must be chosen
deliberately.*
**RECOMMENDATION: must be chosen deliberately** — the silent `FIELD` default is
the root of the current misclassification.

### 4 · Supplementary (raised by §13, needed to implement RB-1)

```
Should a candidate be BLOCKED from reaching ACCEPTED if the workforce record
cannot be created (e.g. no branch resolvable, duplicate detected)?

[ ]  YES — accepting and converting succeed or fail together
[ ]  NO  — accept the candidate, report the failure, fix it afterwards
```

---

**HARD STOP. Nothing will be implemented until Q32 is answered.**

---

# 19 · OWNER DECISIONS — ANSWERED 2026-09-20

| Question | Answer |
|---|---|
| Every ACCEPTED (Hired) candidate → workforce record | **YES** |
| Workforce continues to use the existing `inspectors` table | **YES** |
| `team_role` deliberately selected/derived, never silently defaulted to `FIELD` | **YES** |
| Every hire automatically becomes an Inspector | **"Depends on the type of user using our application — recruiter, technical manpower supplier, etc."** |
| Inspector applicability determined by `FIELD`/`COORD`/`OFFICE` | **"Something else"** |
| ACCEPTED and workforce conversion succeed or fail together | **YES — atomic.** A candidate does not reach ACCEPTED if the workforce record cannot be created |

## The two answers that changed the model

The owner rejected a single global rule and rejected `team_role` as *the*
determinant. Both point at the same thing: **Inspector applicability is a
property of the customer's business, not of the product.**

**FACT — that mechanism already exists and is already load-bearing.**

`connect_cap_catalog()` (`lib/connect_capability.php:26+`) holds **27 business
capabilities**, each mapped to a group and to the coarse modules it makes
relevant. `connect_cap_modules()` unions them for a workspace;
`connect_cap_shows()` gates the specialist modules; `connect_cap_owner_shows()`
already drives the left-hand navigation (`views/layout_top.php:144,155`).

| Capability group | Modules |
|---|---|
| **Recruitment** (Technical Recruitment, Permanent Placement, Executive Search, Contract Recruitment) | **`hr` only — no `operations`** |
| **Inspection & Technical Services** (TPIA, TIC, Vendor/Shop/Resident/Site Inspection, Expediting, Vendor Surveillance, QA/QC, NDT) | `operations` (+ `reporting`) |
| **Resource Supply** (Technical Manpower, Contract/Project/Shutdown/Turnaround Staffing, Freelance & Specialist Supply) | `operations` + `hr` (+ `reporting`) |
| **Project Services** (Project Management/Engineering, Commissioning, Construction Support, Technical Consultancy) | `operations` |

**INFERENCE:** a pure recruitment workspace has **no Operations module**, so
inspection, deployment and utilisation do not exist there. Every hire is
workforce and *nobody is an Inspector* — correctly, because that customer places
people into other companies. A TPIA or manpower supplier has Operations, so the
Inspector concept applies and the question becomes *which* of their people are
deployable.

# 20 · REVISED MODEL — capability decides whether, `team_role` decides who

**Model D′.** Two levels, both reusing mechanisms that already exist. No new
table, no new column, no new engine, no migration.

```
LEVEL 1 — WORKSPACE     Does the Inspector concept exist here at all?
                        Derived from the declared capabilities:
                        `operations` in connect_cap_modules(workspace)?
                          NO  → recruitment-only workspace.
                                Every hire is workforce. Nobody is an Inspector.
                                team_role is not even asked.
                          YES → inspection / supply / project workspace.
                                Continue to Level 2.

LEVEL 2 — PERSON        Which of our people are deployable?
                        team_role, chosen deliberately at hire:
                          FIELD  → deployable Inspector
                          COORD  → workforce, not deployable
                          OFFICE → workforce, not deployable
```

**Why this is the answer to the owner's words rather than a reinterpretation of
them.** "It depends on the type of user" is Level 1. "Something else" — not
`team_role` alone — is satisfied because `team_role` is now the *second* filter,
applied only where capability says the concept exists.

## What each answer now means for the blockers

**RB-1 — closes completely, and atomically.**
Every ACCEPTED candidate gets an `inspectors` row (the workforce record). Because
the owner chose atomic, acceptance is refused when that record cannot be created,
so ACCEPTED will *always* mean a workforce record exists. The hidden checkbox
disappears: conversion is no longer optional, only the classification is a choice.

*Consequence the owner should see:* a recruiter can now be blocked at the final
step by a data problem — most likely an unresolvable branch. The refusal messages
already exist in `rcv_convert()` (`NO_BRANCH`, `BLOCKED`, `ALREADY`), so this is
a wording and routing question, not new machinery.

**RB-2 — largely closes as a consequence.**
With conversion unconditional, `filled` and `joined` converge, so "filled" stops
being able to mean "accepted but nobody joined". What remains is presentational:
showing both numbers. `PARTIALLY_FILLED` already exists; **no new status value is
required.**

**RB-3 — becomes more important, and the target is now precise.**
Conversion becomes the highest-volume creator of `inspectors` rows, so duplicate
prevention matters more. The minimum rule is unchanged: reuse
`ensure_unique_generated_index()` from Batch 3 over employee code and e-mail,
detection first, prevention second.

**Where `team_role` is asked.** In a Level-1 workspace it must be chosen at the
point of conversion. In a recruitment-only workspace it is not asked at all, and
the column keeps its default harmlessly — nothing reads it there, because
Operations is not present.

## Still required before implementation

| # | Item | Why it cannot be assumed |
|---|---|---|
| 1 | **Confirm Model D′** as the reading of "depends on the type of user" | It is my inference from the owner's words plus the capability catalogue, not their literal instruction |
| 2 | **Where `team_role` is captured at hire** — the stage-move form, the offer, or the requisition/position | A UX decision with different training consequences |
| 3 | **Default when a workspace has not configured capabilities** | `connect_cap_configured()` returns false → everything shows. Should such a workspace behave as Level 1 = YES (today's behaviour) or be asked to configure first? |
| 4 | **What a recruiter sees when acceptance is refused** | Atomicity was chosen; the wording and the recovery path are a product decision |

**Nothing is implemented. These four items are the remaining input needed to
write the RB-1/RB-2/RB-3 implementation prompt.**

---

# §21 — LOCKED. The owner's final decision set (2026-09-20)

*Supersedes "Still required before implementation" above. All five items are
answered. Model D′ is **CONFIRMED**.*

## The five locked decisions

| # | Decision | Locked answer |
|---|---|---|
| **1** | May an employee number ever be re-issued? | **NO — never.** Uniqueness is **lifetime and tenant-wide**, not merely among active employees. The number stays attached to the historical record for ever. **Database-level backstop, not PHP validation** |
| **2** | An applicant matches existing staff | **Show the match and require an explicit acknowledgement tick.** Never refuse automatically, never continue silently. Until the tick, Accept is blocked. **The tick is an acknowledgement, not a merge** |
| **3** | Where `team_role` is chosen | **At the requisition / position**, inherited by the candidate, **confirmed at acceptance** subject to permission and audit. **Never a silent `FIELD` default** |
| **4** | A workspace with capabilities not configured | **Do not infer Inspector applicability.** Preserve today's navigation behaviour for backward compatibility, but never let an unconfigured state become an employment-classification rule. Require explicit classification |
| **5** | Acceptance refused | **Candidate stays at the previous stage.** Inline, actionable message naming what to correct. No partial workforce record, no retry-created duplicate, no misleading "Accepted (Hired)" |

## Model D′ — CONFIRMED as locked

**Level 1 — workspace capability decides whether "Inspector" exists at all**

| Workspace kind | `operations` capability | Inspector concept |
|---|---|---|
| Recruitment-only | absent | **does not apply** — every hire is workforce, nobody is an Inspector |
| Inspection & Technical Services (TPIA) | present | **applies** |
| Technical manpower / resource supply | present | **applies** |
| Any other Operations-enabled business | present | **applies** |
| **Not configured / unknown** | unresolved | **cannot be inferred** — explicit classification required (decision 4) |

**Level 2 — the person, only where Level 1 says the concept exists**

| `team_role` | Meaning |
|---|---|
| `FIELD` | deployable **Inspector** |
| `COORD` · `OFFICE` | **workforce employee, not a deployable Inspector** |

Workspace-capability-driven first, person-role-driven second. **No new Employee
table, no Person Hub, no parallel workforce engine.**

## The two invariants this produces

> **ACCEPTED (Hired)** = a workforce record exists · the employee number is valid
> and permanently unique · the person's workforce classification is explicitly
> known.

> **Inspector** = an Operations-enabled workspace **and** a person explicitly
> classified `FIELD`.

Both replace the current hidden-checkbox and silent-`FIELD` model.

## Acceptance of decision 1, and its one consequence

Decision 1 is **explicitly accepted**. The reasoning is right: an employee number
travels into inspection reports, attendance, timesheets, expenses, billing
support and audit records, and re-issuing it makes historical documents
ambiguous. Lifetime uniqueness is also the *simpler* rule to enforce — the key
covers every row that carries a number, with no "is this row live" predicate to
get wrong.

**The consequence, stated plainly:** because retired and inactive records now
count, an install whose historical data already contains a collision is **more**
likely to be found dirty than under a live-only rule. That is the rule working,
not failing. The protection is then reported and left un-installed until a human
resolves it; **nothing is renumbered automatically**, because renumbering
somebody is exactly the historical ambiguity decision 1 exists to prevent.

## Locked scope

Reuse the existing capability catalogue · `inspectors` · `team_role` ·
the recruitment conversion · approval and entitlement mechanisms.
**No new workforce table, no Person Hub, no duplicate engine.**

**This section is the authoritative input to the consolidated RB-1 + RB-2 + RB-3
implementation prompt. Nothing is implemented by this document.**
