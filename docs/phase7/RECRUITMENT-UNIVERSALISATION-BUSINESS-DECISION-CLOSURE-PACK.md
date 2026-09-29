# EXAACT Recruitment Universalisation
## Business Decision Closure Pack

**Status:** LOCKED BUSINESS DECISIONS — no implementation authorised
**Date:** 2026-09-29
**Owner decisions recorded:** C37 · OPEN-1 · OPEN-2 · OPEN-3 · OPEN-4 · C47 · C24 ·
C27 · C12/C13 · C14 · C19 · C32/Q1 · C40 · C41 · C42 · C44/D9 · SELF-APPROVAL

---

## 1. Purpose

This Pack closes the recruitment universalisation **business-decision stage**.

The Task 6C read-only review of the Business Rules Specification found that, although
every rule in that document was stated clearly and consistently, **genuine business
questions were still unresolved** — and four of them existed only inside section-level
*UNDECIDED* blocks, missing from the formal register. A developer working from the
specification alone would have had to invent the answers.

**This Pack records the owner's answers.** It exists so that the next phase has one
short, authoritative decision input rather than a 4,000-line specification to
re-interpret.

## 2. Authority and status

- These decisions are **LOCKED**. They are not to be reopened, reinterpreted,
  simplified or replaced, and the owner is not to be asked to decide them again.
- **No implementation is authorised by this Pack.** It contains decisions, not built
  behaviour. See §7.
- Nothing here changes any previously locked decision: **D1–D9, Q1–Q25, F1, F2, A1–A8,
  B2, B4, C45/C9, C36, C43, G2 and C21 all stand unchanged.**

## 3. Relationship to the Business Rules Specification

The governing specification remains:

> `docs/phase7/RECRUITMENT-UNIVERSALISATION-BUSINESS-RULES-SPEC.md`

That document's own precedence rule is unchanged: **its Sections 1–19 govern, and its
Section 20 is subordinate historical material.**

This Pack sits alongside it as the **later business decision** on the seventeen items
listed above:

> **For these decisions, this Pack governs.** Its wording is the source of record. The
> specification records each closure at **Section 15(A.4)** and points here. **Any
> difference between the two is a defect to be fixed**, not a choice to be exercised.

For everything else, the specification governs and this Pack says nothing.

The three documents fit together like this:

| Document | What it is |
|---|---|
| `RECRUITMENT-UNIVERSALISATION-AUDIT.md` | What the system does **today**, with evidence |
| `RECRUITMENT-UNIVERSALISATION-BUSINESS-RULES-SPEC.md` | What the business has **decided**, in full, with reasoning and evidence |
| **This Closure Pack** | The **final decisions** that closed the last open questions — the authoritative input to implementation planning |

## 4. Final decision register

