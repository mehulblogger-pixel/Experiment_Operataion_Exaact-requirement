# EXAACT Recruitment Universalisation
## Product Rules & Configuration Specification

**Status:** SPECIFICATION ONLY — no implementation authorised
**Derived from:** `docs/phase7/RECRUITMENT-UNIVERSALISATION-AUDIT.md` (the audit)
and `docs/phase7/RECRUITMENT-BUSINESS-DECISION-PACK.md` (commit `a010113`)
**Owner decisions recorded:** D1–D9, as given by the business owner
**Date:** 2026-09-27

---

## How to read this document

Three kinds of statement appear, and they are deliberately never mixed:

| Tag | Meaning |
|---|---|
| **DECIDED** | The business owner has decided this. It is binding. |
| **EVIDENCE** | A verified fact about the system as it stands today, with a file or a count behind it. |
| **UNDECIDED — DO NOT IMPLEMENT** | No business decision exists. Nobody may choose it in code. |
| **CLARIFICATION REQUIRED** | A decision exists but is not yet precise enough to build from. |

Where the audit and a decision disagree, **the decision wins and the
disagreement is written down** — it is never resolved quietly in code.

This document authorises **no change to any running system**. Section 16 states
that explicitly.

---

## IMPORTANT — the decision numbering differs from the Decision Pack

The owner numbered the decisions D1–D9. The Decision Pack numbered them 1–9 in a
different order. **Five of the nine do not line up.** An implementer who reads
"D5 = B" against the Pack's own Decision 5 will apply an approval answer to the
candidate-roles question.

| Owner's number | Subject | Decision Pack number |
|---|---|---|
| **D1** | Candidate lifecycle | Decision 1 ✔ same |
| **D2** | Person specification | Decision 2 ✔ same |
| **D3** | Candidate visibility | **Decision 4** |
| **D4** | Candidate access roles | **Decision 5** |
| **D5** | Approval matrix | **Decision 3** |
| **D6** | Offer audit history | **Decision 7** |
| **D7** | Re-approval after change | **Decision 6** |
| **D8** | Recruitment landing page | Decision 8 ✔ same |
| **D9** | Mobile | Decision 9 ✔ same |

**Rule for all future work: the letter belongs to the question, not to the
number.** Every option letter the owner gave was checked against the options
actually offered for *that subject*, and each one matches the subject correctly.
The numbering differs; the intent does not.

**From this document onward, the owner's D1–D9 numbering is authoritative.**

---

# Section 1 — Executive Summary

## What the business has decided, in plain language

EXAACT will have **one recruitment system**, not one per industry. A TPIA
company, a recruitment agency, a manufacturer and an IT services firm will all
run the same engine; what differs between them is **configuration**, not code.

Six things follow from the nine decisions:

**1. One official candidate journey.** Today two journeys run side by side — an
old fixed one used by all 935 candidates, and a newer configurable one used by 2.
The configurable one becomes the official journey. Everything that reports on
recruitment will eventually read from it.

**2. The approval means something.** Today a manager approves a headcount and the
recruiter afterwards types in the qualification, the experience and the skills —
so the approval does not actually constrain who gets hired. In future the
**approved request carries the core requirement**, and the requisition adds only
execution detail on top. It may add; it may not weaken.

**3. Type it once.** Anything captured and approved earlier flows downstream
automatically. Nobody re-types what the system already knows.

**4. Candidates are a company asset until someone is working on them.** A
candidate who is not hired and not in an active process is visible to authorised
recruitment users company-wide. Once a candidate enters an active process,
visibility follows the recruitment relationship and the user's permissions.

**5. Approval is configurable per organisation.** A corporate HR department may
need department head → HR head → management. An agency may need no internal
approval at all. Both are the same engine with different rules. **No approval
chain is hard-coded.**

**6. Offers are permanently auditable.** Every significant thing that happens to
an offer is recorded and never rewritten.

## The one-line architecture principle

> **One recruitment engine. One approval engine. One pipeline engine. One
> KPI/SLA engine. Industry differences live in configuration, never in a second
> copy of the module.**

## What this document does not do

It does not change anything. It does not migrate the 935 candidates, write a
single approval rule, add a field, or move a screen. It defines the rules so that
a later implementation plan can be written and approved.

## Three findings that change how two decisions should be read

The specification exercise surfaced three facts that the owner should know before
an implementation plan is written. All three are evidence, not opinions.

**Finding 1 — D7 is largely already built.** A material-change engine already
exists for hiring requests: `HREQ_MATERIAL_FIELDS` lists twelve fields whose
change requires re-approval, and `hreq_material_diff()` compares against the
**originally approved snapshot** rather than the previous edit — so ten harmless
edits followed by one material edit still trigger re-approval. It is documented
at `docs/phase3/M4-MATERIAL-CHANGE-MATRIX.md`. D7 is therefore mostly a question
of *adjusting an existing list*, not building a mechanism. See Section 10.

**Finding 2 — but the approved budget is not currently protected.** The hiring
request does carry money (four indicative estimate fields), and the approved
figure already becomes the requisition's budget baseline. However
`est_cost_per_person` is **not** in the material-change list, so today a manager
can approve a cost and the figure can be changed afterwards without re-approval.
This is precisely the gap D7 exists to close. See Section 10.

**Finding 3 — D2's missing fields are fewer than feared, but the important ones
are missing.** Of the core requirement the owner wants approved, the hiring
request already holds role, department, number of positions, location, office,
employment type, client, priority and budget estimates. It does **not** hold
minimum qualification, minimum experience, or essential skills — the three that
decide *who is eligible*. Those exist only on the requisition, created after
approval. See Section 6.

---

# Section 2 — Locked Decisions

Each decision is recorded as the owner gave it, followed by what it binds, what
it does not settle, and any disagreement with the audit.

---

## D1 — The authoritative recruitment lifecycle

**DECIDED: Option B — the configurable pipeline is the authoritative candidate
recruitment lifecycle.**

The legacy `CAND_STAGES` lifecycle must not remain a competing authoritative
lifecycle. Reporting, dashboards, SLA/KPI calculation and recruitment behaviour
must ultimately derive candidate lifecycle state from the configurable pipeline.

**No migration is authorised now.**

### What "authoritative" means — the definition this specification fixes

A lifecycle is *authoritative* when all five of these are true:

1. **Single source of truth.** When a candidate's position in recruitment is
   asked for, the answer is computed from the configurable pipeline
   (`pipeline_id` + `pipeline_stage_id`) and from nothing else.
2. **Single point of change.** A candidate's stage changes through the pipeline
   engine only. No screen, import or integration writes a lifecycle value by
   another route.
3. **Downstream dependency.** Every consumer — funnel, KPI, SLA, ageing,
   dashboards, exports, reports — reads the pipeline, not the legacy field.
4. **Configuration is effective.** Changing the configured stages changes the
   actual behaviour of the candidate screen, not merely the wording.
5. **No silent fallback.** If pipeline state is missing, the system says so. It
   does not quietly fall back to the legacy field and present the result as fact.

Until all five hold, the pipeline is *intended* to be authoritative but is not
yet authoritative. **That distinction must be stated in any status report.**

### How pipeline stages represent the candidate lifecycle

- A **pipeline** is an ordered set of **stages** belonging to one recruitment
  model (an industry, a business type, or a specific hiring style).
- A **stage** carries a *kind* (its business meaning — screening, interview,
  offer, terminal) and a *label* (what the user sees).
- The **kind** is what downstream engines reason about. The **label** is what
  configuration changes freely.
- A candidate occupies exactly one stage of exactly one pipeline at a time.
- Terminal stages end the active process.

**This separation is the mechanism that makes the engine industry-portable:**
an agency's "Submitted to client" and a manufacturer's "Plant interview" can be
different labels over the same kinds, so reports keep working across industries.

### How downstream modules must consume the lifecycle

| Consumer | Must read | Must not read |
|---|---|---|
| Funnel / dashboards | pipeline stage + kind | legacy `stage` |
| KPI / SLA / ageing | pipeline stage + the stage ledger | legacy `stage` |
| Reports / exports | pipeline stage + kind | legacy `stage` |
| Recruitment behaviour (what the user may do next) | pipeline stage + kind | legacy `stage` |

**CLARIFICATION REQUIRED — the reporting contract.** Whether downstream modules
read the *current* stage only, or the *stage history ledger*, differs by consumer
and is not decided here.

### Reconciliation of legacy data — principles only

The owner has not authorised migration mechanics, and the source material does
not prescribe them. The following are **principles that constrain any future
migration**, not a migration design:

1. **Nothing is destroyed.** The legacy `stage` value is preserved on every
   record. It is demoted from authority; it is not deleted.
2. **History is preserved.** The existing stage ledger (`candidate_events`) is
   append-only and must remain so.
3. **Every candidate must land somewhere defensible.** A candidate cannot be left
   with no authoritative position.
4. **A conflict is reported, not guessed.** Where the legacy stage and pipeline
   stage disagree, the disagreement is surfaced for a human, not silently
   overwritten by precedence.
5. **Reversible.** The mapping applied must be recorded so it can be re-run or
   undone.
6. **One-way sync stops.** The present partial write-back is replaced by a single
   direction of truth; two half-mechanisms must not both continue.

