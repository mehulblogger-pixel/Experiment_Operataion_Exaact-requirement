# EXAACT Recruitment — Business Decision Pack

> **Nothing has been built, changed or migrated.** No code, database, screen,
> permission, workflow, stage, terminology or record was touched to produce this.
> This document asks you **nine business questions**. Your answers become the
> rules the next phase is built to.
>
> **You do not need to understand any code to answer these.** If a question here
> cannot be answered without technical knowledge, that is a fault in the question
> — tell me and I will rewrite it.

| | |
|---|---|
| **Source of truth** | `docs/phase7/RECRUITMENT-UNIVERSALISATION-AUDIT.md` |
| **Status** | Awaiting owner decisions — **9 pending** |
| **Branch / commit** | `claude/testing-branch-setup-0gqe8n` · `e7bf1b7` |
| **Date** | 2026-09-27 |

---

## A correction to the audit, before anything else

The audit's table of row counts (§13) was taken from the database's own
*estimated* row statistics, not exact counts. Re-checked with exact counts:
configured pipelines read **4**, not 3, and configured stages **33**, not 32.

**The two figures this pack depends on were exact counts and are confirmed
unchanged:** 935 of 935 candidates on the legacy lifecycle, **2** on a
configured pipeline; and **0** approval rules configured.

---

## What is NOT up for decision

These were confirmed by the audit and are fixed. No question here asks you to
change them.

- Hiring Request (the ask) and Requisition (the execution) stay different things.
- Marketplace Requirement stays separate from Recruitment Requisition.
- `inspectors` stays the workforce record. No new "Person master" is created.
- One approval engine, one KPI engine, one pipeline engine. No second one is built.
- Each customer keeps their own separate database.
- Candidate, Professional, Inspector, Employee and User stay distinct.

---

# DECISION 1 — How a candidate moves through recruitment

**Reference:** audit §17, §32 · **Impact: VERY HIGH** · Everything else waits on this.

### What is happening today

The system has **two** ways of tracking where a candidate has reached, and both
are switched on.

1. **The standard set of stages** — CV received → Submitted to client →
   Shortlisted → Interview → Offer → Accepted. Fixed. Every customer gets these.
2. **A configurable set of stages** — you can define your own stages per
   department, client or job type. This was built, it works, and there are
   already 4 workflows and 33 stages set up.

**All 935 candidates are on the standard set. Exactly 2 are on a configured one.**

So the configurable machinery exists but is not actually running the business.

### Why this matters

This is the first decision because everything else sits on top of it — your
reports, your funnel, your dashboard, and whether a hospital or an IT company
can use EXAACT at all.

Right now the standard list includes **"Submitted to client"**. That makes sense
for a staffing agency. It makes no sense for a hospital hiring its own nurses.

### Your choices

**Option A — Keep the standard stages for everybody.**
One fixed journey. Simple, predictable, identical for every customer.

**Option B — The configurable stages become the real journey.**
Each customer, and each type of hiring, defines its own stages.

**Option C — A fixed spine, with configurable steps in between.**
Certain milestones always exist (Received, Interviewed, Offered, Hired) because
reporting depends on them. Between those, each customer adds their own steps.

### A simple example

You hire a **welding inspector** and a **finance manager** in the same month.

- **Option A:** both go through the identical six stages. The finance manager
  passes through "Submitted to client", which means nothing for an internal hire.
- **Option B:** the inspector goes Received → Client CV submission → Client
  interview → Deployed. The finance manager goes Received → HR screen → Panel →
  Director approval → Offer. Nothing forced, nothing meaningless.
- **Option C:** both officially pass Received → Interviewed → Offered → Hired, so
  one report compares them — but each also has its own in-between steps.

### What changes if you choose this

