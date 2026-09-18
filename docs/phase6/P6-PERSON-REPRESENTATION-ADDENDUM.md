# Phase 6 — Person Representation Addendum

*A documentation-only extension of the §2 audit, covering person representations
the original audit did not reach.*

**Trigger:** writing the §3 canonical domain model surfaced `back_office_staff`
and `lib/identity.php`, neither covered by `P6-PREIMPLEMENTATION-AUDIT.md`
(commit `69e2539`). This addendum closes that gap.

**Scope:** documentation only. No PHP, JavaScript, HTML, CSS, database, migration,
route, API, permission or workflow is changed. The original audit is **not**
rewritten.

> ## NO PERSON SPINE DECISION IS BEING MADE IN THIS STEP.
>
> Options A (keep Person emergent), B (promote an existing representation to hub)
> and C (introduce a dedicated Person record) all remain **open**. This addendum
> supplies evidence; it does not choose.

---

## What this addendum changes about the picture

The §2 audit reported **three** person representations and **two** identity
mechanisms. Both counts were low.

| | §2 audit said | Evidence now shows |
|---|---|---|
| Person representations | 3 — candidate, inspector, professional | **5** — plus `users` and `back_office_staff` |
| Identity mechanisms | 2 | **5** — see below |
| The hub | The marketplace professional row | **Structurally it is `inspectors`** — every cross-domain mechanism points at it. The *resolver* uses the professional row, which is a different thing |

> **UPDATED after §20 (owner-approved).** This table first recorded **four**
> mechanisms. Writing `P6-DUPLICATE-AND-IDENTITY-RULES.md` surfaced a fifth —
> **`candidates.person_ref`**, with `person_key()` and `person_link_rows()`. The
> documented inventory is now:
>
> 1. `cx_identity_link`
> 2. `candidates.inspector_id`
> 3. `users.inspector_id`
> 4. the legacy per-application bridge (`cx_applications`)
> 5. **`candidates.person_ref` / `person_link_rows()`**
>
> **These are five EXISTING MECHANISMS to be evaluated for convergence — not five
> canonical identity systems.** No hub is chosen, none is retired, none is merged,
> and nothing is implemented.

The hub row matters most and is explained in full below.

---

## 1. What `back_office_staff` actually represents

### Schema — the complete definition, unchanged since creation

```sql
CREATE TABLE IF NOT EXISTS back_office_staff (
    id, name VARCHAR(150), emp_code VARCHAR(40) DEFAULT '',
    designation VARCHAR(40) DEFAULT '', department VARCHAR(40) DEFAULT '',
    office_id INT NULL, email VARCHAR(200) DEFAULT '', mobile VARCHAR(40) DEFAULT '',
    ctc DECIMAL(14,2) DEFAULT 0, allowances DECIMAL(14,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'ACTIVE', created_at VARCHAR(30) DEFAULT '')
```

**No column has ever been added to it** — there is no `ensure_column` call for
this table anywhere in the repository.

### The decisive finding: it is already deprecated, in the code, by name

Two comments in production source state the position:

> *"Back-office staff was a second list of people. Rather than strand whatever was
> typed into it, it is brought across into the one register — matched on name so
> running it twice changes nothing."* — `lib/orgadmin.php`

> *"These are people, and people live in one register. Kept as a table so nothing
> already typed here is lost, but the card sends you to the People tab and
> anything still in here is offered for moving."* — `lib/ops.php`

**A migration already exists** (`bos-import`): it reads every row, skips any whose
name already appears in `users`, and calls `person_quick_create()` for the rest —
landing them in `users` with no password. The master-data card for it carries
`'goto' => '/hierarchy?tab=people'` and the note *"people live in Organisation &
people"*.

**So the repository has already decided that `back_office_staff` is a legacy
holding pen, and that the one internal people register is `users`.**

### Answers to the §3 checklist