**UNDECIDED — DO NOT IMPLEMENT:** the default pipeline the 933 legacy candidates
map onto; the legacy-stage → pipeline-stage mapping table; whether migration is
one-off or lazy on next touch; whether terminal legacy candidates are migrated at
all.

### EVIDENCE — the position today

| Fact | Value | Source |
|---|---|---|
| Candidates in total | 935 | audit §17 (exact count) |
| Carrying a legacy stage | 935 (100%) | audit §17 |
| On a configured pipeline | 2 (0.2%) | audit §17 |
| Configured pipelines | 4 | Decision Pack correction |
| Configured stages | 33 | Decision Pack correction |
| Sync direction | one-way, partial | `lib/recruitpipe.php:451-459` |
| Sync coverage | `interview` and `offer` kinds only, never over a terminal legacy stage | source comment, verbatim |
| Stage ledger | `candidate_events` via `rkpi_stage_log()` | `lib/recruitpipe.php:445-449` |
| Engine | `lib/recruitpipe.php` | audit L04 |

**EVIDENCE — a live agency assumption in the default list.** `CAND_STAGES`
contains `'SUBMITTED' => 'Submitted to client'`, a staffing/agency concept sitting
in the default list that every candidate currently uses. It is label-overridable
through the lookup engine, but overriding it changes wording only — not the shape
of the funnel. **This is the clearest single illustration of why D1 matters.**

---

## D2 — Hiring Request vs Requisition person specification

**DECIDED: Option C — core requirement on the Hiring Request, execution detail on
the Requisition.**

### The Hiring Request defines the approved business intent

Including, as applicable: position/role; department; number of positions;
location/office; employment or engagement type; **minimum qualification**;
**minimum experience**; **essential skills**; approved budget or range; other
essential eligibility criteria.

### The Requisition defines recruitment execution detail

It may add: detailed job description; preferred skills; screening questions;
sourcing requirements; client-specific requirements; recruitment instructions;
candidate evaluation criteria; other execution-level information.

### Two binding rules

**Rule D2-A — inheritance is mandatory.**
> Information approved at Hiring Request is inherited by the Requisition wherever
> applicable, so users never enter the same information twice.

**Rule D2-B — the Requisition may strengthen, never weaken.**
> The Requisition must not silently weaken the approved core requirement.

"Weaken" means making the requirement easier to satisfy than what was approved —
lowering a minimum qualification or minimum experience, removing an essential
skill, or widening an engagement type. "Strengthen" (asking for more than was
approved) is permitted, because it cannot spend authority that was not granted.

**CLARIFICATION REQUIRED — the enforcement posture.** Whether an attempt to
weaken is *blocked*, *warned*, or *routed to re-approval* is not decided. This
connects directly to D7 (Section 10) and must be decided with it, not separately.

### EVIDENCE — what exists today, field by field

Verified against `lib/hiringreq.php` (35 columns) and `lib/recruit.php:50-70`.

| Core requirement (D2) | On Hiring Request today? | Where it lives today |
|---|---|---|
| Position / role | **Yes** | `designation`, `job_title`, `position_id`, `grade` |
| Department | **Yes** | `hiring_department_id`, `requesting_department_id` |
| Number of positions | **Yes** | `quantity` |
| Location / office | **Yes** | `office_id`, `work_location` |
| Employment / engagement type | **Yes** | `employment_type` |
| Client (where applicable) | **Yes** | `client_id` |
| Approved budget / range | **Yes, as indicative estimate** | `est_cost_per_person`, `est_cost_basis`, `est_duration_months`, `est_onetime_cost` |
| **Minimum qualification** | **NO** | `requisitions.qualification` only — after approval |
| **Minimum experience** | **NO** | `requisitions.experience_min`, `relevant_experience` — after approval |
| **Essential skills** | **NO** | `requisitions.skills` — after approval |
| Other essential eligibility | **NO** | `requisitions.discipline`, `category`, `trade_id`, `skill_id` — after approval |

**The gap is exactly three concepts: qualification, experience, skills** — the
three that decide *who is eligible*. Everything else the owner listed as core is
already on the approved record.

**EVIDENCE — why this is the highest-value gap.** The audit records at D-E1 that
these fields exist on `requisitions` and on no earlier record, so they are typed
*after* approval by the recruiter with nothing to copy from. The audit's
conclusion, verbatim: *"Effect: the approval does not constrain the
recruitment."* The audit also records at §32 that this same person-spec gap
recurs in **11 of 13 industries** assessed — it is the single most repeated
blocker to industry portability.

**EVIDENCE — the budget estimate is deliberately indicative.** Source comment:
*"These four are deliberately INDICATIVE — nobody knows the exact salary when
raising a request, and demanding one would stall the flow. An estimate the
approver can see beats an exact number they cannot."* Any future change must
preserve that intent: D2 must not turn an estimate into a mandatory exact figure.

---

## D3 — Candidate visibility

**DECIDED: Model A (company-wide), with a condition.**

Candidates who are **not hired** and **not currently under an active recruitment
process** belong to the authorised company-wide candidate pool. Once a candidate
enters an active recruitment process, visibility becomes controlled by the
recruitment relationship and the user's permissions.

The conceptual states are defined in Section 7.

**Documented disagreement with the pure option.** Decision Pack Model A is
"anyone in recruitment sees every candidate" — which is today's behaviour, with no
state condition. The owner's condition adds relationship-and-permission control
once a candidate is active. **This is Model A for the available pool plus a gate
for active candidates** — not pure Model A, and not Model C (which scoped
candidates to an office by default). The owner's decision governs. It is recorded
here as a deliberate refinement, not a contradiction.

**Consequence the owner should see:** because the condition applies only to
*active* candidates, the confidentiality concern that prompted the question —
one branch reading another branch's candidate list and salary expectations — is
addressed **only while those candidates are in an active process**. Candidates in
the available pool remain visible company-wide by design. That is the decision as
given.

---

## D4 — Candidate access roles

**DECIDED: the business roles are Recruiters, Hiring Managers and Department
Heads, subject to the permissions granted to the user's account.**

**Binding rule D4-A — the access formula:**
> Role + Permission + Candidate/Recruitment relationship = effective access

This must **not** be read as "every Recruiter automatically sees everything."

Actions to be distinguished are listed in Section 7.

**UNDECIDED — DO NOT IMPLEMENT.** The Decision Pack asked for a View / Edit / No
access mark against **eleven** roles. Three were answered. The following **eight
remain undecided** and no code may assume a value for them:

HR · Operations manager · Coordinator · Administrator · Finance · Inspector /
field staff · External client · Agency

**CLARIFICATION REQUIRED — role naming.** "Hiring Managers" and "Department
Heads" are the owner's words; the Decision Pack's equivalents were "Recruitment
manager" and "Department manager (the one who asked for the hire)". Whether these
are the same roles under different names, or different roles, must be confirmed
before any permission work.

---

## D5 — Approval matrix

**DECIDED: approval must be supported, and rule-configurable, for these business
events:**

1. Hiring Request
2. Candidate Hiring
3. Salary Approval

**DECIDED: approval rules are configurable per organisation / business
configuration. No universal approval chain is hard-coded.**

A corporate organisation may require *Hiring Request → Department Head → HR Head
→ Management*. A recruitment agency may require no internal hiring-request
approval, or a different model entirely. Both are the same engine.

**DECIDED: capability and activation are separate concepts.** That the system
*can* approve something is distinct from whether approval *is enabled* for a
given organisation and process.

Full detail, including what the engine already supports, is in Section 8.

**CLARIFICATION REQUIRED — what "Candidate Hiring" means.** The existing engine
recognises four approvable entities: `HIRING_REQUEST`, `REQUISITION`, `OFFER`,
`SALARY`. "Candidate Hiring" does not map to one of them unambiguously. It may
mean (a) the existing Offer approval, (b) a new approval at the point of hiring
or joining, or (c) both. **This must be answered before any rule is configured**,
because (b) is a new approval point and not merely configuration.

**Read as: Requisition approval is NOT required.** The owner's list omits
Recruitment Requisition. Recorded as an inferred "No" and flagged for
confirmation, because the audit shows a requisition approval rule can be saved
today and will silently never run — so a "No" here is consistent with reality,
whereas an unnoticed "Yes" would be a trap.

**UNDECIDED — DO NOT IMPLEMENT:** the actual approval chains. The Decision Pack
asked for the chain in the owner's own words (thresholds, sequence, who
approves). The owner's answer establishes that chains must be *configurable*; it
does not state what EXAACT's own chains are. **No approval rule may be written
until the owner states them.**

---

## D6 — Offer audit history

**DECIDED: YES — significant offer lifecycle events must be permanently
auditable.**

Historical offer records must not be deleted or rewritten. Full detail in
Section 9.

---

## D7 — Re-approval after changes

**DECIDED: Option B — not every change to an approved requirement requires
re-approval. Only selected material changes do. Non-material changes continue
without restarting approval.**

**Not authorised for implementation now.**

**Documented broadening of the option.** Decision Pack Decision 6 asked
specifically about *an approved budget changing*, and its Option B was "only
increases beyond a threshold". The owner's D7 generalises this to a
**material-change matrix across several fields**. The broadening is accepted as
the business decision; it is recorded here because an implementer comparing the
two documents would otherwise see a mismatch. Full detail, including the
already-existing mechanism, in Section 10.

---

## D8 — Recruitment landing page

