# EXAACT — Business User Testing Playbook

**Who this is for:** the owner or administrator, sitting in front of EXAACT, with no
programmer beside them.
**What it is:** what to click, what to type, what should happen, and where the
information goes next.

Everything here was read from the **actual application**, not from memory. Where a
screen could not be checked, it says so.

---

# PART 0 — READ THIS FIRST: the words on your screen may differ

EXAACT lets each company **rename things**. The same button can read differently in
two companies, and that is a setting, not a fault.

In the workspace used to write this playbook the screens read:

| This playbook says | Your screen may say |
|---|---|
| Inspection call | **Test request** |
| Job | **Sample** |
| Inspector / engineer | **Analyst** |

**Before you start, look up your own words:** **Admin → Terminology / wording**
(`/terminology`). Write your five or six words on a sticky note. Then read this
playbook with those words in mind.

**Nothing else in this playbook changes** — the buttons are in the same places and
the information flows the same way.

---

# PART 1 — HOW TO USE THIS PLAYBOOK

### What "UAT" means
User Acceptance Testing. You pretend to be a real user, do real work, and check the
system does what the business needs. You are not looking for typos — you are
checking that **work you enter in one place turns up correctly in the next place.**

### Testing versus real production
| | Test workspace | Production |
|---|---|---|
| Who sees the data | only you | your real staff and customers |
| Can you invent people | yes | **no** |
| Can you delete things | yes | **no** |
| Can you issue an invoice | yes | **only if authorised** |

**Use a test workspace for everything in Parts 6–9.**

### How to be sure which one you are in
1. Look at the **web address** in your browser.
2. Look at the **company name** at the top left.
3. Go to **Admin → Company setup** and confirm the company name.

If you cannot tell which company you are in — **stop and ask.** Never guess.

### What information to use
Use obviously fake but realistic entries, and put **UAT** in every name so you can
find and ignore them later:

- Candidate: `Rahul Sharma (UAT)` · `p7.uat.rahul@example.com` · `9800000001`
- Customer: `UAT Industrial Client`

### What you must NEVER enter during testing
- A real person's ID number, bank details, salary, or photograph
- A real customer's name on an invoice
- A real employee number that belongs to somebody

### When to stop immediately
- A page shows programming text (words like `Fatal error`, `SQLSTATE`, `Uncaught`)
- You see **another company's** data
- Money is wrong
- A record you just saved has vanished

Stop, screenshot, and record it. Do not "try again to see if it fixes itself".

