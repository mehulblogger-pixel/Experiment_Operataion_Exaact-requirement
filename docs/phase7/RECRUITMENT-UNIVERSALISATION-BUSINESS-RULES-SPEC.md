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

The traceability table in Section 17 uses three further compound statuses —
*DECIDED / DETAIL PENDING*, *DECIDED / BLOCKED* and *DECIDED / VERIFICATION* —
defined in that section's own status legend.

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
## This is the third issue of this specification

- **Issue 1** converted decisions **D1–D9** as they stood.
- **Issue 2** incorporated the twenty-five supporting decisions **Q1–Q25** and the
  revisions to D1–D9.
- **Issue 3** applies the two rules the owner locked after the Task 3
  consistency review — **F1**, the material-change approval rule, and **F2**, what
  happens when a requirement is relaxed — together with nine editorial corrections
  that review found.
- **Issue 4** (this one) records eight further locked rules, **A1–A8**, in
  **Section 20** as a preservation record. They are **not yet integrated** into
  Sections 2, 10 and 19; that happens in one pass once the remaining configuration
  values are settled. **Where Section 20 and an earlier section disagree, Section 20
  governs.**

**The two rules locked in consistency review:**

| Ref | Rule | Where |
|---|---|---|
| **F1** | Material-change approval inherits the underlying requirement's rule by default; a separate material-change chain takes precedence; the chain is matched on the **proposed changed values**; if neither requires approval, none is required | Sections 2 (D7), 4, 10 |
| **F2** | A relaxed requirement does **not** automatically reconsider a previously rejected candidate. The rejection stands; the candidate remains findable for deliberate human reconsideration | Section 19 |

**Five statements in the first issue are now withdrawn or reversed.** Each is
marked in place and listed in Section 15(E):

| Withdrawn statement | Superseded by |
|---|---|
| "Requisition approval is NOT required" | **D5, Q24** — it is configurable |
| "D9 is not a business decision" | **D9, Q25** — it is both |
| "Whether materiality becomes configurable" was undecided | **Q4** — it is configurable |
| Add-versus-replace on material fields was blocking | **D7** — additions, not replacements |
| "Where does an active recruitment process begin?" was blocking | **D3, Q2** — configurable per organisation and pipeline |

**Section numbers are never reused or renumbered in this document.** New material
takes the next unused number and is placed where it reads best, so every
cross-reference already written stays valid. That is why the candidate version
behaviour added by this issue is **Section 19**, placed immediately after
Section 10.

## The supporting decisions Q1–Q25 — where each one lives

| Q | Subject | Refines | Specified in |
|---|---|---|---|
| Q1 | Candidate Hiring approval configurable | D5 | Section 8 |
| Q2 | "Active recruitment process" configurable; no separate engine | D3 | Section 7 |
| Q3 | Candidate access philosophy | D4 | Section 7 |
| Q4 | Material-field list configurable | D7 | Section 10 |
| Q5 | Pending version; approved version stays effective | D7 | Section 10 |
| Q6 | Effect of a pending change configurable | D7 | Section 10 |
| Q7 | Rejected changes retained in history | D7 | Section 10 |
| Q8 | Who may propose a change | D7, D4 | Sections 7, 10 |
| Q9 | Original chain or separate material-change chain | D7, D5 | Section 10 |
| Q10 | Reason mandatory | D7 | Section 10 |
| Q11 | Supporting documents optional | D7 | Section 10 |
| Q12 | Approved change creates a new version | D7 | Sections 6, 10 |
| Q13 | Candidates move to the latest version | D7-CAND | Sections 6, 19 |
| Q14 | Flag for review, never auto-reject | D7-CAND | Section 19 |
| Q15 | Previous assessments remain valid | D7-CAND | Section 19 |
| Q16 | Review must clear before progressing | D7-CAND | Section 19 |
| Q17 | Review permission | D7-CAND, D4 | Sections 7, 19 |
| Q18 | Clearing requires a reason | D7-CAND | Section 19 |
| Q19 | Continue or Reject, both reasoned | D7-CAND | Section 19 |
| Q20 | Reject is immediate | D7-CAND | Section 19 |
| Q21 | "Hired" configurable — Accepted or Joined | D1, D3 | Section 7 |
| Q22 | No rule means no approval | D5 | Section 8 |
| Q23 | Role profile + administrator override | D4 | Section 7 |
| Q24 | Requisition approval configurable | D5 | Section 8 |
| Q25 | Mobile operational, desktop administrative | D9 | Section 12 |

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

**7. Changing an approved requirement is a proposal, not an edit.** Today a change
stops recruitment until someone re-approves it. In future the change sits alongside
what was approved: work continues on the approved terms, a reason must be given,
and if the change is accepted it becomes a new approved version while the old one is
kept as history. Refused changes are kept too. **Nothing is overwritten.**

**8. When a requirement changes, nobody is dismissed by a machine.** Candidates
already in the pipeline move to the new version. If one may no longer be eligible,
the system flags them for a human to look at — it does not reject them. Their
interviews and scores stay valid, because the work was done honestly against what
was asked at the time.

**9. Doing recruitment belongs on a phone; setting it up belongs on a laptop.**
Reviewing a candidate, approving a request, shortlisting, moving someone through the
pipeline — all operational, all phone-first. Designing pipelines, approval rules and
permissions stays desk work.

## Two risks the first issue flagged are now closed

**The material-field risk is closed.** The first issue warned that a shorter list of
material fields could be read as a replacement, silently weakening nine existing
controls. The decision is explicit: **additions, not replacements.** Nothing is lost.

**The approved-budget gap is being closed.** Budget becomes a material-change
control, so an approved cost can no longer be changed after the fact without going
back.

**A third risk was found in review and is now closed.** Because no approval rule is
configured anywhere today, the rule "no rule means no approval" would have quietly
disabled the material-change control as well. The owner has closed this: a material
change now **inherits the approval rule of the requirement it changes**, so the
control travels with the requirement and cannot be lost by omission. The chain is
matched on the **changed** values, so a threshold cannot be evaded by approving
something small and then enlarging it.

**One risk remains open, deliberately.** Where no approval rule is configured at
all, no approval is required. EXAACT today has no rules configured, so a literal
reading would make every hiring request self-approving — and, through the
inheritance rule above, material changes to them too. That consequence is recorded
in Section 8 as **B2**, a decision the owner still needs to take — not something
chosen quietly.

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

### STATUS — decided, with an implementation dependency (C43)

> **D1 cannot be fully implemented until the configurable pipeline can represent
> both a successful terminal outcome and a closed / not-proceeding outcome.**

The stage-kind vocabulary today is `step · gate · interview · offer · terminal`,
where **`terminal` means the successful end** — its shipped examples are
`ONBOARDING` and `JOINING`. There is **no rejection or closed kind at all**, so
every loss is expressible only in the legacy stage field
(`recruitpipe_legacy_terminal()` = `ACCEPTED · REJECTED · WITHDRAWN ·
OFFER_DECLINED`).

**Consequences, locked with C36/C43:**

- The **`closed` stage kind is a foundational pipeline requirement**, not an
  optional enhancement. See Section 19.
- **The deferred legacy migration inherits this dependency.** The 933 legacy
  candidates cannot be fully represented on the authoritative pipeline while
  rejection, withdrawal and other loss outcomes have nowhere to go, so
  **C15–C18 depend on the closed kind existing.**

D1's direction is decided and unchanged. Only its implementability is
conditional.

### What triggers a change of lifecycle state (A1 · A2 · A6)

| Trigger | Effect | Rule |
|---|---|---|
| A **new effective version** of a Hiring Request or Requisition | Active candidates on it enter Review Required | **A6, A1** |
| The candidate has an **issued, accepted or joined** offer | The new version does **not** reach them | **A2** |
| A reviewer chooses **Reject** | The candidate moves to the pipeline's configured **closed** stage | **C43** |

**"Effective version" is not "approved".** A version becomes effective whether or
not an approval chain was required for it (A6), so the trigger survives an
organisation that configures no approval, a directly raised requisition, and a
change that becomes effective without a workflow.

### What "authoritative" means — the definition this specification fixes

A lifecycle is *authoritative* when all five of these are true:

1. **Single source of truth.** When a candidate's **position in recruitment** is
   asked for, the answer is computed from the configurable pipeline
   (`pipeline_id` + `pipeline_stage_id`) and from nothing else.
   **Reconciliation with Q21 (F3).** Q21 makes "Hired" configurable as *Offer
   Accepted* or *Actual Joined* — business events recorded on the offer and
   workforce records, not on the pipeline. The two rules are reconciled thus: the
   pipeline remains the single source of a candidate's **lifecycle position**,
   while the offer and workforce records remain the source of **those business
   events**; the pipeline's **terminal stage kinds are mapped to the configured
   Hired point**, so the funnel closes at whichever event the organisation has
   configured. Nothing outside the pipeline is consulted to answer *where is this
   candidate*, and the pipeline is not asked to answer *when did they accept or
   join*. **CLARIFICATION (C23a): the owner has confirmed this reading; the mapping
   itself is not designed here.**
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

**Lifecycle position is not the same as a business event.** The pipeline is the
single source of a candidate's **position**; the offer and workforce records remain
the source of the **events** of acceptance and joining (F3, Q21). The pipeline's
terminal stage kinds map to the configured "Hired" point; nothing outside the
pipeline is consulted to answer *where is this candidate*, and the pipeline is not
asked to answer *when did they accept or join*.

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

**DECIDED (A8) — the enforcement posture.** A requisition value **below the
approved minimum** on its Hiring Request is a weakening and is **routed through the
material-change process** as a proposed Requisition version, with a mandatory reason
and the approval route F1 selects. It is neither silently permitted nor blocked
outright.

Weakening is judged **against the approved minimum, never against the
Requisition's previous value**, so reducing an over-specified requisition back
toward the approved floor is **not** a weakening and proceeds freely. The exception
is recorded **on the Requisition**, so it does not relax the standard for other
requisitions raised from the same request. Where no approval is required (F1, Q22)
the weakening still needs a reason and still creates a version — **the control
degrades to auditability, not to nothing.** A relaxation triggers neither candidate
review (A1) nor reconsideration of rejected candidates (F2). Detail in Section 10.

**EVIDENCE.** Only **quantity** is enforced between the two records today:
`hreq_approved_qty()` reads the **approved snapshot's** quantity and
`hreq_remaining_qty()` stops requisitions exceeding approved headcount. No
eligibility field is checked at all. A8 extends that existing
compare-against-what-was-approved pattern from one field to the eligibility
minimums.

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

**DECIDED: a candidate who is not hired AND not under an active recruitment
process belongs to the authorised company-wide candidate pool.**

**DECIDED: active recruitment visibility is controlled by**

> **candidate state + recruitment relationship + permission**

**DECIDED (Q2): the definition of "Active Recruitment Process" is configurable by
organisation and by pipeline.**

**DECIDED (Q2): do not create an unnecessary independent hard-coded active-status
engine.**

The conceptual states, and how active status must be derived, are in Section 7.

**This supersedes the first issue's open question.** The first issue recorded "where
does an active recruitment process begin?" as a blocking decision. It is no longer
a single answer to be agreed: it is a per-organisation configuration, derived from
the pipeline rather than from a mechanism of its own.

**DECIDED (A7) — how scope is obtained.** A candidate does **not** acquire an
office dimension. Recruitment scope for an **active** candidate is **derived from
the requirement they are attached to** — that requirement's office and business
unit — reusing the existing scope helpers. A candidate attached to no requirement
has no scope, because the pool is company-wide. `candidates.sbu` remains
**descriptive** and is not used for access decisions; the requirement is the single
source. **No new column on `candidates`, and no migration of existing candidate
records.**

Locked consequence: **if a requisition moves office or business unit, the active
candidates attached to it inherit that changed scope.**

**DECIDED (C36) — how a candidate becomes available again.** There is **no "return
to pool" transition**. Availability follows this decision's own definition: a person
not hired and under no active recruitment process is in the available pool.
**Availability is evaluated per person across all their candidate rows**, using the
existing pool convergence — never per row — so someone rejected on one requirement
while active on another is **not** available.

**Consequence the owner should see.** Because the gate applies only to *active*
candidates, candidates sitting in the available pool remain visible company-wide by
design. Confidentiality between branches applies from the moment someone is being
worked on, not before. That is the decision as given.

---

## D4 — Candidate access

**DECIDED: the primary recruitment roles are Recruiter, Hiring Manager and
Department Head.**

**DECIDED: the access model is**

> **ROLE DEFAULTS + CONFIGURABLE PERMISSIONS + RECRUITMENT SCOPE**

**DECIDED: role alone must not imply unrestricted access.**

**DECIDED (Q23): default recruitment permissions use a PREDEFINED ROLE PROFILE plus
ADMINISTRATOR OVERRIDE.**

Detail, including the actions to be distinguished, is in Section 7.

**This changes the shape of the work.** The first issue treated the role × action
matrix as thirty-plus separate business decisions. Under Q23 it becomes a **shipped
default that an administrator can change** — so what is needed is a sensible
starting profile per role, not a locked matrix.

**DECIDED (A4) — the override model.** Permissions are configured **per role,
never per user**. An organisation needing different rights for an individual creates
a **custom role with the appropriate base**, so every exception is named and
reviewable in one place. A shipped role profile is a **default, not a floor**: an
administrator may both grant additional rights and remove rights the default
includes. **Sensitive permissions — candidate salary visibility and equivalents — are
never granted or removed as a side effect of a blanket action**; they are always set
deliberately. This reuses the existing `custom_roles` base-role mechanism and the
existing access editor; **no per-user grant layer is introduced.**

**Also DECIDED:** role profiles **track their shipped base**; however, a shipped
change that *adds* a sensitive permission does not propagate that permission without
deliberate administrator action.

**DECIDED (B4) — the shipped profile contents.**

| Action | Recruiter | Hiring Manager | Department Head |
|---|---|---|---|
| View candidates in scope | ✔ | ✔ | ✔ |
| Raise a Hiring Request | — | ✔ | ✔ |
| Raise a Requisition from an approved request | ✔ | — | — |
| Create / edit candidate | ✔ | — | — |
| Move candidate stage | ✔ | — | — |
| Shortlist | ✔ | ✔ | — |
| Reject | ✔ | ✔ | — |
| Schedule an interview | ✔ | — | — |
| Record interview outcome / scorecard | ✔ | ✔ | — |
| Offer create / revise / send | ✔ | — | — |
| Convert candidate to workforce | ✔ | — | — |
| **Propose a material change** (Q8) | ✔ | ✔ | ✔ |
| **Clear a Review Required** (Q17) | — | ✔ | ✔ |
| View candidate salary | — | — | — |
| Export · Delete | — | — | — |

**Two choices in that table are load-bearing:**

- **A Recruiter cannot clear a Review Required.** The review exists *because the
  requirement moved*; letting the person under pressure to fill the role clear their
  own candidates would hollow out the control. This follows the segregation the code
  already asserts — `requested_by_id` is material as *"the identity segregation of
  duties was judged against"*, and `hreq_can_create()` notes that *"approval
  authority is held APART from creation authority on purpose."*
- **A Recruiter can complete the hire**, because **Candidate Hiring approval is the
  control on the conversion** where an organisation configures one (A3) — not an
  assumption that the Recruiter is an approval authority. Without this the three
  shipped roles could not complete a hire at all.

**Everyone may propose a material change**, because proposing is *asking*, not
spending: F1's approval is the control, and under A8 the recruiter is usually the
person who discovers the need.

### Two permission codes are required — a narrow supersession of "no new codes"

These profiles **cannot** be expressed with the three permissions recruitment
declares today (`mod.hiring.view`, `mod.hiring.edit`, `hiring.admin` — where
`hreq_can_create()` is literally `can('mod.hiring.edit')`). Two locked rules need a
role to hold a specific write right **without** holding general recruitment write:

| Code | Why it cannot be folded into `mod.hiring.edit` |
|---|---|
| propose a material change (**Q8**) | A Department Head must propose without gaining candidate create/edit or offer rights |
| clear a Review Required (**Q17**) | The reviewer right must be grantable to a role that does not run the pipeline |

**Salary visibility needs no new code** — the existing `data.salary` permission
already carries it, and already resists blanket grants, which is exactly A4's
sensitive-permission guard.

**This supersedes "no new permission codes at this stage" to that extent and no
further.** It is not a permission-model expansion: there is still no per-user layer,
the existing access engine and `custom_roles` are reused, and every other constraint
stands. The code design itself is deliberately **not** settled here — it belongs to
implementation planning.

**Roles still undecided:** HR · Operations manager · Coordinator · Administrator ·
Finance · Inspector / field staff · External client · Agency. **Nothing may be
assumed for them.**

**UNDECIDED:** the remaining eight roles (HR · Operations manager · Coordinator ·
Administrator · Finance · Inspector / field staff · External client · Agency).
Nothing may be assumed for them.

---

## D5 — Approval framework

**DECIDED: approval is configurable. The approval-capable business events are**

1. Hiring Request
2. **Requisition** (Q24)
3. **Candidate Hiring** (Q1)
4. Salary Approval
5. Offer-related approval where configured

**DECIDED: each organisation configures** whether approval is required, the
approver(s), the sequence, conditions, thresholds, escalation and related
behaviour.

**DECIDED (Q22): if no approval rule is configured for an event, NO APPROVAL IS
REQUIRED. Do not silently impose a default management hierarchy.**

**DECIDED (Q1): Candidate Hiring approval is configurable per organisation** — an
organisation may have none, its own, Offer approval serving as the hiring approval
point, both, or different sequences and conditions.

**DECIDED (Q24): Requisition approval is configurable per organisation.**

Full detail in Section 8.

### Two corrections to the first issue of this specification

**Requisition approval.** The first issue recorded *"Requisition approval is NOT
required"*, inferred from its omission in the earlier decision list. **That
inference was wrong and is withdrawn.** D5 and Q24 make it configurable.