**DECIDED: Option B — EXAACT has one primary Recruitment home.**

It provides access to recruitment execution, pipeline/funnel visibility, KPIs,
relevant actions and relevant recruitment functions. Existing recruitment
destinations must not present themselves to users as competing "Recruitment
homes".

**Nothing may be deleted now.** Existing screens and routes remain. How they are
reused, redirected or integrated is decided later, following
**REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → BUILD**.

**CLARIFICATION REQUIRED — one reading to confirm.** Decision Pack Option B was
"the general Home stays the landing page, with the Command Centre one click
away", while the owner's words emphasise "one primary Recruitment home". These
are compatible under one reading: **the application's general Home remains the
landing page, and within recruitment there is exactly one recruitment home
(the Command Centre), one click away.** This specification adopts that reading.
If the owner instead meant that the Recruitment Command Centre becomes the
application's landing page, that is Decision Pack Option A and this section must
be revised.

---

## D9 — Mobile

**NOT a business decision. A verification requirement.**

The audit did not adequately verify actual recruitment behaviour on a phone.
**Recruitment must not be assumed mobile-ready.** Detail in Section 12.

---

# Section 3 — Decision Dependency Map

Which decisions must be settled before others can be built.

```
                    ┌──────────────────────────────┐
                    │  D1  Authoritative lifecycle │  ← foundation
                    └───────────────┬──────────────┘
                                    │ everything that reports
                                    │ on recruitment sits here
          ┌─────────────────────────┼──────────────────────────┐
          ▼                         ▼                          ▼
   KPI / SLA / ageing        Dashboards / funnel        Industry stage sets
                                                        (Section 13)

                    ┌──────────────────────────────┐
                    │  D2  Person specification    │  ← second foundation
                    └───────────────┬──────────────┘
                 ┌──────────────────┼───────────────────┐
                 ▼                  ▼                   ▼
        Inheritance (S6)   D7 material-change    Industry portability
                           matrix needs the      (11 of 13 industries
                           fields to exist       blocked on this)

   ┌───────────────┐     ┌───────────────┐     ┌───────────────┐
   │ D3 visibility │────▶│ D4 roles &    │     │ D5 approval   │
   │ (states)      │     │ actions       │     │ configurable  │
   └───────────────┘     └───────────────┘     └───────┬───────┘
        both are halves of one access model            │
                                                       ▼
                                              D7 re-approval
                                              (re-enters the
                                               approval engine)

   ┌───────────────┐   ┌───────────────┐   ┌───────────────────────┐
   │ D6 offer audit│   │ D8 one home   │   │ D9 mobile verification│
   └───────────────┘   └───────┬───────┘   └───────────────────────┘
     independent              │ consumes D1 (funnel) and     independent —
                             │ D3/D4 (what a user may see)   verifies whatever
                             ▼                               exists
                      needs nothing decided first
```

## Ordering rules that follow

| Rule | Reason |
|---|---|
| **D1 before any reporting, KPI, SLA or dashboard work** | All of them read lifecycle state. Building them on the legacy field means rebuilding them. |
| **D2 before D7's field matrix can be finalised** | Three of the fields the owner named as material (qualification, experience, skills) **do not exist yet**. A matrix cannot reference them. |
| **D2 before industry rollout** | The person-spec gap blocks 11 of 13 industries (audit §32). |
| **D3 and D4 together, never separately** | They are two halves of one access model: D3 defines *when* a gate applies, D4 defines *who* passes it. Implementing one alone produces a half-mechanism. |
| **D5's "Candidate Hiring" question before any approval rule** | If it means a new approval point, that is new wiring, not configuration. |
| **D7 after D5** | Re-approval re-enters the approval engine; its behaviour depends on how approval is configured. |
| **D6 and D9 independent** | Neither blocks nor is blocked by the others. |
| **D8 after D1** | The one home shows the funnel; the funnel depends on which lifecycle is authoritative. |

## The critical path

> **D1 → D2 → (D3 + D4) → D5 → D7**, with D6, D8 and D9 able to proceed in
> parallel once D1 is settled.

**D1 and D2 are the two foundations. Work built before they are settled is work
that will be redone.**

---

# Section 4 — Recruitment Business Lifecycle

## The end-to-end business flow

```
  ORGANISATION CONFIGURATION
  (business type · industry · recruitment model · approval model ·
   pipeline model · terminology · fields · dropdowns · permissions)
            │
            │  configures every step below — the user never reconfigures
            ▼
  ┌──────────────────────┐
  │   HIRING REQUEST     │   "we need a person"
  │   the approved need  │   core requirement: role, department, quantity,
  └──────────┬───────────┘   location, engagement type, qualification,
             │               experience, essential skills, budget
             ▼
       ◆ APPROVAL ◆            configurable per organisation (D5)
             │                 may be: not required · single · chained ·
             │                 threshold-based · escalating · delegated
             ▼
  ┌──────────────────────┐
  │ APPROVED HIRING REQ. │   snapshot taken — this is what was authorised
  └──────────┬───────────┘
             │  inherits downward (D2-A) — nothing re-typed
             ▼
  ┌──────────────────────┐
  │    REQUISITION       │   "go and find them"
  │  execution detail    │   adds JD, preferred skills, screening questions,
  └──────────┬───────────┘   sourcing, client requirements, evaluation criteria
             │               may strengthen, may not weaken (D2-B)
             ▼
  ┌──────────────────────┐
  │     CANDIDATE        │   sourced, applied, referred, imported, marketplace
  └──────────┬───────────┘
             │
             ▼
  ┌──────────────────────────────────────────────────┐
  │        CONFIGURABLE PIPELINE  (authoritative)    │  D1
  │  stages configured per business type / industry  │
  │  each stage has a business KIND and a LABEL      │
  └──────────┬───────────────────────────────────────┘
             ▼
  ┌──────────────────────┐
  │ INTERVIEW/ASSESSMENT │   multi-round, scorecards
  └──────────┬───────────┘
             ▼
  ┌──────────────────────┐
  │        OFFER         │   every significant event permanently audited (D6)
  └──────────┬───────────┘
             │
       ◆ APPROVAL ◆            offer / salary approval where enabled (D5)
             │
             ▼
  ┌──────────────────────┐
  │   CANDIDATE HIRING   │   acceptance → joining
  └──────────┬───────────┘
             ▼
  ┌──────────────────────┐
  │      WORKFORCE       │   no longer a recruitment-pool candidate (D3)
  └──────────────────────┘
```

## Where approval may occur

| Point in the flow | Approval | Status today | Decision |
|---|---|---|---|
| Hiring Request submitted | **Yes** | configurable **and works** | D5 — confirmed required |
| Requisition raised | **No** (inferred) | configurable but **never fires** | D5 — omitted by owner; confirm |
| Offer to candidate | Yes | configurable **and works** | D5 — see "Candidate Hiring" clarification |
| Candidate Hiring (joining) | **To be clarified** | no such approval entity exists | D5 — CLARIFICATION REQUIRED |
| Salary structure | **Yes** | configurable but **never fires** | D5 — requires wiring, not just a rule |
| Material change to an approved request | **Yes** | **mechanism already exists** | D7 — see Section 10 |

**EVIDENCE.** Of the four approvable entities, only `HIRING_REQUEST` and `OFFER`
actually fire. `REQUISITION` and `SALARY` are configurable, savable — and
silently never run (audit §7.1, §20, §24). **An administrator can configure a
salary approval rule today and it will do nothing.** Two of the three events the
owner requires approval for (Salary Approval, and Candidate Hiring if it is a new
point) therefore need wiring before any rule can take effect.

**EVIDENCE.** No approval rules are configured at all — count **0**. Every
request currently goes to a single person for a single yes/no, and the deadline,
reminder, escalation and delegation machinery is idle.

---

# Section 5 — Organisation Configuration Model

## The principle

> **EXAACT supports different business models through configuration of one
> engine. It never creates a second recruitment module.**

The organisation is configured **once**, by an administrator. Ordinary users then
receive a simple form containing only what applies to their business.

## The configuration dimensions

**No database design is proposed here.** These are business dimensions.

| Dimension | What it decides | Existing mechanism |
|---|---|---|
| **Business type** | The operating model — TPIA, agency, corporate HR, manufacturing, trading, IT, professional services, other | Business Profile / Capabilities (Revamp) |
| **Industry** | Industry defaults and vocabulary | 13 industry templates |
| **Recruitment model** | Internal hiring · client-facing placement · both | *(new dimension — see clarifications)* |
| **Organisation structure** | Offices, departments, designations, positions, org chart | `lib/position.php`, canonical Department vocabulary |
| **Approval model** | Which events need approval, who approves, sequence, thresholds, escalation, delegation | `lib/recruit_approval.php` |
| **Pipeline model** | The candidate journey stages and their kinds | `lib/recruitpipe.php` |
| **Terminology model** | What each object is called | `lib/terms.php` — 12 term packs |
| **Field configuration** | Which fields show, which are mandatory, order, sections, defaults | *(partially exists — see clarifications)* |
| **Dropdown / master configuration** | The permitted values in every list | lookup engine, 22 types |
| **Permission configuration** | Who may do what | `mod.hiring.*` + role model |

## What each dimension influences

