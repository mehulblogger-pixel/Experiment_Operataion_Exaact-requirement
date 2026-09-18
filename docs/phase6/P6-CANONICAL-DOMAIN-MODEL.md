# Phase 6 — Canonical Domain Model

*The business concepts Phase 6 works with, defined so that nobody later treats
two similar database records as the same business thing.*

**Source:** `P6-PREIMPLEMENTATION-AUDIT.md` (commit `69e2539`). Where the audit
did not establish something, this document says **OPEN QUESTION** or **BUSINESS
DECISION REQUIRED** rather than guessing.

**This document changes no product code, no schema and no behaviour.**

---

## The rule that governs everything below

> ## PHASE 6 IS CONVERGENCE, NOT CONSOLIDATION BY DELETION.

The goal is **ENTER ONCE → IDENTIFY ONCE → MAP ONCE → USE EVERYWHERE.**

That is a promise about **the business experience**, not an instruction to merge
tables. The goal is to make the *business* look simple while the specialised
engines underneath keep doing their jobs.

Implementation order, and it is an order:

**REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → BUILD**

**BUILD is the last option.** On the evidence of the §2 audit, Phase 6 is expected
to stop at CONNECT / MAP / EXTEND.

---

# PART 1 — THE PERSON DOMAIN

## PERSON

**Business meaning.** A real human being. One heartbeat.

**Current system representation.** **None, as a record.**

> **CORRECTED (C1).** An earlier draft of this document said Person was resolved
> "using the marketplace professional row as the hub". That described the
> *resolver's traversal*, and it read as a statement about the architecture. It
> is superseded by the evidence in `P6-PERSON-REPRESENTATION-ADDENDUM.md`.

The accurate position, on the audit evidence:

- **There is no dedicated Person table or spine.** Nothing in the repository
  holds a person as a record in their own right.
- **Person identity is currently *emergent*** — it exists only as the pattern
  formed by the existing representations and the relationship mechanisms that
  join them.
- **`inspectors` is the structurally central existing people / workforce
  register.** Three of the five identity mechanisms point at it:
  `candidates.inspector_id`, `users.inspector_id`, and the inspector axis of
  `cx_identity_link`. (The fifth, `candidates.person_ref`, does not — it groups
  candidate rows with one another, inside Recruitment only.)
- **`cx_identity_link` provides the explicit relationship mechanism** — and it is
  the only one of the five that is reversible and audited.
- **The Marketplace Professional is NOT the canonical Person hub.**
  `connect_person_resolve()` traverses *through* the professional row, which is a
  property of that function, not a statement about where person identity lives.
  It also has a consequence worth stating: a person who is not on the marketplace
  has no traversal path at all.

**No hub is chosen here.** Which representation — if any — becomes the canonical
Person architecture remains an **OPEN Phase 6 decision** (Q3, and Q7 in the
addendum).

Two other places in the repository use the word *person* and **neither is an
identity spine** — this must not be mistaken for one:

| Use | What it actually is |
|---|---|
| `person_documents.person_kind` + `person_id` (`lib/identity.php`) | The **ID-document vault** (passport, licence). `person_kind` is a discriminator that today only ever carries `INSPECTOR` — a prepared hook that was never extended |
| `person_sbu_split.user_id` (`lib/costing.php`) | A **cost allocation** of a system *user* across business units |

**Owning domain.** No single owner. Person is, today, an *emergent* concept
produced by the identity links.

**What PERSON is NOT.** It is not a login. It is not a candidate. It is not an
employee. It is not a marketplace profile. It is not a row anybody edits.

**Relationship to the representations.** One person may have **many** of each
representation, or none. A person with no candidate record is still a person.

**Historical independence.** Yes, absolutely. Each representation carries its own
history and that history stays with the representation, not with the person.

> **OPEN QUESTION (see Open Questions, Q3):** whether Phase 6 should keep Person
> as an emergent concept resolved from links, or give it a record of its own. The
> audit did **not** establish that a person record is necessary, and §4 of the
> master prompt forbids creating one unless the audit proves it.

---

## CANDIDATE

**Business meaning.** One person's **participation in one recruitment process**.
Not the person — the *application*.

**Current representation.** `candidates` (Recruitment).