**"Candidate Hiring" is no longer an open question.** The first issue flagged it as
blocking. Q1 answers it by making it a per-organisation choice.

### DECIDED (A3) — Offer approval and Candidate Hiring approval are separate gates

Neither substitutes for the other. **Offer approval gates issuing the offer;
Candidate Hiring approval gates converting an accepted candidate into workforce.**
Where both are configured, **both must pass, in that order** — the order is fixed by
the recruitment lifecycle and is **not configurable**, because joining follows
acceptance which follows issue. Where only one is configured, only that gate
applies. Where neither is configured, no approval is required (Q22).

**A refused Candidate Hiring approval blocks the conversion; it does not reject the
candidate** — rejection remains a human act with a mandatory reason.

The distinction preserved: the **offer** decision is whether the organisation is
willing to make the offer; the **hiring** decision is whether the accepted person is
actually converted into workforce.

**EVIDENCE.** `offer_issue()` refuses an unapproved offer — *"§32 — an UNAPPROVED
offer can never be issued."* The conversion point is `rcv_convert()`, a discrete
named step with **no approval on it today**, and therefore a clean gate.

### DECIDED (B2) — what an organisation starts with, and what a change reaches

**A new organisation starts with approval required.** Until an administrator
configures otherwise, Hiring Requests require approval.

**"No approval rule configured" and "Approval Required = OFF" are distinct states
and must never be conflated.** Where an organisation requires approval but no
matching rule exists, the request does **not** self-approve and no approver is
invented: it waits, and the condition is surfaced as a **configuration problem**
naming what to fix. Q22 is honoured — nothing is invented — while silence is not
mistaken for consent.

**A change to the approval configuration applies to Hiring Requests raised after
it.** Requests already submitted continue under the configuration — and, mid-chain,
the approval chain — in force when they were raised. A requester may **withdraw and
resubmit** to bring a request under the new configuration; that is a deliberate
human act, never automatic.

**Through F1, this default also governs whether material changes require
approval** — one decision with two effects.