| | Hiring Request | Requisition | Candidate | Pipeline | Approval | Offer | Hiring |
|---|---|---|---|---|---|---|---|
| Business type | ✔ fields, labels | ✔ | ✔ | ✔ stage set | ✔ chain | ✔ | ✔ |
| Industry | ✔ defaults | ✔ | ✔ | ✔ stage set | — | ✔ template | — |
| Recruitment model | ✔ client field relevance | ✔ | ✔ submission states | ✔ | ✔ | ✔ | ✔ |
| Org structure | ✔ dept, office, position | ✔ | — | — | ✔ chain by office | — | ✔ |
| Approval model | ✔ | ✔ | — | — | ✔ | ✔ | ✔ |
| Pipeline model | — | — | ✔ | ✔ | — | ✔ gate | ✔ gate |
| Terminology | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Field config | ✔ | ✔ | ✔ | — | — | ✔ | ✔ |
| Dropdowns | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Permissions | ✔ who may raise | ✔ | ✔ who may see | ✔ who may move | ✔ who approves | ✔ | ✔ |

## What can be configured today — EVIDENCE

Already configurable without code (audit §20): vocabulary (12 term packs, 13
industry templates); candidate pipeline stages; approval matrix (rules, levels,
SLA, escalation, delegation); compensation headings; document and letter
templates; positions and org chart; careers posting; role workspaces; 22 lookup
types.

The audit's conclusion, verbatim: *"Configuration mechanisms already exist and
are single. **No second configuration system is needed** for anything listed."*

## What cannot be configured today — EVIDENCE

| Gap | Consequence | Bears on |
|---|---|---|
| `WF_TEAM_ROLES` (FIELD / COORD / OFFICE) | Inspection-shaped team roles are fixed; reads oddly for IT and manufacturing | Section 13 |
| The **shape** of the legacy candidate funnel (labels are overridable; shape is not) | An industry can rename stages but not restructure the journey | **D1** |
| `APPR_ENTITIES` is a fixed constant | Which entities *can* require approval cannot be configured | **D5** |
| Whether a requisition edit needs re-approval | Not configurable | **D7** |
| The person-spec fields | **They do not exist, so there is nothing to configure** | **D2** |

**The last row is the most important line in this section.** Configuration cannot
expose a field that does not exist. D2 must add the three missing concepts before
field configuration can make them optional per industry.

## Labels — a caution

**Do not hard-code replacement terminology now.** "Hiring Request" may be
presented under different words per business type through the existing term-pack
engine. Nothing in this specification authorises renaming anything.

---

# Section 6 — Data / Input Inheritance Principle

## The principle

> ## Enter Once → Inherit Everywhere → Ask Only for New Information

This is a **product design principle**, not an implementation instruction.

## The inheritance chain

```
Organisation Configuration
        ↓   supplies defaults, permitted values, terminology, mandatory rules
Hiring Request
        ↓   the approved core requirement
Approved Hiring Request  ── snapshot ──▶ retained as what was authorised
        ↓   inherited wherever applicable (Rule D2-A)
Requisition
        ↓   adds execution detail only
Candidate
        ↓
Interview / Offer
        ↓
Hiring
        ↓
Workforce
```

## The three-question test

A user may be asked for a piece of information only if **all three** are true:

1. It is **genuinely new** — it did not exist earlier in the chain.
2. It is **not already available** — it cannot be derived, defaulted or inherited.
3. It is **required at this stage** — not merely useful later.

If any answer is no, the field is inherited, defaulted, or not shown.

## EVIDENCE — the pattern already exists and works

The hiring-request-to-requisition conversion already carries seventeen fields
forward, including the approved money figure, and its source comment states the
business reason directly:

> *"THE APPROVED FIGURE BECOMES THE BUDGET BASELINE. Without this the requisition
> starts with no budget, and the placement commercial approved later is measured
> against an estimate nobody approved. Carrying it across means the variance on
> every hire is against a number a named person actually said yes to."*

**This is the pattern to extend, not a pattern to invent.** D2's inheritance
requirement is served by widening an existing, working mechanism to carry three
more fields once they exist.

**EVIDENCE — a further precedent.** `sbu` is already carried forward across
`hiring_requests`, `requisitions`, `candidates` and `inspectors` at each
conversion rather than re-typed (audit D-E2).

**EVIDENCE — managed duplication that must be preserved.** Candidate identity
arrives from six paths (form, careers page, marketplace, agency, portal, import),
and `lib/candpool.php` exists specifically to converge them with confidence
thresholds. The audit classifies this as **managed** duplication, not accidental.
**The inheritance principle must not be applied so as to break that convergence.**

---

# Section 7 — Candidate Visibility & Access (D3 + D4)

**These are two halves of one access model and must be implemented together.**
D3 decides *when* a gate applies. D4 decides *who* passes it.

## D3 — the conceptual candidate states

| State | Business meaning | Who may see |
|---|---|---|
| **Available Candidate** | Not hired, not actively engaged in any recruitment process | Authorised company-wide recruitment users |
| **Active Candidate** | Attached to an active recruitment process | Controlled by recruitment relationship **+** user permission |
| **Hiring Transition** | Reached hiring / offer / acceptance stages | Controlled transition state |
| **Joined / Workforce** | Entered the workforce | No longer treated as a general recruitment-pool candidate |
| **Released / Available Again** | No longer actively engaged; where business rules allow, returns to the available pool | Back to company-wide |

**These are business states, not a technical state machine.**

**UNDECIDED — DO NOT IMPLEMENT:** the exact state machine; the precise triggers
between states; whether these states are derived from pipeline position or stored
separately; what "active recruitment process" means at its boundary (is a
candidate merely *attached* to a requisition active, or only one who has moved
past the first stage?); which business rules permit a return to the pool.

**The boundary question is the load-bearing one.** Everything about who can see
what depends on where "active" begins, and it is not yet defined.

## D4 — the access formula

> **Role + Permission + Candidate/Recruitment relationship = effective access**

## Actions to be distinguished

The specification distinguishes these as separate, separately-grantable actions.
**No values are assigned — the matrix below is a structure, not a decision.**

| Action | What it is |
|---|---|
| View | Open and read a candidate record |
| Create | Add a new candidate |
| Edit | Change candidate details |
| Move candidate stage | Advance or move a candidate through the pipeline |
| Shortlist | Mark a candidate as shortlisted against a requirement |
| Reject | Reject a candidate |
| Interview actions | Schedule, record outcomes, submit scorecards |
| Offer actions | Create, revise, send, withdraw an offer |
| Hire | Convert a candidate into workforce |
| Other sensitive actions | View or edit current salary and salary expectation; export; delete |

**"Other sensitive actions" deserves separate treatment.** Candidate records hold
personal data, current salary and expected salary. Who may read *money* on a
candidate is a narrower question than who may read the candidate.

## The role × action matrix

| Role | View | Create | Edit | Move stage | Shortlist | Reject | Interview | Offer | Hire | Salary fields |
|---|---|---|---|---|---|---|---|---|---|---|
| **Recruiter** | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ |
| **Hiring Manager** | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ |
| **Department Head** | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ | ⬚ |
| HR | — | — | — | — | — | — | — | — | — | — |
| Operations manager | — | — | — | — | — | — | — | — | — | — |
| Coordinator | — | — | — | — | — | — | — | — | — | — |
| Administrator | — | — | — | — | — | — | — | — | — | — |
| Finance | — | — | — | — | — | — | — | — | — | — |
| Inspector / field staff | — | — | — | — | — | — | — | — | — | — |
| External client | — | — | — | — | — | — | — | — | — | — |
| Agency | — | — | — | — | — | — | — | — | — | — |

⬚ = role is in scope (D4) but the per-action grant is **not yet decided**
— = role **entirely undecided**; nothing may be assumed

**UNDECIDED — DO NOT IMPLEMENT.** Thirty cells for the three named roles, and
every cell for the eight unnamed roles. The owner named the roles; the owner has
not yet said what each may do.

## Constraints on any future work

- **No new permission codes at this stage.**
- **Do not replace the existing permission architecture.**
- Access is never granted by role name alone — the formula requires permission
  and relationship as well.

## EVIDENCE — the position today

| Fact | Source |
|---|---|
| Recruitment declares exactly **three** permissions: `mod.hiring.view`, `mod.hiring.edit`, `hiring.admin` | `lib/access.php:156,198` |
| Candidate screens guard on `is_coordinator_level()` — **a role predicate, not a recruitment permission** | `lib/ops.php:6867` |
| The candidate register is **unscoped**: `$where = '1=1'`, plus stage and free-text filters only | `lib/ops.php:6503` |
| By contrast the requisition register **is** office-scoped via `scope_clause('r.office_id','r.sbu')` | `lib/ops.php:5840` |
| `candidates` has **no office column**, so an office filter is not currently possible without a schema change | audit S3 |
| `/candidate?id=` has **no record-level scope check** — any coordinator-level user in any office can open any candidate by id | `lib/ops.php:6867` |

**Two authorisation vocabularies are in play for one module** — module
permissions (`mod.hiring.*`) and role-level predicates (`is_coordinator_level()`).
Hiring-request screens use the former; requisition and candidate screens use the
latter.