| Question | Evidence-based answer |
|---|---|
| What does it represent? | Office staff as an **employment record** — title, department, office, pay |
| Person record or employment record? | **Employment record about a person.** It carries pay and posting, not identity beyond name/email/mobile |
| Identity fields | `name`, `email`, `mobile`, `emp_code` |
| Unique identifiers | **None enforced.** No unique index of any kind |
| Contains e-mail? | Yes | 
| Contains mobile? | **Yes** — and note `users` does **not** |
| Contains name? | Yes, as a single `name` field (not split) |
| Employee/staff identifier? | `emp_code`, free text, not enforced unique |
| Tenant scope | Structural — one database per tenant. No tenant column, and none needed |
| Branch/office scope | **Yes** — `office_id` |
| Active/inactive | Yes — `status`, default `ACTIVE` |
| Which modules read it? | `lib/ops.php` (master-data card), `lib/orgadmin.php` (the migration), `views/ops/hierarchy.php`, plus `lib/reset.php` and `lib/seed_demo_c.php` |
| Which modules write it? | **Only the generic master-data editor** (one `INSERT`, one `DELETE`). No business flow writes it |
| Routes exposing it | The master-data screen, `access => 'admin'`, and the card redirects to `/hierarchy?tab=people` |
| Does `lib/identity.php` serve it? | **No.** That file's `person_kind` only ever carries `INSPECTOR` |
| Links to inspectors? | **None** |
| Links to candidates? | **None** |
| Links to marketplace professionals? | **None** |
| Relationship to `business_partners`? | **None** |
| Used for login/security identity? | **No.** It holds no password, no role, no permissions |
| HR/workforce record, app-user record, or both? | **An HR/workforce record only** |
| Can multiple rows represent one human? | **Yes.** Nothing prevents it |
| Existing duplicate controls | **None on the table.** The *migration* de-duplicates against `users` **by lower-cased full name only** |

### ⚠ A finding inside the existing migration

The `bos-import` path decides "this person is already in the register" **on name
alone** — `LOWER(TRIM(first_name || ' ' || last_name))`.

§5 of the Phase 6 master prompt forbids exactly this: *"Do NOT automatically merge
based solely on: same name, similar name…"*.

**Precisely stated, so it is not overcharged:** this path does not *merge* — it
**skips**, leaving the back-office row where it is. The failure mode is therefore
not a wrong merge but a **wrong skip**: two different people who share a name, and
the second is silently not brought across. It is also a **name-only identity
judgement**, which is the pattern Phase 6 exists to remove.

**Recorded as a finding. Not fixed here.**

---

## 2. What `lib/identity.php` actually does

§5 of this task asked whether it provides a broader identity architecture than §2
captured. **It does not.** It is an **identity-document vault** — passports,
licences and similar — not a person-identity engine.

Forty functions, all of one family: `iddoc_*` (kinds, expiry, masking, encryption
backfill, retention, access logging) plus `person_req_docs()` and
`person_docs_summary()`.

| §5 capability | Present? | Evidence |
|---|---|---|
| Resolves people | **No** | No resolve function |
| Links representations | **No** | No link function |
| Detects possible duplicates | **No** | No dedupe function |
| Maintains identity relationships | **No** | Its table stores *documents about* a person, not relationships between records |
| Provides identity suggestions | **No** | No suggestion function |
| Supports confirmation | **No** | — |
| Supports reversal / undo | **No** | — |
| Cross-domain lookup | **No** | — |

A grep for function names containing *resolve, link, dedup, suggest, match, merge,
confirm* or *unlink* in this file returns **nothing**.

**What it does have that matters to Phase 6:** a *prepared but unused*
polymorphic person reference —

```sql
person_documents ( person_kind VARCHAR(20) DEFAULT 'INSPECTOR', person_id INT, … )
```

`person_kind` is a discriminator, and **the only value it ever carries anywhere in
the repository is `INSPECTOR`**. It is a hook that was designed for more than one
kind of person and never extended.

It also owns two real permissions — **`person.iddoc.view`** and
**`person.iddoc.manage`** — which are the only permissions in the system whose key
begins `person.`. Any future person work must not collide with them.

---