Why not the reverse: turning approval off under a re-evaluation rule would make
in-flight requests **approved en masse with no named approver**, and judging a thing
against what was in force when it was created is the pattern already locked three
times over (F1's approved snapshot, A2's issued offer, A8's approved minimum).

### One gap the owner should see

Today, a hiring request defaults to requiring approval, and when no rule matches it
waits for a manual yes/no. Q22 says the opposite. **With zero rules configured —
EXAACT's present state — a literal implementation would make every hiring request
self-approving.** Documented in Section 8 as **B2**, not resolved.

---

## D6 — Offer audit history

**DECIDED: significant offer lifecycle events must be historically auditable.**

Examples (nine, as given): created · submitted · approved · rejected · revised ·
sent · accepted · declined · withdrawn.

**Note on the count.** Section 9 elaborates this into **ten** rows by separating
*Offer approval* (a decision being taken, by whom and when) from *Offer approved*
(the offer becoming authorised for release). That is an elaboration of the owner's
list for auditing purposes, not an additional business decision.

Historical information must be preserved. Full detail in Section 9.

---

## D7 — Material change control

**DECIDED: selected material changes to an approved recruitment requirement require
re-approval.**

**DECIDED (Q4): the list of material fields is configurable by organisation.**

**DECIDED: existing material-change controls are retained. New controls are
ADDITIONS, NOT REPLACEMENTS.**

**DECIDED: budget must be available as a material-change control.**

**DECIDED: when a material change is proposed —**

1. a **pending proposed version** is created (Q5);
2. the **original approved version remains effective** (Q5);
3. a **reason is mandatory** (Q10);
4. **supporting documents are optional** (Q11);
5. the **approval route is configurable** (Q9);
6. the organisation may use the **original approval chain** or a **separate
   material-change approval chain** (Q9);
7. **rejected changes remain in historical records** (Q7);
8. **approved changes create a NEW APPROVED VERSION** (Q12);
9. the **previous approved version remains historically preserved** (Q12).

**DECIDED (Q6): the effect of a pending material change on recruitment activity is
configurable by organisation.**

**DECIDED (Q8): who may propose a material change follows D4** — role defaults +
configurable permission + recruitment scope.

**DECIDED — the material-change approval rule (F1).**

> Material-change approval **inherits the applicable approval rule of the
> underlying requirement by default**. If a separate material-change approval
> chain is configured, **that chain takes precedence**. The applicable approval
> chain is determined **using the values of the proposed changed version**, not the
> values of the previously approved version. If neither the underlying requirement
> nor a separate material-change rule requires approval, **no approval is
> required**.

**The precedence, in order:**

> **separate material-change rule → applicable underlying rule, matched on the new
> values → no approval if neither requires approval**

**DECIDED (A5) — the effect of a pending change is one graduated level.**
Configured per organisation as a single level over the **existing** execution gate,
not as independent switches:

| Level | Continues | Pauses |
|---|---|---|
| **1** | nothing | everything — *today's behaviour* |
| **2** — *shipped default* | raise requisition · advance · interview | **offer · joining** |
| **3** | raise · advance · interview · offer | **joining** |
| **4** | everything | nothing |

**Level 2 is the product default, not a mandate** — an organisation may configure any
level. Where a level permits work to continue, **the pending change must be visible
to the people doing that work.** No new gate or enforcement mechanism is introduced.

**DECIDED (A6) — versioning applies to both records.** One versioning mechanism
covers **both the Hiring Request and the Requisition**, each with its **own
configurable material-field list**: the Hiring Request's covers the authority it
carries; the Requisition's covers the fields that change **who qualifies or what it
costs**, and not execution wording such as the job description. Where a requisition
has no approving authority behind it (ADR-001), its own version chain still applies.

*Why:* the eligibility criteria that decide "stricter" live on the **requisition**,
which has **no change control of any kind** today. Versioning the Hiring Request
alone would leave a documented path around A1 — raise the requisition's minimum
experience from 5 to 8, and no version, no approval and no candidate review would
occur. And `hreq_remaining_qty()` confirms one request can spawn **several**
requisitions, so request-level versioning alone cannot express one office tightening
while another does not.

**DECIDED (C45 / C9) — budget materiality is judged on the total commitment.**
Per-person cost × quantity × periods, plus any one-time cost, as computed by the
existing commitment calculation — **not on any single cost field**, because halving a
per-person figure while tripling the duration would otherwise pass unnoticed.

- An **increase** is material when it exceeds **a configured percentage of the
  approved commitment, or a configured absolute amount, whichever is greater**.
  Shipped defaults: **10%** or **₹1,00,000**, both configurable per organisation.
- Where **no commitment was approved**, the absolute amount alone applies.
- Completing an incomplete estimate is judged the same way — against the commitment
  **as shown to the approver**. No special exception.
- A **decrease is not material**, consistent with the quantity rule: an increase
  spends authority nobody granted, a decrease stays inside the approval. A
  fundamental change of role is caught by `designation`, `grade` and `position_id`,
  which are already material.

**This does not make the commitment determine the approval chain.** The commitment
already reaches the approval engine under its own context key, deliberately apart
from the headcount band, and **routing by value remains an explicit configuration
choice**. Materiality and routing stay separate controls, as F1 requires.

**DECIDED (G2) — the proposal lifecycle.** A **refused** proposal is closed
permanently and remains in history (Q7). It cannot be amended and resubmitted; a
further attempt is a **new proposal**, which may be pre-filled from the refused one,
carries a reference to it, has its own mandatory reason, and is routed on **its own**
proposed values (F1). This avoids nesting a revision history inside the version
chain.

- **At most one proposal may be pending against a record at a time.** A second
  cannot be raised until the pending one is approved, refused or withdrawn —
  consistent with the single-valued re-approval state the record already carries, and
  required for A5 to resolve one execution level.
- A proposer may **withdraw** their own pending proposal: it closes, requires a
  reason, remains permanently in history, does **not** alter the approved version,
  and restores normal execution under A5.

> Approved version → Proposal → Pending → **Approved / Refused / Withdrawn**, and a
> refused or withdrawn proposal may be followed by a **new** proposal. No historical
> proposal is ever overwritten.

**Not authorised for implementation now.** Full detail, including the three gaps
between these rules and the running system, in Section 10.

### The risk flagged in the first issue is closed

The first issue warned that reading the owner's shorter field list as a
*replacement* would silently weaken nine existing controls. **D7 settles it:
additions, not replacements. No control is lost.**

---

## D7-CANDIDATE — Candidate version behaviour

**DECIDED: when a new approved version is created, existing candidates attached to
that requirement move to the latest approved version (Q13).**

**DECIDED: if a candidate no longer appears to satisfy the latest requirement, DO
NOT automatically reject. Flag REVIEW REQUIRED (Q14).**

**DECIDED: the candidate must be reviewed before progressing (Q16).**

**DECIDED: previous candidate assessments and interviews remain valid and are not
automatically invalidated (Q15).**

**DECIDED: reviewer permission follows role defaults + configurable permission +
recruitment scope (Q17).**

**DECIDED: clearing the review requires a mandatory reason (Q18).**

**DECIDED: the review outcome may be CONTINUE or REJECT; both require a reason
(Q19).**

**DECIDED: if REJECT is selected, the candidate is immediately rejected and no
additional approval is required (Q20).**

Full detail in **Section 19**.

**The principle in one line:** *the requirement changed; the people did not — so a
human looks, and nobody is dismissed by a machine.*

**DECIDED (A1) — who is reviewed, and what the system may decide.** When a stricter
version is applied, **all active candidates** attached to that requirement enter
Review Required. An authorised reviewer decides who continues and who is rejected.
The **existing** candidate-versus-requirement comparison engine is reused as
**decision-support evidence only**: its score, factor results and text matching
**must never automatically clear, reject or gate** a candidate.

*Why all, not only the apparent failures:* two of the three criteria are compared by
text matching and qualification has no comparison factor at all, so an automatic
filter would exempt people from scrutiny on a string match — and deciding who escapes
scrutiny is itself a decision. **A1 therefore does not wait on D2**; the evidence
simply gets richer as those fields arrive.

**DECIDED (A2) — how far a new version reaches.** Up to the point an offer has been
**issued**. A candidate with an issued, accepted or joined offer is neither placed
into Review Required nor re-linked to the new version; they remain attached to the
version in force when their offer was issued. A **draft** offer is not yet a
commitment, so those candidates remain in scope. **This boundary is fixed in the
product and is not derived from the organisation's "Hired" setting (Q21)** — a
reporting preference must not silently decide who can have an offer withdrawn.

*Consequence:* no offer-to-requirement-version link is required for this control.

**DECIDED (C43) — what a Reject writes.** The candidate moves to the pipeline's
configured **closed / not-proceeding** stage, identified **by kind, never by
organisation-specific name**. `terminal` remains reserved for the successful
hired/onboarding end. An ordinary rejection and a Review Required rejection use the
**same** closed stage; the recorded reason distinguishes them, carried in the
existing stage ledger's remark and actor. **Where no closed stage exists, the Reject
fails safely as a configuration problem and must never fall back to the legacy stage
field.** This makes the closed kind a **foundational pipeline requirement** — see
D1's status note above.

---

## D8 — Recruitment home

**DECIDED: one primary Recruitment home**, providing access to recruitment
execution, pipeline/funnel, KPIs, relevant actions and relevant recruitment
functions.

**DECIDED: existing functionality must be reused.**

**Nothing may be deleted during this documentation exercise.** Existing screens and
routes remain. How they are reused, redirected or integrated follows
**REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → BUILD**.

Detail in Section 11.

**DECIDED (C21) — which destination it is.** **`/recruitment` is the one primary
Recruitment home.** It already serves as a direct entry point for recruitment-only
organisations, and it already holds recruitment execution, actions, functions and
worklists. **Funnel and KPI summaries from the existing Command Centre are surfaced
within it**, while the full analytics board remains available as a named destination
from the home.

**Nothing is deleted or rebuilt** — the change follows REUSE → EXTEND → CONNECT.

**Naming forms part of this decision.** Only one destination is presented as the
Recruitment home; the analytics board is renamed for its actual purpose through the
existing terminology engine, so the two no longer read as competing homes. Role
Workspaces remain compatible as per-role landing and curation **within** the one
home.

**CLARIFICATION (C23) — confirmed.** The reading adopted is: *the application's
general Home remains the landing page, and within recruitment there is exactly one
recruitment home, one click away.*

---

## D9 — Mobile

**DECIDED: the mobile model is**

> **MOBILE OPERATIONAL + DESKTOP ADMINISTRATIVE**

**DECIDED: mobile should support the important operational recruitment activities**
— candidate review, approvals, shortlisting, interview actions, candidate updates,
pipeline movement and hiring decisions.

**DECIDED (Q25): complex administration and configuration may remain
desktop-oriented.**

**DECIDED: actual behaviour must later be verified with browser and mobile
testing.**

Detail in Section 12.

### A correction to the first issue

The first issue recorded *"D9 is NOT a business decision. A verification
requirement."* **It is now both** — a decision about what belongs on a phone, and a
verification requirement because nothing has yet been tested on one. **Recruitment
must still not be assumed mobile-ready.**

---

# Section 3 — Decision Dependency Map

Which decisions must be settled before others can be built.

```
                    ┌──────────────────────────────┐
                    │  D1  Authoritative lifecycle │  ← foundation
                    └───────────────┬──────────────┘
          ┌─────────────────────────┼──────────────┬───────────────┐
          ▼                         ▼              ▼               ▼
   KPI / SLA / ageing        Dashboards      Industry stage   Q2 "active
                             / funnel        sets            process" definition
                                                             (must derive from
                                                              the pipeline)

                    ┌──────────────────────────────┐
                    │  D2  Person specification    │  ← second foundation
                    └───────────────┬──────────────┘
                 ┌──────────────────┼───────────────────┬──────────────┐
                 ▼                  ▼                   ▼              ▼
        Inheritance (S6)   D7 material list     Industry portability  Q14 eligibility
                           (3 of 4 additions    (11 of 13 industries   review trigger
                            do not exist yet)    blocked on this)      (needs the fields)

                    ┌──────────────────────────────┐
                    │  Q21  What "Hired" means     │  ← small setting, four consumers
                    └───────────────┬──────────────┘
                 ┌──────────────────┼───────────────────┬──────────────┐
                 ▼                  ▼                   ▼              ▼
          D3 pool membership   D1 terminal point   time-to-hire KPI   Q13 which
          ("not hired")                                              candidates move

   ┌───────────────┐     ┌───────────────┐
   │ D3 visibility │────▶│ D4 role       │
   │ + Q2 active   │     │ defaults+perm │
   └───────┬───────┘     └───────┬───────┘
      both are halves of one access model
                                 │
                                 ├──────────────▶ Q17 reviewer right
                                 ├──────────────▶ Q8 propose-change right
                                 ▼
                        ┌───────────────┐
                        │ D5 approval   │  Q1 · Q22 · Q24
                        │ configurable  │
                        └───────┬───────┘
                                ▼
                        ┌───────────────┐
                        │ D7 material   │  Q4–Q12
                        │ change +      │
                        │ versioning    │
                        └───────┬───────┘
                                ▼
                        ┌────────────────────────┐
                        │ Section 19  candidate  │  Q13–Q20
                        │ version behaviour      │
                        └───────┬────────────────┘
                                ▼
                        ┌───────────────┐
                        │ D9 mobile     │  review + approve on a phone
                        └───────────────┘

   ┌───────────────┐   ┌───────────────┐
   │ D6 offer audit│   │ D8 one home   │  consumes D1 (funnel) + D3/D4
   └───────────────┘   └───────────────┘
     independent
```

## Ordering rules that follow

| Rule | Reason |
|---|---|
| **D1 before any reporting, KPI, SLA or dashboard work** | All of them read lifecycle state. Building them on the legacy field means rebuilding them. |
| **D1 before Q2** | "Active recruitment process" must be derived from pipeline stage kinds, so the pipeline must be authoritative first. |
| **D2 before D7's field list can be finalised** | Three of D7's four additions — qualification, experience, essential skills — **do not exist yet**. |
| **D2 before Q14 can be automatic** | Eligibility cannot be compared against fields that do not exist. |
| **D2 before industry rollout** | The person-spec gap blocks 11 of 13 industries (audit §32). |
| **Q21 early** | One setting feeds pool membership (D3), the terminal point (D1), time-to-hire, and which candidates move (Q13). It looks small; it is not. |
| **D3 and D4 together, never separately** | Two halves of one access model: D3 defines *when* a gate applies, D4 *who* passes it. |
| **D4 before Q8 and Q17** | The propose-change right and the reviewer right live in the role profile. |
| **D5 before D7** | Material-change re-approval re-enters the approval engine; Q9's route choice depends on how approval is configured. |
| **D7 before Section 19** | Candidates cannot move to "the latest approved version" until versions exist. |
| **Section 19 before D9 can be verified** | A phone must be able to clear a review that blocks a candidate. |
| **D6 independent** | Neither blocks nor is blocked. |
| **D8 after D1** | The one home shows the funnel; the funnel depends on the authoritative lifecycle. |

## The critical path

> **D1 → D2 → Q21 → (D3 + D4) → D5 → D7 → Section 19**, with D6 and D8 able to
> proceed in parallel once D1 is settled, and D9 verified last because it exercises
> everything above it.

**D1 and D2 remain the two foundations. Work built before they are settled is work
that will be redone.** Q21 has joined them near the front — not because it is
large, but because four separate things read it.

---
# Section 4 — Recruitment Business Lifecycle

## The end-to-end business flow

```
  ORGANISATION CONFIGURATION
  (business type · industry · recruitment model · approval model ·
   pipeline model · terminology · fields · dropdowns · permissions ·
   material-change list · active definition · "Hired" definition)
            │
            │  configures every step below — the user never reconfigures
            ▼
  ┌──────────────────────┐
  │   HIRING REQUEST     │   "we need a person"
  │   the approved need  │   core requirement: role, department, quantity,
  └──────────┬───────────┘   location, engagement type, qualification,
             │               experience, essential skills, budget
             ▼
       ◆ APPROVAL ◆            configurable (D5). No rule = no approval (Q22)
             │
             ▼
  ┌──────────────────────┐
  │ APPROVED VERSION  v1 │   this is what was authorised
  └──────────┬───────────┘
             │  inherits downward (D2-A) — nothing re-typed
             ▼
  ┌──────────────────────┐
  │    REQUISITION       │   "go and find them"
  │  execution detail    │   adds JD, preferred skills, screening questions,
  └──────────┬───────────┘   sourcing, client requirements, evaluation criteria
             │               may strengthen, may not weaken (D2-B)
             │
       ◆ APPROVAL ◆            configurable per organisation (Q24)
             │
             ▼
  ┌──────────────────────┐
  │     CANDIDATE        │   sourced, applied, referred, imported, marketplace
  └──────────┬───────────┘
             ▼
  ┌──────────────────────────────────────────────────┐
  │        CONFIGURABLE PIPELINE  (authoritative)    │  D1
  │  stages configured per business type / industry  │
  │  each stage has a business KIND and a LABEL      │
  │  stage kinds also define "active engagement" (Q2)│
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
       ◆ APPROVAL ◆            offer / salary approval where configured (D5)
             │
             ▼
  ┌──────────────────────┐
  │   CANDIDATE HIRING   │   acceptance → joining
  └──────────┬───────────┘   ◆ APPROVAL ◆ configurable (Q1)
             │               "Hired" = Accepted OR Joined, by configuration (Q21)
             ▼
  ┌──────────────────────┐
  │      WORKFORCE       │   no longer a recruitment-pool candidate (D3)
  └──────────────────────┘
```

## The material-change loop (D7 · Section 19)

A change to an approved requirement does not edit it. It proposes a new version
alongside it:

```
  ┌──────────────────────┐
  │ APPROVED VERSION  v1 │◀────── stays EFFECTIVE while a change waits (Q5)
  └──────────┬───────────┘
             │  someone proposes a material change
             │  reason MANDATORY (Q10) · documents optional (Q11)
             │  who may propose: role + permission + scope (Q8)
             ▼
  ┌──────────────────────┐
  │  PENDING  VERSION    │   effect on ongoing recruitment: CONFIGURABLE (Q6)
  └──────────┬───────────┘
             │
       ◆ APPROVAL ◆   original chain OR separate material-change chain (Q9)
             │
     ┌───────┬────────┬────────┐
     ▼       ▼        ▼        ▼
  REFUSED  WITHDRAWN        APPROVED
  closed   closed by        ┌──────────────────────┐
  (Q7,G2)  proposer (G2)    │ APPROVED VERSION  v2 │  v1 preserved (Q12)
     │       │              └──────────┬───────────┘
     └───┬───┘                         │  candidates move to it (Q13) —
         │  a NEW proposal may          │  but only up to an ISSUED offer (A2)
         │  follow; never an            ▼
         │  amendment (G2)   ┌──────────────────────┐
         ▼                   │  REVIEW REQUIRED     │  ALL active candidates (A1)
   normal execution          │  CONTINUE or REJECT  │  both need a reason (Q18,Q19)
   restored (A5)             └──────────┬───────────┘  evidence only, never auto
                                        │              (A1) · see Section 19
                                        ▼ on REJECT
                             the configured CLOSED stage (C43)
```

**At most one proposal may be pending against a record at a time (G2)**, which is
what lets A5 resolve a single execution level. **Versioning applies to both the
Hiring Request and the Requisition (A6)**, each with its own material-field list, and
the review trigger is a **new effective version** — not an approval event.

## Where approval may occur

| Point in the flow | Approval | Status today | Decision |
|---|---|---|---|
| Hiring Request submitted | Configurable | configurable **and works** | D5 |
| **Requisition raised** | **Configurable** | configurable but **never fires** | **D5, Q24 — wiring needed** |
| Offer to candidate | Configurable | configurable **and works** | D5 |
| **Candidate Hiring (joining)** | **Configurable** | **no such approval entity exists** | **D5, Q1 — new event** |
| Salary structure | Configurable | configurable but **never fires** | D5 — wiring needed |
| **Material change to an approved version** | **Inherits the requirement's rule; separate chain takes precedence; matched on the NEW values** | mechanism exists; route choice and new-value matching do not | **D7, Q9, F1** |

**Binding rule (Q22 · B2): where no rule is configured for an event, that event
requires no approval** — and no default management hierarchy may be imposed. But
**"no rule configured" and "approval not required" are different states (B2)**: an
organisation that requires approval and has configured no matching rule gets a
**configuration problem**, not a self-approval. **A new organisation starts with
approval required.**

**Order is fixed, not configurable (A3).** Offer approval gates *issuing*; Candidate
Hiring approval gates *converting an accepted candidate into workforce*. Where both
are configured both must pass, in that order, because joining follows acceptance
which follows issue:

> Offer approval → offer issued → candidate accepts → Candidate Hiring approval → workforce

**EVIDENCE.** Of the four approvable entities today, only `HIRING_REQUEST` and
`OFFER` actually fire. `REQUISITION` and `SALARY` are configurable, savable — and
silently never run (audit §7.1, §20, §24). **An administrator can configure a
salary approval rule today and it will do nothing.** Q24 now makes requisition
approval a requirement, so that wiring moves into scope. **Zero approval rules are
configured today.**

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
| **Business type** | TPIA, agency, corporate HR, manufacturing, trading, IT, professional services, other | Business Profile / Capabilities |
| **Industry** | Industry defaults and vocabulary | 13 industry templates |
| **Recruitment model** | Internal hiring · client-facing placement · both | *(new dimension — see clarifications)* |
| **Organisation structure** | Offices, departments, designations, positions, org chart | `lib/position.php`, canonical Department vocabulary |
| **Approval model** | Which events need approval, who, sequence, thresholds, escalation, delegation — and, per Q22, **that configuring nothing means no approval** | `lib/recruit_approval.php` |
| **Pipeline model** | The candidate journey stages and their kinds | `lib/recruitpipe.php` |
| **Terminology model** | What each object is called | `lib/terms.php` — 12 term packs |
| **Field configuration** | Which fields show, which are mandatory, order, sections, defaults | *(partially exists)* |
| **Dropdown / master configuration** | The permitted values in every list | lookup engine, 22 types |
| **Permission configuration** | Role profiles plus administrator override (Q23) | `mod.hiring.*` + role model + Role Workspaces |
| **⊕ Material-change field list** | Which changes to an approved requirement require re-approval (Q4) | `HREQ_MATERIAL_FIELDS` — today a constant |
| **⊕ Material-change approval route** | Original chain, or a separate material-change chain (Q9) | `lib/recruit_approval.php` |
| **⊕ Effect of a pending change** | One graduated level 1–4 over the existing execution gate; **default level 2** (Q6 · **A5**) | `REXEC_ACTIONS` gate — today a hard pause, which becomes level 1 |
| **⊕ Approval required by default** | A new organisation starts with approval **required**; "no rule configured" is a configuration problem, not consent (**B2**) | `approval_required` per record — already defaults to 1 |
| **⊕ Budget materiality threshold** | Percentage of approved commitment, or absolute amount, whichever is greater; defaults **10%** / **₹1,00,000** (**C45**) | `hreq_commitment()` already computes the total |
| **⊕ Role profiles** | The shipped default rights per role, a **default not a floor**, with administrator override (**A4 · B4**) | `custom_roles` base-role mechanism + access editor |
| **⊕ Closed stage kind** | Every pipeline must be able to express a **closed / not-proceeding** outcome, distinct from `terminal` (**C43**) | **Does not exist** — a foundational addition to the stage-kind vocabulary |
| **⊕ "Active recruitment process" definition** | Which pipeline stages count as active engagement (D3, Q2) | pipeline stage *kinds* — **no separate engine** |
| **⊕ "Hired" definition** | Offer Accepted, or Actual Joined (Q21) | acceptance and joining already distinct |

**⊕ = added by the decisions.** Ten new dimensions. **Nine attach to a mechanism that
already exists**; the tenth — the **closed stage kind** — is the one genuine addition,
and it is a prerequisite for D1 rather than an optional extra (see D1's status note).

## What each dimension influences

| | Hiring Request | Requisition | Candidate | Pipeline | Approval | Offer | Hiring |
|---|---|---|---|---|---|---|---|
| Business type | ✔ fields, labels | ✔ | ✔ | ✔ stage set | ✔ chain | ✔ | ✔ |
| Industry | ✔ defaults | ✔ | ✔ | ✔ stage set | — | ✔ template | — |
| Recruitment model | ✔ client field relevance | ✔ | ✔ submission states | ✔ | ✔ | ✔ | ✔ |
| Org structure | ✔ dept, office, position | ✔ | — | — | ✔ chain by office | — | ✔ |
| Approval model | ✔ | ✔ (Q24) | — | — | ✔ | ✔ | ✔ (Q1) |
| Pipeline model | — | — | ✔ | ✔ | — | ✔ gate | ✔ gate |
| Terminology | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Field config | ✔ | ✔ | ✔ | — | — | ✔ | ✔ |
| Dropdowns | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Permissions | ✔ who may raise | ✔ | ✔ who may see | ✔ who may move | ✔ who approves | ✔ | ✔ |
| Material-change list | ✔ | ✔ (if B3 = both) | ✔ triggers review | — | ✔ | — | — |
| Pending-change effect | ✔ | ✔ | ✔ may pause work | — | — | — | — |
| Active definition | — | — | ✔ visibility | ✔ source of truth | — | — | — |
| "Hired" definition | — | ✔ closure | ✔ pool membership | ✔ terminal point | — | ✔ | ✔ |

## Per-organisation configuration is structurally free

Ten decisions require configuration *per organisation* (D3, D5, D7, Q1, Q2, Q4,
Q6, Q9, Q21, Q24). Because every customer has **its own separate database** and no
tenant column exists anywhere, each organisation already holds its own
configuration. **No multi-company plumbing is required for any of them.**

## What can be configured today — EVIDENCE

Already configurable without code (audit §20): vocabulary (12 term packs, 13
industry templates); candidate pipeline stages; approval matrix (rules, levels,
SLA, escalation, delegation); compensation headings; document and letter
templates; positions and org chart; careers posting; role workspaces; 22 lookup
types.

The audit's conclusion, verbatim: *"Configuration mechanisms already exist and are
single. **No second configuration system is needed** for anything listed."*

## What cannot be configured today — EVIDENCE

| Gap | Consequence | Bears on |
|---|---|---|
| `WF_TEAM_ROLES` (FIELD / COORD / OFFICE) | Inspection-shaped team roles are fixed | Section 13 |
| The **shape** of the legacy candidate funnel | An industry can rename stages but not restructure the journey | **D1** |
| A **closed / not-proceeding outcome** on the configurable pipeline | `terminal` means *hired*; every loss is expressible only in the legacy stage field. **Decided as a foundational requirement by C43** | **D1, C43** |
| `APPR_ENTITIES` is a fixed constant | Which entities can require approval is not configurable — and Q1 needs a fifth | **D5, Q1** |
| Whether a requisition edit needs re-approval | Not configurable | **D7, Q24** |
| Which fields are material | A constant, not per-organisation | **D7, Q4** |
| The effect of a pending change | A hard pause, not a choice | **D7, Q6** |
| The person-spec fields | **They do not exist, so there is nothing to configure** | **D2** |

**The last row remains the most important line in this section.** Configuration
cannot expose a field that does not exist — and three of D7's four new material
controls are exactly those missing fields.

## Labels — a caution

**Do not hard-code replacement terminology now.** "Hiring Request" may be presented
under different words per business type through the existing term-pack engine.
Nothing in this specification authorises renaming anything.

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
Approved Hiring Request  ── version ──▶ every approved version retained (Q12)
        ↓   inherited from the LATEST APPROVED VERSION (Rule D2-A, Q13)
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

## Inheritance follows the latest approved version (Q12 · Q13)

Once an approved requirement can exist in more than one version (Section 10),
"inherit from the approval" needs to be precise:

> **Downstream records inherit from the CURRENT APPROVED VERSION, not from the
> original one and not from a pending proposal.**

- A **pending** proposed version inherits nothing downstream — it is not yet
  authority (Q5).
- When a new version is approved, it becomes the source for inheritance, and
  candidates already attached move to it (Q13).
- Earlier versions remain readable as history but are no longer the source (Q12).
- **Both records are versioned (A6)**, so inheritance runs from the Hiring Request's
  current approved version to the Requisition's current approved version, and onward.
  A **refused or withdrawn** proposal inherits nothing downstream (G2).
- A candidate with an **issued, accepted or joined** offer does **not** move to a new
  version (A2); they keep the version in force when their offer was issued.

**Why this matters in business terms:** without this rule, two requisitions raised
a month apart from the same requirement could silently inherit different terms, and
nobody could say which was correct. One current version, one source of truth.

## Inheritance may be strengthened, and weakened only by proposal (A8)

A Requisition inheriting from an approved Hiring Request **may strengthen** the
requirement freely — asking for more than was approved spends no authority it was not
given. Setting a value **below the approved minimum** is a weakening, and is **routed
through the material-change process** rather than blocked or silently allowed (A8).

Weakening is judged **against the approved minimum, not the Requisition's previous
value**, so correcting an over-specified requisition back toward the approved floor
needs no proposal. The exception, once approved, lives **on that Requisition** and
does not relax the standard for others raised from the same request.

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

# Section 7 — Candidate Visibility & Access (D3 · D4 · Q2, Q3, Q17, Q21, Q23)

**These are two halves of one access model and must be implemented together.**
D3 decides *when* a gate applies. D4 decides *who* passes it.

## D3 — the conceptual candidate states

| State | Business meaning | Who may see |
|---|---|---|
| **Available Candidate** | Not hired, not actively engaged in any recruitment process | Authorised company-wide recruitment users |
| **Active Candidate** | Attached to an active recruitment process | Candidate state **+** recruitment relationship **+** permission |
| **Hiring Transition** | Reached hiring / offer / acceptance stages | Controlled transition state |
| **Joined / Workforce** | Entered the workforce | No longer treated as a general recruitment-pool candidate |
| **Released / Available Again** | No longer actively engaged; where business rules allow, returns to the available pool | Back to company-wide |

## "Active recruitment process" is configurable (D3 · Q2)

**DECIDED: the definition of "Active Recruitment Process" is configurable by
organisation and by pipeline.**

**DECIDED (Q2): do not create an unnecessary independent hard-coded active-status
engine.**

This closes the blocking question from the first issue. The boundary is no longer
a single product-wide definition to be agreed; it is a per-organisation
configuration — and it must be **derived from the pipeline**, not stored in a
parallel mechanism of its own.

### How it should be derived, and why

Pipeline stages already carry a business **kind** as well as a label (D1). The
kind is what tells the system what a stage *means*. Active status is therefore a
property an organisation configures **against its pipeline stages** — for example
by marking which stage kinds count as active engagement.

**This is the whole point of Q2's constraint:** a second, separate "is this
candidate active?" mechanism would be a third lifecycle competing with the two the
product is already trying to reduce to one. **One pipeline engine, and active
status read from it.**

### Dependency

**This depends on D1.** Active status cannot be derived from the pipeline until
the pipeline is the authoritative lifecycle.

### EVIDENCE

**No active-status concept exists today.** There is nothing to retire and nothing
to reconcile — but equally nothing to build on until D1 lands.

## "Hired" is configurable (Q21)

**DECIDED: the definition of "Hired" is configurable by organisation —
either Offer Accepted, or Actual Joined.**

**DECIDED: Accepted and Joined remain distinct business events.** Whichever is
configured as "Hired", both continue to be recorded separately.

### Why this is larger than it looks

D3's available pool is defined as candidates who are **not hired**. So the
configured definition of "Hired" decides **who is in the company-wide pool**. It
also decides where the recruitment lifecycle terminates (D1), what time-to-hire
measures (the KPI engine), and which candidates move to a new approved version
(Q13, Section 19).

**One setting, four consumers.** It should be settled early — see Section 3.

### EVIDENCE

Acceptance and joining are **already distinct steps** in the product: the offer
records acceptance, and a separate hand-off creates the workforce record. **Q21
therefore needs a setting and consumers that read it, not a restructuring.**

## D4 — the access model

> ## ROLE DEFAULTS + CONFIGURABLE PERMISSIONS + RECRUITMENT SCOPE

**Binding rule D4-A:** *role alone must not imply unrestricted access.*

This supersedes the wording used in the first issue ("Role + Permission +
Relationship"). The three parts are now named precisely:

| Part | Meaning |
|---|---|
| **Role defaults** | What this role can do out of the box — a predefined profile (Q23) |
| **Configurable permissions** | What an administrator grants or withdraws on top (Q23) |
| **Recruitment scope** | Which candidates and requirements this user's access reaches at all |

## Default permissions: role profile + administrator override (Q23)

**DECIDED: default recruitment permissions use a PREDEFINED ROLE PROFILE plus
ADMINISTRATOR OVERRIDE.**

This changes the shape of the work. The first issue treated the role × action
matrix as a long list of business decisions to be taken one cell at a time. Under
Q23 it becomes **a shipped default that an administrator can change** — so what is
needed is a sensible starting profile per role, not a locked matrix.

### DECIDED (A4) — the override model

**Per role, never per user.** An organisation needing different rights for an
individual creates a **custom role with the appropriate base**, so every exception is
named and reviewable in one place. A shipped profile is a **default, not a floor**: an
administrator may both grant additional rights and **remove** rights the default
includes. **Sensitive permissions — candidate salary visibility and equivalents — are
never granted or removed as a side effect of a blanket action**; they are always set
deliberately.

**No per-user grant layer is introduced.** The existing `custom_roles` base-role
mechanism and the existing access editor are reused.

**Role profiles track their shipped base**; a shipped change that *adds* a sensitive
permission does not propagate without deliberate administrator action.

**Why per role and not per user.** Individual grants accumulate invisibly: nobody
remembers why one person holds something, and *"who can see candidate salaries?"*
stops having a findable answer because it is no longer in one place. A custom role
names the exception instead of hiding it.

### DECIDED (B4) — the shipped default profiles

| Action | Recruiter | Hiring Manager | Department Head |
|---|---|---|---|
| View candidates in scope | ✔ | ✔ | ✔ |
| Raise a Hiring Request | — | ✔ | ✔ |
| Raise a Requisition from an approved request | ✔ | — | — |
| Create / edit candidate | ✔ | — | — |
| Move candidate stage | ✔ | — | — |
| Shortlist | ✔ | ✔ | — |
| Reject | ✔ | ✔ | — |
| Schedule an interview | ✔ | — | — |
| Record interview outcome / scorecard | ✔ | ✔ | — |
| Offer create / revise / send | ✔ | — | — |
| Convert candidate to workforce | ✔ | — | — |
| **Propose a material change** (Q8) | ✔ | ✔ | ✔ |
| **Clear a Review Required** (Q17) | — | ✔ | ✔ |
| View candidate salary | — | — | — |
| Export · Delete | — | — | — |

**A Recruiter cannot clear a Review Required.** The review exists *because the
requirement moved*; letting the person under pressure to fill the role clear their own
candidates would hollow out the control. This follows the segregation the code already
asserts — `requested_by_id` is material as *"the identity segregation of duties was
judged against"*, and `hreq_can_create()` records that *"approval authority is held
APART from creation authority on purpose."*

**A Recruiter can complete the hire**, because Candidate Hiring approval is the
control on the conversion where an organisation configures one (A3) — not an
assumption that the Recruiter is an approval authority. Without it the three shipped
roles could not complete a hire at all.

**Everyone may propose a material change**, because proposing is *asking*, not
spending: F1's approval is the control, and under A8 the recruiter is usually the
person who discovers the need.

**Salary visibility is off in all three** and granted deliberately through the
existing `data.salary` permission, which already resists blanket grants.

### Two permission capabilities are required — a narrow supersession

These profiles cannot be expressed with the three permissions recruitment declares
today (`mod.hiring.view`, `mod.hiring.edit`, `hiring.admin` — where
`hreq_can_create()` is literally `can('mod.hiring.edit')`). Two locked rules need a
role to hold a specific write right **without** general recruitment write:

| Capability | Why it cannot fold into `mod.hiring.edit` |
|---|---|
| **Propose a material change** (Q8) | A Department Head must propose without gaining candidate create/edit or offer rights |
| **Clear a Review Required** (Q17) | The reviewer right must be grantable to a role that does not run the pipeline |

Both follow the project's established permission naming convention; the exact codes
are settled in implementation planning, not here.

**Scope of the supersession — deliberately narrow:**

- **No per-user permission layer**, then or ever under A4.
- **No blanket rewrite of the permission model.**
- The **existing access engine and `custom_roles`** are reused.
- **No new salary permission** — `data.salary` remains the control.
- Every other constraint in this specification stands unchanged.

**Roles still undecided:** HR · Operations manager · Coordinator · Administrator ·
Finance · Inspector / field staff · External client · Agency. Nothing may be assumed
for them.

## The primary recruitment roles

**DECIDED: Recruiter · Hiring Manager · Department Head.**

Other roles remain undecided and **nothing may be assumed for them**: HR ·
Operations manager · Coordinator · Administrator · Finance · Inspector / field
staff · External client · Agency.

## Actions to be distinguished

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
| **Propose a material change** | Put forward a change to an approved requirement (Q8) |
| **Clear a Review Required** | Resolve a flagged candidate — Continue or Reject (Q17, Section 19) |
| Other sensitive actions | View or edit current salary and salary expectation; export; delete |

The last three matter especially. **Who may read *money* on a candidate is a
narrower question than who may read the candidate**, and the two new rights (Q8,
Q17) are decision rights, not data rights.

## Constraints on any future work

- **No new permission codes at this stage** — the role profile and override model
  is expressed through the existing permission architecture wherever it can be.
  Where Q23 genuinely cannot be expressed within the existing three recruitment
  permissions, that is recorded as a clarification, not resolved here.
- **Do not replace the existing permission architecture.**
- Access is never granted by role name alone.

## EVIDENCE — the position today

| Fact | Source |
|---|---|
| Recruitment declares exactly **three** permissions: `mod.hiring.view`, `mod.hiring.edit`, `hiring.admin` | `lib/access.php:156,198` |
| Candidate screens guard on `is_coordinator_level()` — **a role predicate, not a recruitment permission** | `lib/ops.php:6867` |
| The candidate register is **unscoped**: `$where = '1=1'`, plus stage and free-text filters only | `lib/ops.php:6503` |
| By contrast the requisition register **is** office-scoped via `scope_clause('r.office_id','r.sbu')` | `lib/ops.php:5840` |
| `candidates` has **no office column**, so an office filter is not currently possible without a schema change | audit S3 |
| `/candidate?id=` has **no record-level scope check** | `lib/ops.php:6867` |
| Configurable Role Workspaces already exist | audit — a reuse candidate for Q23 |

**Two authorisation vocabularies are in play for one module** — module permissions
(`mod.hiring.*`) and role-level predicates (`is_coordinator_level()`).

**IMPLEMENTATION RECONCILIATION ITEM — `is_coordinator_level()`.** Recorded, not
resolved. Its effect today is that **someone who coordinates inspections, with no
recruitment duty, can open and edit candidate records including salary
expectations.** D4's model cannot be satisfied while a general operations
predicate is the gate, so **reconciling this is a prerequisite of D4, not a
tidy-up afterwards.**

**A consequence the owner should see.** D3 grants the available pool to
"authorised company-wide recruitment users". Today there is no such category — the
gate is an operations role. So D3 as decided is *narrower* than today's behaviour:
implementing it faithfully means deciding who authorised recruitment users are,
which is D4. **This is why the two cannot be split.**

### DECIDED (A7) — recruitment scope is derived, not stored

A candidate does **not** acquire an office dimension. Scope for an **active**
candidate is **derived from the requirement they are attached to** — that
requirement's office and business unit — reusing the existing scope helpers. A
candidate attached to no requirement has no scope, because the available pool is
company-wide (D3). `candidates.sbu` remains **descriptive** and is **not** used for
access decisions; the requirement is the single source.

**No new column on `candidates`. No migration of existing candidate records.**

| Candidate | Has a requirement? | Scope |
|---|---|---|
| In the available pool | No | none needed — company-wide by D3 |
| Active on a requirement | Yes | that requirement's office and business unit |

**Locked consequence:** if a requisition moves office or business unit, the active
candidates attached to it **inherit that changed scope**.

**Why not a column.** It would create a second source of truth for one fact — the
candidate's office and their requirement's office — which can disagree, in a
security-relevant place; and it would require inventing an office for 935 existing
rows. A directly raised requisition (ADR-001) still carries `office_id`, so candidates
on it scope correctly with no gap.

### DECIDED (C36) — availability is evaluated per person

There is **no "return to pool" transition.** Availability follows D3's definition: a
person not hired and under no active recruitment process is in the available pool.

**Availability is evaluated per person across all their candidate rows**, using the
existing pool convergence — **never per row**. Someone rejected on one requirement
while active on another is **not** available. F2's findability then covers deliberate
reconsideration.

---
# Section 8 — Approval Rules (D5 · Q1, Q22, Q24)

## The five approval-capable business events

| Business event | Decision | Works today? |
|---|---|---|
| **Hiring Request** | D5 | **Yes** — configurable and fires |
| **Requisition** | D5, **Q24** | Configurable but **never fires** |
| **Candidate Hiring** | D5, **Q1** | **No such event exists** |
| **Salary Approval** | D5 | Configurable but **never fires** |
| **Offer-related approval, where configured** | D5 | **Yes** — configurable and fires |

### A correction to the first issue of this specification

The first issue recorded **Requisition approval as not required**, inferred from
its omission in the earlier decision list. **That inference was wrong.** D5 and
Q24 make requisition approval configurable per organisation. The earlier statement
is withdrawn.

### Candidate Hiring is configurable, not fixed (Q1)

An organisation may have:

- no separate Candidate Hiring approval;
- a Candidate Hiring approval of its own;
- Offer approval serving as the hiring approval point;
- both;
- different sequences or conditions for each.

**This closes the blocking question from the first issue** — "what does Candidate
Hiring mean?" — by making the answer a per-organisation choice rather than one
product-wide definition.

## The binding rule when nothing is configured (Q22)

> **If no approval rule is configured for an event, NO APPROVAL IS REQUIRED.**
>
> **Do not silently impose a default management hierarchy.**

## What must be configurable

| Capability | Meaning | Existing column |
|---|---|---|
| Whether approval is required | The event may need no approval at all | absence of a rule (Q22) + the per-record `approval_required` control |
| Who approves | Named person, role, or relationship | `approver_role`, `approver_user_id` |
| Approval sequence | Order of a multi-step chain | `seq` |
| Conditions | When a rule applies | `applies_department`, `applies_sbu`, `applies_grade`, `applies_position` |
| Thresholds | Value bands selecting a different chain | `min_amount`, `max_amount` |
| Multiple approvers | More than one approver at a level | multiple levels; multiple rows per level |
| Escalation | What happens when a deadline passes | `escalate_role`, `escalate_user_id`, `sla_days`, `reminder_days` |
| Delegation | Stand-in when an approver is unavailable | supported by the engine |
| Rejection behaviour | What happens to the record on rejection | request/step status model |
| Re-submission behaviour | Whether and how a rejected item may be resubmitted | request/step status model |
| Which business event | The entity the rule governs | `entity` — a text column |

**Every item on the owner's configuration list maps onto a column that already
exists.** Verified column by column in Section 14.

## Capability versus activation — a binding distinction

> **That the system *can* approve something is a different question from whether
> approval *is enabled* for a particular organisation and process.**

1. **Capability** — this business event is approvable at all.
2. **Activation** — for *this* organisation, approval of this event is switched on,
   with these rules.

Under Q22, **activation is expressed by the presence of a rule.** An agency
configures no hiring-request rule and gets no internal approval. A corporate
configures a three-step chain. Neither is a code difference.

## Per-organisation configuration costs nothing structurally

Because every customer has its own separate database and no tenant column exists
anywhere, each organisation already has its own approval rules, levels and
requests. **"Configurable per organisation" requires no multi-company plumbing.**

## Business-model examples — illustrative only

| Business type | Illustrative model |
|---|---|
| Corporate organisation | Hiring Request → Department Head → HR Head → Management |
| Recruitment agency | No internal hiring-request rule at all (Q22 then requires no approval) |
| Manufacturing | Hiring Request → Plant Head → HR, with a headcount threshold |
| Small single-office business | Single approver, no chain |

**These are examples of what the engine must accommodate. None may be seeded as a
default** — seeding one would be exactly the "default management hierarchy" D5
forbids.

## EVIDENCE — the engine exists and is strong

`lib/recruit_approval.php` already provides multi-level chains, named approvers or
roles (including "the reporting manager"), deadlines, reminders, escalation and
stand-ins for absence. **No new approval system is needed.**

## EVIDENCE — three real problems, and one that Q22 changes

| Problem | Detail |
|---|---|
| **Two of four entities never fire** | `HIRING_REQUEST` and `OFFER` work. `REQUISITION` and `SALARY` are configurable, savable, and **silently never run**. An administrator can configure a salary rule today and nothing will happen. Q24 makes requisition approval a requirement, so this wiring is now in scope |
| **The entity list is a fixed constant** | `APPR_ENTITIES` gates which entity values are accepted. Q1 needs a fifth value, so the constant is the gate — though the rule table's `entity` column is free text |
| **Zero rules configured** | Count **0** |

**Configuration alone will not deliver D5.** This must be stated plainly in the
implementation plan: of the five approval-capable events, Hiring Request and Offer
work today, **Requisition and Salary are configurable but never fire**, and
Candidate Hiring may be a new approval point entirely. It is the difference between
"write some rules" and "connect two or three workflows, then write some rules".

### Gap — Q22 versus what the system does today

**Today:** `approval_required` defaults to **1** on a new hiring request. On
submission, the engine looks for a matching rule; when none matches it starts no
approval chain — **but the request has already moved to SUBMITTED and waits for a
manual yes/no.** So with no rules configured, approval is still required, by one
person, once.

**Q22 says the opposite:** no rule means no approval.

**Business implication, stated plainly.** EXAACT today has **zero** approval rules.
If Q22 were implemented literally and nothing else changed, **every hiring request
in EXAACT would become self-approving** until a rule is written. That may be
exactly right for a small single-office business and exactly wrong for a corporate
customer. The mechanism already exists to express either — `approval_required` is
a per-record control, and its default is the lever.

### DECIDED (B2) — the starting state, and what a configuration change reaches

**A new organisation starts with approval required.** Until an administrator
configures otherwise, Hiring Requests require approval.

**"No approval rule configured" and "Approval Required = OFF" are distinct states and
must never be conflated.** Where an organisation requires approval but no matching
rule exists:

- the request does **not** self-approve;
- **no approver is invented**;
- the request **waits**;
- the condition is surfaced as a **configuration problem**, naming what must be fixed.

Q22 is honoured — nothing is invented — while silence is not mistaken for consent.

**A change to the approval configuration applies to Hiring Requests raised after the
change.** Requests already submitted continue under the configuration — and,
mid-chain, the approval chain — in force when they were raised. A requester may
**withdraw and resubmit** to bring a request under the new configuration: a deliberate
human act, never automatic.

**Through F1, this default also governs whether material changes require approval** —
one decision with two effects.

**Why not re-evaluate in flight.** Turning approval off under a re-evaluation rule
would make requests already at SUBMITTED **approved en masse, with no named
approver**. And judging a thing against what was in force when it was created is the
pattern already locked three times: F1's approved snapshot, A2's issued offer, A8's
approved minimum.

### DECIDED (A3) — two gates, in a fixed order

Offer approval and Candidate Hiring approval govern **different events and neither
substitutes for the other**:

> Offer approval → offer **issued** → candidate **accepts** → Candidate Hiring approval → workforce conversion

- **Offer approval** gates **issuing** the offer.
- **Candidate Hiring approval** gates **converting an accepted candidate into
  workforce**.
- Where both are configured, **both must pass, in that order** — the order is fixed by
  the recruitment lifecycle and is **not configurable**, because joining follows
  acceptance which follows issue.
- Where only one is configured, only that gate applies.
- Where neither is configured, no approval is required (Q22, B2).

**A refused Candidate Hiring approval blocks the conversion; it does not reject the
candidate** — rejection remains a human act with a mandatory reason.

**The distinction preserved:** the *offer* decision is whether the organisation is
willing to make the offer; the *hiring* decision is whether the accepted person is
actually converted into workforce.

**EVIDENCE.** `offer_issue()` refuses an unapproved offer — *"§32 — an UNAPPROVED
offer can never be issued."* The conversion point is `rcv_convert()`, a discrete named
step carrying **no approval today**, and therefore a clean gate.

**One consequence to hold in view:** a hiring approval placed *after* acceptance can
fail once a person has accepted and possibly resigned elsewhere. That is a legitimate
choice — some organisations need a final establishment or site-clearance check — but an
organisation switching it on creates that exposure deliberately.

## What is NOT decided

**UNDECIDED — DO NOT IMPLEMENT:**

- The new-organisation default under Q22 (**B2**).
- Whether Offer approval and Candidate Hiring approval may both fire for one
  candidate, and in what order (C38).
- Whether `APPR_ENTITIES` becomes configurable or is simply extended (C32).
- Any organisation's actual chains. Chains are configuration, supplied per
  organisation — **no rule may be seeded in any environment.**

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

# Section 10 — Material Change Control (D7 · Q4–Q12)

## The rule

> **Selected material changes to an approved recruitment requirement require
> re-approval. The list of material fields is configurable by organisation.
> Existing controls are retained — new controls are ADDITIONS, NOT
> REPLACEMENTS.**

**Not authorised for implementation now.**

## The add-versus-replace question is closed

The first issue of this specification flagged a risk: if the owner's shorter list
of material fields were read as a *replacement*, nine existing controls would
quietly weaken — someone could change the approved role, the pay band or the
billing client after approval without going back. **D7 closes this: additions,
not replacements.** All twelve existing material fields are retained. **The risk
is closed, and no control is lost.**

## Budget becomes a material control

**DECIDED: budget must be available as a material-change control.**

This closes the live gap recorded in the first issue: today a manager approves a
cost, and the figure can afterwards be changed without re-approval — while that
approved figure is precisely what becomes the requisition's budget baseline.

## The material-change lifecycle — the nine rules

When a material change is proposed:

| # | Rule | Q |
|---|---|---|
| 1 | A **pending proposed version** is created | Q5 |
| 2 | The **original approved version remains effective** | Q5 |
| 3 | A **reason is mandatory** | Q10 |
| 4 | **Supporting documents are optional** | Q11 |
| 5 | The **approval route is configurable** | Q9 |
| 6 | The organisation may use the **original approval chain** OR a **separate material-change chain** | Q9 |
| 7 | **Rejected changes remain in historical records** | Q7 |
| 8 | An **approved change creates a NEW APPROVED VERSION** | Q12 |
| 9 | The **previous approved version remains historically preserved** | Q12 |

**DECIDED (Q6 · A5): the effect of a pending material change on ongoing recruitment
activity is configurable by organisation — as a single graduated level over the
existing execution gate, not as independent switches.**

| Level | Continues | Pauses |
|---|---|---|
| **1 — Pause everything** | nothing | raise requisition · advance · interview · offer · joining — **today's behaviour** |
| **2 — Continue screening, commit to nobody** *(shipped default)* | raise requisition · advance · interview | **offer · joining** |
| **3 — Continue everything except joining** | raise · advance · interview · offer | **joining** |
| **4 — Continue as normal** | everything | nothing |

**Level 2 is the product default, not a mandate** — an organisation may configure any
level, and **level 1 preserves today's exact behaviour** for anyone who wants it.

**Why level 2 by default.** It blocks exactly what a pending change threatens —
commitments made on authority that is in question — while permitting work that costs
nothing and commits no one. The execution gate's own record states the harm it exists
to prevent: *"an OFFER could be created, approved, ISSUED and accepted against a
requisition whose approval had been invalidated — a commitment made to a person for
headcount nobody had approved."*

**Where a level permits work to continue, the pending change must be visible to the
people doing that work** (C40 — this decision makes answering it necessary rather than
optional).

**This reuses the existing gate.** `REXEC_ACTIONS` already defines the four levers —
`ADVANCE` "move this candidate forward", `INTERVIEW` "schedule an interview", `OFFER`
"make an offer", `JOIN` "record a joining" — already enforced at 23 call sites, already
returning a refusal in words a coordinator can act on; raising a requisition is
separately gated in `hreq_to_requisition()`. **No new gate, no new enforcement point,
no new vocabulary** — the gate consults a configured level instead of a fixed block
list.

**DECIDED (Q8): who may propose a material change follows D4** — role defaults +
configurable permission + recruitment scope.

## The material-change approval rule (F1)

This rule was added to close a contradiction found in the Task 3 consistency
review: D7 requires re-approval of material changes, while Q22 says that where no
rule is configured no approval is required. Without this rule an organisation that
configured no material-change rule would silently lose the control altogether.

> **Material-change approval inherits the applicable approval rule of the
> underlying requirement by default. If a separate material-change approval chain
> is configured, that chain takes precedence. The applicable approval chain is
> determined using the values of the proposed changed version, not the values of
> the previously approved version. If neither the underlying requirement nor a
> separate material-change rule requires approval, no approval is required.**

**The precedence, in order:**

| Order | Rule applied |
|---|---|
| **1** | A **separate material-change approval chain**, where the organisation has configured one |
| **2** | Otherwise, the **approval rule applicable to the underlying requirement**, matched on the **values of the proposed changed version** |
| **3** | If neither requires approval, **no approval is required** |

### Why the chain is matched on the NEW values

Because otherwise a threshold could be evaded by raising small and amending large.

Approval bands for a hiring request are matched on **headcount**, with money as a
separate key. So:

- A manager raises a request for **2 people**. The small-request rule sends it to
  the department head, who approves it.
- The manager then materially changes it to **20 people**.
- **Matched on the original values**, it returns to the department head — who alone
  would now be authorising twenty people.
- **Matched on the changed values**, it goes to whoever the 20-person band names.

The second is the decided behaviour. It closes a route around the thresholds, and
it is the same reasoning the existing engine already applies when it treats a
headcount *increase* as material but a decrease as not.

### Why the control cannot now be lost by omission

The important property of this rule is that **the control travels with the
requirement**. If the requirement itself needed approval, changing it needs
approval — automatically, with nothing extra to configure. Q22's principle still
holds, but it only reaches a material change when the requirement itself was never
subject to approval, which is coherent: **authority that was never granted cannot
be overspent.**

### EVIDENCE — the rule matches the engine and the existing code

| Claim | Evidence |
|---|---|
| Rule matching already uses current values | `appr_match()` scores rules against a context supplied at call time (`lib/recruit_approval.php:1012`), so a re-match naturally reflects the changed values. **No new mechanism is needed.** |
| Bands can be headcount- or money-based | `min_amount` / `max_amount` are scored against the context amount (`lib/recruit_approval.php:1083-1085`); for a hiring request `amount` is **quantity**, with money as a separate key (`lib/hiringreq.php:389-406`) |
| "No approval requirement → no re-approval block" already holds | `hreq_is_executable()` returns true immediately when the record does not require approval (`lib/hiringreq.php:443`), commented *"a workspace that does not require approval"* |

**This is a business-rule clarification, not a request for a new approval
mechanism.**

### Consequence for B2

Because material-change approval inherits from the requirement, the decision about
what a **brand-new organisation** starts with (B2) now governs two things at once:
whether hiring requests require approval, **and transitively** whether material
changes do. One decision, two effects.

## What this means in business language

Today, changing an approved request is a single destructive act: the request
changes, and recruitment stops until somebody re-approves it. Under D7 it becomes
a **proposal**, sitting alongside the approved version rather than replacing it.
The organisation keeps working to what was approved while the proposal waits. If
the proposal is accepted it becomes the new approved version and the old one is
kept as history; if it is refused, the refusal is kept as history too. Nothing is
overwritten and nothing is lost.

**Why this matters commercially:** the record of *what was authorised, by whom,
and what was asked for and refused* becomes complete. That is what makes a hiring
decision defensible months later.

## EVIDENCE — the mechanism largely exists, and its design is sound

| Component | What it does | Location |
|---|---|---|
| `HREQ_MATERIAL_FIELDS` | The authoritative material set — **twelve fields**, each with a recorded business reason | `lib/hiringreq.php:81` |
| `hreq_material_diff()` | Returns the material differences; empty means nothing material moved | `lib/hiringreq.php:601` |
| `approved_snapshot_json` | The snapshot of what was actually approved | `lib/hiringreq.php:204` |
| `reapproval_state` | `NONE` / `REQUIRED` / `IN_PROGRESS` / `REJECTED` | `lib/hiringreq.php:203` |
| `HREQ_REAPPROVAL_BLOCKS` | The states in which recruitment must not run | `lib/hiringreq.php:71` |
| Documented matrix | The business document behind it | `docs/phase3/M4-MATERIAL-CHANGE-MATRIX.md` |

**Two design properties that must survive, in the source's own words:**

> *"MATERIALITY IS JUDGED AGAINST THE APPROVED SNAPSHOT, never against the
> previous edit. Ten harmless edits followed by one material one must still"*
> [require re-approval]

> *"'quantity' is deliberately absent: it is asymmetric… an INCREASE spends
> authority nobody granted, a DECREASE stays inside the approval. Treating them
> alike would force a re-approval on a manager asking for fewer people, which
> teaches people to route around the control."*

**Both are correct. Neither may be lost.** Under a version model, "the approved
snapshot" becomes "the current approved version" — the principle is unchanged.

## The three gaps between the decisions and the running system

**These are documented, not fixed.** No production change is authorised.

### Gap 1 — a pending change currently stops recruitment outright

**Today:** when an approved request is materially changed, recruitment is paused.
`hreq_is_executable()` returns false, and the system tells the user in plain
words: *"This approved request has been changed in a way that needs re-approval.
Recruitment is paused until it is re-approved."*

**Q5 and Q6 supersede this.** The approved version must remain effective, and the
effect on activity must be configurable.

**Business implication:** today's hard pause is not wrong — it is **one option**
of what must become a choice. An organisation that would rather keep recruiting on
the approved terms while a change is considered cannot do that today. The existing
`HREQ_REAPPROVAL_BLOCKS` list is the natural place that choice is expressed.

### Gap 2 — only one approved snapshot is kept, not a version history

**Today:** `approved_snapshot_json` holds a **single** snapshot. There is no chain
of approved versions and no record of a rejected proposal.

**Q7 and Q12 require a chain.**

**Reuse finding:** the product already contains this exact pattern for
quotations — a revision table carrying the parent record, a revision number, who
changed it, when, a summary, and the full snapshot. **This is an existing pattern
to extend, not a mechanism to invent.**

### DECIDED (A6) — versioning applies to both records

**One versioning mechanism covers both the Hiring Request and the Requisition**, each
with its **own configurable material-field list**:

| Record | Its material list covers |
|---|---|
| **Hiring Request** | the **authority** it carries — headcount, budget, role, office, engagement type, minimum eligibility |
| **Requisition** | the fields that change **who qualifies or what it costs** — **not** execution wording such as the job description |

Where a requisition has no approving authority behind it (ADR-001, a directly raised
requisition), **its own version chain still applies.**

**Candidate review under A1 is triggered by a new *effective* version of either
record** — whether or not an approval chain was required for it. **Approval** asks
whether an authority decision is required; an **effective version** records that the
requirement governing recruitment has actually changed. **These must not be
conflated.**

**Why both records, not just the request.** The eligibility criteria that decide
"stricter" live on the **requisition** — `qualification`, `experience_min`,
`relevant_experience`, `skills`, `discipline`, `category`, `trade_id`, `skill_id` — and
D2-B **permits the requisition to strengthen** them. Requisitions have **no change
control of any kind** today: `act_log('REQUISITION', …)` appears only for
approval-chain notes and fulfilment allocations, never a field edit. So versioning the
Hiring Request alone would leave a documented path around A1 — raise the requisition's
minimum experience from 5 to 8, and no version, no approval and no candidate review
would occur.

And `hreq_remaining_qty()` confirms **one request can spawn several requisitions**, so
request-level versioning alone cannot express one office tightening while another does
not.

### Gap 3 — materiality is a constant, not configuration

**Today:** `HREQ_MATERIAL_FIELDS` is a PHP constant. The comment above it reads
*"One rule, one place. Nothing else in the product decides materiality."* That is
good design and the discipline must survive.

**Q4 requires it to be configurable per organisation.** The requirement is
therefore *one rule, one place, **per organisation*** — configuration replacing a
constant, not many places deciding materiality.

## The material fields — existing twelve, plus the additions

**Retained (all twelve, per D7):** `hiring_department_id` · `designation` ·
`grade` · `position_id` · `new_position_requested` · `job_title` ·
`employment_type` · `office_id` · `work_location` · `client_id` · `request_type` ·
`requested_by_id` — plus `quantity`, handled asymmetrically in
`hreq_material_diff()` rather than in the list.

**Added by D7:**

| Addition | Status |
|---|---|
| Approved budget / range | **The field exists** (`est_cost_per_person` and the three related estimate fields). Only its materiality is new |
| Minimum qualification | **Cannot be material until D2 creates it** |
| Minimum experience | **Cannot be material until D2 creates it** |
| Essential skills | **Cannot be material until D2 creates it** |

**This is the hard dependency in Section 3: three of the four additions do not yet
exist as fields.**

## DECIDED (C45 · C9) — budget materiality is judged on the total commitment

**The authoritative measure is the total commitment**, as computed by the existing
calculation:

> **per-person cost × quantity × periods + one-time cost**

**Not any individual cost field.** Watching only the per-person figure would miss the
obvious evasion — halve the per-person cost and triple the duration, and the total
rises while the field falls. The approver authorised a *total*.

| Rule | Value |
|---|---|
| An **increase** is material when it exceeds | a configured **percentage** of the approved commitment **or** a configured **absolute amount**, **whichever is greater** |
| Shipped defaults | **10%** and **₹1,00,000**, both configurable per organisation |
| Where **no commitment was approved** | the absolute amount alone applies |
| An **incomplete estimate later completed** | judged the same way — against the commitment **as shown to the approver**. No special exception |
| A **decrease** | **not material** |

**Why a threshold is required, not merely convenient.** The cost fields are
*deliberately indicative*: *"nobody knows the exact salary when raising a request, and
demanding one would stall the flow. An estimate the approver can see beats an exact
number they cannot."* If any change to an estimate forced re-approval, refining
₹8,00,000 to ₹8,10,000 would send the request back — and people would enter round,
useless numbers instead.

**Why *greater of*.** On ₹3,00,000 the floor governs and small drift is not material;
on ₹1,00,00,000 the percentage governs and the threshold scales. *Smaller of* would
make ₹1 lakh of drift material on a crore-scale estimate.

**Why decreases are not material.** Consistent with the quantity rule: an increase
spends authority nobody granted, a decrease stays inside the approval. A fundamental
change of role that accompanies a reduction is caught by `designation`, `grade` and
`position_id`, **already material** — so money need not be made symmetric to
compensate.

**This does not make the commitment determine the approval chain.** The commitment
already reaches the approval engine under its own context key, deliberately apart from
the headcount band — *"routing by value stays an explicit choice rather than something
that happens to a customer overnight."* **Materiality and routing remain separate
controls**, as F1 requires.

## DECIDED (G2) — the proposal lifecycle

> Approved version → Proposal → Pending → **Approved / Refused / Withdrawn**

A **refused** proposal is **closed permanently and remains in history** (Q7). **It
cannot be amended in place.** A further attempt is a **new proposal**, which:

- may be **pre-filled** from the refused one and **carries a reference** to it;
- has its **own** mandatory reason (Q10);
- is evaluated and **routed on its own proposed values** (F1);
- receives its **own** decision and audit history.

**Why not amend in place.** Q7 requires the refused content to survive, so editing the
same proposal would either overwrite it or force a revision history **nested inside**
the requirement's version chain — two levels of versioning for one concept. A new
proposal keeps one chain, one level, and a complete record. It also keeps a refusal
truthful: a named person refused **specific values**, and those values must not change
under the decision afterwards.

**At most one proposal may be pending against a record at any time.** A second cannot
be raised until the pending one is approved, refused or withdrawn — consistent with the
single-valued re-approval state the record already carries, and required for **A5** to
resolve one execution level.

**A proposer may withdraw their own pending proposal.** Withdrawal **closes** the
proposal, **requires a reason**, **remains permanently in history**, **does not alter
the approved version**, and **restores normal execution** under A5.

**No historical proposal is ever overwritten.**

## DECIDED (A8) — a Requisition that would weaken the approved requirement

A requisition value **below the approved minimum** on its Hiring Request is a
**weakening**, and is **routed through this material-change process** as a proposed
Requisition version, with a mandatory reason and the approval route F1 selects. It is
**neither silently permitted nor blocked outright**.

- Weakening is judged **against the approved minimum, never against the Requisition's
  previous value** — so correcting an over-specified requisition back toward the
  approved floor is **not** a weakening and proceeds freely.
- The exception is recorded **on the Requisition**, not by re-versioning the Hiring
  Request, so it **does not relax the standard** for other requisitions raised from the
  same request.
- Where no approval is required (F1, Q22, B2), the weakening **still requires a reason
  and still creates a version** — the control **degrades to auditability, not to
  nothing**.
- A **relaxation** triggers neither candidate review (A1) nor reconsideration of
  previously rejected candidates (F2).

**EVIDENCE.** Only **quantity** is enforced between the two records today:
`hreq_approved_qty()` reads the **approved snapshot's** quantity and
`hreq_remaining_qty()` stops requisitions exceeding approved headcount. No eligibility
field is checked at all. A8 extends that existing compare-against-what-was-approved
pattern from one field to the eligibility minimums.

## UNDECIDED — DO NOT IMPLEMENT
- Whether versioning applies to the **Hiring Request only or the Requisition too**
  (B3). Q24 making requisitions approvable suggests both; D7 does not say.
- Whether the same material matrix applies to requisition changes (C11).
- Whether a pending change is **visible** to recruiters, and how (C40).
- Whether **rejected** proposed versions are visible to all authorised users or
  only the proposer (C41).
- Whether supporting documents reuse the **existing document DMS** (C42).

---

# Section 19 — Candidate Version Behaviour and Review Required (D7-CANDIDATE · Q13–Q20)

> **Placed here, immediately after Section 10, because it continues the
> material-change story. Numbered 19 because section numbers in this document are
> never reused or renumbered once published — every cross-reference already
> written stays valid.**

## Why this section exists

A material change can alter *who is eligible*. If the approved requirement moves
from five years' experience to eight, the candidates already in the pipeline were
screened against a requirement that no longer applies. The business question is
what happens to those people — and the answer is deliberately humane: **nobody is
thrown out by a machine.**

## The rules

| # | Rule | Q |
|---|---|---|
| 1 | When a new approved version is created, **existing candidates attached to that requirement move to the latest approved version** | Q13 |
| 2 | If a candidate no longer appears to satisfy the latest requirement, **DO NOT automatically reject** | Q14 |
| 3 | Instead the candidate is flagged **REVIEW REQUIRED** | Q14 |
| 4 | **Previous assessments and interviews remain valid** and are not automatically invalidated | Q15 |
| 5 | The candidate **must be reviewed before progressing** | Q16 |
| 6 | Reviewer permission follows **role defaults + configurable permission + recruitment scope** | Q17 |
| 7 | Clearing the review requires a **mandatory reason** | Q18 |
| 8 | The outcome may be **CONTINUE** or **REJECT** — both require a reason | Q19 |
| 9 | If REJECT is chosen, the candidate is **immediately rejected**; no additional approval is required | Q20 |
| 10 | A **relaxed** requirement does **not** automatically reconsider a previously rejected candidate | F2 |

## When a requirement is RELAXED (F2)

Rules 1–9 cover a requirement becoming **stricter**. A requirement can also become
**more relaxed** — version 1 asks for eight years, version 2 for five — and a
candidate rejected under version 1 would now qualify.

**DECIDED:**

> A previously rejected candidate is **not** automatically reconsidered when a
> requirement is relaxed. The rejection stands as a historical decision, correctly
> made against the version in force at the time. However, previously rejected
> candidates remain **findable**, so a recruiter may deliberately reconsider any of
> them. Reconsideration is always a human action initiated by an authorised person;
> **the system does not automatically generate a reconsideration queue.** REVIEW
> REQUIRED remains reserved for active candidates whose progression must be gated.

### The separation this preserves

| Situation | Candidate state | What happens |
|---|---|---|
| Requirement becomes **stricter** | Candidate is **active** | REVIEW REQUIRED acts as a **progression brake** — a human looks before the candidate moves |
| Requirement becomes **more relaxed** | Candidate was **already rejected** | The rejection remains historical. An authorised user may deliberately reconsider |

**No automatic reopening. No automatic rejection reversal. No new
rejection-reason data requirement.**

### Why the two cases are not mirror images

They look symmetrical and are not:

- When a requirement **tightens**, the candidate is live in the pipeline, and the
  flag **stops something that was about to happen**. It is a brake on an action.
- When a requirement **relaxes**, the candidate is already closed. There is nothing
  to brake, so a flag would not protect a decision — it would **manufacture work on
  records nobody was touching**, in proportion to total rejections, which in any
  funnel is the largest population.

Using one state for both would also conflate two different messages — *"stop, check
before you proceed"* and *"you may wish to look at this again"* — and giving them
one name would fail the Zero Training UI gate.

### EVIDENCE — why automatic reconsideration is not possible anyway

**A candidate rejection carries no reason.** `CAND_STAGES` holds a single flat
value, `'REJECTED' => 'Rejected'` (`lib/ops.php:78`). The reason columns that exist
in the product (`cancel_reason`, `closure_reason`, `drop_reason`) belong to
requisitions and jobs, **not** to a candidate rejection.

So the system knows *that* a candidate was rejected; it does not know *why*. Any
rule of the form "reconsider those rejected **because of** the relaxed criterion"
would require a new structured rejection-reason field captured at every rejection.
**This decision deliberately avoids creating that requirement.**

It also means automatic reinstatement would be unsafe: it would reopen candidates
rejected for reasons unrelated to the criterion — a failed reference, availability,
interview performance — quietly undoing sound decisions.

### What "findable" needs — REUSE, not BUILD

The candidate register already filters by stage (audit S2) and candidates already
attach to requisitions. *"Show me the people we turned down for this requirement"*
is close to existing capability.

**UNDECIDED — DO NOT IMPLEMENT:** whether findability is surfaced as a filter on
the existing register, a panel on the requirement, or both; and whether a
deliberate reconsideration re-enters the pipeline at its original stage or at the
start.

## What this means in business language

The requirement changed; the people did not. So the system raises its hand rather
than swinging an axe: *"this person was shortlisted against the old requirement —
someone should look."* A human looks, says why, and either carries on with them or
lets them go. Their interviews and scores are not thrown away, because the work
was done honestly against what was asked at the time.

**Two consequences worth naming:**

- **A flag is not a rejection.** A flagged candidate is still a live candidate;
  they simply cannot advance until someone has looked.
- **A rejection here is final and immediate.** It needs no approval, because
  rejecting a candidate has never needed one. The reason is the control.

## REVIEW REQUIRED — the state's properties

| Property | Rule |
|---|---|
| What raises it | A new approved version whose eligibility the candidate may not meet (Q14) |
| What it does | Blocks progression only (Q16) — it does not reject, hide, or unassign |
| What it does not touch | Prior assessments, interview records and scorecards (Q15) |
| Who may clear it | Role defaults + configurable permission + recruitment scope (Q17) |
| What clearing requires | A mandatory reason, always (Q18) |
| Outcomes | CONTINUE or REJECT, each with a reason (Q19) |
| On REJECT | Immediate rejection, no further approval (Q20) |

## EVIDENCE — what exists and what does not

| Needed | Exists today? |
|---|---|
| Candidate linked to a requirement | **Yes** — candidates attach to a requisition |
| Candidate linked to a *version* of that requirement | **No** — there are no versions (Section 10, Gap 2) |
| A review-flag concept on a candidate | **No** — nothing comparable exists |
| Progression control to gate on | **Yes** — pipeline movement is a single controlled path (D1) |
| Interviews and scorecards as separate records | **Yes** — `lib/recruit_iv.php`, so Q15 is satisfied by the existing structure |
| Candidate rejection without approval | **Yes** — rejection has never required approval, so Q20 matches today |
| A reason captured against an action | **Partly** — `act_log()` records actions; a mandatory reason field on this flow is new |

**Q15 and Q20 are already true of the system.** Q13, Q14, Q16, Q17, Q18 and Q19
are new behaviour built on existing structures.

## Dependencies

- **Q14 depends on D2.** Eligibility cannot be compared against minimum
  qualification, minimum experience or essential skills until those fields exist.
- **Q13 depends on Section 10's version chain.** A candidate cannot move to the
  latest version until versions exist.
- **Q16 depends on D1.** Gating progression requires the authoritative lifecycle
  to be the single path a candidate moves along.
- **Q17 depends on D4.** The reviewer right lives in the role profile.
- **Section 19 feeds D9.** A review that blocks a candidate must be clearable on a
  phone, because the reviewer is often not at a desk.

## BLOCKING — DO NOT IMPLEMENT UNTIL ANSWERED

**B1 — who judges that a candidate "no longer appears to satisfy" the latest
version?** Two readings, with very different consequences:

- **Automatic** — the system compares the candidate's qualification, experience
  and skills against the new version and flags mismatches. Consistent, but
  requires D2's three fields first, and will flag people a recruiter would not
  have.
- **Human** — the system flags *every* candidate on the requirement when a new
  version is approved, and a person decides. Simpler and safer, but creates review
  work on every change.

**This specification does not choose.** It is the trigger for the entire flow, and
it is the owner's decision.

**B5 — do candidates already at offer, accepted or hired stage move to a new
version?** Q13 says existing candidates move; Q16 says review must clear before
progressing — but a candidate who has already joined has nowhere left to progress.

## UNDECIDED — DO NOT IMPLEMENT

- On REJECT, whether the candidate returns to the available company-wide pool or
  stays closed (C36).
- Whether a REJECT writes a terminal pipeline stage, and which one (C43).
- Whether REVIEW REQUIRED is visible to everyone who can see the candidate, or
  only to those who can clear it.
- Whether a single review clears a candidate for one requirement or for all
  requirements they are attached to.

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

## DECIDED (C21) — `/recruitment` is the one primary Recruitment home

**`/recruitment` is the one primary Recruitment home.** It already serves as a direct
entry point for recruitment-only organisations, and it already holds recruitment
execution, actions, functions and worklists. **Funnel and KPI summaries from the
existing Command Centre are surfaced within it**, while the **full analytics board
remains available** as a named destination from the home.

**Nothing is deleted or rebuilt.** The change follows **REUSE → EXTEND → CONNECT**: the
existing home is reused, extended with a funnel and KPI summary, and connected through
to the board for depth. `/recruitment-cc` and the existing analytics engine are
**retained**, not replaced.

**Naming forms part of this decision.** Only one destination is presented as the
Recruitment home; the analytics board is **renamed for its actual purpose through the
existing terminology engine**, so the two no longer read as competing homes.

**Role Workspaces remain compatible** as per-role landing and curation **within** the
unified Recruitment experience — they configure what a role sees on arriving, while
this decision fixes that there is one recruitment destination.

### Why `/recruitment` and not the board

| | `/recruitment` | `/recruitment-cc` |
|---|---|---|
| Already an entry point? | **Yes** — `recruit_home_can()` records that *"index.php can hand a recruitment-only company straight to the command centre BEFORE ops_dispatch()"* | No |
| What it is | **Where people work** — worklists, actions, the workforce conversion | **A board you look at** — funnel, sources, ageing, drop reasons, SLA, KPIs |

Three reasons: making the board the home would mean changing working routing to satisfy
a presentation decision; a KPI board is not where work happens; and **Q25 puts
operational work on a phone and administration on a desk** — a funnel board is
desk-shaped analysis, so leading with it would put the wrong screen first for the
phone-first users D9 serves.

**CLARIFICATION (C23) — confirmed.** The reading adopted is: the application's general
Home remains the landing page, and within recruitment there is exactly one recruitment
home, one click away.

## UNDECIDED — DO NOT IMPLEMENT

How the analytics board is reached and named in navigation; how much funnel and KPI
detail is summarised on the home versus left to the board.

---

# Section 12 — Mobile (D9 · Q25)

## The decision

> ## MOBILE OPERATIONAL + DESKTOP ADMINISTRATIVE

**D9 is now a business decision as well as a verification requirement.** The
earlier reading — that D9 was *only* a verification item — is superseded.

## What mobile must support

Mobile must support the important **operational** recruitment activities:

| Operational activity on mobile | Why it belongs on a phone |
|---|---|
| Candidate review | Reviewing a person is reading and judging — it does not need a desk |
| Approvals | An approver is the person most often away from their laptop |
| Shortlisting | A quick yes/no/maybe on a candidate |
| Interview actions | Scheduling, recording an outcome, submitting a scorecard |
| Candidate updates | Adding what was learnt from a conversation |
| Pipeline movement | Moving a candidate to the next stage |
| Hiring decisions | The decision itself, where the user holds the right |
| Review Required clearance (Section 19) — **INFERENCE, not in the owner's list** | A review that blocks a candidate must be clearable wherever the reviewer is. Proposed on the strength of Section 19; **the owner has not adopted it as an eighth item.** B4 has since made the reviewer right real — held by Hiring Manager and Department Head, not Recruiter — which strengthens the case without adopting it |

## What may remain desktop

Complex administration and configuration may remain desktop-oriented: pipeline
and stage design, approval-rule configuration, material-field configuration,
role profiles and permissions, terminology and lookup management, org-chart and
position administration, document-template design, and bulk or import work.

**The dividing principle, in one line:** *doing the recruitment is operational and
belongs on a phone; setting up how recruitment works is administrative and may
stay on a laptop.*

## This is consistent with the project's existing standard

The repository standard already states that inspectors are phone-first in the
field while coordinators, managers and finance are desk-first, and that the two
must never be averaged into one middle. **D9 applies that same standard inside
recruitment:** a department head approving a request and a recruiter reviewing a
candidate are operational, phone-first users; an administrator configuring the
approval matrix is a desk user. Recruitment therefore spans both, and must be
designed for both.

## Verification is still required — nothing here may be assumed

**Recruitment must not be claimed mobile-ready.** The decision states what mobile
*should* support; it does not establish what it currently *does* support.

### EVIDENCE — why no claim can be made

| Fact | Source |
|---|---|
| Test files referencing mobile widths: **2** | audit §27 |
| Browser-driven tests: **0** — no test file references chromium or playwright | audit §27 |
| The audit did not drive a browser, so **no claim is made** about rendering at 360 / 390 / 412 px | audit §27 |

The audit deliberately did not guess. **Neither does this specification.**

### The journey that must be verified

```
Hiring Request → Approval → Requisition → Candidate → Pipeline
    → Interview → Offer → Hiring → Workforce
```

Relevant **recruiter** and **manager** journeys must both be tested — they are
different people doing different work on different devices. Verification must now
also cover the operational list above, plus the material-change and Review
Required flows introduced by D7 and Section 19.

### Verification constraints

- Any defects discovered are **documented separately**, not fixed inside a
  specification or verification exercise.
- **No mobile changes are authorised during this specification exercise.**

## Dependency

Mobile approvals and mobile candidate review both rely on the access model, so
**D9 depends on D3, D4 and Section 19** being settled. A phone screen cannot show
an approval the permission model has not yet defined.

## UNDECIDED — DO NOT IMPLEMENT

Where exactly the line falls between a mobile "hiring decision" and desktop
"complex administration" for borderline cases — for example an approval that
carries conditions, or a review that requires reading attached documents.

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
| **Version chain for an approved requirement (D7, Q5, Q7, Q12)** | `approved_snapshot_json` holds **one** snapshot only; the product's quotation revision table carries record id, revision number, author, date, summary and full snapshot | **EXTEND an existing pattern.** The shape needed already exists elsewhere in the product. |
| **Material-change approval route (D7, Q9, F1)** | `lib/recruit_approval.php` — many rules per entity; `appr_match()` scores on current values | **REUSE.** Inheritance and new-value matching are the engine's existing behaviour. |
| **REVIEW REQUIRED on a candidate (D7-CAND, Q14, Q16)** | **Nothing comparable exists.** Pipeline movement is a single controlled path to gate on (D1); interviews and scorecards are already separate records, so Q15 holds | **EXTEND.** A new review state on an existing controlled path — not a new engine. |
| **Candidate Hiring approval event (D5, Q1)** | Rule table's `entity` is free text; `APPR_ENTITIES` is the constant that gates accepted values | **EXTEND.** A fifth value on an existing engine. |
| **Findability of previously rejected candidates (F2)** | Candidate register already filters by stage (audit S2); candidates already attach to requisitions | **REUSE.** No new data; no rejection-reason field required. |

## The highest-coupling component

`lib/ops.php` (L20) is the dispatcher and also holds the candidate and requisition
handlers **and** `CAND_STAGES` — the audit marks it *"Shared — highest coupling."*
**D1 and D3/D4 both land here.** Any plan touching it must account for that
coupling explicitly; it is the single riskiest file in the module.

## Three further reuse findings from the D7 / Q1–Q25 decisions

**1. A versioning pattern already exists in the product.** The new material-change
model (Q5, Q7, Q12) needs a chain of approved versions, each with its author,
date, reason and a full snapshot. The product already has exactly that shape for
quotations: a revision table carrying the record id, a revision number, who
changed it, when, a summary, and the complete snapshot. **This is a pattern to
extend, not a mechanism to invent** — and extending a proven one is cheaper and
safer than designing a new one.

**2. "Configurable per organisation" costs nothing structurally.** Ten of the
decisions (D3, D5, D7, Q1, Q2, Q4, Q6, Q9, Q21, Q24) require configuration *per
organisation*. Because EXAACT gives every customer its own separate database and
carries no tenant column anywhere, each organisation already has its own
configuration tables. **No multi-company plumbing is needed for any of them.**

**3. The approval rule table already carries D5's whole configuration list.**
Verified column by column:

| D5 requires | Existing column |
|---|---|
| Whether approval is required | absence of a matching rule (Q22), plus the per-record `approval_required` control |
| Who approves | `approver_role`, `approver_user_id` on each level |
| Approval sequence | `seq` on each level |
| Conditions | `applies_department`, `applies_sbu`, `applies_grade`, `applies_position` |
| Thresholds | `min_amount`, `max_amount` |
| Multiple approvers | multiple levels, and multiple rows per level |
| Escalation | `escalate_role`, `escalate_user_id`, `sla_days`, `reminder_days` |
| Which business event | `entity` — a text column, so a fifth event is data, not schema |

**Only two gaps remain:** `APPR_ENTITIES` is a constant that gates which entity
values are accepted, and `REQUISITION` / `SALARY` are not wired to fire. Neither
is a new engine.

## Summary of verdicts

| Verdict | Count | Meaning |
|---|---|---|
| REUSE, unchanged | 22 | Exists and serves the requirement as-is — now including the material-change approval route (F1) and rejected-candidate findability (F2) |
| REUSE + adjust or reconcile | 2 | The mechanism exists; a list or a predicate needs settling — the material-change field list (D7), and `is_coordinator_level()` vs `mod.hiring.*` (D3/D4) |
| EXTEND | 6 | Exists; needs widening — hiring-request fields (D2), inheritance (Section 6), offer audit (D6), the version chain (D7), REVIEW REQUIRED (Section 19), the Candidate Hiring event (Q1) |
| CONNECT / MAP | 1 | Two landings to be related (D8) |
| **BUILD NEW** | **0** | **Nothing in this specification requires a new engine.** |
| **Total** | **31** | |

**The zero has survived every addition.** Three issues, thirty-five decisions and
two rules locked in consistency review later, the material-change versioning model,
the candidate review flow, a fifth approval event and rejected-candidate
findability all map onto mechanisms that already exist. **Five of the thirty-one
need widening; none needs inventing.**

**That last row is the specification's central architectural claim.** Nine
business decisions, and not one of them requires a new engine.

---

# Section 15 — Open Implementation Clarifications

Everything still requiring a business or technical decision. **Nothing in this
list may be decided in code.**

## A. Resolved by the D1–D9 / Q1–Q25 decisions — no longer open

These were recorded as open in the first issue of this specification. The
decisions have closed them, and they are listed here so nobody reopens them.

| Was | Now closed by | Resolution |
|---|---|---|
| **C1** — what "Candidate Hiring" means as an approval event | **Q1** | Configurable per organisation: none, its own approval, Offer approval serving as it, both, or different sequences |
| **C2** — does the material list add to or replace the existing twelve? | **D7, Q4** | **Additions, not replacements.** Existing controls are retained |
| **C5** — where does "active recruitment process" begin? | **D3, Q2** | Configurable by organisation / pipeline; no separate hard-coded active-status engine |
| **C6** — is Requisition approval off or unstated? | **D5, Q24** | Approval-capable and configurable per organisation. The earlier inferred "No" was wrong |
| **C10** — should materiality become configurable per organisation? | **Q4** | Yes |
| **C4** (partly) — EXAACT's own approval chains | **Q22** | No longer blocking: absence of a rule means no approval. Chains remain configuration, supplied per organisation |
| **C3** (partly) — the role × action matrix | **Q23** | Delivered as a predefined role profile plus administrator override, not as a fixed matrix |
| **C8** (partly) — is budget a material control? | **D7** | Yes, budget must be available as a material-change control. The threshold question remains open as **C45** |
| **F1** — does Q22 disable the material-change control when no rule is configured? | **Owner decision, Task 3 review** | No. Material-change approval **inherits** the underlying requirement's rule; a separate chain takes precedence; matched on the **proposed changed values**; no approval only where neither requires it. Section 10 |
| **F2** — are previously rejected candidates reconsidered when a requirement is relaxed? | **Owner decision, Task 3 review** | No automatic reconsideration. The rejection stands; the candidate remains **findable** for deliberate human reconsideration. Section 19 |
| **F3** — D1's single-source rule vs Q21's configurable "Hired" | **Owner confirmation** | The pipeline remains the source of lifecycle *position*; offer and workforce records remain the source of those *events*; terminal stage kinds map to the configured Hired point. Section 2 (D1) |

## B. Blocking — an implementation plan cannot be written without these

| # | Item | Decision | Why it blocks |
|---|---|---|---|
| **B1** | **Who judges that a candidate "no longer appears to satisfy" the latest approved version** — an automatic comparison of eligibility fields, or human judgement? | Section 19, Q14 | It is the trigger for the entire review flow. An automatic check additionally cannot exist until D2 adds the three missing fields |
| **B2** | **What a brand-new organisation starts with** under Q22 — approval on or off by default | D5, Q22, **F1** | Implemented literally, EXAACT today (0 rules) would move every hiring request to self-approving. **Since F1 makes material-change approval inherit from the requirement, this one decision now also governs whether material changes need approval** — one decision, two effects. See Section 8 |
| **B3** | **Does versioning apply to the Hiring Request only, or to the Requisition as well?** | D7, Q24 | Determines the scope of the whole versioning model |
| **B4** | The **contents of each predefined role profile** — what a Recruiter, Hiring Manager and Department Head may do by default | D4, Q23 | Defaults ship with the product; they cannot be guessed |
| **B5** | **Do candidates already at offer, accepted or hired stage move to a new approved version?** | Q13, Q16 | Q16 requires review before progressing, but a hired person has nowhere to progress |

## C. Required before the relevant decision is built

| # | Item | Decision |
|---|---|---|
| C7 | Enforcement posture when a requisition would weaken the approved requirement: block, warn, or route to material change | D2, D7 |
| C45 | Whether a **threshold** applies to budget changes, and whether any increase is material or only one beyond a band (the unresolved remainder of C8) | D7 |
| C9 | Whether budget **decreases** are material (the quantity precedent suggests not — an inference, not a decision) | D7 |
| C11 | Whether the same material matrix applies to requisition changes as to hiring-request changes | D7, B3 |
| C12 | Mapping the ten offer **business events** onto existing offer states | D6 |
| C13 | Which audit mechanism carries offer events — `act_log`, the stage ledger, or both | D6 |
| C14 | Whether downstream consumers read **current stage** or **stage history** | D1 |
| C15 | The default **pipeline** the 933 legacy candidates map onto | D1 |
| C16 | The legacy-stage → pipeline-stage **mapping table** | D1 |
| C17 | Whether migration is **one-off** or **lazy on next touch** | D1 |
| C18 | Whether **terminal** legacy candidates are migrated at all | D1 |
| C19 | Reconciling **`is_coordinator_level()`** on candidate screens against `mod.hiring.*` | D4 |
| C20 | Whether candidates acquire an **office dimension**, which "recruitment scope" may require | D3, D4 |
| C21 | Which of the two **landings** becomes the one home, and what happens to the other | D8 |
| C22 | Whether **role workspaces** are the mechanism for per-role landing within the one home | D8 |
| C23 | Confirmation of the **D8 reading** adopted in Section 11 | D8 |
| C24 | Whether "Hiring Managers" / "Department Heads" are the same roles as the Pack's "Recruitment manager" / "Department manager" | D4 |
| C25 | The **candidate state machine** and its transition triggers, now that the active boundary is configurable | D3, Q2 |
| C26 | Which business rules permit a candidate to **return to the available pool** | D3 |
| **C36** | On **REJECT** from a Review Required, does the candidate return to the available company-wide pool or stay closed? | Section 19, Q19, Q20, D3 |
| **C37** | Where "Hired" = **Offer Accepted**, what is the candidate's visibility between accepting and joining? | Q21, D3 |
| **C38** | Can **Offer approval and Candidate Hiring approval both fire** for one candidate, and in what order? | Q1 |
| **C39** | Does "**administrator override**" apply per user, per role, or both? | Q23 |
| **C40** | Whether a **pending** material change is visible to recruiters, and how it is presented | D7, Q6 |
| **C41** | Whether **rejected** proposed versions are visible to the proposer only, or to all authorised users | Q7 |
| **C42** | Whether **supporting documents** on a material change reuse the existing document DMS | Q11 |
| **C43** | Whether a **REJECT** from review writes a terminal pipeline stage, and which one | Section 19, D1 |
| **C44** | Where the **mobile / desktop line** falls for borderline cases (conditional approvals, document-heavy reviews) | D9, Q25 |

## D. Not put to the owner as decisions at all

| # | Item | Note |
|---|---|---|
| C27 | **Which industries come first** | The Decision Pack asked. Unanswered. Nothing may be prioritised in code. |
| C28 | `WF_TEAM_ROLES` configurability (FIELD / COORD / OFFICE) | The **second** of the two gaps recurring across industries. Never put to the owner. |
| C29 | Healthcare **licence / registration validity** | A legal gate that does not exist as a concept. |
| C30 | IT services **notice period** handling | Does not exist. |
| C31 | Recruitment agency **client-submission states** | Does not exist as a pipeline concept. |
| C32 | Whether `APPR_ENTITIES` becomes configurable | **Now effectively required** by D5 and Q1 — the constant is the gate on a fifth event. Still never put to the owner as a decision. |
| C33 | **Recruitment model** as a configuration dimension (internal / client-facing / both) | Proposed in Section 5; no existing mechanism identified. |
| C34 | Rate-limiting and abuse controls on the public `/careers` intake | Audit S5 — explicitly not examined in that pass. |
| C35 | Whether the absence of offer auditing was originally intentional | D6 answers the requirement; the history is unestablished. |

## E. Recorded disagreements between sources

| # | Disagreement | Resolution |
|---|---|---|
| G1 | Decision numbering: owner's D1–D9 vs Decision Pack 1–9 differ in five places | Owner's numbering is authoritative. Mapping table at the top. |
| G2 | D7 broadened Pack Decision 6 from *budget* to a *multi-field material matrix*, and now further to a versioning model | Business decision wins; broadening recorded. |
| G3 | D3 is Model A **plus a condition**, the condition now configurable | Business decision wins; refinement recorded. |
| G4 | D8's letter and the owner's prose admit two readings | Section 11 states the reading adopted; C23 asks for confirmation. |
| G5 | Audit row counts: estimates gave pipelines 3 / stages 32; exact counts are **4** and **33** | Corrected. The load-bearing figures (935, 2, 0 rules) stand. |
| G6 | Audit §8 open question — was the missing offer audit intentional? | **D6 supersedes it.** It is a gap and must be closed. |
| **G7** | The first issue of this specification recorded **Requisition approval as not required** | **Reversed by D5 and Q24.** It was an inference from an omission, and it was wrong. |
| **G8** | The first issue recorded **D9 as not a business decision** | **Reversed by D9 and Q25.** It is now both a decision and a verification requirement. |
| **G9** | Live code **pauses recruitment outright** on a pending re-approval | **Q5 and Q6 supersede it.** Today's behaviour becomes one configurable option among several. Documented in Section 10, not changed. |
| **G10** | Live code keeps **one** approved snapshot, not a version chain | **Q7 and Q12 supersede it.** A version history is required. Documented in Section 10, not changed. |
| **G11** | Live code requires a **manual approval** when no rule matches and `approval_required` is 1 | **Q22 supersedes it.** Documented in Section 8 with its business consequence, not changed. |

---
---

# Section 16 — Explicit Non-Goals

## What this document does NOT authorise

**This document authorises no change to any running system.** Specifically, it does
not authorise:

**Implementation of any decision**
- Implementing D1 · migrating candidates · activating pipeline rules
- **Building the material-change versioning model** (Section 10), or any part of it
- **Building the Review Required flow** (Section 19), or any part of it
- **Implementing the material-change approval inheritance or new-value matching**
  (F1) — the rule is decided; the wiring is not authorised
- **Building findability of previously rejected candidates** (F2)
- **Making the material-field list, the active-process definition or the "Hired"
  definition configurable**
- **Adding a Candidate Hiring approval event, or wiring Requisition or Salary
  approval**
- **Changing the default that decides whether approval is required**
- **Creating role profiles, or altering permission defaults**
- **Changing any mobile behaviour**
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
reviewed and approved, and after the blocking clarifications in Section 15(B) are
answered.

---

# Section 17 — Traceability Table

The **Existing Mechanism** column is populated only from evidence in the
repository or the audit. Where no mechanism exists, the cell says so.

## Part 1 — the primary decisions

| Decision | Business Requirement | Existing Mechanism | Future Change Needed | Status |
|---|---|---|---|---|
| **D1** | The configurable pipeline is the authoritative candidate lifecycle | `lib/recruitpipe.php` (L04) — built, tested, admin-configurable; stage ledger `candidate_events` via `rkpi_stage_log()`; 4 pipelines / 33 stages; **2 of 935 candidates adopted** | Reconciliation and migration later; consumers moved off legacy `stage`; partial sync retired | **DECIDED** |
| **D2** | Core requirement approved at Hiring Request; execution detail at Requisition; inherit, never weaken | `lib/hiringreq.php` holds 32 of the core fields incl. 4 budget estimate fields; `requisitions` holds the person-spec fields (`lib/recruit.php:57-58`); inheritance carries 17 fields (`lib/hiringreq.php:1242-1265`) | Add **three** concepts (qualification, experience, essential skills); widen inheritance to the latest approved version; define weaken-enforcement posture | **DECIDED** |
| **D3** | Available pool company-wide; active candidates gated; **active definition configurable per organisation / pipeline** | Candidate register unscoped `$where='1=1'` (`lib/ops.php:6503`); **no office column on `candidates`**; office scope engine works for requisitions (`lib/ops.php:5840`); **no active-status concept exists** | Derive active state from pipeline stage kinds (Q2 — no separate engine); decide whether candidates gain an office dimension | **DECIDED / DETAIL PENDING** |
| **D4** | Role defaults + configurable permissions + recruitment scope | `mod.hiring.view`, `mod.hiring.edit`, `hiring.admin` (`lib/access.php:156,198`); candidate screens gated by `is_coordinator_level()` (`lib/ops.php:6867`); Configurable Role Workspaces exist | Define role profiles with administrator override (Q23); reconcile the coordinator predicate; define recruitment scope | **DECIDED / DETAIL PENDING** |
| **D5** | Configurable approval for **five** events; no rule means no approval | `lib/recruit_approval.php` (L05) carries approvers, sequence, conditions, thresholds, SLA, reminders, escalation, delegation. `HIRING_REQUEST` and `OFFER` fire; **`REQUISITION` and `SALARY` never fire**; `APPR_ENTITIES` is a constant; **0 rules configured** | Add a Candidate Hiring event; **wire Requisition and Salary**; decide the new-organisation default under Q22 | **DECIDED / DETAIL PENDING** |
| **D6** | Significant offer lifecycle events permanently auditable; never rewritten | Two audit mechanisms exist: `act_log()` and `candidate_events`/`rkpi_stage_log()`. **`act_log` count in `lib/recruit_offer.php` = 0** — offers appear in neither | Map the 10 business events; carry them on one existing mechanism. **No third mechanism** | **DECIDED / DETAIL PENDING** |
| **D7** | Material change creates a pending proposed version; approved version stays effective; configurable field list and approval route; full history | `HREQ_MATERIAL_FIELDS` (12 fields), `hreq_material_diff()` against `approved_snapshot_json`, `reapproval_state`, `HREQ_REAPPROVAL_BLOCKS`; documented at `docs/phase3/M4-MATERIAL-CHANGE-MATRIX.md`; **quotation revision table is an existing version-chain pattern** | Make the field list configurable; add budget; add a version chain; make the effect on recruitment configurable; add the separate material-change chain option | **DECIDED / DETAIL PENDING** |
| **D7-CAND** | Candidates move to the latest approved version; eligibility risk is flagged for review, never auto-rejected. **F2:** a relaxed requirement does not auto-reconsider a rejected candidate — the rejection stands and the candidate stays findable | Stage ledger exists; **no review-flag concept, no version link on a candidate**; candidate rejection carries **no reason** (`CAND_STAGES`, `lib/ops.php:78`), which is why automatic reconsideration is neither safe nor possible; register already filters by stage (audit S2) | New review state and its clearance flow (Section 19); findability by REUSE | **DECIDED / DETAIL PENDING** |
| **D8** | One primary Recruitment home; existing functionality reused | `lib/recruit_cc.php` (L09) and `lib/recruit.php` (L01) are **both landings** — audit §16 "possible duplicate"; Role Workspaces exist | UX consolidation later via REUSE → EXTEND → CONNECT → MAP → MIGRATE → DEPRECATE → BUILD. **Nothing deleted** | **DECIDED / DETAIL PENDING** |
| **D9** | Mobile operational, desktop administrative; behaviour still to be verified | Responsive UI and the UI/UX blueprint exist; **2 test files reference mobile widths; 0 browser-driven tests** | Confirm the operational set works on a phone; browser verification of the full journey | **DECIDED / VERIFICATION** |

## Part 2 — the supporting decisions Q1–Q25

| Q | Refines | Requirement | Existing Mechanism | Change Needed | Status |
|---|---|---|---|---|---|
| **Q1** | D5 | Candidate Hiring approval configurable — none, own, via Offer, both, or different sequences | Rule table's `entity` column accepts any value; `APPR_ENTITIES` constant gates it | A fifth event value; sequencing rules where both fire | **DECIDED** |
| **Q2** | D3 | "Active recruitment process" configurable per organisation / pipeline; **no separate hard-coded active-status engine** | Pipeline stage *kinds* already classify stages | Derive active state from stage kinds | **DECIDED** |
| **Q3** | D4 | Candidate access follows the hybrid philosophy | As D4 | As D4 | **DECIDED** |
| **Q4** | D7 | Material-field list configurable per organisation; existing controls retained | `HREQ_MATERIAL_FIELDS` — a constant, one rule in one place | Move from constant to per-organisation configuration, keeping all 12 | **DECIDED** |
| **Q5** | D7 | Change saved as pending; approved version stays effective | `reapproval_state` exists but **pauses recruitment** | Pending version that does not, by itself, stop work | **DECIDED** |
| **Q6** | D7 | Effect of a pending change on activity is configurable | Today: a hard pause (`HREQ_REAPPROVAL_BLOCKS`) | Today's behaviour becomes one option among several | **DECIDED** |
| **Q7** | D7 | Rejected changes remain in history | **Nothing** — one snapshot only | A version chain retaining rejected proposals | **DECIDED** |
| **Q8** | D7, D4 | Who may propose follows role defaults + permission + scope | `hreq_can_create()`, scope helpers | Extend to a propose-change right | **DECIDED** |
| **Q9** | D7, D5 | Original chain **or** a separate material-change chain, organisation's choice. **F1:** inherits the requirement's rule by default; separate chain takes precedence; matched on the **proposed changed values** | Rule table supports many rules per entity; `appr_match()` already scores on current values (`lib/recruit_approval.php:1012`); `hreq_is_executable()` already exempts a record that needs no approval (`lib/hiringreq.php:443`) | A material-change rule kind and the route choice. **New-value matching is existing engine behaviour** | **DECIDED** |
| **Q10** | D7 | Reason mandatory on a material change | `decision_note`; `quote_revisions.summary` as a pattern | Mandatory reason on the proposed version | **DECIDED** |
| **Q11** | D7 | Supporting documents optional | Document DMS exists (`lib/recruit_iv.php`) | Attach optionally to a proposed version | **DECIDED** |
| **Q12** | D7 | Approved change creates a new approved version; previous preserved | **Nothing** for hiring requests; quotation revisions are the pattern | A version chain | **DECIDED** |
| **Q13** | D7-CAND | Existing candidates move to the latest approved version | Candidates link to a requisition, **not to a version** | A version reference on the link | **DECIDED** |
| **Q14** | D7-CAND | Candidate whose eligibility is in doubt is flagged, not rejected | **Nothing** | A review flag. **Needs B1 answered first** | **DECIDED / BLOCKED** |
| **Q15** | D7-CAND | Previous assessments and interviews remain valid | Interviews and scorecards are separate records already | Confirm nothing invalidates them | **DECIDED** |
| **Q16** | D7-CAND | Review must be resolved before the candidate progresses | Pipeline movement is a single controlled path | A gate on stage movement | **DECIDED** |
| **Q17** | D7-CAND, D4 | Review permission follows role defaults + permission + scope | As D4 | A review right in the role profile | **DECIDED** |
| **Q18** | D7-CAND | Clearing a review requires a reason | Audit trail `act_log()` | Mandatory reason captured on clearance | **DECIDED** |
| **Q19** | D7-CAND | Outcome is Continue or Reject; both need a reason | Candidate rejection exists | Two outcomes, both reasoned | **DECIDED** |
| **Q20** | D7-CAND | Reject is immediate, no further approval | Rejection needs no approval today | None beyond the review flow | **DECIDED** |
| **Q21** | D1, D3 | "Hired" configurable — Offer Accepted **or** Actual Joined; both remain distinct events | Acceptance and joining are already distinct steps (`lib/recruit_offer.php`, workforce hand-off) | A per-organisation setting; consumers read it | **DECIDED** |
| **Q22** | D5 | No configured rule means the event needs no approval | `appr_start()` already starts nothing when no rule matches — **but `approval_required` defaults to 1, so the record waits for a manual decision** | Decide the new-organisation default (**B2**) | **DECIDED / DETAIL PENDING** |
| **Q23** | D4 | Predefined role profile + administrator override | Role model and Role Workspaces exist | Define the profiles (**B4**) and the override scope | **DECIDED / DETAIL PENDING** |
| **Q24** | D5 | Requisition approval configurable per organisation | Configurable today but **never fires** | Wire it | **DECIDED** |
| **Q25** | D9 | Mobile operational; complex administration desktop | Responsive UI exists; unverified | Verification | **DECIDED / VERIFICATION** |
---

## Status legend

| Status | Meaning |
|---|---|
| **DECIDED** | The business decision is complete enough to write an implementation plan against, subject to Section 15(B). |
| **DECIDED / DETAIL PENDING** | The direction is decided; specific rules, values, defaults or matrices are still required. |
| **DECIDED / BLOCKED** | The rule is decided but cannot be built until a blocking question in Section 15(B) is answered. |
| **DECIDED / VERIFICATION** | Decided, and additionally requires verification before any claim is made. |

## What the statuses add up to

Of the ten primary decisions, **two are clear to plan against** (D1, D2), **seven**
carry pending detail, and **one** (D9) additionally requires verification. Of the
twenty-five supporting decisions, **twenty-one are clear**, **two** carry pending
detail, **one** (Q14) is blocked on B1, and **one** (Q25) requires verification.

**This is not drift.** The owner decided *direction* deliberately and left *values*
— role profile contents, approval chains, thresholds, defaults — to be set once the
consequences were visible. The five blocking items in Section 15(B) are what must
close before implementation planning begins.

## A note on what did NOT change

Across three issues, thirty-five decisions and two rules locked in consistency
review (F1, F2), the architectural answer has not moved:
**no new engine is required.** One approval engine, one pipeline engine, one KPI/SLA
engine, one configuration model. The five configuration dimensions added by this
issue all attach to mechanisms that already exist, and the versioning model D7 needs
already has a working precedent in the product.

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
| Files created | **0** in this issue — the document already existed |
| Files modified | **1** — this document only |
| Temporary files or scripts created | **None retained** |

**Method.** All facts were established by reading source files and the two source
documents. No application code was executed against production data. No test
suite was run, because no code changed. The repository state was verified with
`git status` before and after.

**Second issue.** The same checks were repeated. Additional **read-only** source
inspection established the three gaps in Section 10 and the Q22 gap in Section 8 —
the approval start path, the re-approval block list, the single approved snapshot,
and the existing quotation revision pattern. **Nothing was executed and nothing was
changed.**

**Third issue.** Applies the owner's two locked rules (F1, F2), carries the F3
reading into Section 2, and applies the nine editorial corrections from the Task 3
consistency review: the misplaced status legend moved from Section 15 to the end of
Section 17; the self-referential counts corrected; the duplicate `C8` identifier
split (the open remainder renumbered **C45**); Section 14's reuse map extended from
26 to 31 rows; the dropped "configuration alone will not deliver D5" warning
restored; the offer-event count reconciled (nine decided, ten in Section 9 as a
documented elaboration); the eighth mobile item marked as an inference the owner has
not adopted; and the two status legends cross-referenced. **Read-only source
inspection only — the approval matcher, the amount-band scoring, the hiring-request
approval context, and the candidate stage constant — to evidence F1 and F2. Nothing
executed, nothing changed.**

**Fourth issue.** Records the eight locked rules A1–A8 in Section 20 as a
preservation record, adds the header pointer to it, and changes nothing else.
Sections 2, 10 and 19 were deliberately **not** edited. Read-only source inspection
supported the evidence cited (the candidate match engine, the offer lifecycle and
issue guard, the workforce conversion point, the permission and custom-role model,
the execution gate's action set, requisition eligibility fields and change control,
candidate table columns, and the approved-quantity comparison). **Nothing executed,
nothing changed.**

**One error was made and corrected during Issue 2.** An editing range overran and
removed Section 16 (Explicit Non-Goals). It was detected by a section-inventory check
before commit and restored from the committed Issue 1 text, then extended. The
section is present and complete.

---

# Section 20 — Appendix: Locked Clarifications A1–A8

> **Status: PRESERVATION RECORD ONLY.** This appendix records eight rules the
> owner locked after the Task 3 consistency review and the Task 5 clarification
> inventory. It is deliberately **not yet integrated** into Sections 2, 10 or 19 —
> that happens once in a single pass, after the remaining Part B values are
> settled, so those sections are not rewritten after every individual decision.
>
> **Where this appendix and an earlier section disagree, this appendix governs.**
> Section 15's register still lists items these rules have closed; that
> reconciliation is part of the final pass, and each entry below names what it
> closes.
>
> Numbered 20 because section numbers in this document are never reused.

## A1 — Candidate eligibility when a requirement becomes stricter

> When a stricter approved requirement version is applied, **all active candidates
> attached to that requirement enter Review Required.** An authorised reviewer must
> decide whether each candidate can continue or should be rejected. The existing
> candidate-versus-requirement comparison engine is reused as **decision-support
> evidence only**. Its score, factor results and text matching **must not
> automatically clear, reject or gate** a candidate.

**Flow:** stricter version → active candidates → Review Required → reviewer sees comparison evidence → Continue or Reject → mandatory reason.

**Also locked:** no automatic rejection · no automatic clearance · no new matching engine · the existing engine is reused · **A1 does not depend on D2 being complete** — richer evidence becomes available as those fields arrive.

**Evidence.** A weighted match engine already exists in `lib/recruit.php`: seven factors (Designation 20, Discipline 20, Skills 15, Experience 15, Business unit 10, Location 10, Cost 10) returning a percentage and a per-factor verdict with evidence such as *"6 vs 8 yrs"*. Its module header states: *"all deterministic & explainable… **Nothing here writes data or makes a decision on its own.**"* Experience is a true numeric comparison; Skills and Discipline are token text matching; **qualification has no factor at all**, because ranked qualification comparison needs an ordered vocabulary that does not exist.

**Closes:** B1. **Integrates into:** Section 19.

## A2 — Which lifecycle points a new version reaches

> A new approved requirement version reaches candidates **up to the point an offer
> has been issued**. Candidates with an issued, accepted or joined offer are
> neither placed into Review Required nor re-linked to the new version; they remain
> attached to the version in force when their offer was issued. A **draft** offer is
> not yet a commitment, so those candidates remain in scope. **This boundary is
> fixed in the product and is not derived from the organisation's "Hired" setting
> (Q21).**

| Candidate state | Reached by a new version? |
|---|---|
| Active, pre-offer | **Yes** → Review Required (A1) |
| Draft offer | **Yes** — not yet a commitment |
| Issued offer | **No** — protected |
| Accepted | **No** — protected |
| Joined | **No** — protected |

**Why not derived from Q21.** Q21 is a measurement and closure setting. If the boundary followed it, changing a reporting preference would silently change who can have an offer withdrawn — one setting carrying two unrelated meanings.

**Evidence.** The offer lifecycle is `DRAFT → PENDING_APPROVAL → APPROVED → ISSUED → VIEWED → ACCEPTED / DECLINED / EXPIRED / WITHDRAWN`; `issued_at` distinguishes a draft from a commitment; `letter_html`, `offer_terms` and `ctc` preserve what was promised, so a later requirement change cannot rewrite it.

**Consequence:** because options B and C were not taken, **no offer-to-requirement-version link is required** for this control.

**Closes:** B5, C37. **Integrates into:** Sections 7, 19.

## A3 — Offer approval and Candidate Hiring approval

> Offer approval and Candidate Hiring approval govern **different events and
> neither substitutes for the other**. Offer approval gates **issuing** the offer;
> Candidate Hiring approval gates **converting an accepted candidate into
> workforce**. Where both are configured, both must pass, in that order — the order
> is fixed by the recruitment lifecycle and is **not configurable**. Where only one
> is configured, only that gate applies. Where neither is configured, no approval
> is required (Q22). A refused Candidate Hiring approval **blocks the conversion; it
> does not reject the candidate** — rejection remains a human act with a mandatory
> reason.

**Flow:** Offer approval → issue offer → candidate accepts → Candidate Hiring approval → workforce conversion.

**The distinction preserved:** the *offer* decision is whether the organisation is willing to make the offer; the *hiring* decision is whether the accepted person is actually converted into workforce.

**Evidence.** `offer_issue()` refuses an unapproved offer — *"§32 — an UNAPPROVED offer can never be issued."* The conversion point is `rcv_convert()`, which writes the workforce record; **no approval exists there today**, but it is a discrete named step and therefore a clean gate.

**Closes:** C38. **Integrates into:** Sections 4, 8.

## A4 — Administrator permission override model

> Recruitment permissions are configured **per role, never per user**. An
> organisation that needs different rights for an individual creates a custom role
> with the appropriate base, so every exception is named and reviewable in one
> place. **A shipped role profile is a default, not a floor:** an administrator may
> both grant additional rights and remove rights the default includes. Sensitive
> permissions — candidate salary visibility and equivalents — are **never granted or
> removed as a side effect of a blanket action**; they are always set deliberately.
> This reuses the existing `custom_roles` base-role mechanism and the existing
> access editor; **no per-user grant layer is introduced.**

**Also locked:** *Role profiles track their shipped base; however, a shipped change that adds a sensitive permission does not propagate that permission without deliberate administrator action.*

**Evidence.** `can($perm)` resolves a user's effective permissions from their role; **no per-user grant layer exists**. `custom_roles` already implements profile-plus-customisation: *"A custom role is **NEVER free-floating**: it copies an existing built-in role's permissions (its 'base'), so it always means something definite and **can never accidentally grant everything**."* The access editor already grants **and** denies. Identity documents already resist blanket grants — they *"back out here and ha[ve] to be granted deliberately."* And a recorded lesson on omission: a module absent from `module_groups()` *"cannot be granted or denied by an administrator at all"*, which had already silently happened to five modules.

**Closes:** C39 and the unregistered direction question (G3). **Integrates into:** Section 7.

## A5 — Effect of a pending material change

> The effect of a pending material change is configured per organisation as **a
> single graduated level over the existing execution gate**, not as independent
> switches:
> **1 — Pause everything:** no recruitment execution activity.
> **2 — Continue screening and interviewing, but make no offer and record no joining.**
> **3 — Continue everything except recording a joining.**
> **4 — Continue as normal.**
> The shipped default is **Level 2**. This reuses the existing `REXEC_ACTIONS`
> execution gate and its refusal messages. **No new gate or independent enforcement
> mechanism is introduced.** Where a level permits work to continue, the pending
> change must be **visible** to the people performing that work.

**Also locked:** Level 2 is the **product default, not a mandate** — an organisation may configure any level. **Level 1 is today's behaviour**, so an organisation wanting no change selects it.

**Evidence.** `REXEC_ACTIONS` already defines the four levers — `ADVANCE` "move this candidate forward", `INTERVIEW` "schedule an interview", `OFFER` "make an offer", `JOIN` "record a joining" — and the gate is asked at 23 call sites. Its header explains the graduation: *"The gate asks a different (larger) set of questions for a joining than for a screening call, **because they cost different things**."* It also records why it exists: *"an OFFER could be created, approved, ISSUED and accepted against a requisition whose approval had been invalidated — **a commitment made to a person for headcount nobody had approved**."* Raising a requisition is separately gated in `hreq_to_requisition()`.

**Closes:** G1 (unregistered — Q6 said "configurable" with no option list anywhere). **Requires:** C40 (pending-change visibility) becomes necessary rather than optional. **Integrates into:** Sections 2, 10.

## A6 — Versioning applies to both records

> Requirement versioning applies to **both** the Hiring Request and the Requisition,
> using **one** versioning mechanism. Each entity has its **own configurable
> material-field list**: the Hiring Request's covers the authority it carries; the
> Requisition's covers the fields that change **who qualifies or what it costs**, and
> not execution wording such as the job description. Candidate review under A1 is
> triggered by a **new effective version** of either record — whether or not an
> approval chain was required for it. Where a requisition has no approving authority
> behind it (ADR-001, a directly raised requisition), its own version chain still
> applies.

**The distinction locked with it:**

| | Meaning |
|---|---|
| **Approval** | whether an authority decision is required |
| **Effective version** | whether the requirement governing recruitment has actually changed |

**These must not be conflated.** The trigger for candidate review is the second, not the first — which preserves A1 when an organisation requires no approval for that entity, when a direct requisition exists, and when a change becomes effective without an approval workflow.

**Why this rule exists.** The eligibility criteria that decide "stricter" live on the **requisition** — `requisitions` carries `qualification`, `experience_min`, `relevant_experience`, `skills`, `discipline`, `category`, `trade_id`, `skill_id` — and D2-B **permits the requisition to strengthen** them. Requisitions have **no change control of any kind** today: `act_log('REQUISITION', …)` appears only for approval-chain notes and fulfilment allocations, never a field edit. Versioning the Hiring Request alone would therefore leave a documented path around A1: raise the requisition's minimum experience from 5 to 8, and no version, no approval and no candidate review would occur.

**Evidence also.** `hreq_remaining_qty()` = approved − converted confirms **one Hiring Request can spawn several Requisitions**, so request-level versioning alone cannot express one office tightening while another does not.

**Closes:** B3, C11. **Integrates into:** Sections 10, 19.

## A7 — Recruitment scope for a candidate

> A candidate does **not** acquire an office dimension. Recruitment scope for an
> **active** candidate is **derived from the requirement they are attached to** —
> that requirement's office and business unit — reusing the existing scope helpers.
> A candidate not attached to any requirement has no scope, because the available
> pool is company-wide by D3. `candidates.sbu` remains **descriptive** and is not
> used for access decisions; the requirement is the single source. This requires
> **no new column on `candidates` and no migration of existing candidate records.**

**Also locked as an expected consequence:** *If a requisition moves office or business-unit scope, the active candidates attached to it inherit that changed recruitment scope.*

**The distinction preserved:** available candidate pool = company-wide; active candidate = scoped through its requirement.

**Evidence.** `candidates` has **no office column** — verified against both the `CREATE TABLE` and every `ensure_column('candidates', …)` call. It does carry `requisition_id` (one requirement per candidate row), `allocation_id` and `sbu`. The requisition register already scopes via `scope_clause('r.office_id','r.sbu')`. An ADR-001 direct requisition still carries `office_id`, so candidates on it scope correctly.

**Why no column.** It would create a second source of truth for one fact — the candidate's office and their requirement's office — which can disagree, in a security-relevant place; and it would require inventing an office for 935 existing rows.

**Closes:** C20. **Integrates into:** Section 7.

## A8 — When a Requisition would weaken the approved requirement

> A requisition value that falls **below the approved minimum** on its Hiring
> Request is a **weakening** and is routed through the material-change process as a
> proposed Requisition version, with a mandatory reason and the approval route
> selected under F1. It is **neither silently permitted nor blocked outright**.
> Weakening is judged **against the approved minimum, never against the
> Requisition's previous value**, so reducing an over-specified Requisition back
> toward the approved floor is not a weakening and proceeds freely. The exception is
> recorded **on the Requisition**, not by re-versioning the Hiring Request, so it
> does not relax the standard for other Requisitions raised from the same Hiring
> Request. Where no approval is required under F1/Q22, the weakening **still
> requires a reason and still creates a version** — the control degrades to
> auditability, not to nothing. A relaxation does **not** trigger candidate review
> under A1 and does **not** automatically reconsider previously rejected candidates
> under F2.

**Evidence.** The only request-to-requisition enforcement today is **quantity**: `hreq_approved_qty()` reads the **approved snapshot's** quantity and `hreq_remaining_qty()` stops requisitions exceeding approved headcount. **No eligibility field is checked at all.** That existing comparison — against what was approved, not against the current value — is the precedent A8 extends from one field to the eligibility minimums.

**Closes:** C7. **Integrates into:** Sections 2 (D2-B), 10.

## Dependencies between these rules

```
A6 (version both records) ──▶ A1 (review trigger = new effective version)
        │                          ▲
        │                          │ A1 no longer waits on D2
        ▼                          │
A8 (weakening routes as a      A2 (…but only up to an issued offer)
    Requisition version)
        │
        └──▶ F1 (which chain reviews it, matched on NEW values)
                    │
                    └──▶ B2 (what an unconfigured organisation starts with —
                             now governs material change too)

A4 (role defaults + override) ──▶ Q8  propose a change
                              └──▶ Q17 clear a Review Required
                              └──▶ B4  the profile contents (open)

A5 (pending-change levels) ──▶ C40 pending change must be visible (open)
A7 (scope from the requirement) ──▶ D3 pool stays company-wide
```

## What these rules did NOT change

**No new engine.** All eight reuse mechanisms that already exist: the approval matcher and its current-value rule matching, the `REXEC_ACTIONS` execution gate, `custom_roles` and the access editor, the candidate match engine, the office and business-unit scope helpers, the approved-snapshot comparison, and the quotation revision pattern.

**Still protected and untouched:** Operations · Quality · Reporting · Money/Billing · Workforce · Marketplace · the existing approval, KPI/SLA and pipeline engines · the existing identity and organisation architecture. No Person Hub. Requisition and Marketplace Requirement remain separate.

## Still open after these eight

Six configuration values, in the agreed order: **B2** (new-organisation approval default, and treatment of requests already at SUBMITTED) · **B4** (the three role profiles) · **C45/C9** (budget materiality) · **C36/C43** (Review Required rejection outcome) · **G2** (resubmit versus a new material-change proposal) · **C21** (primary Recruitment landing).

**No implementation is authorised by this appendix.** Section 16's non-goals apply to every rule recorded here.

---

# STOP

**This specification is complete. No implementation is authorised.**

The next step is **not** code. It is:

1. The owner reviews this specification.
2. The owner answers the **five blocking clarifications** in Section 15(B).
3. A separate **implementation plan** is written and approved.
4. Only then does implementation begin — and **D1, D2 and Q21** come first, because
   everything else is built on them.

**Nothing in this document states or implies that Universal Recruitment is
ready.** It is not. It is specified.

---

## Change log

| Date | Change |
|---|---|
| 2026-09-27 | **Issue 1.** Created. Converts owner decisions D1–D9 into product rules and configuration specification. Sources: the audit and the Business Decision Pack (`a010113`). |
| 2026-09-28 | **Issue 4.** Records the eight locked clarifications **A1–A8** in a new **Section 20** appendix, with each rule's locked wording, its supporting evidence, the register items it closes, the sections it will integrate into, and the dependency map between them. **Preservation only — Sections 2, 10 and 19 are unchanged**, pending the single integration pass after the remaining six configuration values are settled. |
| 2026-09-28 | **Issue 3.** Applies the two rules the owner locked after the Task 3 consistency review — **F1** (material-change approval inherits the underlying requirement's rule; separate chain takes precedence; chain matched on the proposed changed values; no approval only where neither requires it) and **F2** (a relaxed requirement does not automatically reconsider a previously rejected candidate; the rejection stands and the candidate remains findable for deliberate human reconsideration) — carries the **F3** reading into D1, and applies nine editorial corrections (F4–F11). Section 14's reuse map extended to 31 rows; **BUILD NEW remains 0**. |
| 2026-09-27 | **Issue 2.** Incorporates the locked supporting decisions **Q1–Q25** and the revised D1–D9, including the material-change versioning model (D7, Q4–Q12) and candidate version behaviour (D7-CANDIDATE, Q13–Q20, new Section 19). Rewrites Sections 2, 3, 4, 5, 7, 8, 10, 12, 15, 17; extends Sections 1, 6, 14, 16, 18. Withdraws five statements from Issue 1 — see Section 15(E). Five new configuration dimensions recorded, every one attaching to a mechanism that already exists. **No new engine required.** |