**IMPLEMENTATION RECONCILIATION ITEM — `is_coordinator_level()`.** As the brief
requires, this is recorded rather than resolved. Its effect today is that
**someone who coordinates inspections, with no recruitment duty, can open and
edit candidate records including salary expectations.** The audit's open question
— whether this is intended or inherited from when candidates lived in Operations —
remains open. D4's formula cannot be satisfied while a general operations role
predicate is the gate, so **reconciling this is a prerequisite of D4, not a
tidy-up afterwards.**

**A consequence the owner should see.** D3 grants the available pool to
"authorised company-wide recruitment users". Today there is no such category —
the gate is an operations role. So D3 as decided is *narrower* than today's
behaviour, not merely equal to it: implementing D3 faithfully means deciding who
"authorised recruitment users" are, which is D4. This is why the two cannot be
split.

---

# Section 8 — Approval Rules (D5)

## What must be configurable

For each supported business event, configuration must be able to determine, where
applicable:

| Capability | Meaning |
|---|---|
| Whether approval is required | The event may need no approval at all |
| Who approves | Named person, role, or relationship (e.g. the reporting manager) |
| Approval sequence | Order of a multi-step chain |
| Conditions | When a rule applies (office, department, client, request type) |
| Thresholds | Value bands that select a different chain |
| Multiple approvers | More than one approver at a level |
| Escalation | What happens when a deadline passes |
| Delegation | Stand-in when an approver is unavailable |
| Rejection behaviour | What happens to the record on rejection |
| Re-submission behaviour | Whether and how a rejected item may be resubmitted |

## Capability versus activation — a binding distinction

> **That the system *can* approve something is a different question from whether
> approval *is enabled* for a particular organisation and process.**

Two separate configuration concepts:

1. **Capability** — this business event is approvable at all.
2. **Activation** — for *this* organisation, approval of this event is switched on,
   with these rules.

An agency switches internal hiring-request approval **off**. A corporate switches
it **on** with a three-step chain. Neither is a code difference.

## Business-model examples — illustrative only

| Business type | Illustrative model |
|---|---|
| Corporate organisation | Hiring Request → Department Head → HR Head → Management |
| Recruitment agency | No internal hiring-request approval; possibly client-side submission approval instead |
| Manufacturing | Hiring Request → Plant Head → HR, with a headcount threshold |
| Small single-office business | Single approver, no chain |

**These are examples of what the engine must accommodate. They are not EXAACT's
configured chains, and none may be seeded as a default.**

## EVIDENCE — the engine already exists and is strong

`lib/recruit_approval.php` already provides multi-level chains, named approvers or
roles (including "the reporting manager"), deadlines, reminders, escalation and
stand-ins for absence. **No new approval system is needed.** The audit is explicit:
*"The approval engine itself. No new approval system is built whatever you
choose."*

## EVIDENCE — three real problems the owner should know

| Problem | Detail |
|---|---|
| **Two of four entities never fire** | `HIRING_REQUEST` and `OFFER` work. `REQUISITION` and `SALARY` are configurable, savable, and **silently never run** (audit §7.1). An administrator can configure a salary approval rule today and nothing will happen. |
| **Zero rules configured** | Count **0**. Every request goes to one person for one yes/no. All the deadline, reminder, escalation and delegation machinery is idle. |
| **The entity list is a fixed constant** | `APPR_ENTITIES` is a PHP constant, so *which* entities can require approval is not configurable (audit §20). |

**The practical consequence for D5:** of the owner's three required events, one
(Hiring Request) works today; one (Salary Approval) needs **wiring before any rule
can take effect**; and one (Candidate Hiring) may need a new approval point
entirely. **Configuration alone will not deliver D5.** This must be stated plainly
in the implementation plan — it is the difference between "write some rules" and
"connect two workflows, then write some rules".

## What is NOT decided

**UNDECIDED — DO NOT IMPLEMENT:**

- EXAACT's own approval chains — who approves what, in what order, at what
  thresholds. The owner has decided chains must be *configurable*; the owner has
  not stated what they are.
- Whether "Candidate Hiring" is the existing Offer approval or a new approval
  point (see D5 in Section 2).
- Whether Requisition approval is off (inferred from omission) or simply unstated.
- Whether `APPR_ENTITIES` should become configurable, and if so by whom.

**No approval rule may be created in any environment until the owner states the
chains.**

---

# Section 9 — Offer Auditability (D6)

## The rule

> **Significant offer lifecycle events are permanently auditable. Historical
> offer records are never deleted or rewritten.**

## Business events that must be auditable

These are **business events**, listed as the owner gave them. They are
deliberately **not** asserted to be existing technical states.

| Business event | Meaning |
|---|---|
| Offer created | An offer is drafted for a candidate |
| Offer submitted | Put forward for approval |
| Offer approval | An approval decision is taken (by whom, when) |
| Offer approved | Authorised for release |
| Offer rejected | Approval refused |
| Offer revised | Terms changed — what changed, from what, to what |
| Offer sent | Released to the candidate |
| Offer accepted | Candidate accepted |
| Offer declined | Candidate declined |
| Offer withdrawn | Withdrawn by the company |

For each, an audit record should be capable of answering: **what happened, to
which offer, when, by whom, and — for a revision — from what value to what
value.**

## Business event versus technical implementation

**The owner's instruction is explicit: do not assume these exact states exist.**
This specification therefore separates the two:

- **The business requirement** is the list above.
- **The current technical implementation** is `job_offers`, and its state model
  has not been mapped against this list in this exercise.

**CLARIFICATION REQUIRED — the state mapping.** Which of the ten business events
correspond to existing offer states, which are new, and which are combinations,
must be established before implementation. It was not established here because
doing so reliably requires reading offer state transitions in detail, which this
exercise deliberately scoped out.

## EVIDENCE — the gap is real and larger than expected

| Fact | Source |
|---|---|
| The offer table is `job_offers` | `lib/recruit_offer.php` |
| `act_log()` calls in `lib/recruit_offer.php`: **0** | audit §8, verified |
| **Offer transitions appear in neither `act_log` nor the stage ledger** | audit §8 |
| Two audit mechanisms coexist: `act_log` (entity audit) and `candidate_events` / `rkpi_stage_log()` (stage ledger) | audit §8 |
| For comparison: `act_log()` appears **31** times in `lib/hiringreq.php` | audit §8 |

**This is the honest position: offers are currently recorded in neither audit
mechanism.** The hiring request is audited thoroughly, thirty-one times over; the
offer — which is where money, terms and a person's livelihood are decided — is
audited nowhere. The audit recorded this as an open question (was the omission
intentional, the stage ledger being the record?). **D6 answers it: it is a gap,
and it must be closed.**

**CLARIFICATION REQUIRED — which mechanism.** Whether offer auditing should use
`act_log` (the entity audit), the stage ledger, or both, is an implementation
decision. The specification requires only that it be **permanent, attributable and
never rewritten**. Two mechanisms already exist; **a third must not be created.**

## Constraints

- **Append-only.** An audit trail is added to, never edited.
- **No deletion or rewriting of historical offer records.**
- **Attributable.** Every event names the person and the time.
- **No third audit mechanism.**

---

# Section 10 — Change / Re-approval Rules (D7)

## The rule

> **Not every change to an approved requirement requires re-approval. Only
> selected material changes do. Non-material changes proceed without restarting
> approval.**

**Not authorised for implementation now.**

## EVIDENCE — this mechanism already exists, and it is well designed

This is the most significant finding of the specification exercise. A
material-change engine is already built for hiring requests, and its design
already matches the owner's intent.

| Component | What it does | Location |
|---|---|---|
| `HREQ_MATERIAL_FIELDS` | The authoritative list of fields whose change is material — **twelve fields**, each with a recorded business reason | `lib/hiringreq.php:81` |
| `hreq_material_diff()` | Returns the material differences; empty means nothing material moved | `lib/hiringreq.php:601` |
| `approved_snapshot_json` | The snapshot of what was actually approved | `lib/hiringreq.php:204` |
| `reapproval_state` | `NONE` / `REQUIRED` / `IN_PROGRESS` / `REJECTED` | `lib/hiringreq.php:203` |
| `HREQ_REAPPROVAL_BLOCKS` | The states in which recruitment must **not** run | `lib/hiringreq.php:71` |
| Documented matrix | The business document behind it | `docs/phase3/M4-MATERIAL-CHANGE-MATRIX.md` |

**Two design properties worth preserving, in the source's own words:**

> *"MATERIALITY IS JUDGED AGAINST THE APPROVED SNAPSHOT, never against the
> previous edit. Ten harmless edits followed by one material one must still"*
> [require re-approval]

> *"'quantity' is deliberately absent: it is asymmetric… an INCREASE spends
> authority nobody granted, a DECREASE stays inside the approval. Treating them
> alike would force a re-approval on a manager asking for fewer people, which
> teaches people to route around the control."*

**Both properties are correct and must not be lost.** D7 is therefore mostly a
question of *adjusting an existing list*, not building a mechanism.

## The owner's proposed material fields, against what exists

| Owner's field (D7) | Material today? | Note |
|---|---|---|
| Number of positions | **Yes**, asymmetrically | Increase is material; decrease is not. Handled in `hreq_material_diff()`, not in the field list. |
| Approved budget / salary range | **NO** | `est_cost_per_person` is **not** in the material list. **This is the gap D7 exists to close.** |
| Minimum qualification | **NO** | The field does not exist yet (D2). |
| Minimum experience | **NO** | The field does not exist yet (D2). |
| Essential skills | **NO** | The field does not exist yet (D2). |
| Employment / engagement type | **Yes** | `employment_type` |
| Location, where materially relevant | **Yes** | `office_id` (cost and approval chain) and `work_location` (where the person works) |