**Owning domain.** **Recruitment.** Recruitment owns the candidate lifecycle, and
Phase 6 does not change that.

**What CANDIDATE is NOT.** Not a person. Not an employee. Not a marketplace
profile. Not proof that somebody works here.

**Coexistence.** A person may hold **several candidate records at once** —
applying for two roles, or applying again next year. That is normal and must never
be treated as duplication.

**Permitted relationship to Person.** Many candidates → one person.

**Historical independence.** **Required.** A candidate record for a job somebody
did not get is permanent business evidence. It is never deleted, never merged, and
never rewritten because the person's later records look different.

---

## PROFESSIONAL (Marketplace)

**Business meaning.** A person's **marketplace-facing representation** — how they
present themselves to buyers: profile, availability, rates, verified credentials,
ratings, trust standing.

**Current representation.** `cx_professionals` (Connect / Marketplace), with
`cx_pro_files`, `cx_pro_certs`, `cx_pro_projects`, `cx_verifications`,
`cx_ratings`, `cx_bench`, `cx_profile_tax`.

**Owning domain.** **Marketplace.**

**Notable:** it is the **only** person pool with a unique index on e-mail —
deterministic duplicate control the internal pools do not have.

**What PROFESSIONAL is NOT.** Not a person. Not an employee. Not a candidate.
Being on the marketplace is not a claim of any relationship with this company.

**Coexistence.** Yes, with all the others.

**Permitted relationship to Person.** Many professionals *could* resolve to one
person; today the e-mail uniqueness makes more than one unlikely but not
impossible.

**Historical independence.** **Required.** Ratings, verifications and past
assignments are the professional's own record and must not move or change because
a recruitment or employment record was linked.

---

## INSPECTOR

**Business meaning.** The **Operations / Workforce technical resource** — the
person the whole operational chain plans, deploys, costs and pays.

**Current representation.** `inspectors` (Operations).

**Owning domain.** **Operations.**

**Blast radius, measured:** **51 library files read `inspectors`** — attendance,
assets, audits, billing, call profitability, competence, complaints, compliance,
confidentiality, the marketplace modules and more. This single number is why the
answer for Phase 6 is CONNECT, never MIGRATE.

**What INSPECTOR is NOT.** Not a person. Not a candidate. Not a login. Not
necessarily an employee — an inspector may be on our own roll **or** on an
agency's roll (`roll_type`, `staff_kind`).

**Coexistence.** Yes.

**Permitted relationship to Person.** Many inspectors *should* resolve to one
person; today nothing prevents genuine duplicates (no unique index).

**Historical independence.** **Required.** Deployments, timesheets, vouchers and
invoices reference the inspector record. It cannot be merged away.

---

## EMPLOYEE / WORKFORCE RESOURCE

§5 of the master prompt requires the terminology **actually found in the
repository**, and forbids inventing a workforce identity concept. Honouring that:

> **The repository has no single "Employee" or "Workforce Resource" entity.**

What it has instead:

| Representation | What it covers | Owner |
|---|---|---|
| `inspectors` | Field / technical resource, own roll or agency roll | Operations |
| `back_office_staff` | Office staff — name, employee code, designation, department, office, CTC. **Already deprecated in the code**, with an existing migration into `users` | Operations (legacy) |
| `users` | **An internal people register that is also the application account** — see below | Administration |

**"Workforce" is therefore a *view over* these, not a record.**

### USER / APPLICATION ACCOUNT versus PERSON / PEOPLE REPRESENTATION

> **CORRECTED (C2).** An earlier draft described `users` as "system login
> accounts, carrying the security role". That is **understated**, and is
> superseded by the evidence in `P6-PERSON-REPRESENTATION-ADDENDUM.md`.

`users` is **not merely** a login-account and security-role representation. On the
audit evidence it is also an **internal people register carrying employment and
organisational attributes**:

`first_name` · `last_name` · `email` · `department` · `position_title` ·
`home_office_id` · `scope_offices` · `scope_sbus` · `monthly_ctc` ·
`reports_to_id` / `reports_to_name` / `reports_to_position` ·
`weekly_working_days` · `daily_hours` · `is_production` · `is_active` ·
`deactivated_at`

**And it holds an existing relationship to `inspectors`** through
**`users.inspector_id`**, written by `org_import_link_team()`, which creates the
inspector row via `team_member_create()`. The repository's own comment calls this
register *"a single source that flows through to allocation"*.