| | A | B | C |
|---|---|---|---|
| Existing 935 candidates | nothing | need moving onto a workflow | mostly nothing |
| Can a hospital use it? | poorly | yes | yes |
| Reports comparing departments | work today | become harder — different customers count differently | keep working |
| Setup effort for a new customer | none | must design their stages first | small |
| Risk of user confusion | low | **highest** — two people can describe the same hire differently | medium |

### What does NOT change, whichever you pick

Hiring Request, Requisition, interviews, offers, the workforce handover, and every
approval rule. This decision only concerns how a candidate's progress is *tracked*.

**Your decision: A / B / C** → *pending*

---

# DECISION 2 — How much the approval should define about the person

**Reference:** audit §10, §14, §32 · **Impact: VERY HIGH**

### What is happening today

When management approves a hiring request, they see: the department, the job
title, how many people, where, by when, why, and what it costs.

They **do not** see what kind of person is being hired. There is nowhere to
record required experience, qualification, or skills on the request. Those fields
only appear **later**, on the requisition, after approval has been given.

There is also **no maximum-experience field anywhere in the system** — only a
minimum.

### Why this matters

Approval currently does not constrain recruitment.

Management approves *"Senior Engineer × 1, ₹12 lakh"*. That could be filled by a
fresh graduate or by a twenty-year veteran. The approver cannot tell which, and
cannot hold anyone to it afterwards, because nothing about the person was agreed.

### Your choices

**Option A — The request defines the person.**
Qualification, minimum and maximum experience, skills, discipline, certifications
all go on the request. Approval means *"we need this kind of person."*

**Option B — The request defines only the need.**
Approval means *"we need one person in this department at this cost."* The
recruiter decides the person specification afterwards.

**Option C — Core on the request, detail on the requisition.**
A few essentials are approved (qualification, experience band, key skills);
finer execution detail is added later by the recruiter.

### A simple example

The plant asks for one QA Engineer at ₹9 lakh.

- **Option A:** the request says *B.E. Mechanical, 5–8 years, welding QA, CSWIP
  3.1 preferred*. Approved on that basis. A recruiter who later proposes a
  fresher with 1 year is visibly outside what was approved.
- **Option B:** the request says *QA Engineer, 1 person, ₹9 lakh*. Approved. The
  recruiter may fill it with anyone at that cost.
- **Option C:** the request says *B.E. Mechanical, 5–8 years*. Approved. Skills,
  certifications and shift detail are added afterwards.

### What changes if you choose this

| | A | B | C |
|---|---|---|---|
| Raising a request | slower — more to fill in | fastest | slightly slower |
| Does approval bind recruitment? | yes | no | partly |
| Re-typing by the recruiter | eliminated | continues | reduced |
| Suits a recruitment agency / manpower supplier | **best** — this is their core data | poor | workable |
| Suits an internal corporate hire | can feel heavy | light | balanced |

### What does NOT change

Who approves, how many levels, the headcount ceiling, and the cost figures
already on the request.

**Your decision: A / B / C** → *pending*

---

# DECISION 3 — Which things need management approval

**Reference:** audit §7.1, §20, §24 · **Impact: HIGH**

### What is happening today

EXAACT has a full approval system: multi-level chains, named approvers or roles
(including "the reporting manager"), deadlines, reminders, escalation and
stand-ins when someone is on leave.

Four things are listed as approvable. **Only two are actually connected**:

| | Configurable? | Actually works? |
|---|---|---|
| Hiring Request | yes | **yes** |
| Offer to a candidate | yes | **yes** |
| Recruitment Requisition | yes | **no — never fires** |
| Salary structure | yes | **no — never fires** |

And **no approval rules are set up at all**, so every request currently goes to a
single person for a single yes/no. None of the deadline, reminder or escalation
machinery is doing anything.

### Why this matters

Two problems. An administrator can today configure a requisition approval rule,
save it, and it will silently never run. And the control you paid for — chains,
deadlines, escalation — is switched off because nobody has written a rule.

### Your choices

**Part 1 — tick which need approval at all:**