| # | The question | The decision | Status |
|---|---|---|---|
| **C37** | Where "Hired" = Offer Accepted, what is the candidate's visibility between accepting and joining? | Offer Accepted establishes **Hired** where configured. **Hired and Joined remain distinct facts.** Hired-but-not-joined is **Joining Pending**, becoming **Joined** on joining. Joining-pending people **remain visible to authorised users for legitimate follow-up**, but are **not** treated as ordinary active recruitment candidates | **CLOSED** |
| **OPEN-1** | Does clearing Review Required cover one requirement or all? | **Requirement-specific.** Raised for the changed requirement only; clearing clears **only that requirement / process relationship**, never the candidate globally | **CLOSED** |
| **OPEN-2** | Who can see Review Required? | **Anyone with legitimate permission to view the candidate or the process.** **Visibility does not grant authority to clear** — only the configured *Clear Review Required* permission does | **CLOSED** |
| **OPEN-3** | Where does a reconsidered candidate re-enter? | The **stage immediately preceding the rejection**, with a **mandatory reconsideration reason** and the **original rejection retained permanently** | **CLOSED** |
| **OPEN-4** | What about the eight roles beyond the three shipped profiles? | **Eleven role categories are supported.** Role existence does **not** imply permission. The three detailed profiles remain authoritative; **every other profile must be explicitly defined before its capabilities are enabled** | **CLOSED** |
| **C47** | One closed stage kind, or a family? | **One kind — `closed` / not proceeding — with configurable closed OUTCOMES** | **CLOSED** |
| **C24** | Are the role names the Decision Pack's names? | **Map terminology; do not duplicate roles.** Use the existing configurable terminology / role architecture | **CLOSED** |
| **C27** | Which industries first? | **TPIA · Recruitment / Staffing · Manufacturing / Trading** as the first configuration packs. The engine stays industry-neutral | **CLOSED** |
| **C12 · C13** | Offer audit: which events, which mechanism? | **Reuse the existing audit / event ledger. No second offer-specific audit engine** | **CLOSED** |
| **C14** | Current stage or stage history? | **Pipeline = current state. Ledger = history.** Legacy `candidate.stage` is **not** an independent authority | **CLOSED** |
| **C19** | What happens to `is_coordinator_level()`? | **Reconcile it into the configurable role / permission architecture.** It must not remain a universal recruitment authority. Do not blindly delete compatibility logic screens depend on | **CLOSED** |
| **C32 · Q1** | Candidate Hiring approval | **Configurable per organisation, and a DISTINCT gate from Offer approval.** A refusal blocks workforce conversion; it does **not** reject the candidate | **CLOSED** at the business level; the form of the entity-list change stays an implementation choice |
| **C40** | Pending material change visibility | **Visible to authorised users**, showing approved version, proposed change and pending status. The approved version **remains effective until approval** | **CLOSED** |
| **C41** | Rejected material changes | **Permanently retained** with reason and audit; **do not alter the approved version**; **never deleted** | **CLOSED** |
| **C42** | Supporting documents | **OPTIONAL** | **CLOSED** |
| **C44 · D9** | The mobile line | **Operational workflows must work on real mobile browsers**; complex administration may stay desktop. **Not a native app requirement** | **CLOSED** |
| **SELF-APPROVAL** | May a requester approve their own record? | **No. The requester must not approve their own submission. No self-approval loophole** | **CLOSED** |

## 5. Final business rules

### 5.1 Hired versus Joined (C37)

Where an organisation configures **Hired = Offer Accepted**:

| Axis | Values |
|---|---|
| **Recruitment outcome** | Offer Accepted → **Hired** |
| **Joining status** | **Joining Pending** → **Joined** |

- Offer Accepted **establishes Hired** where that is the configured definition.
- **Hired and Joined remain distinct facts**, recorded separately, as Q21 already
  requires.
- **Hired-but-not-Joined is represented as Joining Pending.**
- Joining-pending people **remain visible to authorised users for legitimate
  follow-up** — but are **not** treated as ordinary active recruitment candidates.
- **A2 is unchanged:** once an offer has been **issued**, a later requirement version
  does **not** place that candidate into Review Required.
- **Joining** moves the joining state to **Joined** and permits **workforce hand-off**.
- A **non-joining** outcome **retains the historical Offer Accepted decision**.
- **Do not create a second recruitment lifecycle engine.**

### 5.2 Review Required — scope and visibility (OPEN-1 · OPEN-2)

- **Requirement-specific.** A stricter approved version of Requirement A creates Review
  Required **for Requirement A only**.
- **Clearing it clears only that requirement / process relationship.** It does **not**
  globally clear the candidate.
- **Anyone who already has legitimate permission to view the candidate or the
  recruitment process can see the Review Required state.**
- **Visibility does NOT grant authority to clear it.** Only users holding the
  configured **Clear Review Required** permission may perform the clearance.

### 5.3 Reconsideration of a previously rejected candidate (OPEN-3)

**No automatic reconsideration** — F2 stands. When an authorised user **deliberately**
reconsiders:

- the candidate **returns to the stage immediately preceding the rejection**;
- a **reconsideration reason is mandatory**;
- the **original rejection is retained permanently in history**;
- **actor** is recorded;
- **date and time** are recorded;
- the **previous closed / rejected outcome** is recorded;
- the **previous stage** is recorded;
- the **resulting stage** is recorded;
- the candidate becomes **active again for that specific requirement / process**.

**Rejection history is never erased or overwritten.**

### 5.4 Closed / not proceeding (C47)

**One semantic pipeline stage kind — `closed` / not proceeding — with configurable
closed outcomes (subtypes).** Initial supported examples:

Rejected · Withdrawn · Offer Declined · Offer Withdrawn · Position Closed ·
Requirement Cancelled · Duplicate · Not Available · Not Suitable · Other

**These are OUTCOMES, not stage kinds.** The pipeline stage kinds remain:

> **step · gate · interview · offer · terminal · closed**