**The distinction must nevertheless be preserved**, because one row serves two
different purposes:

| Facet | What it is | Why it must stay distinct |
|---|---|---|
| **USER / APPLICATION ACCOUNT** | A credential and a set of rights — `username`, `password_hash`, `role`, `permissions`, `is_superuser`, two-factor secrets, `scope_offices`, `scope_sbus` | It participates in **authentication, authorisation and organisational scope**. Treating an account as a person would make every access decision an identity decision |
| **PERSON / PEOPLE REPRESENTATION** | A human being's employment facts — name, department, position, office, reporting line, working pattern | This is about who somebody *is* and what they do, not what they may open |

Two consequences follow, and neither is a decision:

- **Not every person is a user.** An inspector on an agency roll, a candidate, or
  a marketplace professional may have no account at all.
- **`users` has no mobile number** — the second-strongest matching identifier —
  while `inspectors`, `candidates`, `cx_professionals` and `back_office_staff` all
  do.

> **`users` is NOT declared the canonical Person hub by this document.** Whether
> it should be is **Q7**, and it remains **OPEN**.

> **⚠ AUDIT GAP — now closed by the addendum.** `back_office_staff` was a person
> representation the §2 audit did not cover, and `users` was understated in it.
> Both are addressed in `P6-PERSON-REPRESENTATION-ADDENDUM.md`, which also
> corrects the representation count from three to **five** and the identity
> mechanism count from two to **five** (four in the addendum as first written,
> plus `candidates.person_ref`, added after §20). The questions of whether office staff
> belong in the identity model, and what becomes of `back_office_staff`, remain
> **OPEN (Q8, Q9)**.

---

## The person relationship model

```
                        PERSON
                 (a real human being —
              today an emergent concept,
             resolved through link records)
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
    CANDIDATE          INSPECTOR       MARKETPLACE
   (Recruitment)      (Operations)      PROFESSIONAL
                                         (Marketplace)

   + BACK-OFFICE STAFF — a fourth representation, not yet
     covered by the §2 audit (see AUDIT GAP above)
```

**The rule, stated so it cannot be misread:**

> These are **representations of the same possible human being**.
> They are **NOT automatically the same database record**, and they must never be
> made into one.

`cx_identity_link` is a **relationship mechanism**. It is not, and must never be
read as, permission to merge or delete a source record.

**Explicitly:**

- **CANDIDATE ≠ PERSON**
- **PROFESSIONAL ≠ PERSON**
- **INSPECTOR ≠ PERSON**

---

## Person identity evidence and match outcomes

Matching must be **deterministic and safe**. Evidence the audit confirmed is
available: **e-mail**, **mobile**, and existing system identifiers. Other evidence
(government/professional identifier, date of birth, qualification, certification)
may be used **only where legitimately held**.

| Outcome | Meaning | Who may act |
|---|---|---|
| **EXACT MATCH** | A single strong deterministic identifier agrees and resolves to exactly one counterpart | May be **suggested** automatically; confirmation still recorded |
| **POSSIBLE MATCH** | Supporting evidence agrees but is not conclusive | Suggested only — an authorised person decides |
| **AMBIGUOUS** | Evidence points at more than one counterpart, or conflicts | **Authorised review only. Never automatic** |
| **NO MATCH** | Nothing links them | Leave unlinked |

**Never match on any of these alone:** same name · similar name · same employer ·
same city · same designation.

**Where a record cannot be confidently linked, it stays unlinked.** Phase 6 must
never create a duplicate person to avoid an unresolved match, and must never
destroy the original record.

Every manual confirmation must carry: **actor · timestamp · source records ·
decision · reason · audit trail.** The existing mechanism already records actor,
timestamp, records and an audit entry through `act_log()`.

---

## PHASE 6 IMPLEMENTATION REQUIREMENT — the Candidate → Inspector identity link

**The audit finding.** When a candidate is hired, the system can create an
Inspector — but the identity relationship between the Candidate and the resulting
Inspector is **not recorded in the existing "same person" mechanism**. It is
recorded only on the candidate's own row.

Measured consequence: the person resolver, asked about that candidate, reports
**no inspector** and **not linked**, one second after the system itself created
that inspector. The roles card has no candidate concept at all.

