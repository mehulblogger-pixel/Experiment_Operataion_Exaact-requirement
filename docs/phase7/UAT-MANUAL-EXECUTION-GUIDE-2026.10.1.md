# EXAACT 2026.10.1 — Manual UAT Execution Guide

**For:** the business owner, testing by hand
**Release candidate:** `0f2123d` · version `2026.10.1` · documentation `b29918c`
**Companion:** `EXAACT-BUSINESS-UAT-END-TO-END-PLAYBOOK.md` (the original journey detail)
**This guide adds what that one assumed:** the setup, user and permission groundwork that
comes *before* the eight journeys.
**Interactive version:** the same 152 steps as a tick-as-you-go page, which keeps the result
sheet and produces the final record — <https://claude.ai/artifact/4QuSHMXjZL8TTWDRGw7iph>
(private to the owner's account). This document remains the authoritative text.

---

## 0. Read this first — it will save you a bad afternoon

### 0.1 Two ways to run this, and they are not the same

Your live site is an **existing, configured, working business**. It has real staff, real
customers, real invoices. You cannot "test setup" on it by re-running setup — that would
be like testing a car's assembly line by dismantling the car you drive.

So choose:

| | **Mode A — verify the live site** | **Mode B — fresh test workspace** |
|---|---|---|
| Where | `operations.mghaiapps.com` | a new, separate workspace |
| Part 1 (setup) | **Read and confirm only.** You check the settings are right. You change nothing. | **Create everything from scratch.** This is the real test of setup. |
| Part 2 (journeys) | Read-heavy; write only on clearly-marked test records | Everything, freely |
| Risk | Low if you follow the rules | None |
| What it proves | The live system is correct and ready | A new customer can be set up from zero |

**My recommendation: do both, in this order — Mode B first, then Mode A.** Mode B is where
you can press everything without fear, so you learn the system's answers there; Mode A then
takes twenty minutes because you already know what you are looking at.

If you only have time for one, do **Mode A**, and mark every Part 1 item "verified, not
created".

### 0.2 The rules that protect your business

While testing on the live site, **do not**:

- issue a real invoice or credit note
- record a real payment
- send a real offer letter to a real person
- convert a real candidate into a real employee
- delete any real record
- change real attendance
- unload the demo data *(see 0.3 — this one has a specific trap)*

If a step needs one of those to prove the point, **write BLOCKED and move on.** A blocked
step is an honest result. A pretended pass is worse than no test at all.

### 0.3 One specific warning

**Do not run a "demo unload" on the live workspace** if any of your real staff hold employee
numbers `EMP01`, `EMP02`, `EMP03`, `EMP04` or `SC-001`. There is a known residue
(backlog item D-06) where the unload clears an approver-mapping row matched by those numbers.
It cannot delete a person, but it can clear a mapping you would then have to re-add. Simply
don't use that button during UAT.

### 0.4 How to record a result

For every numbered step, write one line:

```
Step 1.4.2 | Admin | /users | Created test coordinator | Expected: user appears, role Coordinator
         | Actual: appeared, role correct | PASS | —
```

Six things: **step · who you were · where you were · what you did · what you expected ·
what happened · PASS/FAIL/BLOCKED · comment**.

Screenshot anything that surprises you. Send me results a journey at a time and I will keep
the formal record and classify every failure.

### 0.5 If something frightening happens — stop

Stop that journey immediately and tell me, without trying a workaround, if you see:

- data from another company
- a record disappearing you did not delete
- a money figure that is plainly wrong
- somebody active as an employee who has not joined
- a screen you should not be allowed to open, opening anyway
- an approval going through that should have been refused

These are the only results that stop a release. Everything else we classify and decide.

---

# PART 1 — SETUP, USERS AND PERMISSIONS

> **Mode A (live site):** read each item and confirm it is already correct. Do not change it.
> **Mode B (test workspace):** actually do each item.

## 1.1 Getting in

| # | What to do | What you should see |
|---|---|---|
| 1.1.1 | Open the site address in Chrome | The EXAACT sign-in page. Not an error, not a blank page, not a PHP warning |
| 1.1.2 | Look at the bottom of the sign-in page | A small version line. **After deployment it must read `2026.10.1`.** Before deployment it will read `2026.07.1` — that is expected and correct, and is not a failure |
| 1.1.3 | Sign in as the Master Admin | The home page, headed "Good morning/afternoon/evening, *your name*" |
| 1.1.4 | *(Mode B, fresh install only)* The licence agreement appears first | A "Software Licence Agreement" page that will not let you past until accepted. Read it, accept |
| 1.1.5 | *(Mode B, fresh install only)* The first-run setup wizard appears | A short wizard asking only what nobody else can decide — database and administrator. Complete it |
| 1.1.6 | Open **Admin → Server check & version** (`/preflight`) | A page listing the version, the build date, the database driver, and a checklist of server requirements. **Every required row must be green/OK** |

**1.1.6 is the single most useful screen in this whole guide.** It tells you the version, the
database, and whether the server has what the application needs. If anything there is red,
write it down and stop Part 1 — there is no point testing business rules on a server that is
missing a requirement.

## 1.2 Company settings

Open **Admin → Settings** (`/settings`).

| # | Check | What right looks like |
|---|---|---|
| 1.2.1 | Company / organisation name | Your real trading name, spelled as you want it on documents |
| 1.2.2 | Logo | Present and not stretched |
| 1.2.3 | Financial year start month | Your actual FY start — April for most Indian businesses. **Fixed 2026-10-04:** this dropdown never showed the stored month and always displayed January, and saving the tab wrote January back. Re-check after re-uploading `views/ops/settings.php`. If it still shows January, the value really was overwritten — set it to April and tell me |
| 1.2.4 | Currency and number format | Correct for your invoices |
| 1.2.5 | Address, GST/tax identifiers | Exactly as they must appear on a legal document |
| 1.2.6 | E-mail sending configuration | Configured, and the outbox shows recent successful sends |
| 1.2.7 | Cloud mode / base domain | **Only applies if you sell EXAACT to other companies**, each on its own sub-domain (`acme.yourdomain.com`). You run one company, so this should be **blank**. If it is blank, that is a PASS |

**Mode B:** set each of these and confirm it saves and survives a page reload.
**Mode A:** confirm only. If one is wrong, that is a real finding — note it, don't fix it mid-test.

## 1.3 The lists everything else chooses from

> **Correction (2026-10-04).** The first version of this guide told you to find all
> eleven lists under `/masters`. That was wrong, and it is why several of these came
> back as "nothing such found". They live on **four** different screens. The addresses
> below are taken from the application's own registries and verified.

| # | List | Where it really is | Why it matters |
|---|---|---|---|
| 1.3.1 | **Offices / branches** | `/hierarchy?tab=offices` — the `/masters` card forwards you here on purpose, because this screen also owns the tree and each office's head | Everything is scoped by office |
| 1.3.2 | **Business Units (SBU)** | `/lookup?key=sbu` | Revenue and cost split by unit |
| 1.3.3 | **Departments** | `/lookup?key=department` | Hiring requests and requisitions choose one |
| 1.3.4 | **Designations** | `/lookup?key=designation` | Job titles on requisitions and offers |
| 1.3.5 | **Trades / disciplines** | `/lookup?key=trade` | Which inspector can do which job |
| 1.3.6 | **Activities / services** | **Not a master.** Settings → `/service-scope` and `/service-formats` | What you sell and execute |
| 1.3.7 | **Expense heads (voucher columns)** | `/masters` → "Expense heads (voucher columns)" | What an engineer claims on a job |
| 1.3.8 | **Office expense heads** | `/masters` → "Office expense heads" | A branch's own running costs, and how each spreads across business units. **A different list from 1.3.7, deliberately** |
| 1.3.9 | **Public holidays** | `/masters` → "Public holidays" | Working-day and capacity maths |
| 1.3.10 | **Agencies / subcontractors** | `/masters` → "Recruitment / manpower agencies" | Outsourced manpower |
| 1.3.11 | **Back-office staff** | `/masters` card forwards to `/hierarchy?tab=people` | People live in one register, so the card sends you there rather than keeping a second copy |

For each: **does it open, does it list, can you add one, and does the new one appear in
the place that uses it?** That last part is the real test — add a test designation at
`/lookup?key=designation`, then check it appears in the requisition form's designation
dropdown and on the Add-a-login form.

**A finding worth recording separately:** you are the Master Admin and you could not
find three lists that exist. Even with my bad instructions, a list a business owner
cannot locate is a real usability defect against the "Zero Training UI" standard. It is
logged as **R-12** and is not closed by this correction.

## 1.4 Users, roles and registration

Open **Admin → Users** (`/users`).

| # | What to do | What you should see |
|---|---|---|
| 1.4.1 | Review the user list | Every real person who should have access, nobody who should not. **Look for leavers who still have logins** — that is a genuine finding |
| 1.4.2 | Create a new user (`/user-new`) | The form asks for name, login, e-mail, **role**, **home office**, and the **person** they are |
| 1.4.3 | On `/user-new`, open the **Position / designation** dropdown | It lists the designations from the master **and** ends with "**+ Add a designation not on this list…**". Choose that, type a new title, save — the new designation is added to the master and appears for the next person. Below the field, "Manage the designation master" must open the list (**fixed 2026-10-04** — it used to say "Not found") |
| 1.4.4 | Save, then sign in as that user in a private window | They land on a home page appropriate to their role, not an error |
| 1.4.5 | Edit `uat.coord`: change the **home office** to another branch, save, reload | The new office sticks. Then change it back. Nothing else about the user changes |
| 1.4.6 | Retire a user (`/user-retire`) — **use your test user only** | They can no longer sign in; their historical records remain |
| 1.4.7 | Find the reactivate / unlock control for a retired user | Available to an administrator. **Your finding stands:** the screen calls it *reactivate*, there is no *unlock*, and there is no control on the screen where you retire someone. Logged as **R-13** |

**Create these four test users now — Parts 2 and especially Journey H need them:**

| Test user | Role | Purpose |
|---|---|---|
| `uat.admin` | Master Admin or Admin | the authorised administrator |
| `uat.coord` | Coordinator | ordinary operational user |
| `uat.finance` | Finance | money-only access |
| `uat.inspector` | Inspector / Senior Inspector | field staff, phone user |

Give each a **home office** and a password you control. You will try to break into things with
them in Journey H. Retire them when UAT finishes.

## 1.5 Roles and permissions

Open **Admin → Access** (`/access`).

| # | Check | What right looks like |
|---|---|---|
| 1.5.1 | The role list | The real roles: Master Admin, Business Director, Business Unit Head, Branch Manager, Operation Manager, Coordinator, Finance, Senior Inspector, Inspector, and the sales/marketing roles |
| 1.5.2 | What each role can do | Matches how your business actually works. **If a Coordinator can do something only a Manager should, that is a finding** |
| 1.5.3 | On `/access`, find the **Recruitment** permission group → "**Configure the recruitment module**" | It exists — my earlier wording ("recruitment-administrator") was not what the screen says, which is why you could not find it. Only people who should configure recruitment hold it. **Separate real finding:** the module toggle for recruitment is named "Hiring / candidates" and filed under the **Operations** heading, not Recruitment. Logged as **R-14** |
| 1.5.4 | Office scope | A branch user sees their branch, not every branch |
| 1.5.5 | Open `/access-requests` | **My earlier description was wrong.** This is not internal staff asking for permissions — it is the **Marketplace partner queue**: outside organisations asking to connect to you. If you do not use Marketplace it is correctly empty, and empty is a PASS. Internal access is granted directly on `/access` and `/user-edit` |

## 1.6 Licence and modules

| # | Check | What right looks like |
|---|---|---|
| 1.6.1 | **Admin → Licence** (`/licence`) | Valid, not expired, covering the modules you use |
| 1.6.2 | Seat count | Matches what you pay for; current users within it |
| 1.6.3 | Enabled modules | Recruitment, Operations, Money, Quality, Marketplace as applicable |
| 1.6.4 | **Admin → Billing** (`/billing`) | Your subscription state is readable |
| 1.6.5 | A disabled module | Its screens are genuinely unreachable, not just hidden from the menu |

## 1.7 Approval configuration

Open **Recruitment → Approval Rules** (`/approval-rules`).

| # | Check | What right looks like |
|---|---|---|
| 1.7.1 | The rules list | Your real approval chains |
| 1.7.2 | Who approves what, at what level | Matches your authority matrix |
| 1.7.3 | Self-approval policy panel | Set as your business intends. **Default and correct answer: a requester may not approve their own submission** |
| 1.7.4 | Superuser exception | Enabled only if you genuinely want it |
| 1.7.5 | Delegation (`/approval-delegations`) | Any standing delegations are intended and current |
| 1.7.6 | **Review Required triggers panel** | This is **UAT-G6B-01** — Part 3 tests it in full |

**Part 1 is complete when:** the server check is green, company settings are right, every
master list is populated and feeds the screens that use it, your four test users exist and can
sign in, permissions match how you work, the licence is valid, and approval rules reflect your
real authority.

---

# PART 2 — THE EIGHT BUSINESS JOURNEYS

Use the original playbook (`EXAACT-BUSINESS-UAT-END-TO-END-PLAYBOOK.md`, journeys A–H) for the
click-by-click detail. What follows is **what each journey must prove**, the safe way to prove
it, and the specific things this release changed.

## Journey A — Recruitment, end to end

**The business question:** can we hire somebody properly, and does the system stop us doing it
wrongly?

**The flow:** Hiring Request → Approval → Requisition → Candidate → Pipeline → Offer →
Joining → Workforce.

| # | Step | Where | What must be true |
|---|---|---|---|
| A1 | Raise a hiring request | `/hiring-request` | Only somebody permitted can raise one. It asks for department, designation, quantity, office, reason |
| A2 | Submit it for approval | same | It goes to the right approver per your rules |
| A3 | **Try to approve your own request** | `/approvals` | **It must be refused** unless you deliberately enabled the exception. This is a core control |
| A4 | Approve as the correct approver | `/approvals` | Approved, with who and when recorded |
| A5 | Turn it into a requisition | `/requisitions` | A requisition is created. **A hiring request and a requisition are different things** — confirm both still exist separately |
| A6 | Open the requisition | `/requisition` | Shows the approved requirement: experience, qualification, skills, designation |
| A7 | Add a candidate | `/candidate-new` | Attaches to the requisition |
| A8 | Move the candidate along the pipeline | `/candidate` | The **configured pipeline** is what shows their current stage. The old single "stage" field must not be telling a different story |
| A9 | Record an interview / assessment | `/candidate-interview` | Saved and visible in history |
| A10 | **Change the requirement** — raise minimum experience | `/requisition` | A **proposed new version** is created. **The approved version must not be overwritten** |
| A11 | Approve the new version | `/approvals` | Version 2 becomes current; version 1 remains readable |
| A12 | Look at your candidate again | `/candidate` | **Review Required** has appeared on them, because the bar moved up after they were judged |
| A13 | Try to advance them | `/candidate` | **Refused** while the review is open |
| A14 | Try to clear the review as an unauthorised user | `/candidate-review` | **Refused** |
| A15 | Clear it as the authorised user, with a reason | `/candidate-review` | Allowed, reason recorded |
| A16 | Reject a test candidate | `/candidate` | Rejection is recorded and **stays in history** |
| A17 | Reconsider them | `/candidate` | Only by deliberate action — never automatic. They return to the stage before the rejection |
| A18 | **Offer** — on a test candidate only | `/candidate-offer` | Generates for review. **Do not send it to a real person** |
| A19 | Mark joined — test candidate only | `/candidate-joined` | They become an active team member **at this moment and not before** |
| A20 | Check the team register | `/m/inspectors` | Before A19 they are listed as **joining pending**; after A19, active |
| A21 | **The critical one:** before marking joined, try to allocate them to a job | `/availability`, job allocation | **They must not be offered.** A person who has not started cannot be given work |

**A21 is the single most important check in Journey A.** It is the defect this release's Gate 6
fixed. If a hired-but-not-joined person can be assigned a job, stop and tell me.

## Journey B — Operations, order to cash *(protected module)*

**The business question:** has the recruitment work damaged the business that pays the bills?

| # | Step | Where | What must be true |
|---|---|---|---|
| B1 | Open a customer | `/clients` | Opens, shows contacts and commercial terms per your permission |
| B2 | Raise a call / order | `/call-new` | Creates. Use a **test customer** |
| B3 | Schedule it | `/schedule` or the call | Dates accepted |
| B4 | Allocate an inspector | the call/job | **Only active people are offered.** Joining-pending and leavers must not appear |
| B5 | Open the availability board | `/availability` | Shows who is free. A hired-not-joined person is **named as awaited, not offered as bookable** |
| B6 | Open a job | `/job` | Full detail, status, history |
| B7 | Record execution | `/job-edit` | Saves |
| B8 | Report and QA flow | `/job-qap`, report screens | Reachable, status visible |
| B9 | Billing readiness | `/job-bill`, `/billable-events` | Shows whether the job is ready to bill |
| B10 | **Invoice visibility only** | `/invoice` | You can see invoices. **Do not issue one** |
| B11 | Attendance | `/attendance-review` | Readable. **Do not change real attendance** |
| B12 | Vouchers | `/vouchers` | Readable |

## Journey C — Marketplace

| # | Step | Where | What must be true |
|---|---|---|---|
| C1 | Open Marketplace | `/connect` | Opens |
| C2 | Requirements | `/connect-requirements` | Listed |
| C3 | Professionals / bench | `/connect-bench` | Listed |
| C4 | A marketplace requirement vs a recruitment requisition | both | **They are different things and must stay separate.** A marketplace requirement is not a hiring requisition |
| C5 | Matching / sourcing | `/connect-match`, `/connect-source` | Works, respects permission |
| C6 | Permissions | as an unauthorised user | Marketplace admin screens refused |

## Journey D — Money and billing *(protected module — read only)*

| # | Step | Where | What must be true |
|---|---|---|---|
| D1 | Invoice register | `/invoices` | Lists your invoices |
| D2 | One invoice's detail | `/invoice` | Figures, lines, tax correct |
| D3 | Payments | payment screens | Visible |
| D4 | Financial reports | `/reports`, `/financial-control` | Open, figures plausible |
| D5 | Credit notes | `/credit-note-new` | **Screen opens — create nothing** |
| D6 | As Finance user | sign in as `uat.finance` | Sees money, **cannot** perform recruitment or operational actions |
| D7 | As Coordinator | sign in as `uat.coord` | **Cannot** see what finance-only should hide |
| D8 | Office boundary | as a branch user | Does **not** see another branch's revenue |

**Write nothing in Journey D.** If a figure looks wrong, record it — do not correct it.

## Journey E — Dashboards

| # | Step | Where | What must be true |
|---|---|---|---|
| E1 | Home / Command Centre | `/` | Opens, shows your work |
| E2 | Recruitment dashboard | recruitment home | Funnel counts match what you can count by hand on the lists |
| E3 | Operations dashboard | operations home | Same |
| E4 | KPI / SLA displays | `/mis`, `/reports` | Figures present and plausible |
| E5 | Filters | any dashboard | Change one; the numbers change consistently |
| E6 | **Utilisation report** | `/reports` | This is **UAT-G6B-02** — Part 3 |
| E7 | Each role's home | sign in as each test user | Each sees a home suited to their job, with nothing they shouldn't |
| E8 | Any number that looks wrong | — | **Write it down. Do not fix it during UAT** |

## Journey F — Search

| # | Step | Where | What must be true |
|---|---|---|---|
| F1 | Press Ctrl+K, search a candidate name | anywhere | Found; clicking opens the record |
| F2 | Search a requisition | | Found |
| F3 | Search a hiring request | | Found |
| F4 | Search an inspector / team member | | Found |
| F5 | Search a call / job / customer | | Found |
| F6 | As a branch user, search something from another branch | sign in as a scoped user | **Not found, or found without the parts they may not see** |
| F7 | **Multi-workspace only:** search for something you know exists in another company | | **Absolutely nothing from the other company.** If anything appears, stop immediately |

## Journey G — The inspector's phone

**Use a real phone if you have one.** Resizing a desktop browser is a fallback, not the test.

| # | Step | What must be true |
|---|---|---|
| G1 | Sign in as `uat.inspector` on the phone | Works; the keyboard doesn't cover the fields |
| G2 | The home screen | Shows their work, readable without zooming |
| G3 | My Jobs / My Work | Their jobs, not everybody's |
| G4 | Open one job | Readable; buttons big enough to hit with a thumb |
| G5 | Status and next action | Clear what to do next |
| G6 | The menu drawer | Opens, and the **close button is easy to hit** (this release raised it to a proper touch size) |
| G7 | Scroll every screen sideways | **Nothing should scroll sideways.** Check at the three common widths — roughly 360, 390 and 412 pixels, i.e. a small Android, an iPhone, a large Android |
| G8 | Try to reach an admin screen as the inspector | **Refused** |
| G9 | Attendance / punch, if used | Works on the phone |

## Journey H — Negative and security **(mandatory — a failure here blocks the release)**

Sign in as the *wrong* person and try things. Nothing here is destructive.

| # | Attempt | As | What must happen |
|---|---|---|---|
| H1 | Open `/approval-rules` | `uat.coord` | **Refused** — "only an administrator can configure approval rules" |
| H2 | Open `/users` | `uat.coord` | **Refused** |
| H3 | Open `/licence` or `/billing` | `uat.coord` | **Refused** |
| H4 | Approve your own hiring request | `uat.coord` | **Refused** (unless you deliberately enabled the exception) |
| H5 | Clear a Review Required flag | `uat.coord` without the permission | **Refused** — being able to *see* it must not mean being able to *clear* it |
| H6 | Perform a recruitment action | `uat.finance` | **Refused** |
| H7 | Open an admin screen | `uat.inspector` | **Refused** |
| H8 | View somebody's salary / CTC | `uat.coord` | **Hidden or refused** |
| H9 | **Type a forbidden address straight into the browser bar** — e.g. `/users`, `/approval-rules`, `/licence` | each test user | **Still refused.** Hiding a menu item is not security; the address must be refused too |
| H10 | Open another branch's client commercial terms | a branch-scoped user | **Refused or hidden** |
| H11 | **Multi-workspace only:** try to reach a record ID belonging to another company | any user | **Refused.** If other-company data appears, **STOP THE ENTIRE UAT** |
| H12 | Sign in with a retired user | retired test user | **Refused** |
| H13 | Sign in with a wrong password three times | any | Sensibly handled; no crash, no information leak |

**H9 and H11 are the two that matter most.** H9 catches the commonest real security mistake —
hiding a button but leaving the door open. H11 is the one that would end a release.

---

# PART 3 — THE TWO CHECKS NEW IN THIS RELEASE

## UAT-G6B-01 — The Review Required "Redefined" switch

**What it is, in business terms.** When an approved job requirement changes, the people already
in the process may no longer be the right people. Where that's so, each gets a **Review
Required** flag somebody must decide before they can be offered a job. One part of that rule is
now yours to control: whether a *redefined* requirement — the role became Electrician where it
said Welder — raises the flag. Until this release it could only be changed by editing the
database.

Sign in as `uat.admin`. Go to **Recruitment → Approval Rules** (`/approval-rules`).

| # | Step | What must be true |
|---|---|---|
| G6B-01.1 | Find the panel "When does a requirement change need its candidates re-checked?" | Present |
| G6B-01.2 | Read it | You understand what it does **without asking me**. If you don't, that is a finding — write down what confused you |
| G6B-01.3 | Note the state | Tick is **ON**, pill reads **Redefined: ON** |
| G6B-01.4 | Find a control for "stricter" | **There is none, deliberately.** The screen states a stricter requirement *always* raises a review and that this is not configurable |
| G6B-01.5 | Untick, press **Save triggers** | Confirmation appears |
| G6B-01.6 | Reload the page | Still **OFF**, pill reads **Redefined: OFF** — it stuck |
| G6B-01.7 | Check the audit trail (`/audit-log`) | Your change is recorded — who, when, from what to what |
| G6B-01.8 | With it OFF: change a test requisition's **designation** and approve the new version | Candidates **do not** get Review Required |
| G6B-01.9 | With it OFF: raise a test requisition's **minimum experience** and approve | Candidates **do** get Review Required — the mandatory rule survived |
| G6B-01.10 | Tick it back ON, save, reload | **ON** again |
| G6B-01.11 | With it ON: change a designation and approve | Candidates **do** get Review Required |
| G6B-01.12 | As `uat.coord`, open `/approval-rules` | **Refused**, and no trigger control visible |

**Leave it ON** at the end unless you have a business reason not to — ON is how every workspace
behaved before this screen existed.

## UAT-G6B-02 — Utilisation excludes people who haven't started

**What it is, in business terms.** The per-person utilisation table answers "how much of the
capacity we have did we actually use". Somebody hired but not yet joined was never counted in
capacity, yet appeared in the table with zero days used — reading either as "this person is
idle, chase them" (they can't work yet) or "this report is broken".

You need three real people in one office: one **active**, one **hired but not yet joined**, one
**leaver**. Use people already in those states. **Do not change anybody's status to make this
test work** — that would invalidate it.

| # | Step | Where | What must be true |
|---|---|---|---|
| G6B-02.1 | Open the Utilization panel | `/reports` | A row per person with man-days and a percentage |
| G6B-02.2 | Find your **active** person | | **Present** — the report still works |
| G6B-02.3 | Find your **hired-not-joined** person | | **Absent** |
| G6B-02.4 | Find your **leaver** | | **Present** — they may have worked in the period, and those days count |
| G6B-02.5 | Open the **person filter** on that same screen | | The hired-not-joined person is **still selectable.** Left out of a total is not hidden from the business |
| G6B-02.6 | Open the availability board | `/availability` | They are **named as hired but not yet joined**, and not offered as bookable |
| G6B-02.7 | Open the team register | `/m/inspectors` | They **are** listed, as before |
| G6B-02.8 | Filter the register to "Joining pending" | `/m/inspectors` | They appear; the active person and leaver do not |

**If G6B-02.3 shows them, or G6B-02.5 does not,** stop and tell me — those two are the whole
point of the change.

---

# PART 4 — RECORDING AND SIGN-OFF

## 4.1 Your result sheet

| Journey | Executed | PASS | FAIL | BLOCKED | Notes |
|---|---|---|---|---|---|
| Part 1 Setup | | | | | |
| A Recruitment | | | | | |
| B Operations | | | | | |
| C Marketplace | | | | | |
| D Money | | | | | |
| E Dashboards | | | | | |
| F Search | | | | | |
| G Inspector phone | | | | | |
| H Negative / Security | | | | | |
| UAT-G6B-01 | | | | | |
| UAT-G6B-02 | | | | | |

## 4.2 How I will classify each failure

Send me what happened; I will classify it, and I will not call something a product defect
until I have established the expected behaviour from the locked decisions:

| | Meaning | Who acts |
|---|---|---|
| **A** | Application defect — the product is wrong | me, in a fix gate |
| **B** | Data issue — the product is right, the data is odd | you, or a data task |
| **C** | Environment issue — hosting, server, network | your host |
| **D** | Test procedure issue — the test can't be run as written | me, fix the playbook |
| **E** | Business decision needed — behaviour is defensible, policy isn't settled | you decide |
| **F** | Documentation issue — the guide is wrong | me |

## 4.3 What "B-5 PASS" requires

All eight journeys **plus** both Gate 6B checks actually executed and accepted by you. Not
inferred, not assumed, not substituted with my local test runs. If any journey is BLOCKED we
decide together whether it blocks the release.

**Journey H is special:** a genuine permission bypass or any cross-company data leak is a
release blocker until understood, no matter how good everything else looks.

## 4.4 After B-5

Still open after UAT, and not part of it:

- **B-2** — a production backup taken, restored into a scratch database, and proven
- **B-3** — production environment verification
- **B-4** — deployment, checksum verification, production smoke test

**Deployment happens after all of those, not before.** Your live site will keep reporting
`2026.07.1` until then, and that is correct.

---

## Appendix — screens referenced, by address

**Setup & admin:** `/login` · `/preflight` · `/setup` · `/settings` · `/masters` · `/m/...`
(the masters family) · `/users` · `/user-new` · `/user-edit` · `/user-retire` · `/access` ·
`/access-requests` · `/licence` · `/billing` · `/audit-log`

**Recruitment:** `/hiring-request` · `/hiring-requests` · `/approvals` · `/approval-rules` ·
`/approval-delegations` · `/requisitions` · `/requisition` · `/candidates` · `/candidate` ·
`/candidate-new` · `/candidate-interview` · `/candidate-review` · `/candidate-offer` ·
`/candidate-joined` · `/candidate-pool`

**Operations:** `/clients` · `/calls` · `/call-new` · `/call` · `/schedule` · `/availability` ·
`/jobs` · `/job` · `/job-edit` · `/job-qap` · `/job-bill` · `/attendance-review` · `/vouchers` ·
`/m/inspectors`

**Money:** `/invoices` · `/invoice` · `/billable-events` · `/credit-note-new` ·
`/financial-control`

**Marketplace:** `/connect` · `/connect-requirements` · `/connect-bench` · `/connect-match` ·
`/connect-source`

**Reporting:** `/` (home) · `/reports` · `/mis` · `/search` (or Ctrl+K)

*Addresses are taken from the application's own route table at commit `0f2123d`. If one does
not exist on your live site, that itself is a finding — tell me which.*