## 3. The mechanism the §2 audit missed: `users.inspector_id`

`users` carries `inspector_id INT NULL` (added in `ops_migrate()`, commented
*"inspector self-service link"*).

It is written in exactly one place, `org_import_link_team()`, whose own comment
states the intent:

> *"Every login (bar the root Master Admin) belongs to a team-member row… the
> register is a single source that flows through to allocation. Idempotent: a
> login that already carries a link is left untouched."*

That function calls `team_member_create()` — **which inserts a row into
`inspectors`**.

**So there is a fourth identity mechanism, and it runs
`users → users.inspector_id → inspectors`.**

Two consequences:

1. **`inspectors` is the structural hub**, not `cx_professionals`. Three of the
   cross-domain mechanisms point at `inspectors`; only the marketplace ledger treats the
   professional row as the hub. The §3 canonical model's statement that the
   professional row is the hub describes **the resolver's traversal**, not the
   data. *(See §11 below — the canonical model needs a documentation correction.)*
2. **`users` is the internal people register** that the repository's own comments
   call "the one register".

---

## 4. Person representation matrix

| | **Candidate** | **Inspector** | **Marketplace Professional** | **User** | **Back-office staff** |
|---|---|---|---|---|---|
| **Table** | `candidates` | `inspectors` | `cx_professionals` | `users` | `back_office_staff` |
| **Purpose** | One person's participation in one recruitment process | Operations / workforce technical resource | Marketplace-facing profile | Login **and** internal people register | Legacy office-staff employment record |
| **Identity fields** | first/middle/last name, email, mobile | name, first/middle/last, email, mobile, `emp_code` | email, name, mobile, **`pan`**, **`gstin`** | username, first/last name, email · **no mobile** | name, email, mobile, `emp_code` |
| **Unique identifiers** | **None** | **None** | **`ux_cx_pro_email` — UNIQUE on email** | `username` UNIQUE | **None** |
| **Tenant scope** | Structural (database per tenant) | Structural | Structural | Structural | Structural |
| **Branch scope** | `sbu`; branch reached **through the requisition** | `home_office_id`, `sbu`, `sbus` | **NONE — tenant-global** | `home_office_id` + **defines** `scope_offices`, `scope_sbus` | `office_id`, `sbu` |
| **Existing identity link** | `candidates.inspector_id` → inspector · `cx_identity_link.candidate_id` → professional · **`person_ref` groups candidate rows as one person** | Target of every cross-domain mechanism | `cx_identity_link.professional_id` | **`users.inspector_id` → inspector** | **NONE** |
| **Relationship to others** | → inspector, → professional. **No link to user or back-office** | ← candidate, ← user, ← professional | ← candidate, ↔ inspector | → inspector only | **None at all** |
| **Owning module** | Recruitment | Operations | Marketplace | Administration | Operations (legacy) |
| **Read paths** | Recruitment, Phase 4/5 KPI, careers, exports | **51 library files** | Marketplace modules, bench, match, ratings | Auth, org chart, scope, approvals, costing | 5 files — master card, migration, hierarchy view, reset, seed |
| **Write paths** | Candidate routes, careers intake, stage route | Stage route conversion, `team_member_create()`, org import, master editor | Marketplace registration and profile | User admin, org import, `person_quick_create()` | **Generic master editor only** |
| **Known risks** | No unique key; duplicates easy | No unique key; **orphan rows provable**; 51 consumers | Tenant-global while its counterparts are branch-scoped | Person data split from `inspectors`; **no mobile** | Unlinked, unmaintained, name-only de-duplication in its migration |
| **Phase 6 requirement** | R1, R2, R5 | R1, R2, R3, R5 | R3, R5 | **NEW — R13** | **NEW — R14** |

**No additional person representation was found.** Searched: every
`CREATE TABLE IF NOT EXISTS` in `lib/`, filtered for *person / people / employ /
staff / worker*. `person_sbu_split` is a **cost allocation of a user**, not a
person record; `person_documents` holds **documents about** a person.

---

## 5. Existing identity links discovered