| Business object | Needs approval? |
|---|---|
| Hiring Request (the ask for a person) | Yes / No |
| Recruitment Requisition (the execution order) | Yes / No |
| Offer to a candidate | Yes / No |
| Salary structure | Yes / No |

**Part 2 — for each "Yes", who approves?**

Describe it in your own words. For example: *"Up to ₹6 lakh, the department head
alone. Above ₹6 lakh, department head then HR head. Above ₹15 lakh, add the
director."* Or by branch: *"Ahmedabad hires need the branch manager."* Both are
already supported.

### A simple example

Today, a request for 20 people at ₹2 crore and a request for 1 person at ₹3 lakh
follow exactly the same path — one person, one click, no deadline.

### What does NOT change

The approval engine itself. No new approval system is built whatever you choose.

**Your decisions: the four Yes/No answers, plus the chain in your own words** → *pending*

---

# DECISION 4 — Who can see which candidates

**Reference:** audit §28 · **Impact: HIGH**

### What is happening today

**Every recruiter and coordinator in the company can see every candidate**, in
every office. Candidates are not tagged to a branch at all — the information
simply does not exist on the record.

This is different from requisitions, which *are* restricted by office.

### Why this matters

For a single-office business this is harmless and convenient. For several
branches competing for the same people, or an agency running separate desks for
separate clients, it means one branch can read another's entire candidate list —
including who they are talking to and what they expect to be paid.

### Your choices

**Model A — Company-wide.** Anyone in recruitment sees every candidate. (Today.)

**Model B — Office-scoped.** Ahmedabad recruiters see Ahmedabad candidates only.

**Model C — Shared pool with permission.** Candidates belong to an office, but
can be shared with another office when someone authorises it.

### A simple example

Baroda is interviewing a senior welding inspector who is asking for ₹14 lakh.

- **Model A:** Ahmedabad sees the candidate and the expectation, and may approach
  the same person.
- **Model B:** Ahmedabad does not see them at all. If the same person applies in
  Ahmedabad, both branches may unknowingly pursue them.
- **Model C:** Ahmedabad does not see them by default. If Ahmedabad has a
  matching vacancy, Baroda can share the candidate deliberately.

### What changes

| | A | B | C |
|---|---|---|---|
| Effort | none | existing 935 candidates need an office | as B, plus a sharing step |
| Duplicate effort across branches | possible today | more likely | least likely |
| Confidentiality between branches | none | full | controlled |

### What does NOT change

Nothing about requisitions, offers or the workforce. This is only about who can
open a candidate record.

**Your decision: A / B / C** → *pending*

---

# DECISION 5 — Which roles may see and edit candidates

**Reference:** audit §21 · **Impact: MEDIUM**

### What is happening today

Candidate screens are open to anyone at "coordinator level or above" — a general
operations role, **not** a recruitment role. Recruitment has its own three
permissions, but the candidate screens do not use them.

So someone who coordinates inspections, with no recruitment duty, can open and
edit candidate records including salary expectations.

### Why this matters

Candidate records hold personal data, current salary and expected salary. Who
should be able to read that is a business decision, not a technical one.

### Your choices

Mark each role **View / Edit / No access**:

| Role | View | Edit | No access |
|---|---|---|---|
| Recruiter | | | |
| Recruitment manager | | | |
| HR | | | |
| Department manager (the one who asked for the hire) | | | |
| Operations manager | | | |
| Coordinator | | | |
| Administrator | | | |
| Finance | | | |
| Inspector / field staff | | | |
| External client | | | |
| Agency | | | |

### What does NOT change

The recruitment workflow itself. This only decides who may open the screens.

**Your decision: the table above** → *pending*

---

# DECISION 6 — When an approved budget changes

**Reference:** audit §10, §34 F-06 · **Impact: MEDIUM**

### What is happening today

The *number of people* approved is strictly enforced — you cannot recruit 11
against an approved 10. That was deliberately hardened.