**`terminal` and `closed` are distinct** — terminal is the successful hired /
onboarding end. **Closed outcome + reason + audit history** is what supports
reporting. **Do not create another rejection lifecycle or a duplicate lifecycle
engine.**

### 5.5 Roles and terminology (C24 · OPEN-4)

**Terminology (C24).** Do not create duplicate roles merely because business
terminology differs. Map through the **existing configurable terminology / role
architecture**:

| Business term | Maps to |
|---|---|
| Recruitment Manager | the existing / configurable recruitment **execution** role |
| Department Manager | the existing / configurable department **authority** role |
| Hiring Manager | the Hiring Manager concept |

**Do not rename or duplicate the existing permission architecture solely to match
terminology.**

**Coverage (OPEN-4).** The universal system supports eleven role categories:
**1** Recruiter · **2** Hiring Manager · **3** Department Head · **4** HR ·
**5** Operations Manager · **6** Coordinator · **7** Administrator · **8** Finance ·
**9** Inspector / Field Staff · **10** External Client · **11** Agency.

- **Role existence does not imply unrestricted permission.** Access is:
  > **Role → Permission Profile → Recruitment Scope → actual access**
- Permissions are **configurable per role, not per individual user**.
- The three already detailed default profiles — **Recruiter · Hiring Manager ·
  Department Head** — **remain authoritative**.
- **Every other role profile must be explicitly defined before its corresponding
  workflow capabilities are enabled.**
- **Administrator is NOT automatically equivalent to unrestricted business
  authority.**
- **Sensitive permissions remain deliberate.**

### 5.6 Industry priority (C27)

First universal validation / configuration packs: **1** TPIA · **2** Recruitment /
Staffing · **3** Manufacturing / Trading. **The engine remains universal and
industry-neutral — the architecture must not be hard-coded around TPIA.**

### 5.7 Offer audit (C12 · C13)

**Reuse the existing audit / event ledger. Do NOT build a second offer-specific audit
engine.** Offer lifecycle events must be **permanently auditable** through the existing
authoritative mechanism.

### 5.8 Pipeline authority (C14)

| Question | Answer |
|---|---|
| Current state | the **authoritative configurable pipeline** |
| History | the **stage / event ledger** |

**Legacy `candidate.stage` is NOT an independent authoritative lifecycle. Do not
maintain two competing lifecycle authorities.**

### 5.9 Coordinator access (C19)

**Reconcile the existing `is_coordinator_level()` behaviour into the configurable role
/ permission architecture.** It must **not** remain a universal recruitment authority.
**Do not blindly delete compatibility logic if existing screens depend on it — reuse or
extend safely.**

### 5.10 Candidate Hiring approval (C32 · Q1)

Configurable per organisation. **Offer Approval and Candidate Hiring Approval are
DISTINCT gates.**

- **Both configured:** Offer approval → offer issued → candidate accepts → Candidate
  Hiring approval → workforce conversion.
- **Only one configured:** only that gate applies.
- **Neither configured:** neither applies.
- **A Candidate Hiring refusal blocks workforce conversion; it does not automatically
  reject the candidate.**

### 5.11 Material change — pending, rejected, documents (C40 · C41 · C42)

- **Pending changes must be visible to authorised users**, showing the **currently
  approved version**, the **proposed version / change**, and the **pending status**.
  **The approved version remains effective until approval.**
- **Rejected material changes remain permanently in history**, retain **reason and
  audit information**, and **do not alter the approved version**. **Rejected proposals
  are not deleted.**
- **Supporting documents are OPTIONAL.**

### 5.12 Self-approval

> **The requester must not approve their own submission. Do not create a
> self-approval loophole.**

### 5.13 Mobile (D9 · C44)

**Operational workflows must work on real mobile browsers.** Complex administration
and configuration may remain desktop-oriented. **This is not a requirement to build
native Android or iOS applications.**

## 6. Dependencies affected

