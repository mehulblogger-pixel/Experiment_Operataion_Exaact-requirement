# EXAACT — Business UAT & End-to-End Process Playbook

**For:** the business owner and the people who will actually use EXAACT.
**Not for:** developers. Nothing in this document asks you to write code, run a
database command, open a terminal, or touch a server.

**System under test:** `https://operations.mghaiapps.com`
**Playbook version:** 1.0
**Written against application version:** commit `cd96ace`
**Date written:** 24 September 2026

---

## 0. Read this page first — how this playbook was built, and its one limitation

I want you to trust this document, so I am going to be straight with you about
how it was produced.

**What I did.** I did not write a generic "SaaS testing guide". Every screen
name, every button label, every field name and every message quoted in this
playbook was read out of the actual EXAACT application. I started a real copy of
EXAACT running the exact code that is in your repository, signed in as each
different kind of user, opened each screen, and wrote down exactly what was
there. Where I quote a message the system shows you, that is the real wording,
character for character.

**The one limitation, stated plainly.** The secure environment this playbook was
written in cannot reach `https://operations.mghaiapps.com` over the internet —
outbound connections to it are blocked. So I verified everything against the
**application's own source code and a live copy of it running that same code**,
not against a browser session on your production website.

**What that means for you, practically:**

- If production is running the same version as the repository (commit `cd96ace`),
  everything in this playbook will match what you see.
- If production is running an **older** version, some screens may differ.

**So the very first thing you do is Step 0 below.** It takes two minutes and
tells you whether this playbook matches your live site. Do not skip it.

Anything I could not confirm is explicitly marked **⚠️ NOT VERIFIED** — I have
not guessed anywhere. If you find something marked NOT VERIFIED, that is me
being honest, not me being lazy.

### Step 0 — Confirm this playbook matches your live site (do this first)

| # | What to do | What you should see |
|---|---|---|
| 0.1 | Open `https://operations.mghaiapps.com` in Chrome | The EXAACT sign-in page |
| 0.2 | Sign in with your administrator login | A page whose heading is **"Good morning/afternoon/evening, <your name> 👋"** |
| 0.3 | Look at the left-hand menu strip | You should see area links including **Operations, Sales, Quality & Accreditation, Money, Reporting, Insights, Directory, Admin, Marketplace** |
| 0.4 | Open **Admin** from the left strip | A page headed **"Admin"** |

**If 0.2, 0.3 and 0.4 all match** → this playbook applies to your site. Continue.

**If they do not match** → stop and tell your technical contact: *"Production
does not match commit cd96ace. Please confirm which version is deployed before
we run UAT."* Running this playbook against a different version will produce
false failures and waste your team's time.

---

## 1. The safety rules — read before you touch anything

You told me production already contains **demo records 1–7** and no real
business data yet. These rules keep that true.

### 1.1 The golden rules

> **RULE 1 — Never edit or delete a record you did not create.**
> If you did not create it during this UAT, leave it alone. That includes every
> demo record already on the system.

> **RULE 2 — Everything you create must be labelled as a test.**
> Put `UAT-2026-` at the front of any reference you type, and `UAT TEST -` at
> the front of any person's or company's name. So: `UAT TEST - Rajesh Kumar`,
> `UAT-2026-PIPE-01`.

> **RULE 3 — Do not issue real financial or legal documents.**
> Draft them, look at them, then stop. Section 9 lists exactly which buttons
> cross that line and must not be pressed.