| # | Mechanism | Connects | Recorded where | Reversible | Audited | Duplicate control |
|---|---|---|---|---|---|---|
| 1 | `cx_identity_link` | professional ↔ inspector · candidate ↔ professional | Link ledger | **Yes** — `status='UNLINKED'` | **Yes** — `act_log()` | **PHP only.** Proved: two identical live rows coexist |
| 2 | `candidates.inspector_id` | candidate → inspector | Column on the candidate | No | No | Stale check only. Proved: duplicate inspectors, first orphaned |
| 3 | **`users.inspector_id`** | user → inspector | Column on the user | No | No | *"a login that already carries a link is left untouched"* — a read-then-write check |
| 4 | `cx_applications.inspector_id` / `applicant_professional_id` | applicant → inspector or professional | Per-application columns | n/a | n/a | Per-application, not a person link |
| **5** | **`candidates.person_ref`** · `person_key()` · `person_link_rows()` | **candidate ↔ candidate** — several applications as one person, **within Recruitment only** | Column on the candidate | No | **No** | Group read-then-write. Falls back to mobile-10, then e-mail, when `person_ref` is unset |

**Only mechanism 1 is reversible and audited.** The other four are plain columns.

**Mechanism 5 was added after §20 (owner-approved).** It is
**Recruitment-domain** — it reaches no inspector, professional, user or
back-office record. Its discovery is recorded in
`P6-DUPLICATE-AND-IDENTITY-RULES.md`, and its consequence is **Q13**, which is
**OPEN**.

---

## 6. Missing identity relationships

| Missing edge | Consequence |
|---|---|
| **candidate ↔ inspector in the ledger** | The person resolver cannot see that a candidate became an employee (proved in §2) |
| **user ↔ candidate** | An employee who applies for another internal role is two unconnected records |
| **user ↔ professional** | An employee who is also in the marketplace pool is unconnected |
| **back-office staff ↔ anything** | Completely isolated |
| **professional ↔ user** | Same person, no path |
| **`connect_identity_roles()` expressing a candidate or a user** | Its keys are `name, professional_id, inspector_id, is_professional, is_inspector, linked, bench_count` — it cannot report either |

---

## 7. Tenant and scope observations

**Tenant.** Isolation is **structural** — one database per tenant, no `tenant_id`
column anywhere in these representations, and no cross-connection query in any
person path examined. A cross-tenant link would require a query to reach another
database, and none does.

> **This remains an argument, not a proof.** §22 of the master prompt requires it
> to be *tested*. It has not been, and this addendum does not test it.

**Branch — a real asymmetry, measured:**

| Representation | Branch scope |
|---|---|
| `users` | `home_office_id`, and it **defines** `scope_offices` / `scope_sbus` |
| `inspectors` | `home_office_id`, `sbu`, `sbus` |
| `back_office_staff` | `office_id`, `sbu` |
| `candidates` | `sbu` only — branch reached **indirectly**, through the requisition |
| `cx_professionals` | **NONE. Tenant-global** |

**The observation that matters:** linking a **branch-scoped** inspector to a
**tenant-global** professional is an act that crosses a scope boundary. Today
nothing in the link path evaluates branch scope at all — the guards check only
that both rows exist.

A user restricted to Branch A could therefore create a link involving an inspector
they can see and a professional that has no branch, and the resulting link is
visible to Branch B. **Whether that is wrong depends on a decision not yet taken**
(Q5 — which scope class applies to an identity link). Recorded as a gap, not a
defect, and **not fixed**.

**Candidates are the subtle case.** Because a candidate carries no office, its
branch is inherited from its requisition — which is why Phase 5 scopes candidates
through the requisition (`rasg_cand_scope`). Any future candidate-side identity
link must use that same rule rather than inventing a second one.

---

## 8. Does the §3 canonical model need correction?

**Yes — two documentation corrections, neither urgent, neither taken here.**