| Dependency | Effect of these closures |
|---|---|
| **C43 → D1 → C15–C18** | C47 settles the vocabulary **before** the closed kind is built: one kind, configurable outcomes. D1's dependency is unchanged, and the legacy migration items still inherit it |
| **A1 · A2 · F2** | Unchanged. OPEN-1 scopes the flag to one requirement, OPEN-2 separates seeing it from clearing it, OPEN-3 defines re-entry. C37 explicitly leaves A2's issued-offer boundary intact |
| **A3 · Q1 · C32** | Unchanged and reinforced: two distinct gates in a fixed order |
| **A4 · B4 · Q23 · C46** | Unchanged. OPEN-4 widens **coverage**, not permission; C46 (exact codes) remains an implementation choice |
| **Q21 · D3** | C37 completes the accepted-to-joined gap without altering what "Hired" means or how the pool is derived |
| **A5 · G2** | C40 makes A5 levels 2–4 usable as A5 requires; C41 confirms G2's retention |
| **D6** | C12/C13 convert "which mechanism" into "reuse the existing one" |
| **Still open after this Pack** | **C15 · C16 · C17 · C18** (legacy migration mechanics, gated on C43) · **C22** (Role Workspaces as the per-role mechanism) · **C25** (candidate state machine) · **C46** (exact permission codes) · **C28–C31 · C33 · C34 · C35** (never put to the owner) · findability surfacing for F2. **None is a business decision awaiting the owner** |

## 7. Implementation has NOT occurred

**Nothing in this Pack has been built.** No PHP, JavaScript, CSS, HTML, schema,
database, route, permission, workflow, test, configuration or deployment change has
been made in recording these decisions. The Pack authorises **no** change to any
running system.

**Nothing here states or implies that Universal Recruitment is ready.** It is decided.

The next step is a **separate, controlled implementation-readiness prompt** based on
this Pack — not code.

## 8. Implementation guardrails

These apply to every future implementation phase:

- **REUSE existing engines before extending.**
- **Do not create duplicate lifecycle engines.**
- **Do not create duplicate approval engines.**
- **Do not create duplicate KPI engines.**
- **Do not merge requisitions and `cx_requirements`.**
- **Do not create a Person Hub.**
- **Do not add a candidate office column.**
- **Do not make `candidate.stage` independently authoritative.**
- **Do not use match scoring as an automatic Review Required decision.**
- **Do not automatically reject or clear candidates based on matching scores.**
- **Do not automatically reconsider rejected candidates.**
- **Do not erase historical rejection, change or approval records.**
- **Do not introduce per-user recruitment permissions.**
- **Do not make Administrator unrestricted by default.**
- **Do not allow self-approval.**
- **Do not weaken approved Hiring Request requirements silently.**
- **Do not bypass the configurable approval engine.**
- **Do not change Operations / Quality / Reporting / Money / Workforce / Marketplace
  behaviour without explicit dependency evidence.**
- **No production deployment as part of any documentation task.**

## 9. Supersession and history

**What this Pack supersedes.** For the seventeen decisions in §4, this Pack is the
later and authoritative statement. Where the Business Rules Specification previously
recorded any of them as **open, undecided, pending or requiring clarification**, that
wording is superseded — and has been corrected in place, with each closure recorded at
**Section 15(A.4)** of that specification.

**What it does not supersede.** Every other locked decision stands: **D1–D9, Q1–Q25,
F1, F2, A1–A8, B2, B4, C45/C9, C36, C43, G2, C21.** The specification's own precedence
rule is unchanged — **its Sections 1–19 govern, Section 20 is subordinate.**

**History.** The two documentation defects that made this Pack necessary were found by
the **Task 6C** read-only business review and are recorded there and in the
specification's Section 18:

1. **Section 17** asserted that no remaining item was a business decision awaiting the
   owner. It was not true.
2. **Section 15(C)** was incomplete: **OPEN-1 … OPEN-4** appeared only in section-level
   *UNDECIDED* blocks and never in the register.

Both are corrected. **The decisions recorded here were taken by the business owner, in
the owner's own terms, and are reproduced rather than interpreted.**

---

# STOP

**These decisions are locked. No implementation is authorised.**

The next step is **not** code. It is a separate implementation-readiness prompt built
on this Pack and on the reconciled Business Rules Specification.

---

## Change log

| Date | Change |
|---|---|
| 2026-09-29 | **Created.** Closes the recruitment universalisation business-decision stage: C37 · OPEN-1 · OPEN-2 · OPEN-3 · OPEN-4 · C47 · C24 · C27 · C12/C13 · C14 · C19 · C32/Q1 · C40 · C41 · C42 · C44/D9 · SELF-APPROVAL. Issued together with the correction of the two documentation defects found by the Task 6C review. **Documentation only — no implementation.** |