**Two conclusions follow:**

1. **Three of the seven cannot be made material until D2 creates them.** This is
   the hard dependency recorded in Section 3.
2. **The budget gap is live today.** A manager approves a cost; the figure can
   afterwards be changed without re-approval, and the approved figure is what
   becomes the requisition's budget baseline. **This is the single most
   consequential item in D7.**

## A question the owner must answer — does the list shrink?

The existing material list contains **twelve** fields. The owner named **seven**
concepts. Fields currently material that the owner did **not** name:

`hiring_department_id` (the department the person joins) · `designation` (the
role) · `grade` (the pay band) · `position_id` (the establishment seat) ·
`new_position_requested` (whether a new seat is being asked for) · `job_title`
(what was approved, in the approver's words) · `client_id` (the client contract
it is billed against) · `request_type` (the basis on which it was authorised) ·
`requested_by_id` (the identity segregation of duties was judged against)

**CLARIFICATION REQUIRED — and this one carries risk.** If the owner's list is
read as a *replacement*, nine fields lose materiality and **an existing control
is weakened** — someone could change the approved role, the pay band, or the
billing client after approval without going back. If it is read as *additions to*
the existing list, nothing is lost and the budget gap is closed.

**This specification does not choose.** It records that the safer reading is
"additions", flags the consequence of the other, and leaves the decision with the
owner as the brief requires.

## The material-field matrix

**CLARIFICATION REQUIRED — the exact material-field matrix is not finally
approved.** The owner's instruction is explicit: *"Do not treat this list as
finally approved."*

The future system should be **capable of defining which changes trigger
re-approval**. Today that capability is a PHP constant — one rule in one place,
which is good design, but not configurable per organisation. Whether it should
become configurable is itself undecided.

**UNDECIDED — DO NOT IMPLEMENT:**