### Is it my mistake or the system's?
Ask in this order:
1. **Did the screen tell me something?** A message in plain English ("Choose a
   team…") is the system working. Fix your input and continue — record **PASS**.
2. **Did nothing happen at all?** Check you filled every field marked `*`.
3. **Did programming text appear?** That is the system. Record **FAIL**.

### Recording a result
Keep a simple sheet: Test ID · Date · PASS/FAIL · Screenshot name · Note.

### Screenshots
Capture one whenever the playbook says so, and **always** on a failure. Use the
whole browser window so the address bar is visible.

---

# PART 2 — THE NAVIGATION YOU ACTUALLY HAVE

Down the left of every screen (read from the live application):

| Menu | Goes to | What it is for |
|---|---|---|
| 🏠 Dashboard | `/` | your starting page |
| 🧭 Owner home | `/owner` | owner's summary |
| 🔍 Search records | `/search` | find anything |
| 🔗 Where the flow is broken | `/flow-gaps` | work stuck between steps |
| 🧭 What to fix | `/advisor` | suggestions |
| 🎯 Sales | `/sales` | enquiries and quotations |
| 🧑‍🏭 Marketplace | `/marketplace` | the public professional marketplace |
| 🛠️ Operations | `/operations` | the delivery work |
| 🧭 Recruitment | `/recruitment-cc` | **Recruitment Command Centre** |
| 🛡️ Quality & Accreditation | `/quality` | complaints, equipment, methods |
| 📑 Reporting | `/reporting` | writing and issuing reports |
| 💰 Money | `/money` | billing, invoices, profitability |
| 📊 Insights | `/insights` | dashboards and analytics |
| 🏢 Directory | `/directory` | clients, vendors, portals |
| ⚙️ Admin | `/admin` | settings, users, masters |

> **Note:** the Recruitment menu opens the **Command Centre** (`/recruitment-cc`),
> not the older action view. Both exist; the Command Centre is the one on the menu.

### Inside Recruitment (Command Centre tiles)

| Tile | Goes to |
|---|---|
| ＋ New requirement | `/requisition-new` |
| ＋ Add candidate | `/candidate-new` |
| Requirements | `/requisitions` |
| Candidates | `/candidates` |
| Departments & designations | `/departments` |
| Positions — roles & headcount | `/positions` |
| Compensation setup | `/comp-setup` |
| Document templates | `/doc-templates` |
| Approval rules | `/recruit-approvals` |
| Careers page | `/careers-admin` |

### Where your **people** live
The workforce register is at **`/m/inspectors`** — reached through
**Admin → Masters**, then the people/analyst entry. In the test workspace its title
read **"Analyst register"** with a **"+ Add analyst"** button.

### Inside Operations

| Tile | Goes to |
|---|---|
| ＋ New test request *(your wording may say "call")* | `/call-new` |
| Scheduling board | `/schedule` |
| New requests awaiting triage | `/calls` |
| Report pending | `/jobs` |
| Recurring | `/recurring` |
| SLA targets | `/sla-targets` |

### Inside Money

| Tile | Goes to |
|---|---|
| Billable events | `/billable-events` |
| Invoice tracker | `/invoicing` |
| Billing workspace | `/to-bill` |
| Profit by test request | `/call-profit` |

---

# PART 3 — THE COMPLETE BUSINESS LIFECYCLE

This is the verified flow. Each arrow says **what information moves**.

```
HIRING REQUEST            (someone asks for a person)
   │  the approved request becomes the authority to recruit
   ▼
APPROVAL                  (status must reach "Approved")
   │  only an Approved request may be acted on
   ▼
REQUISITION / REQUIREMENT (how many, which office, WHICH TEAM)
   │  carries: designation, quantity, office, team, customer
   ▼
CANDIDATE                 (a person applying against that requirement)
   │  moves through stages: CV received → … → Offer released
   ▼
ACCEPT / HIRE             (one button: stage = "Accepted (Hired)")
   │  ALL AT ONCE: stage changes AND a workforce record is created
   ▼
WORKFORCE RECORD          (the person now exists as staff)
   │  gets an employee number; keeps the team from the requirement
   ▼
MARK AS JOINED            (a SEPARATE action — hired is not joined)
   │  records the date they actually started
   ▼
TEAM MEMBER / INSPECTOR   (available to Operations if your company does site work)
   │  the person can now be put on work
   ▼
TEST REQUEST / CALL       (the customer asks for work)
   ▼  SCHEDULING → INSPECTION (job) → REPORT → QA (report issued)
   ▼  TIMESHEET → EXPENSE
   ▼
BILLING                   (only once the job is closed AND the report issued)
   ▼
DASHBOARDS                (counts, revenue, utilisation)
```

### The five rules this flow must obey

1. **Accepting somebody hires them.** There is no tick box to forget. If the stage
   becomes "Accepted (Hired)", a workforce record is created in the same moment.
2. **All or nothing.** If anything fails, the candidate stays where they were and
   **no half-record** is left behind. *(A "transaction" — think of EXAACT treating
   several actions as one package: all of them happen, or none of them do.)*
3. **Hired is not joined.** After accepting, the person is **not** shown as joined
   until somebody presses **Mark as joined**.
4. **The team is chosen, never assumed.** If nobody has said which team the person
   joins, the acceptance is refused with a message.
5. **An employee number is never re-used** — not even after somebody leaves.

---

# PART 4 — WHERE EACH RECORD COMES FROM AND GOES

| Business object | Created from | Created by | Main information | Next step | Where to verify |
|---|---|---|---|---|---|
| Company / workspace | Admin → Company setup | Administrator | company name, industry, wording | everything else | `/workspace/setup` |
| User (login) | Admin → User register | Administrator | name, e-mail, role, office | can sign in | `/users` |
| Team member (workforce) | Masters → people register, **or automatically on hire** | Admin, or recruitment | name, employee number, **team**, office | available to Operations | `/m/inspectors` |
| Hiring request | Recruitment → Hiring requests | Manager | designation, quantity | approval | `/hiring-requests` |
| Approval | on the hiring request | Approver | status → Approved | requisition | `/hiring-requests` |
| Requisition / requirement | Recruitment → ＋ New requirement | Recruiter/Manager | designation, **how many**, office, **which team**, customer | candidates | `/requisitions` |
| Candidate | Recruitment → ＋ Add candidate | Recruiter | name, e-mail, mobile, requirement | stages → hire | `/candidates` |
| Acceptance / hire | Candidate screen → Recruitment tab | Coordinator+ | stage = Accepted (Hired) | **workforce record** | candidate screen |
| Employee number | automatically at hire | the system | e.g. `EMP01` | permanent | `/m/inspectors` |
| Joining | Candidate screen → **Mark as joined** | Coordinator+ | the date they started | requirement's "joined" count | candidate screen |
| Test request / call | Operations → ＋ New test request | Coordinator | customer, what is needed | scheduling | `/calls` |
| Job / sample | from the call | Coordinator | person assigned, dates | report | `/jobs` |
| Report | Reporting → New test certificate | Inspector/Engineer | findings | QA / issue | `/documents` |
| Timesheet | against the job | Person or coordinator | day, hours | billing | job screen |
| Expense | against the job | Person or coordinator | travel, local, food | billing | job screen |
| Invoice | Money → Billing workspace | Finance | amount, customer | dashboards | `/invoices` |
| Audit record | automatically | the system | who did what, when | evidence | `/audit-log` |

---

# PART 5 — WHO CAN DO WHAT (in business language)

EXAACT has these roles (Admin → Roles & permissions):

Master Admin · Admin (legacy) · Business Director · Business Unit Head ·
Branch Manager · Branch Application Manager · Operation Manager · Asst. Manager ·
Coordinator · Business Development Manager · Key Accounts Manager ·
Marketing Manager · Marketing Executive · Finance · Senior Inspector · Inspector

Two groups decide most of what happens in recruitment:

- **Admin level** — the managers (Master Admin, Admin, Business Director, Business
  Unit Head, Branch Manager, Branch Application Manager, Operation Manager).
- **Coordinator level** — admin level **plus** Asst. Manager and Coordinator.

**Accepting a candidate, and marking somebody joined, require coordinator level.**
Sales and Finance roles are deliberately *not* in these groups.

| Role | Enters at | Typically can | Typically cannot |
|---|---|---|---|
| **Master Admin** | Dashboard | everything, including settings and recovery | — (use sparingly; it bypasses every check) |
| **Business Director** | Dashboard | see every number, every branch | edit operational records |
| **Branch Manager** | Dashboard | run one branch: calls, jobs, hiring, users **in their own office** | manage users in other offices; change system settings |
| **Coordinator** | Dashboard | add candidates, move stages, **accept/hire**, **mark joined**, schedule work | approve beyond their authority; change settings |
| **Finance** | Money | invoices, receipts, billing | operational scheduling |
| **Inspector** | My work | their own jobs, their reports, their timesheets | see other people's pay or manage users |

> Verify each of these against **Admin → Roles & permissions** in *your* workspace —
> roles can be tuned per company.

---

# PART 6 — HOW EVERY TEST IS WRITTEN

Each test looks like this. Follow it top to bottom.

```
TEST ID          a short code, e.g. REC-001
BUSINESS PROCESS what business activity this is
PURPOSE          why it matters
STARTING POINT   where you must be before step 1
STEP 1,2,3…      click / type / press
EXPECTED RESULT  in plain English
DATA CREATED     what now exists
WHERE TO VERIFY  exactly where to look
NEXT DATA FLOW   where that information goes next
PASS CONDITION   what you must see
FAIL CONDITION   what means trouble
SCREENSHOT       whether to capture one
```

---

# PART 7 — THE COMPLETE RECRUITMENT JOURNEY (do this first)

**Your test person for the whole of Part 7:**

| Field | Value |
|---|---|
| Name | `Rahul Sharma (UAT)` |
| E-mail | `p7.uat.rahul@example.com` |
| Mobile | `9800000001` |

⚠ Use a **test workspace**. Do not use a real person.

---

### TEST ID: LOGIN-001 — Sign in

**PURPOSE:** confirm you can get in, and that the sign-in page protects itself.

**STARTING POINT:** the EXAACT web address, signed out.

**STEP 1** — Type your **Username** and **Password**, press **Sign in**.

**EXPECTED RESULT:** you land on the Dashboard, greeted by name.

**PASS:** you see the Dashboard and the left-hand menu.
**FAIL:** you are returned to the sign-in page with no explanation, or you see
programming text.

> If you are sent to a **first-time setup** page instead, that is normal for a brand
> new workspace. Fill in the company name and press save; you will then reach the
> Dashboard. This is **Company setup**, and it only appears once.

**SCREENSHOT:** yes — Screenshot 01.

---

### TEST ID: REC-001 — Raise a hiring request

**BUSINESS PROCESS:** somebody asks for a person to be hired.
**PURPOSE:** recruitment should only act on an **approved** request.

**STARTING POINT:** Dashboard.

**STEP 1** — Left menu → **🧭 Recruitment**.
**STEP 2** — Look for **Hiring requests** (address `/hiring-requests`).
**STEP 3** — Press **+ New request**.
**STEP 4** — Enter the designation and how many people are needed. Save.

**EXPECTED RESULT:** the request is listed with status **Draft**.

**DATA CREATED:** a hiring request.
**WHERE TO VERIFY:** `/hiring-requests` — your request appears in the list.
**NEXT DATA FLOW:** once approved, it becomes the authority for a requisition.

**PASS:** the request exists and shows **Draft**.
**FAIL:** nothing is listed, or the status is already Approved without anyone
approving it.

**SCREENSHOT:** yes — Screenshot 02.

> **The statuses a hiring request can hold:** Draft · Submitted · Under review ·
> **Approved** · Rejected · Cancelled. **Only "Approved" allows recruitment to act.**

---

### TEST ID: REC-002 — Approve the request

**STEP 1** — Open the request from `/hiring-requests`.
**STEP 2** — Use the approval action available to your role.

**EXPECTED RESULT:** status becomes **Approved**.

**PASS:** status reads Approved.
**FAIL:** you can approve your own request when your company's rules say you should
not — record it and check **Admin → Approval rules**.

---

### TEST ID: REC-003 — Create the requirement *(the most important form)*

**BUSINESS PROCESS:** turn the approved request into a real vacancy.

**STARTING POINT:** Left menu → **🧭 Recruitment** → **＋ New requirement**
(address `/requisition-new`).

**Enter these (the screen really has them):**

| Field on screen | What to put |
|---|---|
| **Type \*** | New |
| Client | leave blank, or pick your UAT customer |
| Office | your test office |
| Responsible 1 — Recruiter | yourself |
| Department | any |
| Business Unit | any |
| **Designation / position \*** | Inspector |
| **Which team** — *confirmed again when somebody is accepted* | **Coordinator / office-based** |
| **How many? \*** | `2` |
| Project / site | `UAT Project` |

**STEP — press "Save requisition".**

**EXPECTED RESULT:** the requirement is saved and you land on the requirement's own
page.

**DATA CREATED:** a requisition holding designation, quantity, office and **the
team**.
**WHERE TO VERIFY:** `/requisitions` — your requirement is listed.
**NEXT DATA FLOW:** candidates are added against it, and **the team you chose here
travels to the person when they are hired.**

**PASS:** saved, and re-opening it still shows **Coordinator / office-based**.
**FAIL:** the team choice is empty when you re-open it.

> **Why "Which team" matters.** It decides whether the person is a field inspector
> who goes to site, a coordinator, or back-office. If nobody chooses, EXAACT
> **refuses** the hire rather than guessing — see REC-007.

**SCREENSHOT:** yes — Screenshot 03.

---

### TEST ID: REC-004 — Add the candidate

**STARTING POINT:** **🧭 Recruitment** → **＋ Add candidate** (`/candidate-new`).

| Field | Value |
|---|---|
| First name | `Rahul` |
| Last name | `Sharma (UAT)` |
| E-mail | `p7.uat.rahul@example.com` |
| Mobile | `9800000001` |
| Requirement | the one from REC-003 |

Press **Save**.

**EXPECTED RESULT:** the candidate's own page opens.

**DATA CREATED:** a candidate linked to your requirement.
**WHERE TO VERIFY:** `/candidates`.
**PASS:** the candidate is listed against the right requirement.

**SCREENSHOT:** yes — Screenshot 04.

---

### TEST ID: REC-005 — Move the candidate through the stages

**STARTING POINT:** the candidate's page → the **Recruitment** tab.

> **Important:** the candidate screen has tabs across the top — Overview, Pipeline,
> Interviews, Documents, Offer, **Recruitment**, CV, Timeline. The controls for
> moving somebody live on the **Recruitment** tab. If you cannot find the buttons,
> you are on the wrong tab.

**STEP** — In **Move this candidate**, choose a stage and press **Update stage**.

**The stages EXAACT actually has:**
CV received · Submitted to client · Shortlisted · Interview scheduled ·
**Offer released** · Offer declined (backed out) · On hold · Rejected ·
**Accepted (Hired)** · Withdrawn

Move to **Offer released**.

**PASS:** the stage changes and appears in the timeline.

---

### TEST ID: REC-006 — Accept (hire) the candidate ⭐ *the critical test*

**STARTING POINT:** candidate page → **Recruitment** tab.

**STEP 1** — In **Move this candidate**, choose **Accepted (Hired)**.
**STEP 2** — A panel appears asking **"Which team do they join?"** It should already
show **Coordinator / office-based**, carried from the requirement. Leave it.
**STEP 3** — Press **Update stage**.

**EXPECTED RESULT:** the candidate is now **Accepted (Hired)**, and a workforce
record has been created for them **in the same moment**.

**DATA CREATED:**
- the candidate's stage is Accepted (Hired)
- **a team-member record**
- **an employee number** (e.g. `EMP01`)
- the team is recorded as **Coordinator**, exactly what you chose

**WHERE TO VERIFY:**
1. On the candidate page — the person is linked to a team record.
2. **`/m/inspectors`** (the people register) — `Rahul Sharma (UAT)` is listed with an
   employee number.

**NEXT DATA FLOW:** the person is now staff. If your company does site work they can
be put on jobs.

**PASS — all four must be true:**
- [ ] stage reads **Accepted (Hired)**
- [ ] the person appears in the people register
- [ ] they have an **employee number**
- [ ] their team reads **Coordinator**, **not** "Field"

**FAIL:**
- accepted but **no** team record → serious, stop and report
- the team reads **Field** when you chose Coordinator → serious, stop and report
- **two** records for the same person → serious, stop and report

**SCREENSHOT:** yes — Screenshot 05 (candidate) and Screenshot 06 (people register).

> **There is no tick box.** Older versions had "also add this person to Inspectors".
> It is gone, deliberately — accepting somebody *is* hiring them, so it cannot be
> forgotten.

---

### TEST ID: REC-007 — The refusal test *(prove it will not guess)*

**PURPOSE:** confirm EXAACT refuses rather than inventing a team.

**STEP 1** — Create a **second requirement**, and leave **Which team** on
**"— not decided yet —"**.
**STEP 2** — Add a second candidate against it (`Priya Nair (UAT)`,
`p7.uat.priya@example.com`).
**STEP 3** — Try to move that candidate to **Accepted (Hired)** without choosing a
team.

**EXPECTED RESULT:** the hire is **refused** with a message in plain English telling
you nobody has said which team the person joins.

**PASS:** refused, **and** the candidate is still at the stage they were at, **and**
no new person appears in the people register. **This is a PASS — the refusal is the
feature.**
**FAIL:** the person is hired anyway and lands in the "Field" team.

**SCREENSHOT:** yes — Screenshot 07 (the message).

---

### TEST ID: REC-008 — Hired is not joined ⭐

**PURPOSE:** hiring somebody on paper is not the same as them turning up.

**STEP 1** — Go back to Rahul's page → **Recruitment** tab.
**STEP 2** — Look at the requirement's counts (`/requisitions`, open the requirement).

**EXPECTED RESULT:** the requirement shows **1 hired** and **0 joined**.

**PASS:** "joined" is **zero** even though somebody is hired.
**FAIL:** joined already counts them — that would mean EXAACT is claiming somebody
started work when nobody said so.

**STEP 3** — On the candidate page (Recruitment tab) find **"Have they actually
joined?"**, pick today's date, press **Mark as joined**.

**EXPECTED RESULT:** the page now shows **"Joined on <date>"** and a **"Not joined
after all"** button.

**PASS:** joined count becomes **1**; hired stays **1**.
**FAIL:** the button cannot be found (check you are on the **Recruitment** tab), or
pressing it changes nothing.

**SCREENSHOT:** yes — Screenshot 08.

---

### TEST ID: REC-009 — Undo a joining

**STEP** — Press **Not joined after all**.

**EXPECTED RESULT:** the joining is removed. The person is **still hired** — they
keep their employee number and team record.

**PASS:** joined count returns to 0; the person remains in the people register.
**FAIL:** the person disappears, or their employee number changes.

---

### TEST ID: REC-010 — The register tells the truth

**STEP** — Open the requirement (`/requisitions` → your requirement).

**EXPECTED RESULT:** you can see **how many were asked for**, **how many are hired**
and **how many have joined**, and they are **three different numbers**.

**PASS:** a part-filled requirement shows as partly filled, not as finished.

---

# PART 8 — DUPLICATE AND DATA-SAFETY TESTS

⚠ **DO NOT PERFORM ANY OF PART 8 IN PRODUCTION.** Use a test workspace.

These tests deliberately try to create a mess. In every case a **clear message is a
PASS** — you are checking that EXAACT stops you.

---

### DUP-001 — The same employee number twice
**Where:** people register (`/m/inspectors`) → **+ Add**.
**Do:** add a person and give them an employee number that somebody already holds.
**Should happen:** refused.
**PASS:** refused, and only one person holds that number.
**FAIL:** two people end up with the same number.

### DUP-002 — Somebody who has left
**Do:** mark a test person as having left, then try to give a **new** person the
**same employee number**.
**Should happen:** still refused.
**Why it matters:** the number stays attached to the person who earned it, for ever,
so your history stays readable.
**PASS:** refused, and the old record is untouched.

### DUP-003 — The same e-mail address
**Where:** Admin → User register → add a person, or the people register.
**Do:** add a second person with an e-mail a **current** colleague already uses.
**Should happen:** you are stopped, **and told who they may already be, by name**.
**PASS:** stopped with a message naming the existing person.
**FAIL:** a silent second record, or programming text.

### DUP-004 — The same name only
**Do:** add two different people who genuinely share a name, with different e-mails.
**Should happen:** **both are allowed.**
**Why:** a name is not an identity. Rajesh Patel does not block Rajesh Patel.
**PASS:** both are created.

### DUP-005 — A candidate who is already on your staff
**Do:** create a candidate using the e-mail or mobile of an existing team member, and
try to accept them.
**Should happen:** a warning panel shows the possible match, and you must **tick to
confirm** before it will proceed.
**PASS:** blocked until you tick; allowed once you tick.
**FAIL:** it either proceeds silently, or refuses even after you tick.

> Ticking is **not** a merge. It records that a human looked and decided. Two
> separate records remain.

### DUP-006 — Re-hiring somebody who left
**Do:** take a test person who has **left**, then hire a new candidate with the same
e-mail.
**Should happen:** **allowed** — a leaver is history, not a duplicate.
**PASS:** the new engagement is created and the old record stays as it was.

### DUP-007 — Duplicates already in your data
**Where:** Admin → the duplicates screen (`/duplicates`).
**Do:** just look.
**Should happen:** existing duplicates are **listed**, never silently deleted or
merged.
**PASS:** you get a list you can act on.

### DUP-008 — Your data is yours alone (tenant isolation)
⚠ **Never use real customer data for this test.**
**Do:** sign in to test **Company A**, create `ISO-TEST-A`. Sign out. Sign in to test
**Company B**. Search for `ISO-TEST-A`.
**Should happen:** Company B finds **nothing**.
**PASS:** not found. Repeat in reverse.
**FAIL:** 🔴 **CRITICAL — stop everything and report immediately.**

---

# PART 9 — THE COMPLETE DELIVERY JOURNEY (TPIA)

Continue with the person you hired in Part 7. This proves **one piece of work
produces one of everything.**

⚠ Test workspace only.

| Step | Who | Where to click | What to enter | What is created | Verify at |
|---|---|---|---|---|---|
| 1 | Coordinator | Directory → Client register → add | `UAT Industrial Client` | customer | `/clients` |
| 2 | Coordinator | Operations → **＋ New test request** | customer, what is needed | the request/call | `/calls` |
| 3 | Coordinator | Operations → **Scheduling board** | assign **Rahul Sharma (UAT)**, set the date | the job | `/jobs` |
| 4 | Inspector | the job | start/finish dates | work recorded | the job |
| 5 | Inspector | Reporting → **＋ New test certificate** | findings | the report | `/documents` |
| 6 | Approver | the report | issue/approve it | report **issued** | `/documents` |
| 7 | Inspector | the job | day and hours | timesheet | the job |
| 8 | Inspector | the job | travel / local / food | expense | the job |
| 9 | Coordinator | the job | close the job | job closed | `/jobs` |
| 10 | Finance | Money → **Billing workspace** | check readiness | invoice *(see Part 13)* | `/to-bill` |
| 11 | Anyone | Insights → Dashboards | — | the numbers | `/reports` |

### What to check at the end — ONE of everything

- [ ] **one** person hired
- [ ] **one** team record
- [ ] **one** employee number
- [ ] **one** job for that work
- [ ] **one** report
- [ ] **one** timesheet
- [ ] **one** expense claim
- [ ] **one** billable amount

**Where duplication could creep in, and what stops it:**

| Risk | What stops it |
|---|---|
| Two team records for one person | the employee-number rule, and the e-mail check |
| The same job billed twice | once billed, a job leaves the "to bill" list |
| Two people in one approved vacancy | the requirement's count is checked at the moment of saving |

**BILL-GATE-001 — billing must refuse too early.** Before closing the job and issuing
the report, open **Money → Billing workspace**. It should say the job is **not ready**
and tell you why. **That refusal is a PASS.** Then close the job and issue the report,
and check it becomes ready.

---

# PART 10 — CANDIDATE, WORKFORCE, INSPECTOR, USER: what is the difference?

This confuses everybody, so here it is in plain words.

```
   CANDIDATE                   somebody applying. Not staff. Can be rejected.
       │  accepted (hired)
       ▼
   TEAM MEMBER (workforce)     a real member of staff, with an employee number.
       │  Mark as joined
       ▼
   JOINED                      they actually started. A separate fact, with a date.
       │  only if your company does site work
       ▼
   INSPECTOR                   a team member whose team is "Field", in a company
                               whose work includes Operations.
```

**USER** is separate from all of these: a **login**. A team member does not
automatically get a login, and a login does not automatically make somebody staff.
You create logins at **Admin → User register**.

**PROFESSIONAL** is a Marketplace idea — somebody with a public profile on the
marketplace. Not the same as your staff.

### When does each thing happen?

| Question | Answer |
|---|---|
| When does a candidate become hired? | the moment their stage becomes **Accepted (Hired)** |
| When is the team record created? | **the same moment** — not later, not by a separate step |
| When is the employee number issued? | in that same moment |
| When does joining happen? | only when somebody presses **Mark as joined** |
| When does somebody become an Inspector? | when their team is **Field** **and** your company's work includes Operations |
| What if your company does **not** do site work? | they are recorded as **office-based**, and no inspector role is invented for them |
| What if the company has never said what work it does? | the hire is **refused** with a message rather than guessed at |
| What if an existing employee applies? | you are warned and must tick to confirm |
| What about a re-hire? | allowed — a leaver does not block their own return |

---

# PART 11 — MARKETPLACE (a different journey)

**Marketplace is not Recruitment.** Keep them apart:

| | Recruitment | Marketplace |
|---|---|---|
| What it is | **you** hiring **your** staff | a public place where companies and professionals meet |
| The demand record | a **requisition / requirement** | a **marketplace requirement** |
| The person record | a **candidate** → your team member | a **professional** with their own profile |
| Who owns the person | your company | the professional themselves |

**Screens (from the live Marketplace menu):** Requirements (`/connect-requirements`),
Guided post (`/connect-concierge`), Talent search (`/connect-talent`), Passports
(`/passport-share`), Verification desk (`/connect-verify`), Messages
(`/connect-messages`), Agency bench (`/connect-bench`), Organisations
(`/connect-orgs`).

**MKT-001** — Marketplace → **Requirements** → post a test requirement.
**MKT-002** — **Talent search** → confirm you can search the shared pool.
**MKT-003** — Confirm a marketplace requirement does **not** appear in
`/requisitions`, and a recruitment requisition does **not** appear in
`/connect-requirements`. **They are separate registers and must stay separate.**

---

# PART 12 — OPERATIONS SCREEN BY SCREEN

| Screen | Reach it | Enter | Creates | Goes next to |
|---|---|---|---|---|
| New test request | Operations → ＋ New test request | customer, requirement, dates | the call | scheduling |
| Scheduling board | Operations → Scheduling board | who does it, when | the job | the person's work list |
| Job | Operations → report pending / `/jobs` | dates, findings | work record | report |
| Test certificate | Reporting → ＋ New test certificate | the report | report record | QA / issue |
| Timesheet | on the job | day, hours | time record | billing |
| Expense | on the job | travel, local, food | expense record | billing |
| Billing workspace | Money → Billing workspace | confirm | invoice | dashboards |

---

# PART 13 — MONEY: WHAT IS SAFE TO TOUCH

| Screen | Safety |
|---|---|
| Money → Billing workspace — **looking** at readiness | 🟢 **SAFE** |
| Money → Profit by test request | 🟢 **SAFE** — reading only |
| Money → Invoice tracker — **looking** | 🟢 **SAFE** |
| Marking a job "ready to bill" | 🟡 **CAUTION** — changes state |
| **Raising an invoice** | 🔴 **DO NOT PERFORM IN PRODUCTION** without written authority |
| **Recording a payment/receipt** | 🔴 **DO NOT PERFORM IN PRODUCTION** |
| Editing an existing invoice | 🔴 **DO NOT PERFORM IN PRODUCTION** |

### How to test billing WITHOUT issuing anything
1. Open **Money → Billing workspace** (`/to-bill`).
2. Find your UAT job.
3. Read the **readiness** information — it tells you whether it could be billed and,
   if not, **why**.
4. **Stop there.** You have proved the gate works without creating a financial
   document.

**If any amount looks wrong — stop before issuing anything and report it.**

---

# PART 14 — DASHBOARDS: PROVING THE NUMBERS MOVE

The way to test a dashboard is: **note the number, do one thing, look again.**

| Dashboard | Where | Do this one thing | What should change |
|---|---|---|---|
| Recruitment | `/recruitment-cc` | add one candidate | candidate count +1 |
| Recruitment | `/recruitment-cc` | accept one candidate | hired +1; **joined unchanged** |
| Requirement | `/requisitions` → open it | mark somebody joined | joined +1; hired unchanged |
| Operations | `/operations` | create one test request | new requests +1 |
| Operations | `/operations` | close one job | report-pending / closed move |
| Money | `/to-bill` | close a job with an issued report | it appears as billable |
| Insights | `/reports` | — | the same facts, summarised |

**DASH-001:** write the number down **before**, do the one action, refresh, and check
it moved by exactly one. **A number that does not move, or moves by two, is a FAIL.**

---

# PART 15 — TENANT ISOLATION (covered in DUP-008)

See Part 8, **DUP-008**. This is the single most important safety test in the
system, and a failure is **critical**.

---

# PART 16 — MOBILE CHECKLIST

Use an Android phone in Chrome, or your browser's phone preview at **360×800**,
**390×844** and **412×915**.

For each screen below, tick all seven:

- [ ] the page loads
- [ ] **no sideways scrolling**
- [ ] buttons are visible
- [ ] buttons are big enough to tap with a thumb
- [ ] no text is cut off or overlapping
- [ ] forms can be filled, and the Save button can be reached
- [ ] Back works

**Screens to check:**

| Screen | Where |
|---|---|
| Marketplace front door | `/connect` |
| Sign in | `/login` |
| Join as a professional | from the marketplace front door |
| Staff sign-in | link at the foot of the marketplace page |
| Dashboard | `/` |
| Candidate register | `/candidates` |
| Candidate page + **Recruitment tab** | open any candidate |
| People register | `/m/inspectors` |
| Operations home | `/operations` |

**Already verified by automated checks on the marketplace front door** at all three
widths: no sideways scrolling, tappable buttons, nothing clipped. Please confirm on a
real phone — a real thumb finds things a measurement does not.

---

# PART 17 — WHEN SOMETHING GOES WRONG

| What you see | What it means | What to do |
|---|---|---|
| The expected screen | working | continue |
| A message in plain English | the system is guiding you | fix your input, continue — often a **PASS** |
| A duplicate warning | the protection is working | **PASS** if you expected it |
| `Fatal error`, `SQLSTATE`, `Uncaught`, a blank white page | a real fault | 🔴 **STOP.** Screenshot. Record exactly what you clicked. Do not continue that test |
| Sent back to sign-in unexpectedly | session or permission problem | **FAIL.** Screenshot. Stop that test |
| A record you saved has vanished | possible data loss | 🔴 **STOP IMMEDIATELY** |
| **Another company's** information | isolation failure | 🔴 **CRITICAL STOP.** Report at once |
| A money amount that looks wrong | billing problem | 🔴 **STOP before issuing anything** |

---

# PART 18 — DEFECT REPORT FORM

Copy this for each problem. **You do not need any technical information.**

```
DEFECT ID:        UAT-001
DATE:
MODULE:           (e.g. Recruitment)
SCREEN:           (the heading at the top of the page)
WEB ADDRESS:      (copy from the browser bar)
MY ROLE:          (e.g. Administrator)

WHAT I WAS TRYING TO DO:

WHAT I CLICKED (in order):
  1.
  2.
  3.

WHAT I ENTERED:

WHAT I EXPECTED TO HAPPEN:

WHAT ACTUALLY HAPPENED:

SCREENSHOT FILE:

SEVERITY:   CRITICAL / HIGH / MEDIUM / LOW
            CRITICAL = data loss, wrong company's data, wrong money
            HIGH     = a business process cannot be completed
            MEDIUM   = a workaround exists
            LOW      = cosmetic

CAN I CARRY ON TESTING?   YES / NO
```

---

# PART 19 — PHASE 7 PRODUCTION CLOSURE CHECKLIST

| # | Item | Status | Evidence needed | Owner | Decision |
|---|---|---|---|---|---|
| A | Code baseline confirmed | ☐ | commit reference | developer | continue |
| B | Production environment verified | ☐ | which company, which address | owner + hosting | **stop if unclear** |
| C | Backup taken and usable | ☐ | date, size, where it is | hosting | **stop if missing** |
| D | Production smoke test | ☐ | this playbook, Day 1 items | owner | continue |
| E | Recruitment journey (Part 7) | ☐ | Screenshots 01–08 | owner | continue |
| F | Team record + employee number | ☐ | Screenshot 06 | owner | continue |
| G | Duplicate protection (Part 8) | ☐ | Screenshots of the messages | owner | continue |
| H | Delivery journey (Part 9) | ☐ | one-of-everything checklist | operations | continue |
| I | Billing safety (Part 13) | ☐ | readiness screen, no invoice issued | finance | continue |
| J | Company separation (DUP-008) | ☐ | "not found" screenshot | owner | **critical** |
| K | Mobile (Part 16) | ☐ | phone screenshots | owner | continue |
| L | Dashboards move (Part 14) | ☐ | before/after numbers | owner | continue |
| M | Audit trail shows your actions | ☐ | `/audit-log` screenshot | owner | continue |
| N | Roles behave as expected (Part 5) | ☐ | one screenshot per role | owner | continue |

**Do not sign off Phase 7 while any row is FAIL, or while B or C is missing.**

---

# PART 20 — YOUR "DO THIS NOW" LIST

### DAY 1 — recruitment *(about 1 hour)*
- [ ] Sign in (LOGIN-001)
- [ ] Look at the Dashboard
- [ ] Note your own wording (Admin → Terminology)
- [ ] Raise a hiring request (REC-001)
- [ ] Approve it (REC-002)
- [ ] Create the requirement, **choose the team** (REC-003)
- [ ] Add candidate Rahul Sharma (UAT) (REC-004)
- [ ] Move him to Offer released (REC-005)
- [ ] **Accept/hire him** (REC-006)
- [ ] Check the people register: employee number and team (REC-006)
- [ ] **The refusal test** (REC-007)
- [ ] Check hired ≠ joined, then Mark as joined (REC-008)
- [ ] Undo the joining (REC-009)

### DAY 2 — delivery and money *(about 1 hour)*
- [ ] Create a test customer
- [ ] Create a test request
- [ ] Schedule Rahul onto it
- [ ] Record the work
- [ ] Write and issue the report
- [ ] Add a timesheet and an expense
- [ ] Close the job
- [ ] **Look at** billing readiness — do not issue (Part 13)
- [ ] Check the dashboards moved (Part 14)

### DAY 3 — safety *(about 1 hour)*
- [ ] Duplicate tests DUP-001 to DUP-007
- [ ] **Company separation DUP-008**
- [ ] Mobile checklist (Part 16)
- [ ] Sign in as a second role and check the limits (Part 5)
- [ ] Look at the audit trail (`/audit-log`)

### BEFORE GOING LIVE
- [ ] Backup confirmed, with a date
- [ ] Production address and company confirmed
- [ ] No CRITICAL or HIGH defects open
- [ ] Every screenshot filed

---

# PART 21 — PLAIN-ENGLISH GLOSSARY

| Term | What it means |
|---|---|
| **Transaction** | EXAACT treats several related actions as one package: they all succeed, or the system undoes all of them. This is why you never get "hired with nobody behind them". |
| **Requisition / requirement** | an approved vacancy: how many people, where, which team |
| **Candidate** | somebody applying — not yet staff |
| **Team member / workforce record** | a real member of staff, with an employee number |
| **Employee number** | a permanent code. Never re-used, even after somebody leaves |
| **Team role** | Field (goes to site), Coordinator, or Back office |
| **Capability** | what kind of work your company does; it decides whether "Inspector" means anything here |
| **Entitlement / module** | which parts of EXAACT your company has bought. Unbought parts do not appear |
| **Tenant** | one company's own private data. Companies never see each other |
| **Audit trail** | the record of who did what, and when |

---

# PART 22 — SCREENSHOTS

Screenshots were captured from a **running demo workspace**, not from production.
They show the real screens, but your company name, wording and data will differ.

| # | Screen | File |
|---|---|---|
| 01 | Dashboard | `01-dashboard.png` |
| 02 | Recruitment Command Centre | `02-recruitment-cc.png` |
| 03 | Requisition register | `03-requisitions.png` |
| 04 | New requirement form | `04-requisition-new.png` |
| 05 | Candidate register | `05-candidates.png` |
| 06 | Add candidate | `06-candidate-new.png` |
| 08 | Test-request register | `08-calls.png` |
| 09 | Job/sample register | `09-jobs.png` |
| 10 | Invoices | `10-invoices.png` |
| 11 | Dashboards | `11-reports.png` |
| 12 | Organisation & people | `12-users.png` |
| 13 | Masters | `13-masters.png` |
| 14 | Audit trail | `14-audit.png` |
| 15 | Hiring requests | `15-hiring-requests.png` |
| 16 | People register | `16-team-register.png` |
| 17 | Terminology | `17-terminology.png` |

Ask your developer for these image files, or capture your own as you go — **your own
screenshots are better evidence**, because they show your data.

---

# PART 23 — THE ONE-PAGE DATA FLOW

```
  LOGIN ─────────────► your role decides what you can see and do
     │
  COMPANY SETUP ─────► names, wording, what work you do
     │
  HIRING REQUEST ────► "we need a person"        (Draft → Approved)
     │ an APPROVED request is the authority to recruit
  REQUISITION ───────► how many · where · WHICH TEAM · which customer
     │ the requirement's team travels with the person
  CANDIDATE ─────────► one applicant against that requirement
     │ stages: CV received → … → Offer released
  ACCEPT (HIRED) ────► ONE action, ONE package:
     │                  • stage becomes Accepted (Hired)
     │                  • a TEAM RECORD is created
     │                  • an EMPLOYEE NUMBER is issued
     │                  • the TEAM from the requirement is written on it
  MARK AS JOINED ────► a SEPARATE action, with a date. Reversible.
     │
  TEAM MEMBER ───────► available to Operations (as an Inspector only if their
     │                  team is Field AND your company does site work)
  TEST REQUEST ──────► the customer asks for work
  SCHEDULING ────────► that person, that date → a JOB
  JOB ───────────────► the work itself
  REPORT ────────────► findings, then issued by QA
  TIMESHEET/EXPENSE ─► against that job and that person
  CLOSE THE JOB ─────► now, and only now, it can be billed
  BILLING ───────────► one job, one invoice value
  DASHBOARDS ────────► counts, revenue, utilisation — each counted once
```

**Read that column downwards and you have the whole business.** Every test in this
playbook is checking one of those arrows.

---

## What this playbook does NOT cover

Written down so nobody assumes otherwise:

1. **Production.** Nothing here was run against your live system. Part 19 rows B, C
   and D still need doing by someone with production access and a backup.
2. **Approval routing rules.** Your company's own rules live in
   **Admin → Approval rules**; this playbook checks that approval *gates* recruitment,
   not that your specific routing is right.
3. **Modules you have not bought.** Screens for those do not appear, and that is
   correct, not a fault.
4. **Your wording.** Check **Admin → Terminology** first (Part 0).