> **RULE 4 — If a screen shows you a technical error, that is a defect.**
> If you ever see the words `SQLSTATE`, `PDOException`, `Fatal error`, or a wall
> of file names and line numbers, **stop, screenshot it, and log a defect**.
> That is never normal, never "just a warning", and never something to work
> around. A real error message written in plain English ("That joining date
> could not be read") is the system working correctly — that is different.

### 1.2 Things you will never be asked to do

This playbook will **never** ask you to run a database command, edit code,
upload files to the server, change configuration, run a migration, delete a
production record, reset the workspace, recreate the company, change someone's
permissions, bypass a security check, or open browser developer tools. If you
think a step requires any of that, you have misread it — ask before proceeding.

### 1.3 Your UAT naming convention

| What you are creating | Type this at the front | Full example |
|---|---|---|
| A client or vendor company | `UAT TEST - ` | `UAT TEST - Northfield Steels` |
| A person (candidate, contact) | `UAT TEST - ` | `UAT TEST - Rajesh Kumar` |
| A job title / designation | `UAT-2026-` | `UAT-2026-Welding Inspector` |
| A project / site reference | `UAT-2026-` | `UAT-2026-PIPE-01` |
| A purchase-order reference | `UAT-2026-` | `UAT-2026-PO-0001` |

EXAACT numbers most records itself (you will see references like `REQ-2607-09`,
`JOB-E0136`). You cannot choose those numbers — which is exactly why you put
your `UAT-` marker in the fields you *can* type, so that every test record is
findable afterwards.

### 1.4 Cleaning up afterwards

Do **not** delete your UAT records at the end. Two reasons: deleting is riskier
than leaving, and your test records are the evidence that UAT happened. Instead,
at sign-off, search for `UAT` (Section 12) and attach the result list to your
sign-off sheet. When you are ready to go live with real data, ask your technical
contact to archive the UAT records in one controlled operation.

---

## 2. How to use this playbook

Each test step is written in the same shape, always in this order:

> **Screen** → **What you click** → **What you type** → **What should happen** → **Where you go next**

A step has passed only if **what actually happened matches "What should
happen" exactly**. "It looked roughly right" is a fail. "It worked but showed a
warning" is a fail — log it.

**Record your result as you go** using the PASS/FAIL sheet in Section 14. Do not
wait until the end; you will not remember.

**Who should run each journey** is stated at the top of that journey. Some
journeys genuinely need two different people signed in on two different
computers (or one person and two browsers), because EXAACT deliberately stops
the same person approving their own work. Where that is required, it is called
out clearly.

---

## 3. System map — what EXAACT actually is

EXAACT is one system that runs **four connected businesses**:

1. **Operations (TPIA)** — a client asks for an inspection; you put a person on
   it; they do it; a report is issued; you bill it.
2. **Recruitment** — somebody needs headcount; it is approved; candidates are
   sourced and selected; one is hired and actually joins.
3. **Marketplace ("Connect")** — external clients post requirements and external
   professionals apply, outside your own payroll.
4. **Money** — what was earned, what can be billed, what was invoiced, what it cost.

Everything else (Quality, Reporting, Insights, Directory, Admin) exists to
support those four.

### 3.1 The screen you land on

When you sign in you land on your **Dashboard** — headed *"Good
morning/afternoon/evening, <your name> 👋"*. What appears on it depends on your
role. This is deliberate: a field inspector and a finance manager should not see
the same page.

### 3.2 The left-hand strip (navigation)

EXAACT deliberately keeps navigation **shallow** — a short list of areas, each
opening a page of tiles, rather than deep folding menus.

| Area | What lives there |
|---|---|
| **Operations** | Work orders, jobs, scheduling, recruitment, vouchers |
| **Sales** | Leads, opportunities, inquiries, quotes, project costing |
| **Quality & Accreditation** | Complaints, nonconformities, equipment, audits, competence |
| **Money** | Billable events, invoices, billing workspace, costs, profit |
| **Reporting** | Report register, endorsements, expediting, compliance |
| **Insights** | Dashboards, analytics, sales and management dashboards |
| **Directory** | Clients, vendors, activities, client and vendor portals |
| **Admin** | Company setup, users, roles, settings, terminology, licence |
| **Marketplace** | Requirements, talent search, verification, organisations |

**You will not see all of these.** You only see areas your role may open. That is
correct behaviour, not a fault.

### 3.3 Three ways to get anywhere

EXAACT gives you three routes to the same place — test all three:

1. **The left strip** → area → tile.
2. **The command palette** — the 🧭 button in the top bar, or press **Ctrl+K**
   (**⌘K** on a Mac). Type a few letters of any screen name and jump straight there.
3. **Search records** — the 🔍 search box, which searches across every register.

### 3.4 One important thing about wording

**The words on your screens are configurable.** Under *Admin → Terminology /
wording*, a company can rename the everyday objects. This playbook uses the
**shipped default wording**:

| This playbook says | Your site may call it |
|---|---|
| Work Order | Call, Service Request, Job Card… |
| Job | Deputation, Assignment… |
| Team Member | Engineer, Inspector, Resource… |
| Office | Branch, IBO… |
| Business Unit | SBU, Division… |
| Requisition | Requirement, Manpower Request… |

If your site uses different words, **the screen is still the right screen** —
match by position and purpose, not by the exact word. Do not log a defect
because a screen says "Requirement" where this playbook says "Requisition".

---

## 4. Role map — who can do what

EXAACT ships with **sixteen** roles. This is the real, verified list.

| Role | In plain English | Works mostly on |
|---|---|---|
| **Master Admin** | Owns the whole workspace, can do everything | Laptop |
| **Admin (legacy)** | Same reach as Master Admin; older accounts | Laptop |
| **Business Director** | Sees the whole business, approves at the top | Laptop |
| **Business Unit Head** | Runs one line of business | Laptop |
| **Branch Manager** | Runs one office | Laptop |
| **Branch Application Manager** | Runs applications for one office | Laptop |
| **Operation Manager** | Runs day-to-day operations | Laptop |
| **Asst. Manager** | Supports operations | Laptop |
| **Coordinator** | Raises work orders, allocates people, runs recruitment | Laptop |
| **Business Development Manager** | Chases new business | Laptop |
| **Key Accounts Manager** | Looks after existing major clients | Laptop |
| **Marketing Manager** | Runs marketing | Laptop |
| **Marketing Executive** | Executes marketing | Laptop |
| **Finance** | Billing, invoicing, costs | Laptop |
| **Senior Inspector** | Does inspections, reviews others' work | **Phone, in the field** |
| **Inspector** | Does inspections | **Phone, in the field** |

### 4.1 How much each role can actually reach — measured, not guessed

I signed in as each role and counted the screens each one can open. Use this as
your expected result when testing access.

| Role | Screens reachable | Areas visible |
|---|---|---|
| Master Admin / Admin | **162** | Sales, Quality, Money, Reporting, Insights, Directory, Admin, Marketplace |
| Business Director | **132** | Sales, Quality, Money, Reporting, Insights, Directory, Admin, Marketplace |
| Branch Manager | **125** | Sales, Quality, Money, Reporting, Insights, Directory, Admin, Marketplace |
| Business Unit Head | **116** | Sales, Quality, Money, Reporting, Insights, Directory, Admin, Marketplace |
| Operation Manager | **103** | Sales, Quality, Money, Reporting, Insights, Directory, Admin, Marketplace |
| Coordinator | **94** | Sales, Quality, Money, Reporting, Insights, Directory, Admin, Marketplace |
| Branch Application Manager | **77** | Sales, Quality, Money, Reporting, Insights, Directory, Admin, Marketplace |
| Asst. Manager | **72** | Quality, Reporting, Insights, Directory, Marketplace |
| Finance | **63** | Sales, Quality, Money, Reporting, Insights, Directory |
| Marketing Manager | **40** | Sales, Quality, Money, Insights, Directory |
| Business Development Manager | **33** | Sales, Quality, Insights, Directory |
| Marketing Executive | **29** | Sales, Quality, Insights, Directory |
| **Inspector / Senior Inspector** | **29** | Quality, Reporting |

**A note on Operations.** The "Areas visible" column above lists the *tile-based*
areas. **Operations is not in that column because it does not use tiles** — it has
its own working home page (Backlog, Schedule, Assignments). Operations still
appears in the left strip for every role that may use it, including Coordinators,
managers and Finance. Do not log "Operations is missing" as a defect on the
strength of this table.

Two things to notice, because they are the point of the design:

- **An inspector sees 29 screens, not 162.** Their phone is not a cut-down
  version of the manager's laptop — it is a different, much shorter world.
- **Finance sees Money but not Operations.** A finance user cannot allocate
  people to jobs. That is deliberate.

⚠️ **NOT VERIFIED — Key Accounts Manager.** The test workspace had no active
Key Accounts Manager account, so I could not measure this role. Create one during
UAT and record what it can reach. Do not assume it matches Business Development Manager.

---

## 5. Module map — every screen, by area

This is the verified tile list, taken from the running application as a Master
Admin. Lower-permission roles see fewer tiles in each area.

### Operations
Operations does not use tiles — it has its own working home page showing
**Backlog**, **Schedule register**, **Assignment register** and data-quality
flags, with **＋ New work order** and **Scheduling board** buttons at the top.

Operations screens: Work orders · New work order · Jobs · Scheduling board ·
Capacity outlook · Recurring services · Contract exceptions · Contract openings ·
Team member availability · Attendance reconciliation · Timesheet · Vouchers ·
Inspector ratings · Recruitment Command Centre · Hiring requests · Requisitions ·
Candidates · New hiring request · New requisition · Add candidate · Positions ·
Departments · Org chart · Import org chart · My approvals · Export data ·
Hiring workflows · Compensation setup · Document templates · Approval rules ·
Approval delegation · Careers page · Project costing.

### Sales
Leads · Opportunities · Inquiries · Quotes · Quote templates · Pre-order
checklist · Project costing · Approvals · Pipelines & funnels · Advertising return.

### Quality & Accreditation
*Everyday quality:* Complaints & appeals · Client acceptance · Customer
satisfaction · Items & samples · Hold & witness points · Evidence review ·
Nonconformity workspace.
*Accreditation registers:* Equipment & calibration · Method library · Decision
rules · Controlled documents · Retention schedule · Data & information control ·
Risks & opportunities · Competence & authorisation · Impartiality · Disclosure
consent · Internal audits · Management review · Confidentiality · Site entry
documents · Identity documents · SLA targets.

### Money
*Billing:* Billable events · Invoice tracker · Billing workspace · Revenue
reconciliation.
*Costs & margins:* Contract number register · Office costs & overheads ·
Month-end cost run · Cost reconciliation · Reimbursable duplication · Business
Unit profit & loss · Profit by work order.

### Reporting
*Reports:* Report register · New report · Endorsement register.
*Expediting:* Expediting register · Project delivery.
*Writing:* Technical writing · Learning insights.
*Governance:* Where we stand.

### Insights
Dashboards · Analytics & performance · Sales dashboard · Management dashboard.

### Directory
Activity · Client register · Vendor register · Client holds · Find duplicates ·
Asset issuance · Client portal · Vendor portal.

### Admin
*Set up your workspace:* Company setup.
*Masters:* Masters.
*People:* User register · Organisation · Roles & permissions · Role workspaces ·
Single sign-on.
*Configuration:* System settings · Backup & restore · AI settings · Terminology /
wording · Form Designer · Build forms with AI · Service scope · Report formats by
service · Company profile.
*Report configuration:* Approver mapping · Approval rules · Report templates ·
Report audit trail.
*Super admin:* Control panel · Companies · Product package.
*Connections:* Ads Pro connection · Licence · MGH Books.

### Marketplace
Requirements · Guided post · Talent search · Passports · Industry taxonomy ·
Qualification taxonomy · Verification desk · Rating-integrity desk · Messages ·
Channels · Agency bench · Market analytics · Organisations · Access requests.

---

## 6. The journeys — index

| # | Journey | Who runs it | Roughly |
|---|---|---|---|
| **A** | Recruitment, end to end — **both** entry routes | Coordinator + an approver | 90 min |
| **B** | TPIA: work order → report → invoice | Coordinator + Inspector + Finance | 90 min |
| **C** | Marketplace | Master Admin | 30 min |
| **D** | Money and billing | Finance | 45 min |
| **E** | Dashboards and "what do I do next" | Every role | 30 min |
| **F** | Search | Any role | 15 min |
| **G** | The inspector's phone | Inspector, **on a real phone** | 45 min |
| **H** | Negative and security tests | Two people | 60 min |

Run **A** and **B** first — they are the business. The rest can follow.

---

## 7. Journey A — Recruitment, end to end

**Who:** a Coordinator (or Master Admin) plus a **second person** to approve.
**Why two people:** EXAACT will not let the person who raised a hiring request
approve their own request. That is a control, and you should prove it works.

### A.0 — The single most important thing to understand about recruitment

EXAACT has **two legitimate ways to start recruiting**, and you must test both.

**Route 1 — the governed route (with approval):**

> Hiring Request → *approved* → Requisition → Candidates → Offer → Hired → **Joined**

Somebody asks for headcount. Somebody with authority approves the ask. Only then
does a requisition exist to recruit against.

**Route 2 — the direct route (no hiring request):**

> Requisition → Candidates → Offer → Hired → **Joined**

A requisition is raised directly, without a preceding hiring request.

**Both routes exist in the live application — I confirmed both doors are
present and working.** This is a known open business decision (recorded
internally as ADR-001). **It is not a defect and must not be logged as one.**
Your job during UAT is to decide, as the business owner, whether you want both
doors open in real life or only Route 1. Record your decision on the sign-off
sheet in Section 17.

### A.1 — Route 1: raise a hiring request

| # | Screen | Click | Type | Should happen |
|---|---|---|---|---|
| A1.1 | Any | Left strip → **Operations** | — | Operations home opens |
| A1.2 | Operations | Press **Ctrl+K**, type `hiring requests`, press Enter | — | Page headed **"Hiring requests"** |
| A1.3 | Hiring requests | **+ New request** | — | Page headed **"New hiring request"** |

The form asks for these (⭐ = required):

| Field | What to enter |
|---|---|
| **Requested by** ⭐ | Your own name |
| Requesting department — who needs the person | Any department |
| **Job title** ⭐ | `UAT-2026-Welding Inspector` |
| Designation | Leave blank or pick any |
| Which department will they join? | Any department |
| Against which position? — optional | Leave blank |
| Job description — what this person will actually do | `UAT test record. Do not action.` |
| **How many people?** ⭐ | `2` |
| Branch | Your office |
| Work location | `UAT-2026-PIPE-01` |
| Project / contract reference | `UAT-2026-PO-0001` |
| Needed by | A date about a month away |
| Employment type | Any |
| Priority | Any |
| Kind of request | Any |
| Reason | `UAT test` |

| # | Click | Should happen |
|---|---|---|
| A1.4 | **Create request** | Returns to the hiring request, showing a reference (e.g. `TPIA2993-HR`) and a status |

**✅ Expected status after creating:** `DRAFT` or `SUBMITTED` — which one depends
on whether your workspace requires approval. Both are correct. Write down which
you saw; you need it for A1.6.

### A.2 — Test the control: you cannot approve your own request

| # | What to do | Should happen |
|---|---|---|
| A2.1 | Still signed in as the **person who raised it**, try to approve it | **You are refused.** There is no approve control available to you, or using one is rejected |

**✅ This is a PASS if you are refused.** EXAACT deliberately stops a requestor
deciding their own request — the same principle that stops one person both
approving and issuing a report. **If you** *can* **approve your own request,
that is a serious defect — log it immediately as Critical.**

The single exception designed into the system is a Master Admin, who may
override. If you tested as Master Admin and it allowed you, repeat the test as a
Coordinator before deciding.

### A.3 — Approve the request (second person)

| # | Screen | Click | Should happen |
|---|---|---|---|
| A3.1 | Sign in as the **approver** (different person) | — | Their dashboard |
| A3.2 | Press **Ctrl+K**, type `my approvals` | Enter | Page headed **"My approvals"** |
| A3.3 | Find your `UAT-2026-Welding Inspector` request | Open it | The request detail |
| A3.4 | Approve it | — | Status becomes **Approved** |

**✅ Expected:** status `APPROVED`. This is the **only** status from which
recruitment may begin.

**Also test the refusal:** try to raise a requisition from a request that is
still `DRAFT` or `SUBMITTED`. **It must be refused.** Recruitment cannot begin
from an unapproved ask.

### A.4 — Turn the approved request into a requisition

| # | Screen | Click | Should happen |
|---|---|---|---|
| A4.1 | The approved hiring request | The control that raises a requisition from it | A new requisition is created, carrying the details across |
| A4.2 | — | — | The requisition shows a reference like `REQ-2607-09` and a status such as **"Open (approved, sourcing)"** |

**✅ Check the details carried over** — job title, headcount (2), department,
branch. If you have to retype them, log a defect.

### A.5 — Route 2: raise a requisition directly

Now test the other door.

| # | Screen | Click | Should happen |
|---|---|---|---|
| A5.1 | Press **Ctrl+K**, type `new requisition` | Enter | Page headed **"New requisition"** |

This is a **five-step wizard**. The steps are labelled:

> **Step 1 Position** → **Step 2 Where & when** → **Step 3 Selection** → **Step 4 Commercial** → **Step 5 Approval**

Use **Next →** to move forward, **← Back** to go back, **Save requisition** to finish.

**Step 1 — Position.** Key fields: Type ⭐, Client, Client contact, Contract
number, PO reference (`UAT-2026-PO-0001`), Quotation ref, Office, Responsible 1 —
Recruiter, Responsible 2 — Reporting manager, Department, Business Unit,
**Designation / position** ⭐ (`UAT-2026-Welding Inspector`), **Which team —
confirmed again when somebody is accepted**, **How many?** ⭐ (`2`).

> 🔑 **Do not skip "Which team".** This is the field that decides whether the
> person you hire is a **field inspector who can be sent to site**, an
> **office-based coordinator**, or **back office**. Section 7.9 tests it properly.

**Step 2 — Where & when.** Project / site (`UAT-2026-PIPE-01`), Locations
required (one per line), Deployment groups, Work model, Deployment location,
Start date, End date, Duration (months), Duty hours, Shift.

**Step 3 — Selection.** Discipline, Speciality, Minimum qualification,
Certificates required, Experience (min years), Relevant experience, Key
responsibilities.

**Step 4 — Commercial.** Other allowances, Food, Accommodation, Travel, Local
conveyance.

**Step 5 — Approval.** Review and **Save requisition**.

| # | Click | Should happen |
|---|---|---|
| A5.2 | **Save requisition** | Requisition created with its own reference and an open status |

**✅ Expected:** this works **without** a hiring request. That is Route 2 — the
direct path — behaving as designed.

### A.6 — Add candidates

| # | Screen | Click | Should happen |
|---|---|---|---|
| A6.1 | The requisition | **Add candidates →** | Page headed **"Add candidate CV"** |

Key fields on that form:

| Field | What to enter |
|---|---|
| Résumé file / …or paste résumé text | Optional — you may paste text and press **Extract →** to have EXAACT read it |
| **Against requisition (management approval)** ⭐ | Your UAT requisition |
| **First name** ⭐ | `UAT TEST - Rajesh` |
| Last name | `Kumar` |
| Experience (years), Email, Mobile | Anything sensible |
| Client, Against call / requirement | Leave as defaulted |
| Trade / discipline, Designation offered | Anything |
| Source | Any |
| Expected rate (₹), Rate type | Anything |
| CV received date | Today |

| # | Click | Should happen |
|---|---|---|
| A6.2 | **Add candidate** (or **Add & select**) | Candidate created, listed against your requisition |
| A6.3 | Repeat for a second candidate `UAT TEST - Priya Sharma` | Two candidates against the requisition |

**✅ Test the "Extract →" feature:** paste a few lines of CV text and press
**Extract →**. Name, experience and contact details should be filled in for you.
This is a convenience — if it misreads, log it as **Low** severity, not a blocker.

### A.7 — Move a candidate through selection

Open a candidate. The candidate register groups people by stage, and the real
stage names are:

> **CV received** → **Submitted to client** → **Shortlisted** → **Interview
> scheduled** → **Offer released** → **Accepted (Hired)**

with these possible endings: **Offer declined (backed out)**, **On hold**,
**Rejected**, **Withdrawn**.

| # | Do | Should happen |
|---|---|---|
| A7.1 | Move the candidate to **Submitted to client** | Stage changes; the change is recorded in the candidate's history |
| A7.2 | Move to **Shortlisted** | Same |
| A7.3 | Use **Schedule** to book an interview (Round, When, Mode, Location / link, Competencies, Panel) | Interview created |
| A7.4 | Record the interview outcome | Recorded |
| A7.5 | Move to **Offer released** | Stage changes |

**✅ Every stage move must be recorded in history** with who did it and when. Open
the candidate's history and confirm your name and today's date are there. If
history is missing, log it as **High** — an unauditable pipeline is a real problem.

### A.8 — The distinction that matters most: **Accepted is not Joined**

This is the single most important test in the whole recruitment journey, so read
this before you do it.

> **"Accepted (Hired)"** means the paperwork is done — you hired them.
> **"Joined"** means **the person actually turned up and started work.**

These are **not the same thing**, and EXAACT deliberately keeps them apart. A
manager needs to be able to say *"8 of 10 hired, but only 6 have actually
joined"* — because the 2 who never showed up are a real business problem.

| # | Screen | Click | Should happen |
|---|---|---|---|
| A8.1 | The candidate | Move to **Accepted (Hired)** | Heading shows the name and **Accepted (Hired)** |
| A8.2 | Same page | Look for **Mark as joined** | The control is present, under a question reading **"Have they actually joined? — hired is not the same as started"** |

**✅ Confirm first that hired ≠ joined.** At this point the person is hired but
**not** joined. Any count of "joined" must **not** include them yet.

Now test the guard rails:

| # | Test | Expected message — **exact wording** |
|---|---|---|
| A8.3 | Try **Mark as joined** on a candidate who is *not* Accepted | *"Only an accepted (hired) person can be marked as joined."* |
| A8.4 | Try it on an accepted person who has **no team record** | *"This person has no team record yet, so their joining cannot be recorded."* |
| A8.5 | Enter a joining date **in the future** | *"A joining date in the future cannot be recorded — mark them joined on the day they start."* |
| A8.6 | Enter a valid joining date (today or earlier) | *"Recorded as joined on YYYY-MM-DD."* |
| A8.7 | Undo the joining | *"Joining removed. This person is still recorded as hired."* |

**✅ A8.7 is the proof the design is right:** removing the joining leaves them
*still hired*. The two facts are independent, exactly as they should be.

**If any of A8.3–A8.5 is allowed through, log it as High.** Those are the
controls that stop your "joined" figure becoming fiction.

### A.9 — Workforce vs Inspector — and the silent-default trap

Another distinction EXAACT takes seriously:

> **Workforce** = everybody employed or engaged by your company.
> **Inspector** = a workforce member whose team role is **Field** — one who can
> be sent to site.
>
> **Every inspector is workforce. Not every workforce member is an inspector.**

This matters because if a back-office hire is silently classified as a field
inspector, they will start appearing in scheduling and capacity screens as
somebody you can send to a site. That is a real operational error.

**The test:**

| # | Test | Expected |
|---|---|---|
| A9.1 | On the requisition, check **"Which team"** | It offers the three real options: **Field — goes to site**, **Coordinator / office-based**, **Back office** |
| A9.2 | Create a requisition and **leave "Which team" unset**, then take a candidate through to Accepted | **EXAACT must ask you what kind of team member this is.** It must **not** quietly decide "Field" for you |
| A9.3 | Choose **Back office** and complete the hire | The person is workforce, but does **not** appear as an inspector available for site deployment |
| A9.4 | Open the Scheduling board and look for that person | They must **not** be offered as someone you can send to site |

**🚩 A9.2 is the critical one. If the system silently makes them a field
inspector without asking, log it as High and stop — do not continue hiring
through that path until it is resolved.**

### A.10 — Multi-vacancy: one hire must not close a two-person requisition

| # | Test | Expected |
|---|---|---|
| A10.1 | Your requisition asked for **2** people. Hire **one** | The requisition stays **open**, showing 1 of 2 filled |
| A10.2 | Hire the second | Now it shows 2 of 2 |

**🚩 If hiring the first person closes a two-person requisition, log it as
Critical** — you would silently lose half your headcount.

### A.11 — Sourcing from more than one place

On the requisition detail you will see **Add source**. This is how one
requirement is filled from several places — some from your own payroll, some
through an agency — while staying **one** requirement.

| # | Test | Expected |
|---|---|---|
| A11.1 | **Add source**, set *How many people* to `1` | A source is recorded against the requisition |
| A11.2 | Try to promise **more people than the requisition asked for** | **Refused**, or clearly flagged as over-committed |

**✅ A11.2 must not silently accept over-allocation.** Being shown the problem is
acceptable; silently swallowing it is not.

### A.12 — Recruitment Command Centre

| # | Screen | Click | Should happen |
|---|---|---|---|
| A12.1 | Press **Ctrl+K**, type `recruitment` | Enter | Page headed **"Recruitment Command Centre"** |

Confirm these sections are present and that your UAT records appear in the
numbers: **Needs attention today** · **Ownership, deployment & the requirement
tracker** · **Hiring demand & pipeline volume** · **Manpower P&L** ·
**Conversion, speed & cost** · **Funnel, outcomes & source performance** ·
**Trend, department load & hiring-manager activity** · **Why we lose candidates
& how long they wait**.

**✅ The most important check:** find where it reports **hired** against
**joined**. Those two numbers must differ in the way your test data says they
should. If they are always identical, the Accepted/Joined distinction is not
reaching the reports — log it as High.

---

## 8. Journey B — TPIA: from a client's request to money in

**Who:** a Coordinator, an Inspector, and a Finance user. You need at least
**two different people** for the report approval test (Section 8.5).

The chain you are testing:

> **Work order** (the client asks) → **Job** (you put a person on it) →
> **Work done** → **Report** (drafted, approved, issued) → **Billing** →
> **Invoice** → **Voucher** (the inspector's expenses)

### B.1 — Raise a work order

| # | Screen | Click | Should happen |
|---|---|---|---|
| B1.1 | Left strip → **Operations** | — | Operations home, showing **Backlog**, **Schedule register**, **Assignment register** |
| B1.2 | Operations | **＋ New work order** | Page headed **"New work order"** |

The form is long because a real inspection order is. The fields that matter:

| Field | What to enter |
|---|---|
| Client | Pick a demo client, **or** **+ Add new** → `UAT TEST - Northfield Steels` |
| Quote — what was sold | Leave blank if you have none |
| Contract number — from the quote | `UAT-2026-PO-0001` |
| Line item on the quote | Leave blank |
| Vendor / manufacturer (site) | **+ Add new** → `UAT TEST - Pipeworks Ltd` |
| Inspection is at the client's own premises | Leave unticked |
| Shared folder / drive link | Leave blank |
| Business Unit | Any |
| Activity code | Any |
| Service line (sets the report format) | Any — **note which**, it decides the report format |
| Type of inspection | Any offered |
| Product category | Any |
| Site (client's site) | `UAT-2026-PIPE-01` |
| Work order received | Today |
| Client's required date | A week away |
| Shape of the engagement | Whichever matches your test |
| How many days, continuously? | `2` |
| Outstation — the team member travels | Tick it (so you can test travel expenses later) |
| Date 1 / Date 2 | Two dates next week |
| **Contracting Office — who holds the order** | Your office |
| **Executing Office — who does the work** | Your office |
| Forward to coordinator | Yourself |
| Region | Any |

| # | Click | Should happen |
|---|---|---|
| B1.3 | **Save work order** | Work order created with its own reference |

**✅ Expected status.** If you set an **Executing Office**, the work order should
be **FORWARDED**. If you left it blank, it should be **OPEN**. Both are correct —
check you got the one matching what you entered.

**Also test:** the **+ Add another date** button should let you add more
inspection dates before saving.

### B.2 — Data-quality flags

Go back to the Operations home and look at **"Data-quality flags (advisory —
complete or override on the call)"**.

**✅ Expected:** if you left optional-but-important fields blank, your work order
appears here as an advisory flag. The word **advisory** is the point — it tells
you, it does not block you. If a flag *blocks* you from proceeding, log it as
Medium; that is not what advisory means.

### B.3 — Allocate a person (create the job)

| # | Screen | Click | Should happen |
|---|---|---|---|
| B3.1 | Operations home → **Backlog** | Find your UAT work order, click **Allocate** | The allocation screen |
| B3.2 | — | Choose a team member and the dates | — |
| B3.3 | — | Save | A **Job** is created with a reference like `JOB-E0136` |

**✅ Expected:** the work order's status moves to **ALLOCATED**.

**Also test the Scheduling board:**

| # | Screen | Should show |
|---|---|---|
| B3.4 | Press **Ctrl+K** → `scheduling board` | Page headed **"Scheduling board"** with **Who is where**, **Who's free?** and **Needs a person** |
| B3.5 | Use **‹ Earlier / Today / Later ›** and the **From / To** dates | The board moves through time |
| B3.6 | **Who's free?** | People with no work on those dates |

**✅ Your newly allocated person must appear in "Who is where" on those dates,
and must NOT appear in "Who's free?" for the same dates.** If they appear in
both, log it as High — you will double-book people.

### B.4 — The inspector does the work

Hand over to the **Inspector** (ideally on a real phone — see Journey G).

| # | Screen | Click | Should happen |
|---|---|---|---|
| B4.1 | Inspector signs in | — | Their own short dashboard |
| B4.2 | **My Jobs** | — | Page headed **"My Jobs"**, listing the job you just allocated |
| B4.3 | Open the job | — | The job detail |

**✅ The inspector must see the job you allocated.** If they do not, stop —
nothing downstream will work.

### B.5 — The report: drafted, approved, issued

This is where EXAACT's most important financial control lives.

| # | Screen | Click | Should happen |
|---|---|---|---|
| B5.1 | Press **Ctrl+K** → `new report` | Enter | Page headed **"New report"** |
| B5.2 | **Start from a work order — everything known is filled in for you** | Pick your UAT work order, press **Load** | The form fills itself in |

Remaining fields: **Report type** ⭐, Title / subject, Inspection date, Office /
branch, Business Unit, Client, Vendor / manufacturer, Purchase order, Product
category, **Inspector**, **Approver**, Remarks.

| # | Click | Should happen |
|---|---|---|
| B5.3 | **Generate report** | Report created in status **DRAFT** |

**✅ Check "Load" actually saved you work.** If it fills in nothing, log it as
Medium — that button is the whole point of starting from a work order.

Now walk the report through its life. The real statuses are:

> **DRAFT** → **SUBMITTED / VETTING** → **UNDER REVIEW** → **APPROVED** →
> **ISSUED**, with **Sent back** as the return path.

| # | Do | Expected |
|---|---|---|
| B5.4 | Submit the report | Moves to **VETTING** (if vetting is switched on) or **UNDER REVIEW** |
| B5.5 | As the reviewer, **send it back** with a remark | Returns to **DRAFT**; **a remark is required** |
| B5.6 | Try to send back **without** a remark | **Refused** |
| B5.7 | Fix and resubmit, then approve | **APPROVED** |

#### 🔒 B5.8 — The segregation-of-duties test (do not skip)

> **The person who approved a report must not be the person who issues it.**

| # | Test | Expected |
|---|---|---|
| B5.8 | Signed in as **the same person who approved it**, try to issue it | **Refused** |
| B5.9 | Sign in as a **different** authorised person and issue it | Report becomes **ISSUED** and is **locked** |

**🚩 If the same person can both approve and issue, log it as Critical.** This is
a financial and accreditation control, not a nicety.

| # | Test | Expected |
|---|---|---|
| B5.10 | Try to edit an **ISSUED** report | **Refused** — it is locked |
| B5.11 | Use the revise path instead | A **new draft** is created; the issued one stays untouched |

**✅ B5.11 is correct behaviour** — history is never rewritten.

### B.6 — Close the job

| # | Screen | Click | Should happen |
|---|---|---|---|
| B6.1 | **My Jobs** (as the inspector) or the Job register | **Close job & record expenses** | The close screen |
| B6.2 | Enter **Report upload date** ⭐ and expenses: Travel (₹), Local conveyance (₹), Food (₹), Lodging (₹), Misc (₹), Expense notes | — | — |
| B6.3 | The close screen | **Upload & Close** | Job closes, with a clear confirmation |

**✅ Expected:** the job moves to **Closed**, and report approval becomes
**PENDING**.

| # | Test | Expected |
|---|---|---|
| B6.4 | Try to close the **same job again** | **Refused** |

### B.7 — Billing readiness — a check, not a document

> **Billing readiness is a check that everything needed to bill is present. It
> is not an invoice and it raises no money.** It tells you an invoice can safely
> be raised.

| # | Screen | Click | Should happen |
|---|---|---|---|
| B7.1 | Left strip → **Money** → **Billing workspace** | — | Page headed **"Work waiting to be billed"** |
| B7.2 | Set **Month (by closed date)** to this month, press **Apply** | — | Your closed UAT job is listed with an **Include JOB-…** tick box |

**✅ Your job must appear here once closed, and must not appear before it was
closed.**

### B.8 — Invoice

> ⚠️ **STOP — read Section 9 before pressing anything on this screen.**

| # | Screen | Click | Should happen |
|---|---|---|---|
| B8.1 | Billing workspace | Tick **Include** for your UAT job only | Only your job is ticked |
| B8.2 | — | **Draft invoice for the ticked work** | A **draft** invoice is created |
| B8.3 | Open **Money → Invoice tracker** (or **Invoices**) | — | Page headed **"Invoices"** with columns Invoice, Customer, Branch, PO / contract, Tax, Settled, Outstanding, Due, Status |
| B8.4 | Find your draft | — | It is there, in **draft** status |

**🛑 STOP HERE. Do not send, finalise, approve or issue the invoice.** Drafting
proves the chain works. Issuing creates a real financial document. See Section 9.

**✅ The end-to-end proof:** open your draft invoice and confirm it traces back
to your work order, your job and your client. If the chain is broken anywhere,
log it as Critical — that is the revenue path.

### B.9 — Voucher (the inspector's expenses)

| # | Screen | Click | Should happen |
|---|---|---|---|
| B9.1 | Press **Ctrl+K** → `vouchers` | Enter | Page headed **"Inspector vouchers"** |
| B9.2 | **Open / create** for your inspector and this month | — | A voucher |
| B9.3 | Check the expenses you entered at B6.2 are on it | — | They are |

The voucher's life is: **DRAFT → SUBMITTED → APPROVED → PAID**.

| # | Test | Expected |
|---|---|---|
| B9.4 | Submit it as the inspector | **SUBMITTED** |
| B9.5 | Try to approve it **as the same inspector** | **Refused** |
| B9.6 | Approve as a manager | **APPROVED** |

**🛑 Do not mark it PAID** — that asserts money left the business. See Section 9.

### B.10 — Profit

| # | Screen | Should show |
|---|---|---|
| B10.1 | **Money → Profit by work order** | Your UAT work order with revenue, cost and margin |
| B10.2 | **Money → Business Unit profit & loss** | Your test figures included |

**✅ Sanity check:** the cost should include the expenses you entered at B6.2. If
your travel and lodging are missing from the cost, log it as High — margins will
be overstated.

---

## 9. 🛑 The "do not press" list — real documents and real money

Everything above is safe: it creates draft and internal records. The buttons
below **create real business documents or assert real financial facts**. During
UAT, **do not press them** unless your owner has explicitly said so in writing
for a specific test.

| Screen | Button | Why it is off-limits |
|---|---|---|
| Invoices | Finalise / issue / send an invoice | Creates a real financial document, may be numbered in a legal sequence, may e-mail the client |
| Vouchers | **PAID** | Asserts money has actually left the business |
| Candidate | **Standard offer letter →** | A real offer to a real person |
| Candidate | **Standard appointment letter →** | A real employment document |
| Candidate | **Confirmation of employment →** | A real employment document |
| Candidate | **Internship offer letter →** | A real employment document |
| Candidate | **Relieving & experience letter →** | A real employment document |
| Report | Issue / finalise a report on a **real** client's work | A real issued report a client may rely on |
| Careers page | Publishing a live job advert | Makes it publicly visible; real people may apply |
| Any screen | Anything that sends e-mail or a message to an outside party | Reaches real people |

**Generating a draft or a preview is fine.** The line is: does anyone outside
this UAT team receive it, rely on it, or does it assert money moved?

**If in doubt, do not press it. Ask.**

### 9.1 — A safe way to test documents anyway

You can still prove the document machinery works:

| # | Test | Expected |
|---|---|---|
| 9.1.1 | On your **UAT TEST -** candidate, press **Draft offer** | A draft offer is prepared |
| 9.1.2 | Preview the offer letter without sending it | You can read it |
| 9.1.3 | Check it shows the right name, role, salary and dates | It does |
| 9.1.4 | **Do not send it** | — |

Because the candidate is named `UAT TEST - …` and does not exist, nothing can
reach a real person even if a draft is left behind.

---

## 10. Journey C — Marketplace ("Connect")

**Who:** Master Admin. **Roughly 30 minutes.**

The Marketplace is a different business from your own operations. Here, **clients
post requirements** and **external professionals apply**. A professional is
**not your staff** — that is the whole point. They only become your workforce if
you actually hire them.

### C.1 — The Marketplace area

| # | Screen | Should show |
|---|---|---|
| C1.1 | Left strip → **Marketplace** | Tiles: Requirements · Guided post · Talent search · Passports · Industry taxonomy · Qualification taxonomy · Verification desk · Rating-integrity desk · Messages · Channels · Agency bench · Market analytics · Organisations · Access requests |

### C.2 — Post a requirement

| # | Screen | Click | Should happen |
|---|---|---|---|
| C2.1 | Marketplace | **Requirements** | The requirements register |
| C2.2 | — | Create a new requirement, titled `UAT-2026-Marketplace Welding Inspector` | Created in **DRAFT** |
| C2.3 | — | Post it | Moves to **OPEN** |

A marketplace requirement's life is:

> **DRAFT → OPEN → SHORTLISTING → AWARDED → CLOSED**, with **CANCELLED** and
> **EXPIRED** as the ways out.

| # | Test | Expected |
|---|---|---|
| C2.4 | From **OPEN**, move to **SHORTLISTING** | Allowed |
| C2.5 | From **SHORTLISTING**, go back to **OPEN** (reopen) | Allowed |
| C2.6 | Try to jump straight from **DRAFT** to **AWARDED** | **Refused** — an illegal move is rejected, not quietly applied |
| C2.7 | **CLOSE** it, then try to reopen | **Refused** — closed is final |

**✅ C2.6 and C2.7 are the real tests.** A system that lets you skip steps will
eventually let somebody award work that was never advertised.

### C.3 — Applications

An application's life is:

> **APPLIED → SHORTLISTED → OFFERED → ACCEPTED**, with **DECLINED**,
> **WITHDRAWN** and **REJECTED** as the ways out.

| # | Test | Expected |
|---|---|---|
| C3.1 | Take an application from **APPLIED** to **SHORTLISTED** | Allowed |
| C3.2 | Try to jump **APPLIED → ACCEPTED** directly | **Refused** |
| C3.3 | **Award** the requirement to a shortlisted applicant | The requirement becomes **AWARDED** *and* that application becomes **ACCEPTED** — both together |
| C3.4 | Check the other applicants | Not left stranded in a misleading state |

**✅ C3.3 is the important one** — the award and the acceptance must move
together. If a requirement can be AWARDED while every application is still
APPLIED, log it as High.

### C.4 — Talent search and verification

| # | Screen | Test | Expected |
|---|---|---|---|
| C4.1 | **Talent search** | Search for a skill | Professionals listed with their qualifications |
| C4.2 | **Verification desk** | Open it | Credentials waiting to be checked |
| C4.3 | — | Confirm an **unverified** credential is clearly marked as unverified | It is |

**✅ C4.3 matters commercially.** If an unverified certificate looks identical to
a verified one, you could place somebody on a site on the strength of a
certificate nobody checked. Log as High if they are indistinguishable.

### C.5 — Professional ≠ your workforce

| # | Test | Expected |
|---|---|---|
| C5.1 | Take a marketplace professional. Open **Operations → Scheduling board** | They are **not** offered as one of your own people to deploy |
| C5.2 | Open your **workforce / team member** list | They are **not** in it |

**🚩 If a marketplace professional appears in your own workforce without being
hired, log it as High** — you would be scheduling somebody who does not work for you.

⚠️ **NOT VERIFIED — the external-facing side.** This playbook verifies the
Marketplace **from the inside** (your staff's view). I could not test the
**public-facing portals** — a real external client posting a requirement, or a
real external professional applying from their own login — because that needs
external accounts and a reachable public site. **Test these separately before you
open the Marketplace to outside users**, and treat them as unproven until you do.

---

## 11. Journey D — Money and billing

**Who:** Finance. **Roughly 45 minutes.**
**Prerequisite:** Journey B, at least as far as B.7.

### D.1 — What Finance can and cannot do

Before anything else, prove the boundary:

| # | Signed in as Finance, try | Expected |
|---|---|---|
| D1.1 | Open **Money → Invoices** | ✅ Opens |
| D1.2 | Open **Money → Billing workspace** | ✅ Opens, headed **"Work waiting to be billed"** |
| D1.3 | Open **Work orders** | ✅ Opens — Finance may *look* at operations |
| D1.4 | Try to **allocate a person to a job** | ❌ **Refused** — you see a page headed **"Allocate a job"** explaining you may not do this |
| D1.5 | Open **My Jobs** | ❌ **Refused** — a page headed **"My jobs"** explaining why |
| D1.6 | Open **Admin** | ❌ **Refused** — *"This area isn't available to your role."* |
| D1.7 | Open **New requisition** | ❌ **Refused** |

**✅ Note what "refused" looks like, because this is a deliberate design choice:**
EXAACT **explains** why you cannot do something, on a proper page, instead of
silently bouncing you back to the dashboard. A silent bounce with no explanation
is a defect — log it as Medium.

### D.2 — The money screens

| # | Screen | What to check |
|---|---|---|
| D2.1 | **Billable events** | Work that has become billable |
| D2.2 | **Invoice tracker** | Invoices and what is outstanding |
| D2.3 | **Billing workspace** | Closed work waiting to be billed |
| D2.4 | **Revenue reconciliation** | Revenue recorded vs revenue expected |
| D2.5 | **Contract number register** | Money tracked against client contract numbers |
| D2.6 | **Office costs & overheads** | Costs by office |
| D2.7 | **Month-end cost run** | The monthly cost process |
| D2.8 | **Cost reconciliation** | Costs that do not agree |
| D2.9 | **Reimbursable duplication** | Expenses claimed twice |
| D2.10 | **Business Unit profit & loss** | Profit by line of business |
| D2.11 | **Profit by work order** | Profit on one job |

**✅ Every one must open without a technical error and show either data or a
plain-English empty state** ("Nothing to show yet"). A blank white page is a defect.

### D.3 — Drafting an invoice

| # | Screen | Click | Should happen |
|---|---|---|---|
| D3.1 | **Billing workspace** | Set **Month (by closed date)**, **Apply** | Your closed UAT job listed |
| D3.2 | — | Tick **Include** for your UAT job **only** | Only yours ticked |
| D3.3 | — | **Draft invoice for the ticked work** | Draft invoice created |

Also available: **Draft combined invoice (all projects)** and **Draft this project
only**.

| # | Test | Expected |
|---|---|---|
| D3.4 | Use **Draft this project only** with your UAT project | Only your UAT work is on it |
| D3.5 | Open the draft and check the amounts | They match the job |

**🛑 Do not finalise, send or issue the invoice** — Section 9.

### D.4 — Duplicate billing

| # | Test | Expected |
|---|---|---|
| D4.1 | Go back to the Billing workspace. Is your job **still** offered as unbilled? | It should **not** be — it is now on a draft invoice |
| D4.2 | Try to draft a **second** invoice for the same job | **Refused**, or the job is clearly flagged as already billed |

**🚩 If you can bill the same job twice with no warning, log it as Critical.**
That is the single most expensive kind of error a billing system can make.

### D.5 — Reimbursable duplication

| # | Screen | Test | Expected |
|---|---|---|---|
| D5.1 | **Reimbursable duplication** | Open it | Expenses that look like they were claimed twice |
| D5.2 | — | Check whether your UAT voucher expenses appear | They should **not**, since you claimed them once |

---

## 12. Journey E — Dashboards and "what do I do next"

**Who:** every role, one at a time. **Roughly 30 minutes total.**

EXAACT tries to answer one question the moment you sign in: **what should I do
next?** Test that it answers it honestly for each role.

### E.1 — Per-role dashboard test

For **each** role you use in real life, sign in and check:

| # | Check | Expected |
|---|---|---|
| E1.1 | The greeting | **"Good morning/afternoon/evening, <name> 👋"** |
| E1.2 | Is what you see relevant to your job? | An inspector sees their own work; a finance user sees money; a coordinator sees allocation |
| E1.3 | Is anything shown that you have **no permission to act on**? | **Nothing should be** |
| E1.4 | Are the most urgent items **at the top**? | Yes |
| E1.5 | Does every number link somewhere useful? | Clicking a count opens the list behind it |

**🚩 E1.3 is the sharp one.** If a dashboard offers you a button you are not
allowed to press, that is a defect — log it as Medium, with the role and the button.

### E.2 — My Work

| # | Screen | Click | Should happen |
|---|---|---|---|
| E2.1 | Press **Ctrl+K** → `my work` | Enter | Page headed **"My Work"** |
| E2.2 | — | — | Everything waiting on **you**, across every module, in one place |

**✅ Cross-check:** pick three things you know are waiting on you (an approval you
were asked for, a job you were allocated, a voucher to approve). All three should
be on My Work. If something is missing, log it as High — people will rely on this
page and miss work.

### E.3 — Command Centre

| # | Screen | Should show |
|---|---|---|
| E3.1 | Press **Ctrl+K** → `command centre` | Page headed **"Command Centre"** — attention, money and health for the whole business |

Available to management and finance roles, not to inspectors. Confirm an
inspector cannot open it.

### E.4 — The counts must be true

Pick any count badge on any area tile and open it.

| # | Test | Expected |
|---|---|---|
| E4.1 | Count says *N* | The list behind it has exactly *N* rows |

**🚩 If the badge says 5 and the list has 12, log it as High.** Once people learn
the numbers lie, they stop using them.

---

## 13. Journey F — Search

**Who:** any role. **Roughly 15 minutes.**

### F.1 — The three ways to find things

| # | Method | How | Expected |
|---|---|---|---|
| F1.1 | Record search | The 🔍 box, **"Search every register"** | Page headed **"Search"** with **Everything** and category tabs |
| F1.2 | Command palette | 🧭 in the top bar, or **Ctrl+K** / **⌘K** | Type a few letters of a screen name, jump straight to it |
| F1.3 | Within a register | The **Search** box on a register | Filters that register |

### F.2 — Find your UAT records

| # | Search for | Expected |
|---|---|---|
| F2.1 | `UAT` | Every UAT record you created, across work orders, requisitions, candidates, clients, vendors |
| F2.2 | `UAT TEST - Rajesh` | Your UAT candidate |
| F2.3 | `UAT-2026-PIPE-01` | Your UAT work order and requisition |
| F2.4 | Your UAT work order reference | That work order |

**✅ F2.1 is also your clean-up tool** — it is how you produce the list of test
records for your sign-off sheet.

### F.3 — Search respects permissions

| # | Test | Expected |
|---|---|---|
| F3.1 | As an **Inspector**, search `UAT` | Only things an inspector may see. **Candidates and invoices must not appear** |
| F3.2 | As **Finance**, search `UAT` | Money records yes; recruitment candidates no |

**🚩 If search returns records the user cannot open, log it as High.** Search
must not become a way around permissions — even showing a title can leak a
client name or a salary.

### F.4 — Search behaves sensibly

| # | Test | Expected |
|---|---|---|
| F4.1 | Search for gibberish (`zzzqqq`) | A plain **"Nothing matches that."** — not an error, not a blank page |
| F4.2 | Search for a single letter | Either results or a sensible "type a bit more" message |
| F4.3 | Search with odd characters (`'`, `%`, `"`) | Handled calmly. **No `SQLSTATE` or technical error** |

**🚩 F4.3 is a security test.** If typing a quote mark produces a database error,
**stop UAT and log it as Critical immediately.**

---

## 14. Journey G — The inspector's phone

**Who:** a real Inspector, on a **real phone**, ideally with patchy signal.
**Roughly 45 minutes.**

> **Do not test this by shrinking your laptop browser window.** Use an actual
> phone. Inspectors use EXAACT standing on a factory floor, in gloves, in poor
> light, on mobile data. That is the real test.

### G.1 — What I already measured

To be straightforward about what is already known: I tested the inspector's main
screens at phone size (390 × 844, a typical iPhone) and measured:

| Screen | Sideways scrolling | Tables readable on a phone |
|---|---|---|
| Dashboard | ✅ None | ✅ Yes |
| My Jobs | ✅ None | ✅ Yes |
| My Work | ✅ None | ✅ Yes |

So the layout is sound. What I **cannot** test from here is a real phone, a real
thumb, real signal and real daylight. That is what you are doing.

### G.2 — Signing in on a phone

| # | Test | Expected |
|---|---|---|
| G2.1 | Open the site on the phone browser | Sign-in page fits the screen, no sideways scrolling |
| G2.2 | Sign in | Dashboard, greeting by name |
| G2.3 | Can you read everything without zooming? | Yes |
| G2.4 | Rotate to landscape and back | Layout adapts, nothing cut off |

### G.3 — The inspector's world is short

| # | Test | Expected |
|---|---|---|
| G3.1 | Look at the whole menu | About **29** destinations — not 162 |
| G3.2 | Which areas appear? | **Quality** and **Reporting** only |
| G3.3 | Is anything offered you cannot use? | No |

**✅ This shortness is the design, not a missing feature.** Do not log "I can't
see Invoices" as a defect for an inspector.

### G.4 — My Jobs, the screen that matters

| # | Screen | Test | Expected |
|---|---|---|---|
| G4.1 | **My Jobs** | Open it | Page headed **"My Jobs"**, listing your work |
| G4.2 | — | Read a row without scrolling sideways | You can |
| G4.3 | — | Tap a job | It opens |

### G.5 — Closing a job in the field

| # | Test | Expected |
|---|---|---|
| G5.1 | Tap **Close job & record expenses** | The close form |
| G5.2 | Enter **Report upload date** ⭐ | Date picker works with a thumb |
| G5.3 | Enter Travel, Local conveyance, Food, Lodging, Misc (₹) and notes | Number keypad appears for money fields |
| G5.4 | Tap **Upload & Close** | Job closes; a clear confirmation |
| G5.5 | Can you attach a photo or file from the phone? | Yes |

**✅ G5.3 matters more than it sounds.** If a money field brings up a letter
keyboard instead of a number pad, every inspector pays for it several times a
day. Log as Medium.

### G.6 — Thumbs, not mice

| # | Test | Expected |
|---|---|---|
| G6.1 | Tap each main button with your thumb, not a fingernail | Each is easy to hit first time |
| G6.2 | Any button you keep missing? | None |

**Known, already measured:** the main working buttons meet the 44-pixel
touch-target standard. Four small pieces of *navigation furniture* are below it —
the sidebar close **✕** (20px), a sidebar group toggle (33px), the search **🔍**
icon (34px), and the **🧭 Go to…** palette button (32px). These are **known
observations, already recorded**. If you find them fiddly, note it against this
section rather than raising it as a new defect.

### G.7 — Bad signal

| # | Test | Expected |
|---|---|---|
| G7.1 | Turn mobile data off, then tap something | A sensible message — **not** a technical error or a frozen screen |
| G7.2 | Turn data back on and retry | It works |
| G7.3 | Submit a form as the signal drops | Either it saves, or it tells you clearly that it did not. **Never silently lose the data** |

**🚩 G7.3 is the one that will hurt you in real life.** If an inspector fills in a
close form on a factory floor and it vanishes silently, they will stop trusting
the app. Log any silent loss as **High**.

---

## 15. Journey H — Negative and security tests

**Who:** two people, one of them an administrator. **Roughly 60 minutes.**

These are the tests that matter most, because they prove EXAACT protects you
when somebody makes a mistake or goes looking where they should not.

### H.1 — Not signed in

| # | Test | Expected — **verified** |
|---|---|---|
| H1.1 | Sign out. Try to open the work order register directly | Sent to the **sign-in page** |
| H1.2 | Try the candidate register directly | Sent to the **sign-in page** |
| H1.3 | Try the invoices screen directly | Sent to the **sign-in page** |
| H1.4 | Sign in | You arrive at the application |

**🚩 If any screen opens with real data while signed out, stop UAT immediately
and log it as Critical.**

### H.2 — Signed in, but not allowed

I verified this one thoroughly. Signed in as an **Inspector**, every one of these
was correctly refused:

| # | As an Inspector, try to open | Expected — **verified** |
|---|---|---|
| H2.1 | Work order register | ❌ Refused |
| H2.2 | Candidate register | ❌ Refused — *"You don't have permission to open People & hiring. Ask your workspace administrator if this should be available to you."* |
| H2.3 | Invoices | ❌ Refused |
| H2.4 | New requisition | ❌ Refused |
| H2.5 | Allocate a job | ❌ Refused |
| H2.6 | Admin | ❌ Refused — *"This area isn't available to your role."* |
| H2.7 | **My Jobs** | ✅ **Allowed** — this is their own work |

**✅ Note the quality of the refusal.** EXAACT tells you *what* you cannot open
and *what to do about it* ("ask your workspace administrator"). That is the
standard. A blank page, a silent bounce to the dashboard with no message, or a
technical error is a defect — log it as Medium.

### H.3 — Guessing at other people's records

| # | Test | Expected |
|---|---|---|
| H3.1 | Open one of your own records and note the number at the end of the web address | e.g. `...?id=292` |
| H3.2 | Change the number to another one and press Enter | Either the record (if you are allowed it) or **"Nothing matches that."** |
| H3.3 | As an **Inspector**, try the number of a **candidate** record | **"Nothing matches that."** — never the candidate's details |

**🚩 H3.3 is critical.** If changing a number in the address bar shows an
inspector somebody's salary or a client's commercial terms, **stop UAT and log it
as Critical.**

**✅ Note the wording.** EXAACT says *"Nothing matches that."* rather than "you
are not allowed to see record 41" — because the second version confirms the
record exists. That is correct and deliberate.

### H.4 — Approving your own work

EXAACT enforces the same principle in three places. Test all three:

| # | Test | Expected |
|---|---|---|
| H4.1 | Raise a hiring request, then try to approve it yourself | ❌ **Refused** |
| H4.2 | Approve a report, then try to issue that same report yourself | ❌ **Refused** |
| H4.3 | Submit your own expense voucher, then try to approve it yourself | ❌ **Refused** |

**🚩 Any of these succeeding is Critical.** These are the controls an auditor
will ask about.

### H.5 — Bad data in forms

| # | Test | Expected |
|---|---|---|
| H5.1 | Save a form with **required** (⭐) fields empty | Refused, with each missing field clearly named |
| H5.2 | Enter an end date **before** the start date | Refused or clearly flagged |
| H5.3 | Enter a joining date in the future | *"A joining date in the future cannot be recorded — mark them joined on the day they start."* |
| H5.4 | Enter letters in a money field | Refused, plainly |
| H5.5 | Enter a negative headcount (`-5`) | Refused |
| H5.6 | Enter an enormous number (`999999999`) | Refused or handled calmly — **never a technical error** |
| H5.7 | Paste 5,000 characters into a short text box | Handled calmly |
| H5.8 | Type `'` `"` `%` `<script>` into a text box and save | Stored and shown as plain text. **No `SQLSTATE`, no pop-up box appearing** |

**🚩 H5.8 has two failure modes, both Critical:**
- a database error appears → log immediately;
- a pop-up box appears when you view the record → log immediately.

### H.6 — Form protection (verified)

| # | Test | Expected — **verified** |
|---|---|---|
| H6.1 | Open a form, leave it sitting for a long time, then submit | Either it saves, or you are returned to the form. **No record is created incorrectly** |
| H6.2 | Use the browser **Back** button after saving, then save again | No duplicate record |
| H6.3 | Double-click a save button | **One** record, not two |

I verified the underlying protection directly: a form submitted without EXAACT's
security token is **rejected and creates nothing**. I confirmed no record was
written.

### H.7 — Sessions

| # | Test | Expected |
|---|---|---|
| H7.1 | Sign in, then sign out, then press **Back** | You do **not** get back in |
| H7.2 | Sign in on two browsers at once | Both work, or you are told why not |
| H7.3 | Change your password, then use the old one | Refused |

### H.8 — Deliberately breaking things

| # | Test | Expected |
|---|---|---|
| H8.1 | Ask for a web address that does not exist | A friendly **"Not found"** page with a way back — **not** a technical error |
| H8.2 | Try to close a job that is already closed | **Refused** |
| H8.3 | Try to bill work that is already billed | **Refused** |
| H8.4 | Try to edit a report that has been issued | **Refused** — it is locked |
| H8.5 | Try to edit an approved hiring request | **Refused** |
| H8.6 | Try to reopen a cancelled marketplace requirement | **Refused** |

**✅ Every one of these should be a polite refusal in plain English.** The test
is not whether EXAACT stops you — it should — but whether it explains itself.

---

## 16. Recording your results

### 16.1 — PASS / FAIL sheet

Copy this for each journey. Fill it in **as you go**.

```
EXAACT UAT — RESULT SHEET

Journey:            ____________________________________
Tester name:        ____________________________________
Role signed in as:  ____________________________________
Date:               ____________________________________
Device:             Laptop / Tablet / Phone  (model: __________)
Browser:            Chrome / Safari / Edge / Firefox  (version: ______)

+--------+----------------------------------+------+------+----------------------+
| Step   | What I tested                    | PASS | FAIL | Notes / defect ref   |
+--------+----------------------------------+------+------+----------------------+
| A1.1   |                                  |      |      |                      |
| A1.2   |                                  |      |      |                      |
| A1.3   |                                  |      |      |                      |
|        |                                  |      |      |                      |
+--------+----------------------------------+------+------+----------------------+

Steps passed:  ______ / ______
Steps failed:  ______
Defects raised: ____________________________________

Could a real person do their real job using this journey?   YES / NO
If NO, what stopped them: ______________________________

Signed: ____________________   Date: ______________
```

### 16.2 — The rules for marking a step

- **PASS** — what happened matched "What should happen" exactly.
- **FAIL** — anything else. Including "it worked but looked wrong", "it worked
  but took 30 seconds", "it worked but showed a warning".
- **BLOCKED** — you could not run the step because an earlier one failed. Say
  which step blocked you.
- **NOT RUN** — you skipped it. Say why.

**Never mark a step PASS because it "probably" works.** An untested step is
NOT RUN, and that is a perfectly respectable answer.

---

## 17. Defect report template

One defect per report. A developer who has never seen your screen must be able
to reproduce it from your description alone.

```
EXAACT UAT — DEFECT REPORT

Defect ID:       UAT-DEF-____
Date:            ____________________
Raised by:       ____________________
Journey / step:  ____________________   (e.g. Journey A, step A8.4)

SEVERITY  (tick one)
[ ] CRITICAL — money, data loss, or a security hole. Cannot go live.
[ ] HIGH     — a core business task cannot be completed. Needs fixing before go-live.
[ ] MEDIUM   — works, but wrong, confusing, or needs a workaround.
[ ] LOW      — cosmetic. Wording, spacing, alignment.

WHO I WAS SIGNED IN AS
Role:     ____________________
Username: ____________________

WHERE IT HAPPENED
Screen name (as shown at the top): ____________________
Web address:                       ____________________
Device / browser:                  ____________________

WHAT I DID  (numbered, so it can be repeated exactly)
1. 
2. 
3. 

WHAT I EXPECTED TO HAPPEN

WHAT ACTUALLY HAPPENED

EXACT WORDING of any message shown on screen:
"                                                          "

SCREENSHOT ATTACHED?   YES / NO      (please always attach one)

DOES IT HAPPEN EVERY TIME?
[ ] Every time   [ ] Sometimes   [ ] Only happened once

WHICH RECORD(S)  (so the record can be found again)
Reference / number: ____________________

BUSINESS IMPACT — what does this stop us doing, in plain terms?

```

### 17.1 — Deciding severity

| If… | Severity |
|---|---|
| Money could be wrong, lost, or billed twice | **CRITICAL** |
| Somebody can see data they should not | **CRITICAL** |
| Somebody can approve their own work | **CRITICAL** |
| Data you entered disappeared | **CRITICAL** |
| A technical error page (`SQLSTATE`, `Fatal error`) appeared | **CRITICAL** |
| A core job cannot be finished at all | **HIGH** |
| A number on a dashboard does not match the list behind it | **HIGH** |
| It works but needs a workaround | **MEDIUM** |
| A silent refusal with no explanation | **MEDIUM** |
| Wording, spacing, colour, alignment | **LOW** |

**When you are unsure between two levels, choose the higher one.** It costs a
conversation; the alternative costs a live incident.

---

## 18. Production smoke test — 15 minutes

Run this **every time** a new version is deployed, and **immediately before**
you go live. It is not a full test; it answers one question: *is the system
fundamentally alive and safe?*

| # | Do this | It has passed if | If it fails |
|---|---|---|---|
| S1 | Open `https://operations.mghaiapps.com` | Sign-in page appears within a few seconds | **STOP. Site is down.** |
| S2 | Check the address bar shows a padlock (https) | Padlock present | **STOP. Security problem.** |
| S3 | Sign in as an administrator | Dashboard, greeting by name | **STOP.** |
| S4 | Look at the left strip | Your areas are listed | **STOP.** |
| S5 | Open **Operations** | Backlog / Schedule / Assignment registers | Log HIGH |
| S6 | Open **Work orders** | The register loads with data | Log HIGH |
| S7 | Open **Candidates** | The register loads | Log HIGH |
| S8 | Open **Money → Invoices** | The register loads | Log HIGH |
| S9 | Press **Ctrl+K**, type `my work`, press Enter | **"My Work"** opens | Log MEDIUM |
| S10 | Search `UAT` | Results (or a clean "nothing matches") | Log MEDIUM |
| S11 | Open any one record | It opens, showing its details | Log HIGH |
| S12 | Sign out | Returned to sign-in | Log HIGH |
| S13 | While signed out, try to open the candidate register directly | **Sent to sign-in** | **STOP. CRITICAL.** |
| S14 | Sign in as an **Inspector** | Their short dashboard | Log HIGH |
| S15 | As the Inspector, open **My Jobs** | It opens | Log HIGH |
| S16 | As the Inspector, try to open **Invoices** | **Refused** | **STOP. CRITICAL.** |
| S17 | On a **phone**, sign in and open **My Jobs** | Readable, no sideways scrolling | Log MEDIUM |
| S18 | Anywhere in S1–S17, did you see `SQLSTATE`, `PDOException` or `Fatal error`? | **No** | **STOP. CRITICAL.** |

**Smoke test result:** ALL PASS → safe to proceed. **Any STOP → do not go live.**

```
SMOKE TEST RECORD
Date/time: ____________  Run by: ____________  Version/commit: ____________
Result:  [ ] ALL PASS    [ ] FAILED at step ______
Notes: ___________________________________________
```

---

## 19. Final sign-off checklist

Sign this only when everything below is genuinely true. Your signature says the
business is prepared to run on this system with real clients and real money.

### 19.1 — Coverage

| # | | Done |
|---|---|---|
| 1 | Step 0 confirmed production matches the tested version | ☐ |
| 2 | Journey A (Recruitment) completed — **both** entry routes | ☐ |
| 3 | Journey B (TPIA, work order → invoice) completed | ☐ |
| 4 | Journey C (Marketplace) completed | ☐ |
| 5 | Journey D (Money & billing) completed | ☐ |
| 6 | Journey E (Dashboards) completed for **every** role you use | ☐ |
| 7 | Journey F (Search) completed | ☐ |
| 8 | Journey G (Phone) completed **on a real phone** | ☐ |
| 9 | Journey H (Negative & security) completed | ☐ |
| 10 | Smoke test passed on production | ☐ |

### 19.2 — The controls that must work

| # | | Confirmed |
|---|---|---|
| 11 | A requestor **cannot** approve their own hiring request | ☐ |
| 12 | A report approver **cannot** also issue that report | ☐ |
| 13 | A person **cannot** approve their own expense voucher | ☐ |
| 14 | **Accepted (Hired)** and **Joined** are genuinely different, and reports show both | ☐ |
| 15 | Hiring somebody as **Back office** does **not** make them a deployable field inspector | ☐ |
| 16 | The system **asked** what kind of team member a hire is — it never silently chose "Field" | ☐ |
| 17 | Hiring one person does **not** close a multi-vacancy requisition | ☐ |
| 18 | The same work **cannot** be invoiced twice | ☐ |
| 19 | An issued report **cannot** be edited | ☐ |
| 20 | Signed-out users reach **no** business screen | ☐ |
| 21 | An inspector **cannot** reach candidates, invoices or admin | ☐ |
| 22 | Changing a record number in the address bar does **not** expose other people's data | ☐ |
| 23 | No screen showed `SQLSTATE`, `PDOException` or `Fatal error` at any point | ☐ |

### 19.3 — Business decisions you must record

| # | Decision | Your answer |
|---|---|---|
| 24 | **Both recruitment routes** exist (Hiring Request → Requisition, and Requisition direct). Do you want both open in real life, or only the approved route? | ☐ Both ☐ Approved route only |
| 25 | Does the shipped wording suit your business, or do you want it changed under *Admin → Terminology / wording*? | ☐ Keep ☐ Change: __________ |
| 26 | Which roles will you actually use? (You do not have to use all sixteen.) | __________ |
| 27 | Who signs off invoices, and who issues reports? They must be **different** people. | Approver: ______ Issuer: ______ |

### 19.4 — Outstanding defects

| Severity | How many open | Acceptable to go live? |
|---|---|---|
| CRITICAL | ______ | **Must be zero** |
| HIGH | ______ | Must be zero, or each has a written owner decision |
| MEDIUM | ______ | Acceptable with a fix date |
| LOW | ______ | Acceptable |

### 19.5 — Signature

```
I confirm that the testing recorded in this playbook was carried out,
that the results are recorded honestly, and that the outstanding
defects listed above are accepted by the business.

UAT Lead:          __________________  Date: __________
Business Owner:    __________________  Date: __________
Technical Contact: __________________  Date: __________

DECISION:
[ ] APPROVED — go live
[ ] APPROVED WITH CONDITIONS — conditions: ______________________
[ ] NOT APPROVED — reasons: ____________________________________
```

---

## 20. Appendix A — What was verified, and how

Being precise about this so you know exactly how much weight each statement bears.

| Evidence level | What it means |
|---|---|
| **VERIFIED — LIVE** | I signed in and used the running application and observed this directly |
| **VERIFIED — CODE** | Read from the application's own source; the behaviour is written into the program |
| **NOT VERIFIED** | I could not confirm it. Test it yourself and record the result |

| What | Level | How |
|---|---|---|
| The 16 roles | VERIFIED — CODE | The role list in the program |
| Screens reachable per role (162 / 132 / … / 29) | **VERIFIED — LIVE** | Signed in as each role and counted |
| Area and tile lists | **VERIFIED — LIVE** | Read from the running application as an administrator |
| Screen headings and button labels | **VERIFIED — LIVE** | Read off each rendered page |
| Form field names, and which are required | **VERIFIED — LIVE** | Read off each rendered form |
| Candidate stage names | **VERIFIED — LIVE** | Read off the candidate register |
| Accepted-vs-Joined messages | **VERIFIED — CODE + LIVE** | Exact wording from the program; control seen on the live candidate screen |
| Inspector refused candidates / invoices / admin | **VERIFIED — LIVE** | Signed in as an inspector and tried each one |
| Finance refused job allocation and My Jobs | **VERIFIED — LIVE** | Signed in as Finance and tried each one |
| Signed-out users sent to sign-in | **VERIFIED — LIVE** | Tried each screen while signed out |
| Form security token rejects bad submissions | **VERIFIED — LIVE** | Submitted without a valid token; confirmed no record was created |
| Phone layout, no sideways scrolling | **VERIFIED — LIVE** | Measured at 390 × 844 on Chromium |
| Touch-target sizes | **VERIFIED — LIVE** | Measured; four navigation items below 44px (Section G.6) |
| Object lifecycles and statuses | VERIFIED — CODE | The program's own lifecycle definitions |
| Both recruitment routes exist | **VERIFIED — LIVE** | Both entry points present and reachable |
| **Production site itself** | ⚠️ **NOT VERIFIED** | Not reachable from the build environment — hence Step 0 |
| **Marketplace external portals** | ⚠️ **NOT VERIFIED** | Needs external client / professional accounts |
| **Key Accounts Manager role** | ⚠️ **NOT VERIFIED** | No active account existed to measure |
| **E-mail and notifications actually arriving** | ⚠️ **NOT VERIFIED** | Needs a real mail server; test in production with your own address |
| **Performance under real load** | ⚠️ **NOT VERIFIED** | Needs many concurrent real users |

### 20.1 — Known observations (do not raise these as new defects)

| # | Observation | Severity | Status |
|---|---|---|---|
| 1 | Four navigation items are below the 44px touch standard: sidebar close ✕ (20px), sidebar group toggle (33px), search 🔍 (34px), **🧭 Go to…** palette (32px). The main working buttons all meet it. | LOW | Known, recorded |
| 2 | Two recruitment entry routes both exist (ADR-001). This is an **open business decision**, not a fault. | n/a | Awaiting your decision — item 24 |

---

## 21. Appendix B — Quick reference

### If you see this, do this

| You see | It means | Do |
|---|---|---|
| `SQLSTATE…`, `PDOException`, `Fatal error`, a wall of file paths | A genuine program fault | **Screenshot. Log CRITICAL. Stop that journey.** |
| *"Nothing matches that."* | The record does not exist, or you may not see it | Normal. Not a defect |
| *"You don't have permission to open …"* | Your role may not use that screen | Normal. Not a defect |
| *"This area isn't available to your role."* | Same | Normal. Not a defect |
| A plain-English validation message | The system caught bad data | Normal — this is it working |
| A blank white page | Something failed to load | Log HIGH |
| A spinner that never stops | Something failed | Log HIGH |
| Silently bounced to the dashboard with no message | A refusal that failed to explain itself | Log MEDIUM |

### Keyboard shortcuts

| Keys | Does |
|---|---|
| **Ctrl+K** (**⌘K** on Mac) | Open the command palette — jump to any screen |
| **Esc** | Close the palette |

### Where things live

| To do this | Go |
|---|---|
| Raise a work order | Operations → ＋ New work order |
| Put somebody on a job | Operations → Backlog → Allocate |
| See who is free | Operations → Scheduling board → Who's free? |
| Ask for headcount | Operations → Hiring requests → + New request |
| Recruit against approved headcount | Operations → Requisitions → ➕ New requisition |
| Add a CV | Operations → Candidates → + Add candidate CV |
| Record that somebody started | The candidate → **Mark as joined** |
| Write a report | Reporting → New report |
| Bill closed work | Money → Billing workspace |
| See what is waiting on me | **Ctrl+K** → My Work |
| Change the words on screens | Admin → Terminology / wording |

---

## 22. Final word

This playbook tests whether EXAACT can run your business. It is deliberately
hard on the things that cost money when they go wrong: billing the same work
twice, letting one person approve their own work, confusing somebody who was
*hired* with somebody who actually *turned up*, and quietly turning a back-office
hire into somebody you might send to a refinery.

If a step fails, that is the playbook doing its job. Log it honestly, mark the
severity high rather than low when you are unsure, and let the fix happen before
real client money depends on it.

**Do not sign Section 19 until every CRITICAL is closed.**