**The eventual Phase 6 requirement:**

```
Candidate → hired → Inspector created → EXPLICIT identity relationship recorded
```

**Not implemented in §3.** Recorded here as a requirement only.

---

## MATERIAL PHASE 6 INTEGRITY REQUIREMENT — the hiring conversion

The audit proved the candidate → inspector conversion has a real partial-failure
and concurrency problem:

- the Inspector can be created **before** the candidate relationship is recorded;
- failure of the second write leaves an **orphan Inspector** — a live person
  record nobody points at;
- concurrent or double execution can create **duplicate Inspector records**;
- the existing guard reads the candidate's state from a row fetched **earlier in
  the request**, so it is a stale check and cannot guarantee uniqueness.

Measured: two inspector rows for one person were accepted, the candidate ended up
pointing at the second, **and the first stayed live**.

**Classification: MATERIAL PHASE 6 INTEGRITY REQUIREMENT.**

The eventual implementation must determine the **smallest compatible** transaction
/ uniqueness / concurrency solution. **Not fixed in §3.**

---

## PHASE 6 REQUIREMENT — duplicate protection on the identity link itself

**The audit proved** that two identical live identity-link records can exist for
the same pair. All three indexes on the link ledger are non-unique; duplicate
control is `SELECT → check → INSERT` in PHP only.

Phase 6 must eventually provide **database-level** protection. The implementation
must consider: unique constraint · race condition · duplicate-insert handling ·
**existing historical links** (a constraint must not reject data already there) ·
tenant isolation · relationship direction · relationship type.

**Not implemented in §3.**

---

# PART 2 — THE ORGANISATION DOMAIN

## ORGANISATION

**Business meaning.** A real-world organisation. One legal entity in the world.

**The central rule:**

> **A business role is a RELATIONSHIP, not a new organisation.**
>
> One company that is simultaneously a **Client**, a **Supplier** and an
> **Employer** is **ONE organisation with three relationships** — never three
> organisations.

**Roles an organisation may hold:** Client · Employer · Supplier · Agency ·
Subcontractor · Marketplace organisation · Recruitment client · Vendor · Service
provider.

## Existing organisation representations

| Representation | Current purpose | Owning domain | Authoritative? | Organisation or role? | Existing mapping | Known gap |
|---|---|---|---|---|---|---|
| **`business_partners`** | The company master: legal name, display name, statutory identifiers, group parent | Core / Operations | **Yes — the spine** | **Organisation**, with roles already modelled as flags (`is_client`, `is_vendor`, `is_subcontractor`) | Referenced by `cx_organisations.party_id` | — |
| **`cx_organisations`** | Marketplace-side organisation: org type, package, onboarding contact, approval state | Marketplace | No — a marketplace **participation** record | Mostly a **role/participation** on top of an organisation | **`party_id` → `business_partners`** | Whether `party_id` is reliably populated is unmeasured |
| **`agencies`** | Recruitment manpower/subcontract agency: contract, fees, guarantee window | Recruitment | No | **Organisation** (duplicating the spine) | **NONE** | **No cross-reference at all** |
| **"Clients" / "Suppliers" / "Vendors"** | Not separate tables — these are the **role flags** on `business_partners` | Core | n/a | **Roles** | n/a | — |

**No physical merge is proposed.** `business_partners` is already the spine the
model needs; the others connect to it.

## PHASE 6 REQUIREMENT — Agency ↔ Organisation mapping

**The audit finding.** The recruitment agency representation exists separately
from the business-partner representation, with **no recorded relationship**. The
same real firm can therefore exist in both domains with nothing saying they are
one organisation.

**Eventual direction:**

```
AGENCY  ↔  ORGANISATION / BUSINESS PARTNER      (controlled relationship)
```

**Not implemented in §3.**

## Organisation identity evidence and match outcomes

Evidence the audit confirmed exists on `business_partners`: **legal name ·
display/trade name · GSTIN · PAN · CIN · TAN · MSME/Udyam · state · website ·
`parent_id`**. `agencies` carries name, GSTIN and contact. `cx_organisations`
carries name and contact.

> **Name similarity alone must NEVER establish identity.**