| # | What §3 says | What the evidence shows | Severity |
|---|---|---|---|
| **C1** | Person is resolved "with the marketplace professional row as the hub" | True of the **resolver's traversal**, but **`inspectors` is the structural hub** — the cross-domain mechanisms point at it. §3's sentence is not wrong, but it is easy to misread as a statement about the data | Documentation clarity |
| **C2** | `users` appears only as "system login accounts, carrying the security role" | Understated. `users` is **the internal people register** by the repository's own account, carries employment attributes (department, position title, office, CTC, reporting line, working days) and **holds a link to `inspectors`** | Material omission |

Per this task's §1, **these are reported for owner approval, not applied.** If
approved, the change is to the *Person* and *Employee / Workforce Resource*
sections of `P6-CANONICAL-DOMAIN-MODEL.md` only.

The §3 statement that `back_office_staff` is a confirmed person representation is
**upheld** — and this addendum adds that it is already deprecated in favour of
`users`.

---

## 9. New open questions

**Q7 — Is `users` the internal person register, and should it be the hub?
OPEN QUESTION.**
The repository's own comments call it "the one register" and it already links to
`inspectors`. This is the strongest evidence yet for option **B** (promote an
existing representation). Against it: `users` is a *login* table, every row
implies an account, and **it has no mobile number** — the second-strongest
matching identifier. Not decided.

**Q8 — Should `back_office_staff` be finished off? BUSINESS DECISION REQUIRED.**
It is already deprecated with a working migration. Options: complete the
migration and retire the table; leave it as a holding pen; or bring it into the
identity model. Note the master prompt forbids deleting historical records —
retiring the table is therefore *not* deleting its rows.

**Q9 — Does a back-office staff member belong in the identity model at all?
BUSINESS DECISION REQUIRED.** *(This is Q6 of §3, now sharpened.)* If the answer
to Q8 is "migrate to `users`", this question may dissolve.

**Q10 — Should the `bos-import` name-only skip be corrected?
BUSINESS DECISION REQUIRED.** It is a name-only identity judgement, which Phase 6
exists to remove — but it skips rather than merges, so the risk is a missed
person, not a wrong one.

**Q11 — What scope applies to an identity link whose two ends have different
scope? OPEN QUESTION.** *(Sharpens Q5.)* Branch-scoped inspector ↔ tenant-global
professional. Neither end's scope is obviously the answer.

**Q12 — Does `person_documents.person_kind` become the extension point?
OPEN QUESTION.** A polymorphic person reference already exists and carries only
`INSPECTOR`. Whether Phase 6 extends it, or leaves it alone as a document-vault
detail, is undecided.

---

## 10. Additional Phase 6 implementation requirements

*Added to R1–R12 in the canonical model. **None implemented.***

| # | Requirement | Source | Severity |
|---|---|---|---|
| **R13** | Decide and document the place of **`users`** in the person model — it is a person representation with an existing inspector link that the §2 audit missed | This addendum §3 | Prerequisite to any person-spine decision |
| **R14** | Decide and document the disposition of **`back_office_staff`** — already deprecated, migration exists, no links | This addendum §1 | Prerequisite |
| **R15** | Any identity link must evaluate **branch scope**, including the asymmetric case where one end is tenant-global | This addendum §7 | Constraint, gated on Q11 |
| **R16** | **Prove** tenant isolation for identity links by test, rather than resting on the structural argument | This addendum §7 · master §22 | Evidence requirement |
| **R17** | Review the **`bos-import` name-only skip** | This addendum §1 | Gated on Q10 |

---

## What this addendum did NOT do

- **No product code, schema, migration, route, API, permission or workflow
  changed.** Verified by diff.
- **The original §2 audit was not rewritten**, as instructed.
- **The §3 canonical model was not edited** — its two corrections are reported
  above for approval.
- **No person-spine decision was made.**
- **Nothing was fixed** — not the conversion, not the duplicate protection, not
  the name-only skip, not the missing links.
- **Cross-tenant isolation was not tested**, only reasoned about.
- **Row counts were not gathered.** Any that appear in earlier documents came from
  a freshly-provisioned test workspace and say nothing about a real installation.
