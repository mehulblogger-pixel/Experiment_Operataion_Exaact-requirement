# EXAACT — The Recruitment Module

**The single living reference for this module.** Everything asked and answered
about recruitment belongs here. There is no second document.

| | |
|---|---|
| **Document** | `docs/recruitment/RECRUITMENT-MODULE.md` |
| **Version** | 1.0 |
| **Last updated** | 2026-09-27 |
| **Owner** | Mehul (business) · maintained by whoever changes the module |

---

## How to update this document

This document is **built to be edited in the middle**, not only appended to.

1. **Every section has a permanent ID** — `R1`, `R2`, `R3`… **The ID never
   changes and is never reused**, even if the section moves, is rewritten, or is
   retired. Links and conversations can point at `R6` for ever.
2. **To add a section anywhere** — beginning, middle or end — give it the next
   unused number and put it where it reads best. Numbers do **not** have to run
   in order down the page. That is the whole point: order is for the reader,
   IDs are for the reference.
3. **To change a section**, edit it in place and update its `Updated:` stamp.
4. **To retire a section**, mark it `RETIRED` with one line saying what replaced
   it. Do not delete it — a reference someone quoted last year must still resolve.
5. **Always add a line to the Change log (R99)** and bump the version at the top.
6. **When the module changes, this document changes in the same commit.** Same
   rule as `docs/02-permission-matrix.md`. A reference that lags the code is
   worse than no reference, because people trust it.

Version numbering: `1.0` → `1.1` for a new or rewritten section, `1.0.1` for a
correction or clarification.

---

## Contents