| Outcome | Meaning |
|---|---|
| **EXACT MATCH** | A statutory identifier (GSTIN / CIN / PAN) agrees and resolves to exactly one organisation |
| **POSSIBLE MATCH** | Name and supporting evidence agree; no statutory identifier confirms it |
| **AMBIGUOUS** | Evidence points at more than one organisation, or conflicts. **Authorised review only** |
| **NO MATCH** | Nothing links them |

Every organisation relationship decision must record: **source organisation ·
target organisation · relationship type · match confidence · decision · actor ·
timestamp · audit reason.**

> **OPEN QUESTION (Q4):** `business_partners` is the obvious spine, but the audit
> did not establish whether every domain may *write* to it or only read it.
> Recorded as a decision, not taken here.

---

# PART 3 — THE TAXONOMY DOMAIN

These concepts are **not interchangeable** and must never be collapsed into one
master.

| Concept | Business meaning | Current home |
|---|---|---|
| **Department** | Internal organisational structure — the part of *our* business somebody belongs to | Phase 2 canonical Department over `lookup_values` (`lib/deptorg.php`) — **LOCKED** |
| **Designation** | The **job title** carried by a person or advertised on a vacancy | `lookup_values` (designation), linked to Department |
| **Position** | A specific **establishment seat** in the organisation structure — a chair that exists whether or not somebody sits in it | Position master (Phase 3) |
| **Vacancy** | An **unfilled seat being recruited for** — the demand, not the title | Expressed through requisition quantity and Phase 4 allocation |
| **Job Family** | A grouping of related roles for career and comparison purposes | `cx_job_families` |
| **Discipline** | A technical field of practice (e.g. welding, NDT, electrical) | `cx_disciplines`, `cx_tax_nodes` |
| **Domain** | A broader field of application | `cx_tax_nodes` |
| **Specialisation** | A narrower field within a discipline | `cx_tax_nodes` |
| **Sub-specialisation** | Narrower again | `cx_tax_nodes` |
| **Skill** | A specific capability a person has | `cx_tax_nodes`, `cx_profile_tax` |
| **Trade** | A recognised vocational trade | `cx_iti_trades` |
| **Equipment / Technology** | Plant, equipment or technology worked on | `cx_equipment_groups`, `cx_equipment_types` |
| **Activity** | A kind of work performed | `cx_inspection_stages` and related |
| **Certification** | A credential awarded and verifiable | `cx_certifications_registry`, `cx_prof_certifications`, `cx_pro_certs` |
| **Qualification** | Academic / formal attainment level | `cx_qualification_levels` |
| **Security Role** | What a **login** may do in the software | `users.role` — **Administration** |

**Two of these are confused most often, so they are stated flatly:**

- **Department ≠ Designation.** A department is where you sit; a designation is
  what you are called.
- **Vacancy designation ≠ Security role.** "Senior Welding Inspector" is a job
  title. "MANAGER" is a permission level in the software. They must never be the
  same list.
- **Staff title ≠ Vacancy designation.** What we call somebody who works here is
  not necessarily what we advertise.

## The Department rule

**The Phase 2 Department architecture remains authoritative and LOCKED.** It
already carries code, name, status, hierarchy, aliases, synonyms, legacy mappings,
display terminology, pending mappings and audit.

**Previously locked decisions, recorded unchanged:**

| Stored value | Canonical Department |
|---|---|
| QA / QC | **Quality** |
| HSE / Safety | **Safety / HSE** |
| FINANCE | **Commercial / Finance** |
| NDT | **Department** (NDT is itself a department) |
| HR | **HR** |

**NDT may simultaneously be:**

- an internal **Department** (a part of our business), **and**
- a technical **discipline**, **and**
- a **trade / skill / specialisation** where applicable.

**These are different semantic uses of the same word and all three are correct at
once.** Nothing in Phase 6 may force a choice between them.

> **Do NOT rewrite stored historical values merely to standardise terminology.**
> Use mappings. A record that says "QA/QC" keeps saying "QA/QC"; the mapping tells
> the reader it means Quality.

## Taxonomy convergence

Internal organisational taxonomy **and** technical/professional taxonomy must
**coexist and be mapped**. The richer marketplace taxonomy must not be discarded
because recruitment screens have simpler fields.

The resolution ladder, which the marketplace graph already implements:

```
EXACT (normalised) MATCH
   → APPROVED ALIAS / SYNONYM
      → SUGGESTION (fuzzy / AI where already supported)
         → AMBIGUOUS
            → UNKNOWN
```

**AI and fuzzy matching may SUGGEST. They must NEVER silently merge or rewrite an
authoritative value.** An unknown value must remain safely representable until an
authorised person resolves it.

---

# PART 4 — THE RECRUITMENT DEMAND DOMAIN

**These are four different things. Do not call them all "requirement".**

| Term | Business meaning | Owner |
|---|---|---|
| **Hiring Request** | The business **ask** — somebody wants people | M4 |
| **Approved Hiring Request** | The **authorised** business need, after approval | M4 — owns the approval ceiling |
| **Recruitment Requisition** | The recruitment **execution record** — the work of finding them | M3 (fulfilment), M5 (accountability) |
| **Fulfilment Allocation** | **Phase 4's** record of how many seats of the approved demand are promised to which source | **Phase 4 — authoritative** |
| **Marketplace Requirement** | The **marketplace demand representation** — what is advertised to the pool | Marketplace |

## Recruitment Requisition vs Marketplace Requirement

> **`requisitions` and `cx_requirements` remain SEPARATE entities and must NEVER
> be physically merged.**

**Phase 4's allocation model remains authoritative for source allocation.**

**What already exists** (audit finding): the relationship is already built, as a
Phase 4 fulfilment source —

```
requisition_allocations.source            = 'MARKETPLACE'
requisition_allocations.source_entity_id  → cx_requirements.id
```

— existence-checked, and **refused outright** when the workspace has not bought
the Connect module (`NO_ENTITLEMENT`, never a hidden option). Because the
allocation is bounded by Phase 4's COMMITTED ceiling, **one approved demand cannot
be promised twice.**

**What the eventual Phase 6 relationship must answer:**

1. Which recruitment demand is this Marketplace demand serving?
2. Which allocation authorised the Marketplace sourcing?
3. How many seats are being sourced?
4. How does the system prevent double-counting?

Questions 1–3 are answered today **only in one direction** (requisition →
marketplace). Question 4 is answered by Phase 4 for the *allocation*.

**Not solved in §3.** Conceptual ownership only.

## Marketplace Professional in the demand model

A **Marketplace Professional** is a marketplace representation of a person. It may
relate to a Person, a Candidate, an Inspector or a workforce representation — **but
it remains a Marketplace-domain record**, owned by Marketplace.

Existing marketplace identity mechanisms are protected. **No new professional
identity system.**

## The fulfilment model

```
        APPROVED DEMAND                    (M4 — the ceiling)
               ↓
     RECRUITMENT REQUISITION               (M3 / M5 — the execution record)
               ↓
      FULFILMENT ALLOCATION                (Phase 4 — AUTHORITATIVE)
               ↓
             SOURCE
     ┌─────────┼─────────┬──────────────┐
  INTERNAL   AGENCY   MARKETPLACE   OTHER SOURCE
               ↓
    CANDIDATE / PROFESSIONAL
               ↓
      SELECTION / JOINING
               ↓
  WORKFORCE / DEPLOYMENT                   (Operations, where applicable)
```

Marketplace is **one** possible source. Agency is another. Internal/direct is
another.

**Phase 6 must not replace the Phase 4 allocation engine, and must not create a
second source ledger.**

The chain must continue to answer: *who supplied the person · which source was
used · which allocation received the credit · which requirement and seat the
person filled · which recruiter was accountable · which marketplace professional
record represents them.*

---

# PART 5 — CROSS-CUTTING RULES

## Historical integrity

> **CURRENT IDENTITY ≠ HISTORICAL BUSINESS FACT.**

| If… | Then… |
|---|---|
| A recruiter changes | Historical recruiter credit **does not move** (Phase 5 already enforces this from the M5 ledger) |
| A person changes employer | Past assignments and employment records remain historically correct |
| An organisation changes name | Historical documents remain understandable and traceable |
| A taxonomy term changes display name | Historical records remain interpretable through the mapping |

**Identity mapping must improve future traceability without rewriting history.**

## Field ownership

> **ONE AUTHORITATIVE WRITER PER BUSINESS FIELD.**

The final field-by-field matrix is **not** required at §3. What is required is the
principle and the list of fields the implementation must later resolve:

| Domain | Fields needing an owner |
|---|---|
| **Person-level** | name · mobile · e-mail · qualification · certification |
| **Candidate** | recruitment status · requisition relationship · recruitment-specific information |
| **Professional** | marketplace profile · availability · rates · marketplace-specific information |
| **Inspector** | technical and operational information · workforce information |
| **Organisation** | legal name · trade name · GSTIN · PAN · CIN · address · contact · business role |

**No uncontrolled bidirectional copying.** Where synchronisation is genuinely
needed, the direction must be stated explicitly and in one direction only.

## Tenant and scope

Every future relationship must respect **TENANT · ORGANISATION · BRANCH · PROJECT
· USER** scope where applicable.

> **A relationship is never valid merely because both records exist.**

The implementation must verify the relationship is *permitted within the relevant
scope* — not merely that two ids resolve. Tenancy is structural (one database per
tenant), which makes a cross-tenant link hard by construction, **but §22 requires
it to be proved, and it has not been.**

> **OPEN QUESTION (Q5):** which scope class applies to each relationship is not
> yet decided. Recorded, not guessed.

---

# PART 6 — WHAT PHASE 6 WILL NOT DO

Phase 6 will **NOT** replace:

`inspectors` · `candidates` · marketplace professionals · `business_partners` ·
`agencies` · Departments · the marketplace taxonomy · `requisitions` ·
`cx_requirements` · Phase 4 allocations · M3 approval/SLA · M4 re-approval ·
M5 recruiter accountability · the Phase 5 KPI engine.

**It will CONNECT them.**

No second identity engine · no second organisation engine · no second taxonomy
engine · no second Marketplace engine · no second KPI engine · no second approval
engine · no second recruitment pipeline engine.

---

# PART 7 — THE CONCEPTUAL MODEL

**These are conceptual domains and relationships. They are NOT instructions to
create one table per box.** Most boxes below already exist; several are views over
existing records; none is a new master.

```
PERSON                                  ORGANISATION
│                                       │
├── CANDIDATE        (Recruitment)      ├── CLIENT          ┐
│                                       ├── EMPLOYER        │ roles on ONE
├── INSPECTOR        (Operations)       ├── SUPPLIER        │ organisation —
│                                       ├── AGENCY          │ never separate
└── MARKETPLACE PROFESSIONAL            └── MARKETPLACE     ┘ organisations
                     (Marketplace)          ROLE

  (+ BACK-OFFICE STAFF — audit gap)


RECRUITMENT DEMAND                      TAXONOMY
│                                       │
├── HIRING REQUEST       (M4)           ├── DEPARTMENT      (Phase 2 — LOCKED)
│      ↓ approved                       ├── JOB FAMILY
├── RECRUITMENT REQUISITION (M3/M5)     ├── DESIGNATION
│      ↓                                ├── DISCIPLINE
└── FULFILMENT ALLOCATION (Phase 4)     ├── SPECIALISATION
        │                               ├── TRADE
        ├── INTERNAL                    ├── SKILL
        ├── AGENCY                      ├── CERTIFICATION
        ├── MARKETPLACE ──────────────► └── QUALIFICATION
        └── OTHER SOURCE
                 │
                 └──► MARKETPLACE REQUIREMENT   (separate entity — never merged)
```

---

## Open Questions Requiring Owner Decision

*Only questions the §2 audit did not answer. None is decided here.*

**Q1 — Marketplace advert quantity. BUSINESS DECISION REQUIRED.**
`cx_requirements.positions` is the marketplace advert's own headcount, independent
of the approved recruitment demand. Should it be treated as **a representation of
approved recruitment demand** (and therefore constrained by it), or as **a
source-side requested quantity** that may legitimately differ — for instance
because one marketplace advert serves several buyers?
*A view was offered in conversation earlier; it was a suggestion, not a decision,
and the audit does not settle it.*

**Q2 — Reverse navigation. BUSINESS DECISION REQUIRED.**
The relationship runs requisition → marketplace requirement only. Should Phase 6
add Marketplace → Recruitment reverse navigation, so that from an advert you can
see which approved demand it serves? There is a legitimate argument against: the
marketplace may not be entitled to see recruitment internals.