The *money* is not. Once a requisition exists, its budget can be edited upward
with no further approval.

### Why this matters

Management approves ₹9 lakh. Nothing stops that becoming ₹14 lakh afterwards, and
nobody is told.

### Your choices

**Option A — Any increase needs approval again.**
**Option B — Only increases beyond a threshold** (for example 10%, or ₹1 lakh).
**Option C — No approval needed** — the recruiter may adjust freely.

### A simple example

₹9 lakh approved. The market says ₹11 lakh — a 22% increase.

- **A:** back to the approver before recruiting continues.
- **B (10% threshold):** back to the approver, because 22% exceeds it. A move to
  ₹9.5 lakh would not go back.
- **C:** changed immediately; nobody is notified.

### What does NOT change

The headcount ceiling stays strictly enforced whichever you choose.

**Your decision: A / B / C — and if B, the threshold** → *pending*

---

# DECISION 7 — Whether offer changes are recorded

**Reference:** audit §8, §34 F-10 · **Impact: MEDIUM**

### What is happening today

Hiring requests keep a detailed history — who did what, when. Candidate stage
movements are logged too.

**Offers are not recorded in either.** The offer's current status is visible, but
not who changed it, when, or what it was before.

The audit could not tell whether this was intentional or an oversight, and said
so rather than guessing.

### Why this matters

An offer is a commercial commitment to a person. If a CTC is questioned later —
by the candidate, by finance, or in a dispute — there is currently no record of
who set it or changed it.

### Your choices

**Option A — Keep a full history** of every offer change: who, what, when, from
which status to which.
**Option B — Leave as is.** Current status only.

### A simple example

A candidate accepts at ₹11 lakh but says they were offered ₹12 lakh.

- **A:** the record shows it was issued at ₹12 lakh, revised to ₹11 lakh on the
  14th by a named person.
- **B:** the system shows ₹11 lakh, accepted. The earlier figure is gone.

**Your decision: A / B** → *pending*

---

# DECISION 8 — What "Recruitment" should open

**Reference:** audit §16, §34 F-11 · **Impact: MEDIUM (screen layout only)**

### What is happening today

Two landing screens exist:

- **Recruitment Home** — a launchpad: links to requests, requisitions, candidates,
  settings.
- **Recruitment Command Centre** — a working board: the funnel, live numbers, what
  needs attention.

### Why this matters

Only that a recruiter clicking "Recruitment" should land where their work is,
without learning which of two doors is the right one.

### Your choices

**Option A — Command Centre is the home**, with links to everything else inside it.
**Option B — Home stays the landing**, with the Command Centre one click away.
**Option C — Keep both as they are** — they serve different people.

### A simple example

A recruiter starts their day and clicks Recruitment.

- **A:** sees 12 candidates awaiting interview and 3 offers expiring — starts working.
- **B:** sees a menu and chooses where to go.
- **C:** as today.

### What does NOT change

No screen is deleted whichever you choose. This is about which one opens first.

**Your decision: A / B / C** → *pending*

---

# DECISION 9 — Mobile (not a decision — a scheduled check)

**Reference:** audit §27 · **Impact: MEDIUM**

The audit **did not** test recruitment on a phone and deliberately made no claim
about it. No automated test covers mobile behaviour either.

**Nothing is being asked of you here.** This is recorded so it is not forgotten:
a separate check on real phone widths is needed, which is a testing task, not a
business decision.

**Your decision: none — schedule the check** → *pending scheduling*

---

# The naming question (not a decision yet)

**Reference:** audit §18, §22 · Part 12 of the brief

The audit found the system is **far less industry-locked than expected**. Words
like SBU, deputation, man-day and department already re-word themselves per
customer. Only a narrow set is genuinely fixed:

- **FIELD / COORD / OFFICE** — the three staff types, written into the code.
- Four example texts on the requisition form mentioning welding, NDT and CSWIP.