| ID | Section | Updated |
|---|---|---|
| [R1](#r1--what-this-module-is) | What this module is | 2026-09-27 |
| [R2](#r2--who-can-use-it) | Who can use it — industries, trades, business models | 2026-09-27 |
| [R3](#r3--the-people-who-touch-it) | The people who touch it | 2026-09-27 |
| [R4](#r4--the-journey-end-to-end) | The journey, end to end | 2026-09-27 |
| [R5](#r5--what-is-configurable) | What is configurable | 2026-09-27 |
| [R6](#r6--approvals--what-exists-and-what-is-actually-wired) | Approvals — what exists, and what is actually wired | 2026-09-27 |
| [R7](#r7--what-an-approver-sees-and-what-they-need) | What an approver sees, and what they need | 2026-09-27 |
| [R8](#r8--known-gaps) | Known gaps | 2026-09-27 |
| [R9](#r9--decisions-waiting-on-the-owner) | Decisions waiting on the owner | 2026-09-27 |
| [R10](#r10--where-the-code-lives) | Where the code lives | 2026-09-27 |
| [R99](#r99--change-log) | Change log | 2026-09-27 |

---

## R1 — What this module is

*Updated: 2026-09-27*

The part of EXAACT that turns **a need for a person** into **a person who has
started**, with a decision trail at every step.

It covers asking for a hire, approving it, raising the requirement, sourcing and
tracking candidates, interviewing them, agreeing a salary, issuing an offer, and
handing over to onboarding.

It is **not** a payroll system and not an HRMS. It stops the day somebody joins.

---

## R2 — Who can use it

*Updated: 2026-09-27*

### 2.1 Industries — 13 ready-made templates

Inspection, testing & certification · Testing & calibration laboratory ·
Manufacturing & engineering · Trading & distribution · Professional & consulting
services · Construction, projects & infrastructure · IT & software services ·
Healthcare & diagnostics · Education & training · Logistics & transport ·
Real estate & facilities · Recruitment & staffing · General B2B

### 2.2 Vocabulary packs — 12

The product renames itself to match the trade, so no screen uses a word the
customer's own people do not use:

General business · Inspection & certification · Manufacturing & fabrication ·
Trading & distribution · EPC & contracting · Field service & maintenance ·
**Manpower supply & staffing** · **Recruitment agency** · Export & import ·
Logistics & transport · Professional services · Testing laboratories

### 2.3 Business models — 4, and they are genuinely different

| Model | What a "requisition" means to them |
|---|---|
| **Employer hiring for itself** | a seat in its own establishment |
| **Recruitment agency** | a client's order to find somebody |
| **Manpower supplier / staffing** | a deployment against a client contract |
| **Project / contract company** | mobilising a team against a won contract |

All four are supported because a requisition carries a **client**, a **contract
reference** and a **billing rate** — not only a department. That is what
separates this from an in-house-only HR tool.

### 2.4 Engagement types — 6

Permanent · Fixed-term contract · Temporary · Part time · Consultant /
freelance · Intern / trainee

### 2.5 Honest limit

Breadth is not depth. The industry templates change **vocabulary, funnel stages
and lead sources**. They do **not** add industry-specific hiring logic — no
licence tracking for healthcare, no security clearance for defence, no shift
rostering for manufacturing. See [R8](#r8--known-gaps).

---

## R3 — The people who touch it

*Updated: 2026-09-27*

| Who | What they do |
|---|---|
| **Requester** | any department head or manager who needs somebody |
| **Approver** | sanctions the hire; who this is, is configurable ([R6](#r6--approvals--what-exists-and-what-is-actually-wired)) |
| **Recruitment Manager** | configures the module (`hiring.admin`) |
| **Recruiter** | sources and moves candidates |
| **Interviewer / panel** | scores candidates against a scorecard |
| **HR** | salary structure, offer, letters |
| **Finance** | budget and CTC |
| **Candidate** | applies through the public careers page |

---

## R4 — The journey, end to end

*Updated: 2026-09-27*

```
Hiring request  →  Requisition  →  Candidates  →  Interviews  →  Salary  →  Offer  →  Onboarding
  (the ASK,          (the WORK      (sourcing)     (scored)      (built)   (approved,
   approved)          ORDER)                                                issued)
```

**Hiring request** — Draft → Submitted → Under review → Approved / Rejected /
Cancelled. Only **Approved** may become a requisition. An approved request that
materially changes is sent back for re-approval; an increase spends authority
nobody granted, a decrease does not.

**Requisition** — Open → Candidate proposed → Offer released → Partly filled →
Hired → Closed / Cancelled. Carries the client, contract and billing rate.
A ten-vacancy requisition is **not** closed by the first hire.

**Candidates** move along a **configurable pipeline** — the stages are set per
workspace, and the narrowest matching pipeline wins (by client, business unit,
department, position, employment type or grade).

**Offer** — Draft → Pending approval → Approved → Issued → Viewed → Accepted /
Declined / Expired / Withdrawn.

---

## R5 — What is configurable

*Updated: 2026-09-27*

Without a developer:

- **Vocabulary** — every object's name, per workspace (R2.2)
- **Pipelines** — candidate stages, and which requisitions each applies to
- **Approval matrix** — rules, levels, approvers, SLA, escalation (R6)
- **Compensation** — salary headings, statutory components, CTC build-up
- **Document studio** — offer, appointment and other letter templates
- **Positions & org chart** — the establishment, importable from a spreadsheet
- **Careers page** — public job posting and application intake
- **Role workspaces** — what each role lands on and can reach

---

## R6 — Approvals — what exists, and what is actually wired

*Updated: 2026-09-27*

### 6.1 The engine — genuinely capable

At **Admin → Approval rules**. A rule matches on entity, department, business
unit, grade, position, office, **amount band** and effective date. Each rule has
levels; each level names a person **or a role token** — including org-chart
tokens such as *reporting manager* and *HOD*, resolved up the reporting line.
Each level has SLA days, reminder days and an escalation target. Delegation is
supported. A materially changed request is re-approved.

### 6.2 What is declared vs what actually runs

| Entity | Configurable? | Actually starts an approval? |
|---|---|---|
| **Hiring Request** | yes | **yes** |
| **Offer** (candidate hiring) | yes | **yes** |
| **Requisition (SRF)** | yes | **NO — never, anywhere in the application** |
| **Salary structure** | yes | **NO — never, anywhere in the application** |

**This is a trap, not a feature.** An administrator can configure a requisition
approval rule today, save it, and it will never once fire. Requisition and
Salary approvals are started only inside test files.

### 6.3 Current state of this installation

**Zero rules are configured.** With no rule matching, everything falls back to a
single direct decision. All the SLA, reminder, escalation and multi-level
machinery is built and dormant.

### 6.4 How a requisition is authorised today

It inherits the hiring request's approval stamp at conversion. The **headcount
ceiling is enforced** — you cannot raise requisitions for more people than were
approved, and that is checked again after the write because two people clicking
at once had beaten a check-then-insert on MariaDB. The **budget is not**: a
requisition's cost can be edited afterwards with no re-approval.

---

## R7 — What an approver sees, and what they need

*Updated: 2026-09-27*

### 7.1 Today — in the inbox

Type · subject · **value in money** · who asked · date raised · how long it has
sat · SLA due · which rule and level.

### 7.2 Today — on the request itself

Requested by · requesting department · joining department · designation · grade ·
position (or "new position requested") · job title · job description · how many ·
branch · work location · project / contract reference · needed by · employment
type · kind of request · priority · reason · cost per person · basis · duration ·
one-time cost.

Good on **the seat**. Silent on **the person**.

### 7.3 What is missing, ranked by how much it changes a decision

1. **Establishment position** — *how many people does this department already
   have, against how many are sanctioned?* Without it the approver is deciding
   in a vacuum. **The data already exists** in the position master and org
   chart; it is simply not put in front of them. **Highest value of anything in
   this document.**
2. **Budget position** — is this within the department's approved manpower
   budget? The commitment is calculated but compared to nothing.
3. **Who exactly are we hiring** — the person-spec. This is what makes an
   approval *binding* rather than decorative. See [R8](#r8--known-gaps).
4. **Replacement detail** — who left, when, at what cost. A replacement and a
   new post are different decisions and currently look identical.
5. **Requester history** — a department's fifth "urgent" hire this quarter looks
   exactly like its first.
6. **Internal fill** — any bench or redeployment candidate.
7. **Consequence of refusal** — what breaks if this is declined.

**Recommendation: build 1, 2 and 3.** Items 4–7 are worth having; those three
change decisions.

---

## R8 — Known gaps

*Updated: 2026-09-27*

### G1 — The hiring request has no person-spec *(open, highest priority)*

No field anywhere for: **experience (minimum or maximum)**, education
qualification, key skills, certifications or licences, discipline / trade,
languages, acceptable notice period, industry background.

The **requisition** — the *next* stage, *after* approval — does have skills,
qualification, minimum experience, relevant experience, discipline, category,
trade and skill.

Two consequences:

- **Maximum experience does not exist anywhere in the system.** Only minimum.
- **Nothing carries forward**, because the request has none to give. The
  recruiter re-types it — the same re-typing problem fixed for contracts in
  ADR-004.

**Why it matters more than it looks:** the approval does not constrain what is
recruited. "Senior Engineer × 1, ₹12 lakh" could be filled by a fresh graduate
or a twenty-year veteran. The approver cannot tell, and cannot hold anyone to it
afterwards, because nothing was agreed.

**Sharpest for the two business models that are most about matching people** —
recruitment agency and manpower supply. Of the four models in R2.3, the two that
live or die on the person-spec are the two least well served today.

### G2 — Requisition and Salary approvals are configurable but never fire *(open)*

See [R6.2](#62-what-is-declared-vs-what-actually-runs). Either wire them or
remove them from the screen. A setting that does nothing is worse than no
setting.

### G3 — A requisition's budget can drift after approval *(open)*

See [R6.4](#64-how-a-requisition-is-authorised-today). Headcount is guarded;
money is not.

### G4 — Industry templates are vocabulary, not hiring logic *(accepted, not a defect)*

See [R2.5](#25-honest-limit). Recorded so nobody promises a customer more than
the templates deliver.

---

## R9 — Decisions waiting on the owner

*Updated: 2026-09-27*

| # | Question | Why it matters |
|---|---|---|
| D1 | Should the person-spec be **compulsory** on a hiring request, or optional? | Compulsory makes the approval binding; optional keeps raising a request quick. |
| D2 | **Requisition and Salary approvals — wire them up, or remove them** from the configuration screen? | Today they are configurable and inert. |
| D3 | Should a change to a requisition's **budget** after approval force re-approval, as a hiring request's does? | Closes G3. |

Answer here, and the answer moves into the section it belongs to.

---

## R10 — Where the code lives

*Updated: 2026-09-27*

| Concern | File |
|---|---|
| Hiring request | `lib/hiringreq.php` |
| Approval engine | `lib/recruit_approval.php` |
| Candidate pipeline | `lib/recruitpipe.php` |
| Offer | `lib/recruit_offer.php` |
| Compensation | `lib/comp_config.php` |
| Candidate de-duplication | `lib/candpool.php` |
| Vocabulary | `lib/terms.php` |
| Industry templates | `lib/industry.php` |
| Screens | `views/ops/hiring_request*.php`, `requisition*.php`, `candidate*.php`, `recruit*.php`, `comp_setup.php`, `doc_templates.php` |

Related decisions: `docs/adr/ADR-001` (direct requisition path) ·
`ADR-002` (one word per object) · `ADR-003` (the process decides the screen).

---

## R99 — Change log

*Updated: 2026-09-27*

| Version | Date | What changed |
|---|---|---|
| 1.0 | 2026-09-27 | Created. R1–R10 written from a walk through the live code and database: who can use it, the journey, what is configurable, the state of approvals, what an approver sees, four known gaps and three open decisions. |