**Q3 — The authoritative Person representation. OPEN QUESTION.**
Today Person is *emergent*: there is no person record, and identity exists only
as the pattern formed by the five representations and the **five** mechanisms
that join them. `inspectors` is the structurally central register; `cx_identity_link`
is the only explicit, reversible, audited relationship mechanism. The resolver
`connect_person_resolve()` traverses through the marketplace professional row —
which is a property of that function, **not** a canonical hub, and which means a
person outside the marketplace has no traversal path.

Should Phase 6 (a) keep the emergent model and add the missing edges,
(b) promote one existing representation to hub, or (c) introduce a person record?
**Option (c) is discouraged by §4 of the master prompt unless the audit proves it
necessary — and the audit did not.** See also **Q7** in the addendum, which asks
the same question of `users` specifically.

**No hub is chosen in this document.**

**Q4 — The authoritative organisation representation. OPEN QUESTION.**
`business_partners` is the evident spine. The audit did not establish whether
every domain may *write* to it, or only read it and hold its own role record.

**Q5 — Scope class per relationship. OPEN QUESTION.**
Which of GLOBAL / TENANT / ORGANISATION / BRANCH / USER / PROJECT applies to an
identity link, an organisation link, a taxonomy mapping and a requirement mapping
is not yet decided.

**Q6 — Back-office staff. AUDIT GAP + BUSINESS DECISION REQUIRED.**
`back_office_staff` is a fourth person representation the §2 audit did not cover.
Is an office staff member allowed to be the same human as an inspector or a
candidate, and should it participate in the identity model at all?

---

## Phase 6 Implementation Requirements Identified by §3

*Requirements for later implementation. **None is implemented now.***

| # | Requirement | Source | Severity |
|---|---|---|---|
| R1 | **Candidate → Inspector identity link** recorded in the existing mechanism | §2 audit · §7 | Implementation requirement |
| R2 | **Safe candidate → Inspector conversion** — transaction, uniqueness, concurrency; smallest compatible change | §2 audit · §8 | **MATERIAL INTEGRITY** |
| R3 | **Duplicate identity-link protection** at database level, not PHP-only | §2 audit · §9 | **MATERIAL INTEGRITY** |
| R4 | **Agency ↔ Organisation mapping** | §2 audit · §12 | Implementation requirement |
| R5 | **Person identity lookup across representations** — the resolver must read both mechanisms, and the roles card must be able to express a candidate | §2 audit | Implementation requirement |
| R6 | **Organisation identity lookup across representations** | §11 | Implementation requirement |
| R7 | **Taxonomy mapping** between canonical Department and the technical taxonomy | §15, §16 | Implementation requirement |
| R8 | **Recruitment ↔ Marketplace demand relationship** — preserve Phase 4 ownership; answer the four questions | §18 | Implementation requirement, gated on Q1/Q2 |
| R9 | **Historical integrity** — no mapping may rewrite a historical fact | §21 | Constraint on all of the above |
| R10 | **Tenant / scope protection** on every relationship | §23 | Constraint, gated on Q5 |
| R11 | **Concurrency protection** on every relationship write | §29 of master prompt | Constraint |
| R12 | **Extend the §2 audit to cover `back_office_staff`** before implementation | This document | Prerequisite |

---

## Document quality check

| Check | Status |
|---|---|
| Every entity has a clear business definition | ✔ |
| Similar entities explicitly distinguished | ✔ |
| Existing representations identified | ✔ (incl. one the audit missed, flagged) |
| No physical merge proposed | ✔ |
| Candidate ≠ Person explicit | ✔ |
| Professional ≠ Person explicit | ✔ |
| Inspector ≠ Person explicit | ✔ |
| Organisation roles distinguished from organisation identity | ✔ |
| Department ≠ Designation | ✔ |
| Recruitment Requisition ≠ Marketplace Requirement | ✔ |
| Fulfilment Allocation remains Phase 4 authority | ✔ |
| M3–M5 and Phase 4–5 ownership preserved | ✔ |
| Historical integrity defined | ✔ |
| Ambiguous identity never auto-merged | ✔ |
| Tenant / scope principles defined | ✔ |
| Open questions separated from decisions | ✔ |
| Implementation requirements separated from current work | ✔ |
| No product code changed | ✔ |
| No database change made | ✔ |
| No migration created | ✔ |