**Nothing will be renamed.** Before anything is, the design phase must answer:
*what should a universal product call these?* For example, does a hospital or a
software company recognise "Field / Coordinator / Back office" as the way they
classify staff? That question comes after the decisions above.

---

# Which industries should come first

**Reference:** audit §32

Taken from the audit's evidence, not assumption:

| Industry | Works today | Works with setup only | Needs building |
|---|---|---|---|
| Inspection / TPIA | ✔ | — | — |
| Testing laboratory | ✔ | — | — |
| Construction / projects | ✔ | ✔ | — |
| Manufacturing | — | ✔ | person spec |
| Trading & distribution | — | ✔ | person spec |
| Professional services | — | ✔ | person spec |
| IT services | — | ✔ | person spec · notice period · staff types |
| Healthcare | — | ✔ | person spec · **licence & registration validity** |
| Education | — | ✔ | qualification as a first-class thing |
| Logistics | — | ✔ | person spec |
| Real estate | — | ✔ | person spec |
| **Recruitment agency** | — | ✔ | person spec · client submission steps |
| General B2B | — | ✔ | person spec |

**The same gap appears in 11 of 13 rows: the person specification** — which is
Decision 2. No industry is blocked by the inspection vocabulary that prompted the
audit.

**Question: which industries must EXAACT support first?** Choosing two or three
is more likely to succeed than all thirteen.

**Your decision: which industries, in which order** → *pending*

---

# Decision register

| # | Decision | Business question | Your decision | Impact | Build later? |
|---|---|---|---|---|---|
| OQ-1 | Candidate lifecycle | How does a candidate's progress get tracked? | **Pending** | Very High | Yes |
| OQ-5 | Person specification | How much does approval define about the person? | **Pending** | Very High | Yes |
| OQ-2 | Approval | What needs approving, and by whom? | **Pending** | High | Yes |
| OQ-3 | Candidate visibility | Who can see which candidates? | **Pending** | High | Yes |
| OQ-4 | Candidate access roles | Which roles may view/edit candidates? | **Pending** | Medium | Yes |
| OQ-7 | Budget re-approval | When must a budget increase go back? | **Pending** | Medium | Yes |
| OQ-6 | Offer history | Should offer changes be recorded? | **Pending** | Medium | Yes |
| OQ-8 | Recruitment landing | What should "Recruitment" open? | **Pending** | Medium | UX only |
| OQ-9 | Mobile | — verification, not a decision | **Pending** | Medium | UAT |
| — | Industry priority | Which industries first? | **Pending** | High | Planning |

### Dependencies between them

- **OQ-1 blocks the most.** Reports, dashboards and industry stages all follow it.
- **OQ-5 blocks Decision 2's field work** and is the gap in 11 of 13 industries.
- **OQ-3 and OQ-4 are related** — visibility and role access are two halves of one
  question. Worth answering together.
- **OQ-6, OQ-7, OQ-8** stand alone and can be answered in any order.

---

## Appendix — prepared for after Decision 2 (not a decision)

*Included only so that, once you choose A, B or C on the person specification,
the next step is quick. This is a starting list, not a proposal, and no field
will be added without your decision.*

| Field | Likely classification |
|---|---|
| Qualification | Universal |
| Experience — minimum | Universal |
| Experience — maximum | Universal *(does not exist anywhere today)* |
| Key skills | Universal |
| Discipline / trade | Industry-specific |
| Certification | Industry-specific (critical in inspection, construction) |
| Licence / professional registration | Industry-specific (critical in healthcare) |
| Notice period acceptable | Optional (matters most in IT) |
| Preferred location | Optional |
| Languages | Optional |
| Gender | Configurable — **only where lawful and genuinely required** |

---

# STOP

Nothing further will be built, migrated, renamed or changed until these decisions
are made and explicitly approved.

**Nine answers, and the next phase has its rules.**