- The final material-field matrix.
- Whether the owner's list adds to, or replaces, the existing twelve.
- Whether a threshold applies to budget changes (the Decision Pack offered "beyond
  10% or ₹1 lakh"; the owner's generalisation did not restate a threshold), and
  whether any budget increase is material or only one beyond a band.
- Whether budget *decreases* are material (by the quantity precedent, probably
  not — but that is an inference, not a decision).
- Whether materiality becomes configurable per organisation.
- Whether the same matrix applies to requisition changes as to hiring-request
  changes.

## Constraint

Whatever is decided, the two existing design properties hold: **materiality is
judged against the approved snapshot, never the previous edit**, and **asymmetric
fields stay asymmetric.**

---

# Section 11 — Recruitment UX (D8)

## The rule

> **EXAACT has one primary Recruitment home.** Existing recruitment destinations
> must not present themselves to users as competing "Recruitment homes".

## What the one home provides access to

- Recruitment execution (the work to be done)
- Pipeline / funnel visibility
- KPIs
- Relevant actions
- Relevant recruitment functions

## What is explicitly not authorised

**No screen or route may be deleted now.** Existing functionality is preserved.
How existing destinations are reused, redirected or integrated is determined
later, following the established sequence:

> **REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → BUILD**

Read as an ordered preference: reuse what exists before extending it; extend
before connecting; connect before mapping; and **build only when nothing else
serves**. Deprecation comes second-to-last, and only after a mapping exists.

## EVIDENCE — the position today

| Fact | Source |
|---|---|
| `/recruitment` and `/recruitment-cc` are **both landings** | audit §16 |
| The audit's verdict: **"Possible duplicate.** Home is a launchpad; CC is the funnel/KPI board. OPEN QUESTION whether two landings are warranted." | audit §16 |
| Components: `lib/recruit.php` (Command Centre / launchpad) and `lib/recruit_cc.php` (Phase 7 Command Centre) | audit L01, L09 |

**EVIDENCE — what is *not* a duplicate.** The audit checked and found these to be
intentionally distinct, and D8 must not be used to collapse them:

- `/candidate-new` vs `/careers` — authenticated intake vs public application;
  different permissions, different data completeness.
- `/candidate-pool` vs `/candidates` — read-only convergence view vs the working
  register.
- `/requisition-allocations` vs `/requisition` detail — the *same* component
  reused, not duplication.

**No duplicate candidate-edit, interview, offer or joining screens exist.** The
consolidation D8 calls for is therefore narrow: **it concerns the two landings,
not the recruitment screens generally.** That is a much smaller and safer piece of
work than "consolidate recruitment UX" might suggest, and it should be scoped
accordingly.

## Dependency

D8's home shows the funnel and KPIs, both of which read lifecycle state.
**D8 should follow D1**, or the one home will be built over the legacy lifecycle
and rebuilt afterwards.

## UNDECIDED — DO NOT IMPLEMENT

Which of the two landings becomes the one home; whether the other is redirected,
merged, or retained for a different audience; what the navigation entry is called;
whether role workspaces (which already exist) are the mechanism by which different
roles land in different places within the one home.

---

# Section 12 — Mobile Verification (D9)

## D9 is a verification requirement, not a business decision

> **Recruitment must not be assumed mobile-ready.**

## EVIDENCE — why no claim can be made

| Fact | Source |
|---|---|
| Test files referencing mobile widths: **2** | audit §27 |
| Browser-driven tests: **0** — no test file references chromium or playwright | audit §27 |
| The audit did not drive a browser, so **no claim is made** about rendering at 360 / 390 / 412 px | audit §27 |

The audit deliberately did not guess. **Neither does this specification.**

## The journey that must be verified

A later browser and mobile verification pass must test, at minimum, the full
chain:

```
Hiring Request → Approval → Requisition → Candidate → Pipeline
    → Interview → Offer → Hiring → Workforce
```

Relevant **recruiter** and **manager** journeys must both be tested — they are
different people doing different work on different devices.

## Verification constraints

- Any defects discovered are **documented separately**, not fixed inside a
  specification or verification exercise.
- **No mobile changes are authorised during this specification exercise.**
- The existing project standard applies: inspectors are phone-first in the field;
  coordinators, managers and finance are desk-first on a laptop. Recruitment
  spans both — a recruiter may work at a desk while a department head approves
  from a phone. **Design for both; never average them into one middle.**

---

# Section 13 — Industry Portability

## The principle

> **The same recruitment engine supports every business type. Industry
> differences are configuration and defaults, never a separate engine.**

## How one engine serves each business type

| Business type | What configuration supplies | What differs |
|---|---|---|
| **TPIA** | Today's behaviour | Inspection disciplines, client-facing deployment, certificate requirements |
| **Recruitment agency** | Client-submission stages in the pipeline; no internal hiring-request approval; person-spec is critical | Works *for* clients rather than for itself; the candidate is the product |
| **Corporate HR** | Internal department chains; establishment/position control; multi-step approval | Hires for itself; budget and headcount discipline dominate |
| **Manufacturing** | Plant/shift stage set; trade and skill vocabularies | Volume hiring of trades; location-critical |
| **Trading** | Simple pipeline; commercial roles | Small volumes, fast cycles |
| **IT services** | Notice-period handling; skill-matrix screening; bench/allocation thinking | Skills change fastest; notice periods are a first-class concern |
| **Professional services** | Qualification-led screening | Credential-driven eligibility |
| **Healthcare** | Licence / registration validity | Registration currency is a legal gate |
| **Education** | Qualification as a first-class concept | Qualification *is* the requirement |
| **Logistics, real estate, construction, general B2B** | Standard configuration | Nothing structural |

## EVIDENCE — the audit's portability assessment

The audit assessed thirteen industries against the current code. Its central
finding, verbatim:

> *"The same two gaps recur in 11 of 13 rows: **the person-spec**, and
> `WF_TEAM_ROLES`. No industry is blocked by the TPIA vocabulary that prompted
> this audit."*

**This is the key result for industry portability, and it is good news.** The
thing everyone feared — that EXAACT is too inspection-shaped to serve other
industries — is **not** the blocker. The vocabulary is already overridable. The
blockers are two specific, nameable gaps:

| Gap | Industries affected | Decision that closes it |
|---|---|---|
| **The person specification** (qualification, experience, essential skills) | 11 of 13 | **D2** |
| **`WF_TEAM_ROLES`** — fixed as FIELD / COORD / OFFICE | Notably IT services and manufacturing, where it "reads oddly" | **None — undecided** |

Two industries additionally need concepts that do not exist at all: **healthcare**
needs licence/registration validity, and **IT services** needs notice-period
handling. **Recruitment agency** needs client-submission states, where the audit
marks person-spec as *critical* rather than merely needed.

**UNDECIDED — DO NOT IMPLEMENT:** `WF_TEAM_ROLES` configurability; healthcare
licence validity; IT notice period; agency client-submission states. None of these
was put to the owner as a decision.

## Which industries come first

**UNDECIDED.** The Decision Pack asked which industries should be served first.
**The owner has not answered.** No industry may be prioritised, seeded, or
defaulted in code until they do.

## The architectural guarantee

Adding an industry must require **configuration only**: a pipeline stage set,
vocabulary, dropdown values, approval rules, and field visibility. It must never
require a new module, a new engine, a branch on business type in code, or a copy
of an existing screen.

---

# Section 14 — Existing Architecture Reuse

Every requirement mapped to the component that already serves it. **Nothing in
this table is invented** — each is from the audit's component inventory or was
verified in source during this exercise.

| Requirement | Existing component | Reuse verdict |
|---|---|---|
| Authoritative candidate lifecycle (D1) | `lib/recruitpipe.php` (L04) — configurable pipeline / stage engine | **REUSE.** Built, tested, admin-configurable. It needs adoption, not construction. |
| Stage history / ledger | `candidate_events` via `rkpi_stage_log()` | **REUSE.** Append-only ledger already in place. |
| Hiring Request (D2 core requirement) | `lib/hiringreq.php` (L02) | **EXTEND.** Three fields to add; 32 already present. |
| Requisition (D2 execution detail) | `lib/recruit.php` (L01), `lib/reqfulfil.php` (L03) | **REUSE.** Person-spec fields already live here. |
| Inheritance down the chain (Section 6) | Hiring-request → requisition conversion, `lib/hiringreq.php:1242-1265` | **EXTEND.** Already carries 17 fields including the budget baseline. |
| Candidate identity convergence | `lib/candpool.php` (L16), `CANDPOOL_CONFIDENCE` | **REUSE — and protect.** Managed duplication by design. |
| Candidate access (D3, D4) | `lib/access.php` (`mod.hiring.view`, `mod.hiring.edit`, `hiring.admin`); office scope engine (`scope_clause`, `scope_office_clause`) | **REUSE + RECONCILE.** The scope engine already works for requisitions; candidates lack an office dimension. `is_coordinator_level()` must be reconciled. |
| Approval, all events (D5) | `lib/recruit_approval.php` (L05) — chains, roles, SLA, reminders, escalation, delegation | **REUSE.** No new approval system. Two entities need **wiring**. |
| Re-approval on material change (D7) | `HREQ_MATERIAL_FIELDS`, `hreq_material_diff()`, `approved_snapshot_json`, `reapproval_state` in `lib/hiringreq.php` | **REUSE + ADJUST.** The mechanism exists and is well designed. The field list is the open question. |
| Offer lifecycle (D6) | `lib/recruit_offer.php` (L07), `job_offers` | **EXTEND.** Auditing is absent (`act_log` count 0). |
| Audit trail infrastructure (D6) | `act_log()` (entity audit) **and** `candidate_events` / `rkpi_stage_log()` (stage ledger) | **REUSE one of the two.** Both exist. A third must not be created. |
| KPI / SLA / performance | `lib/recruit_kpi.php` (L08) | **REUSE.** One KPI engine. Consumes D1. |
| Recruitment home (D8) | `lib/recruit_cc.php` (L09) and `lib/recruit.php` (L01) | **CONNECT / MAP.** Two landings; neither deleted. |
| Terminology per business type | `lib/terms.php` — 12 term packs, 13 industry templates | **REUSE.** No renaming authorised. |
| Dropdown / master values | Lookup engine, `lk_options_or()`, 22 lookup types | **REUSE.** The configurable-dropdown mechanism. |
| Organisation structure | `lib/position.php` (L17) — position master, org chart, manpower validation; canonical Department vocabulary | **REUSE.** |
| Compensation configuration | `lib/comp_config.php` (L18) | **REUSE.** |
| Offer / letter documents | `lib/doc_templates.php` (L19) — Document Studio | **REUSE.** |
| Role landing per role | Configurable Role Workspaces | **REUSE.** May serve D8. |
| Interviews & assessment | `lib/recruit_iv.php` (L06) — multi-round, scorecards, document DMS | **REUSE.** |
| Multi-source fulfilment | `lib/recruit_fulfil.php` (L10), `requisition_allocations` | **REUSE.** |
| Recruiter accountability | `lib/recruit_assign.php` (L11) | **REUSE.** |
| Public application intake | `lib/careers.php` (L15) | **REUSE.** |
| Exports | `lib/recruit_export.php` (L13) | **REUSE.** Consumes D1. |
| Job description generation | `lib/recruit_jd.php` (L14) | **REUSE.** |
| Tenant isolation | One database per tenant; no `tenant_id` column anywhere | **REUSE — structural.** Cross-tenant leakage is not possible through query omission. |

## The highest-coupling component

`lib/ops.php` (L20) is the dispatcher and also holds the candidate and requisition
handlers **and** `CAND_STAGES` — the audit marks it *"Shared — highest coupling."*
**D1 and D3/D4 both land here.** Any plan touching it must account for that
coupling explicitly; it is the single riskiest file in the module.

## Summary of verdicts

| Verdict | Count | Meaning |
|---|---|---|
| REUSE, unchanged | 20 | Exists and serves the requirement as-is |
| REUSE + adjust or reconcile | 2 | The mechanism exists; a list or a predicate needs settling — the material-change field list (D7), and `is_coordinator_level()` vs `mod.hiring.*` (D3/D4) |
| EXTEND | 3 | Exists; needs widening — hiring-request fields (D2), inheritance (Section 6), offer audit (D6) |
| CONNECT / MAP | 1 | Two landings to be related (D8) |
| **BUILD NEW** | **0** | **Nothing in this specification requires a new engine.** |
| **Total** | **26** | |

**That last row is the specification's central architectural claim.** Nine
business decisions, and not one of them requires a new engine.

---

# Section 15 — Open Implementation Clarifications

Everything still requiring a business or technical decision. **Nothing in this
list may be decided in code.**

## A. Blocking — an implementation plan cannot be written without these

| # | Item | Decision | Why it blocks |
|---|---|---|---|
| **C1** | What does **"Candidate Hiring"** mean as an approval event — the existing Offer approval, a new approval at joining, or both? | D5 | If new, it is new wiring, not configuration. Changes the size of the work. |
| **C2** | Does the owner's material-field list **add to** or **replace** the existing twelve? | D7 | "Replace" silently weakens nine existing controls. |
| **C3** | The **role × action matrix** — 30 undecided cells for three named roles, plus 8 entirely undecided roles | D4 | Access cannot be built from role names alone. |
| **C4** | **EXAACT's own approval chains** — who approves what, in what order, at what thresholds | D5 | "Configurable" is the mechanism; the rules themselves are unstated. |
| **C5** | Where does **"active recruitment process"** begin? | D3 | The entire visibility model hinges on this boundary. |
| **C6** | Is **Requisition approval** off (inferred from omission) or simply unstated? | D5 | An unnoticed "yes" would be a trap: a rule can be saved today that never runs. |

## B. Required before the relevant decision is built

| # | Item | Decision |
|---|---|---|
| C7 | Enforcement posture when a requisition would weaken the approved requirement: block, warn, or route to re-approval | D2 |
| C8 | Whether a budget **threshold** applies, and whether any increase is material or only one beyond a band | D7 |
| C9 | Whether budget **decreases** are material (the quantity precedent suggests not — an inference, not a decision) | D7 |
| C10 | Whether the material matrix becomes **configurable per organisation**, or stays one rule in one place | D7 |
| C11 | Whether the same material matrix applies to **requisition** changes as to hiring-request changes | D7 |
| C12 | Mapping the ten offer **business events** onto existing offer states | D6 |
| C13 | Which audit mechanism carries offer events — `act_log`, the stage ledger, or both | D6 |
| C14 | Whether downstream consumers read **current stage** or **stage history** | D1 |
| C15 | The default **pipeline** the 933 legacy candidates map onto | D1 |
| C16 | The legacy-stage → pipeline-stage **mapping table** | D1 |
| C17 | Whether migration is **one-off** or **lazy on next touch** | D1 |
| C18 | Whether **terminal** legacy candidates are migrated at all | D1 |
| C19 | Reconciling **`is_coordinator_level()`** on candidate screens against `mod.hiring.*` | D4 |
| C20 | Whether candidates acquire an **office dimension** (a schema change with migration consequences) | D3 |
| C21 | Which of the two **landings** becomes the one home, and what happens to the other | D8 |
| C22 | Whether **role workspaces** are the mechanism for per-role landing within the one home | D8 |
| C23 | Confirmation of the **D8 reading** adopted in Section 11 | D8 |
| C24 | Whether "Hiring Managers" / "Department Heads" are the same roles as the Pack's "Recruitment manager" / "Department manager" | D4 |
| C25 | The exact **state machine** and transition triggers for the five candidate states | D3 |
| C26 | Which business rules permit a candidate to **return to the available pool** | D3 |

## C. Not put to the owner as decisions at all

| # | Item | Note |
|---|---|---|
| C27 | **Which industries come first** | The Decision Pack asked. Unanswered. Nothing may be prioritised in code. |
| C28 | `WF_TEAM_ROLES` configurability (FIELD / COORD / OFFICE) | The **second** of the two gaps recurring across industries. Never put to the owner. |
| C29 | Healthcare **licence / registration validity** | A legal gate that does not exist as a concept. |
| C30 | IT services **notice period** handling | Does not exist. |
| C31 | Recruitment agency **client-submission states** | Does not exist as a pipeline concept. |
| C32 | Whether `APPR_ENTITIES` should become configurable | Currently a PHP constant. |
| C33 | **Recruitment model** as a configuration dimension (internal / client-facing / both) | Proposed in Section 5; no existing mechanism identified. |
| C34 | Rate-limiting and abuse controls on the public `/careers` intake, which accepts unauthenticated input that creates candidate rows | Audit S5 — explicitly not examined in that pass. |
| C35 | Whether the absence of offer auditing was originally intentional | D6 answers the requirement; the history is unestablished. |

## D. Recorded disagreements between sources

| # | Disagreement | Resolution |
|---|---|---|
| G1 | Decision numbering: owner's D1–D9 vs Decision Pack 1–9 differ in five places | Owner's numbering is authoritative from this document onward. Mapping table at the top. |
| G2 | D7 broadens Pack Decision 6 from *budget* to a *multi-field material matrix* | Business decision wins; broadening recorded. |
| G3 | D3 is Model A **plus a condition** — neither pure Model A nor Model C | Business decision wins; refinement recorded. |
| G4 | D8's letter (Pack Option B) and the owner's prose admit two readings | Section 11 states the reading adopted; C23 asks for confirmation. |
| G5 | Audit row counts: `TABLE_ROWS` estimates gave pipelines 3 / stages 32; exact counts are **4** and **33** | Corrected. The load-bearing figures (935, 2, 0 rules) came from exact counts and stand. |
| G6 | Audit §8 open question — was the missing offer audit intentional? | **D6 supersedes it.** It is a gap and must be closed. |

---

# Section 16 — Explicit Non-Goals

## What this document does NOT authorise

**This document authorises no change to any running system.** Specifically, it does
not authorise:

**Implementation of any decision**
- Implementing D1 · migrating candidates · activating pipeline rules
- Configuring approval rules · changing forms, labels or dropdowns
- Changing permissions · consolidating screens
- Modifying candidate visibility · modifying offer audit
- Modifying budget controls · running migrations · deploying · releasing

**Any change to code or data**
- PHP · JavaScript · CSS · HTML · templates
- Database · schema · migrations · seed data
- Routes · permissions · workflows · pipeline stages · terminology
- Configuration · existing records

**Architectural changes explicitly prohibited**
- **No separate recruitment engines** — not for TPIA, agency, corporate, IT, or
  any other business type. One engine, configured.
- **No Person Hub / Person master.** Existing identity architecture stands.
- **No merging of Recruitment Requisition with Marketplace Requirement.** They
  stay separate.
- **No duplicate candidate, person or identity engines.**
- **No second approval engine, pipeline engine, KPI engine or configuration
  system.**
- **No rebuilt dashboards.**
- **No redesign of protected modules.**

**Modules explicitly protected — untouched by this specification**

Operations · Quality · Reporting · Money / Billing · Workforce · Marketplace ·
the existing approval engine · the existing KPI/SLA engine · the existing
configurable pipeline engine · the existing identity architecture · the existing
organisation architecture

**Other non-goals**
- Fixing anything discovered during this exercise. Findings are recorded, not
  fixed.
- Refactoring, tidying, or renaming.
- Deciding anything in Section 15.
- Choosing which industries come first.
- Claiming that Recruitment is mobile-ready.
- Claiming that Universal Recruitment is ready.

## What must happen next

A **separate implementation plan**, written only after this specification is
reviewed and approved, and after the blocking clarifications in Section 15(A) are
answered.

---

# Section 17 — Traceability Table

The **Existing Mechanism** column is populated only from evidence in the
repository or the audit. Where no mechanism exists, the cell says so.

| Decision | Business Requirement | Existing Mechanism | Future Change Needed | Status |
|---|---|---|---|---|
| **D1** | The configurable pipeline is the authoritative candidate lifecycle | `lib/recruitpipe.php` (L04) — built, tested, admin-configurable; stage ledger `candidate_events` via `rkpi_stage_log()`; 4 pipelines / 33 stages configured; **2 of 935 candidates adopted** | Reconciliation and migration later; downstream consumers moved off legacy `stage`; one-way partial sync retired | **DECIDED** |
| **D2** | Core requirement approved at Hiring Request; execution detail at Requisition; inherit, never weaken | `lib/hiringreq.php` holds 32 of the core fields incl. 4 budget estimate fields; `requisitions` holds `qualification`, `skills`, `experience_min`, `relevant_experience`, `discipline`, `category`, `trade_id`, `skill_id` (`lib/recruit.php:57-58`); inheritance precedent carries 17 fields (`lib/hiringreq.php:1242-1265`) | Add **three** concepts to the hiring request (qualification, experience, essential skills); widen inheritance; define weaken-enforcement posture | **DECIDED** |
| **D3** | Candidate visibility by state: available pool company-wide; active candidates gated | Candidate register unscoped `$where='1=1'` (`lib/ops.php:6503`); **no office column on `candidates`**; office scope engine exists and works for requisitions (`scope_clause`, `lib/ops.php:5840`) | Define the state machine and the "active" boundary; decide whether candidates gain an office dimension | **DECIDED / DETAIL PENDING** |
| **D4** | Role + Permission + Relationship = effective access | `mod.hiring.view`, `mod.hiring.edit`, `hiring.admin` (`lib/access.php:156,198`); candidate screens gated by `is_coordinator_level()` (`lib/ops.php:6867`) | Reconcile the coordinator predicate against `mod.hiring.*`; complete the role × action matrix. **No new permission codes at this stage** | **DECIDED / DETAIL PENDING** |
| **D5** | Configurable approval for Hiring Request, Candidate Hiring, Salary — per organisation | `lib/recruit_approval.php` (L05) — chains, named approvers and roles, thresholds, SLA, reminders, escalation, delegation. `HIRING_REQUEST` and `OFFER` fire; **`REQUISITION` and `SALARY` never fire**; `APPR_ENTITIES` is a constant; **0 rules configured** | Clarify "Candidate Hiring"; **wire Salary approval**; then configure rules. Per-organisation rule scoping | **DECIDED / DETAIL PENDING** |
| **D6** | Significant offer lifecycle events permanently auditable; never rewritten | Two audit mechanisms exist: `act_log()` (entity) and `candidate_events`/`rkpi_stage_log()` (stage ledger). **`act_log` count in `lib/recruit_offer.php` = 0** — offers appear in neither | Map the 10 business events to offer states; carry them on one existing mechanism. **No third mechanism** | **DECIDED / DETAIL PENDING** |
| **D7** | Only material changes to an approved requirement trigger re-approval | **Already built:** `HREQ_MATERIAL_FIELDS` (12 fields), `hreq_material_diff()` judged against `approved_snapshot_json`, `reapproval_state`, `HREQ_REAPPROVAL_BLOCKS`; documented at `docs/phase3/M4-MATERIAL-CHANGE-MATRIX.md`. **`est_cost_per_person` is NOT material** | Decide add-vs-replace; add budget to the material set; add the three D2 fields once they exist; decide threshold | **DECIDED / DETAIL PENDING** |
| **D8** | One primary Recruitment home; no competing recruitment landings | `lib/recruit_cc.php` (L09) and `lib/recruit.php` (L01) are **both landings** — audit §16 verdict "possible duplicate"; Configurable Role Workspaces exist | UX consolidation later via REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → BUILD. **Nothing deleted** | **DECIDED / DETAIL PENDING** |
| **D9** | Recruitment must be verified on mobile, not assumed | Responsive UI and the UI/UX blueprint exist; **2 test files reference mobile widths; 0 browser-driven tests** | A browser verification pass over the full journey; defects documented separately | **VERIFICATION** |

## Status legend

| Status | Meaning |
|---|---|
| **DECIDED** | The business decision is complete enough to write an implementation plan against, subject to Section 15(A). |
| **DECIDED / DETAIL PENDING** | The direction is decided; specific rules, values or matrices are still required. |
| **VERIFICATION** | Not a decision. Work to be verified before any claim is made. |

**Seven of the nine carry pending detail.** That is not a failure of the
decision exercise — it reflects that the owner decided *direction* deliberately
and left *rules* to be stated once the consequences were visible. The six blocking
items in Section 15(A) are the ones that must close before implementation planning
begins.

---

# Section 18 — Validation Record

Performed before this document was committed.

| Check | Result |
|---|---|
| Production PHP changed | **None** |
| JavaScript changed | **None** |
| CSS changed | **None** |
| HTML / templates changed | **None** |
| Database schema changed | **None** |
| Database records changed | **None** — inspection was read-only |
| Routes changed | **None** |
| Permissions changed | **None** |
| Workflows changed | **None** |
| Terminology changed | **None** |
| Migrations created | **None** |
| Production configuration modified | **None** |
| Existing data rewritten | **None** |
| Files created | **1** — this document |
| Temporary files or scripts created | **None retained** |

**Method.** All facts were established by reading source files and the two source
documents. No application code was executed against production data. No test
suite was run, because no code changed. The repository state was verified with
`git status` before and after.

---

# STOP

**This specification is complete. No implementation is authorised.**

The next step is **not** code. It is:

1. The owner reviews this specification.
2. The owner answers the **six blocking clarifications** in Section 15(A).
3. A separate **implementation plan** is written and approved.
4. Only then does implementation begin — and D1 and D2 come first, because
   everything else is built on them.

**Nothing in this document states or implies that Universal Recruitment is
ready.** It is not. It is specified.

---

## Change log

| Date | Change |
|---|---|
| 2026-09-27 | Created. Converts owner decisions D1–D9 into product rules and configuration specification. Sources: the audit and the Business Decision Pack (`a010113`). |
