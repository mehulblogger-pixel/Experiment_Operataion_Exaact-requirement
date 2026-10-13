# EXAACT 2026.10.1 — Manual UAT Execution Guide

**For:** the business owner, testing by hand
**Release candidate:** the head of `claude/testing-branch-setup-0gqe8n` · version `2026.10.1`

> ## ⚠ READ THIS BEFORE YOU TEST ANYTHING
>
> Your last two runs were both recorded against candidate `0f2123d`. **Thirty-five fixes
> have been made since, and none of them is on your live site.** Running this guide against
> an un-deployed build re-finds defects that are already closed and costs you a full day
> for nothing.
>
> **Upload the branch first.** Then confirm you are testing the new build: the sign-in page
> footer must read **`v2026.10.1`** (it is printed with a leading “v”), and `/users` must
> show an **Office** column. If it does
> not show that column, you are on the old build — stop, and deploy.
>
> ### What is already fixed — do not spend time re-finding these
>
> | You reported | Now |
> |---|---|
> | 1.3.6 Masters shows different content depending on how you arrive | One breadcrumb and one filing system everywhere |
> | 1.5.2 Permissions too coarse | Six separate rights per module: view, add, edit, archive, delete, approve |
> | 1.5.3 Recruitment missing from `/access`; ticking View changed every module | Recruitment is listed; the bulk tick is a labelled row scoped to its own group, with Undo |
> | 1.5.4 / H10 Other branches visible; no Money module | A branch role with no branch now sees **nothing**, not everything; Money has its own heading |
> | 1.5.5 / G6B-01 / 1.7.1 Screens you could not find | Named for what they do, and findable by typing "module" or "approval" into **Find a screen** |
> | 1.6.3 / 1.6.5 No way to switch modules on or off | **Admin → Modules & features**. Switching one off makes its screens genuinely refuse, not merely hide |
> | A2 / A6 Requisition asks what the hiring request should have | The hiring request now asks for the minimum qualification, experience and skills, and the approver sees them |
> | A21.1 "Mark as joined" does nothing | It was never drawn on the default pipeline. Fixed |
> | A21.7 Joining-pending person not on the availability board | Named there now, marked awaited and not bookable |
>
> Two things you flagged are **your decision, not defects**, and I have not changed them:
> which modules each role holds by default, and whether a Coordinator should see
> Marketplace.

**Companion:** `EXAACT-BUSINESS-UAT-END-TO-END-PLAYBOOK.md` (the original journey detail)

**This guide adds what that one assumed:** the setup, user and permission groundwork that
comes *before* the eight journeys.

**How much work this is — measured, not estimated:**

| | Checks | What it is |
|---|---|---|
| Part 1 | 47 | Setup, users, permissions |
| Part 2 | 84 | The eight business journeys |
| Part 2★ | 24 | The three release-blocking checks, expanded step by step |
| Part 3 | 20 | The two checks new in this release |
| Part 4 | 156 | Every screen, opened once |
| **Total** | **331** | |

Parts 1–3 are the business test and are where real defects surface. **Part 4 is a sweep** —
one row per screen, roughly ten seconds each — and exists so that no screen reaches your
customers unopened. You may run Part 4 on a second sitting; mark it so on the result sheet.

**Interactive version:** a tick-as-you-go page for **Parts 1–3** that keeps the result sheet
and produces the final record — <https://claude.ai/artifact/4QuSHMXjZL8TTWDRGw7iph>
(private to the owner's account). **It predates Part 4 and does not contain it**, so record
Part 4 on the sheet in §5.1. This document remains the authoritative text.

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

### 0.5 Build your test data FIRST — this is the fix for half your failures

Measured on a clean workspace: **46 of the 156 screens look empty until something exists
to show.** That is why your last two runs are full of *"nothing found"*, *"not described"*
and *"what do you mean by it"* — you were being asked to inspect a thing that had not been
created. That was my fault, not yours, and it is the single biggest waste in both runs.

Spend twenty minutes making these **nine records** before you start. Every later step
refers back to them by name, so you never have to guess which record a step means.

> **The menu names are not the words you would expect, so they are given exactly below.**
> Two in particular: the work-order register is called **Project Deputation**, and the
> recruitment registers are filed under **Operations**, not under Recruitment. If you cannot
> find something, type the name into **Find a screen** rather than hunting the menu.

| # | Make this | Where — exact menu path | Call it | Which steps need it |
|---|---|---|---|---|
| D1 | A **client** | **Directory → Client register** → *Add* | `ZZ Test Client` | B, D, quotes, invoices |
| D2 | A **work order** for D1 | **Operations → Project Deputation** — *that is the work-order register; the name is unexpected* — then **New work order** | `ZZ Test Call` | B, scheduling, job |
| D3 | A **job** from D2 | Open D2 → allocate. It then appears under **Operations → Jobs** | — | B, reports, G |
| D4 | A **hiring request**, approved | **Operations → Hiring requests** → **New hiring request**, then submit and approve as the Master Admin | `ZZ Test Hire` | A, A6, approvals |
| D5 | A **recruitment requirement** from D4 | Open D4 → **Start recruiting**. *(This is the only way in — `/requisition-new` deliberately refuses, and says so.)* | — | A, A6, A7 |
| D6 | A **candidate** on D5 | **Operations → Candidates** → **Add candidate** | `ZZ Test Candidate` | A, A7, G6B-01 |
| D7 | **D6 hired, but NOT marked as joined** | Move D6 to the final stage, record the hire — then **stop**. Do **not** press *Mark as joined* | — | **A21, G6B-02 — the hired-not-joined person every one of those steps asks for** |
| D8 | A **second branch**, and a coordinator homed to it | **Admin → Organisation** → the **Offices** tab for the branch, then the **People** tab to set that coordinator's office | `ZZ Branch B` / `zz.coordB` | **H10, scope** |
| D9 | A **quotation** for D1 | **Sales → Quotes** → New | `ZZ Test Quote` | D, approvals, 1.7 |

**D7 is the one that defeated both runs.** Steps A21.1–A21.7 and G6B-02.1–02.8 all ask you
to find "your hired-not-joined person". If nobody is in that state, those twelve steps
cannot be executed and every one of them reads as a failure. Make D7, and leave them there.

**D8 is what makes H10 testable.** "Open another branch's client" needs two branches and a
user confined to one of them. With one branch the step is meaningless.

> **On the live site:** prefix every test record with `ZZ ` so you can find and remove them
> afterwards, and never attach one to a real customer, a real invoice or a real person.

### 0.6 If something frightening happens — stop

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
| 1.1.2 | Look at the bottom of the sign-in page | A small version line, printed with a leading “v”. **After deployment it must read `v2026.10.1`.** Before deployment it will read `v2026.07.1` — that is expected and correct, and is not a failure |
| 1.1.3 | Sign in as the Master Admin | The home page, headed "Good morning/afternoon/evening, *your name*" |
| 1.1.4 | *(Mode B, fresh install only)* The licence agreement appears first | A "Software Licence Agreement" page that will not let you past until accepted. Read it, accept |
| 1.1.5 | *(Mode B, fresh install only)* The first-run setup wizard appears | A short wizard asking only what nobody else can decide — database and administrator. Complete it |
| 1.1.6 | Open `/preflight` by typing it into the address bar — **it has no menu tile**, so do not hunt for one | A page listing the version, the build date, the database driver, and a checklist of server requirements. **Every required row must be green/OK** |

**1.1.6 is the single most useful screen in this whole guide.** It tells you the version, the
database, and whether the server has what the application needs. If anything there is red,
write it down and stop Part 1 — there is no point testing business rules on a server that is
missing a requirement.

## 1.2 Company settings

Open **Admin → System settings** (`/settings`).

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
| 1.3.6 | **Activities / services** | **Not a master.** Admin → **Service scope** (`/service-scope`) and **Report formats by service** (`/service-formats`). Both screens exist and always did; nothing in the menu pointed at them, which is why you found "nothing such". Reach them from the Admin area page, or type *service* into **Find a screen** | What you sell and execute |
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

Open **Admin → User register** (`/users`).

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

Open **Admin → Roles & permissions** (`/access`) — the menu does not call it “Access”.

| # | Check | What right looks like |
|---|---|---|
| 1.5.1 | The role list | The real roles: Master Admin, Business Director, Business Unit Head, Branch Manager, Operation Manager, Coordinator, Finance, Senior Inspector, Inspector, and the sales/marketing roles |
| 1.5.2 | What each role can do | Every module now has **six separate rights** — View, Add, Edit, Archive, Delete, Approve. Check a few rows against how you actually work. **If a Coordinator can do something only a Manager should, that is a finding** — but note that *which* modules a role holds by default is a business decision I have deliberately left to you, not a defect |
| 1.5.3 | On `/access`, find the **Recruitment** heading, and the **Money** heading | Both are now present. The module list is filed under the same nine headings as the left-hand menu, so what you see here matches what you navigate by. **Also check the bulk row:** the row reading *"Apply to every module in <group>"* changes only that group, and an **Undo** button appears naming what it just did. If ticking one thing changes another group, that is a finding |
| 1.5.4 | Office scope | Use **D8**. Sign in as `zz.coordB` (homed to ZZ Branch B) and open Work orders: you must see Branch B's work and **not** Branch A's. Then, as the Master Admin, open `/users` — there is now an **Office** column, and anyone with no branch is flagged **not set** in amber. An account marked *not set* now sees **nothing** rather than everything; that is the fix, and a banner tells both the person and you |
| 1.5.5 | Open `/access-requests` (type *access requests* into **Find a screen**; it has no menu tile because it belongs to Marketplace) | **My earlier description was wrong.** This is not internal staff asking for permissions — it is the **Marketplace partner queue**: outside organisations asking to connect to you. If you do not use Marketplace it is correctly empty, and empty is a PASS. Internal access is granted directly on `/access`, and per person from `/users` → open the person → **Edit** |

## 1.6 Licence and modules

| # | Check | What right looks like |
|---|---|---|
| 1.6.1 | **Admin → Licence** (`/licence`) | Valid, not expired, covering the modules you use |
| 1.6.2 | Seat count | Matches what you pay for; current users within it |
| 1.6.3 | Enabled modules | `/licence` shows what you **own**. To see and change what is switched **on**, open **Admin → Modules & features** — the link is on the licence screen itself. All seven modules are listed with an on/off control; Administration is always on and correctly has none |
| 1.6.4 | Type `/billing` into the address bar — **it has no menu tile either** | Your subscription state is readable |
| 1.6.5 | A disabled module | **Now testable.** On **Admin → Modules & features**, switch **Money** off (it lists what will be hidden and asks you to confirm). Then type `/invoicing` into the address bar: you must be **refused**, not merely find it missing from the menu. Switch Money back on and confirm `/invoicing` opens again. *(Verified here: refused with a 403, restored on switching back.)* |

## 1.7 Approval configuration

Open **Recruitment approvals** (`/recruit-approvals`) for recruitment chains, and **Quote & report approvals** (`/approval-rules`) for quotations and inspection reports. They are two different screens governing two different things.

| # | Check | What right looks like |
|---|---|---|
| 1.7.1 | The rules list | **There are two approval screens, and they used to share one name.** `/approval-rules` is now **Quote & report approvals**; recruitment lives on **Recruitment approvals** (`/recruit-approvals`), and each screen names the other. Check the chain that matches what you are testing — and that is also where UAT-G6B-01's Review Required panel lives |
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
| A1 | Raise a hiring request | `/hiring-request` | Only somebody permitted can raise one. It asks for department, designation, quantity, office, reason — **and now, under "What must the person have?", the minimum qualification, minimum experience and essential skills**. Those three are the floor the recruitment is held to. Fill them in; D4 depends on it |
| A2 | Submit it for approval | same | It goes to the right approver per your rules. **On the pickers:** each list-backed field now says where its list is kept, and says something different when the list is genuinely empty. A dropdown with nothing in it on a new workspace is a setup gap, not a broken dropdown — follow the link it offers and add the first entry |
| A3 | **Try to approve your own request** | `/approvals` | **It must be refused** unless you deliberately enabled the exception. This is a core control |
| A4 | Approve as the correct approver | `/approvals` | Approved, with who and when recorded |
| A5 | Turn it into a requisition | `/requisitions` | A requisition is created. **A hiring request and a requisition are different things** — confirm both still exist separately |
| A6 | Open the requisition | `/requisitions` → **click the requisition** (don't type `/requisition` — a detail screen needs a record, so typing it alone gives *not found*) | Shows the approved requirement **on the screen itself, without pressing Edit**: minimum qualification, minimum experience and essential skills, carried across from what was approved in A1. Check they match A1 exactly — this is the floor, and the recruitment may ask for more but not less |
| A7 | Add a candidate | `/candidate-new` | Attaches to the requisition |
| A8 | Move the candidate along the pipeline | `/candidates` → **click your candidate** | The **configured pipeline** is what shows their current stage. The old single "stage" field must not be telling a different story |
| A9 | Record an interview / assessment | On the **candidate screen** — the *Interviews* panel. Not a separate address | Saved and visible in history |
| A10 | **Change the requirement** — raise minimum experience | The **requisition screen** from A6 → *Edit* | A **proposed new version** is created. **The approved version must not be overwritten** |
| A11 | Approve the new version | `/approvals` | Version 2 becomes current; version 1 remains readable |
| A12 | Look at your candidate again | `/candidates` → **your candidate** | **Review Required** has appeared on them, because the bar moved up after they were judged |
| A13 | Try to advance them | The **candidate screen** — same place as A8 | **Refused** while the review is open |
| A14 | Try to clear the review as an unauthorised user | On the **candidate screen** — the *Review required* panel. Not a separate address | **Refused** |
| A15 | Clear it as the authorised user, with a reason | Same *Review required* panel on the **candidate screen** | Allowed, reason recorded |
| A16 | Reject a test candidate | The **candidate screen** | Rejection is recorded and **stays in history** |
| A17 | Reconsider them | The **candidate screen** | Only by deliberate action — never automatic. They return to the stage before the rejection |
| A18 | **Offer** — on a test candidate only | On the **candidate screen** — the *Offer* panel. Not a separate address | Generates for review. **Do not send it to a real person** |
| A19 | Mark joined — test candidate only | The **candidate screen** → **Mark as joined** | They become an active team member **at this moment and not before** |
| A20 | Check the team register | `/m/inspectors` | Before A19 they are listed as **joining pending**; after A19, active |
| A21 | **The critical one:** before marking joined, try to allocate them to a job | `/jobs` → **open a job** → *Assignment lifecycle* | **They must not be offered.** A person who has not started cannot be given work. **Do not test this from the one-line version — use Part 2★, step by step.** The wrong dropdown passes by accident |

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
| B6 | Open a job | `/jobs` → **click a job** | Full detail, status, history |
| B7 | Record execution | The **job screen** → **Edit** | Saves |
| B8 | Report and QA flow | The **job screen** → the **QAP / report** buttons | Reachable, status visible |
| B9 | Billing readiness | `/job-bill`, `/billable-events` | Shows whether the job is ready to bill |
| B10 | **Invoice visibility only** | `/invoices` → **open one** | You can see invoices. **Do not issue one** |
| B11 | Attendance | `/attendance-review` | Readable. **Do not change real attendance** |
| B12 | Vouchers | `/vouchers` | Readable |

## Journey C — Marketplace

| # | Step | Where | What must be true |
|---|---|---|---|
| C1 | Open Marketplace | `/connect` | Opens |
| C2 | Requirements | `/connect-requirements` | Listed |
| C3 | Professionals / bench | `/connect-bench` | Listed |
| C4 | A marketplace requirement vs a recruitment requisition | both | **They are different things and must stay separate.** A marketplace requirement is not a hiring requisition |
| C5 | Matching / sourcing | `/jobs` → open a job → **🔎 Source manpower**. (Admins can tune the ranking at `/connect-match-weights`. **There is no `/connect-match` screen** — my earlier draft named one that does not exist; typing it gives *not found*, and that is not a defect) | Works, respects permission |
| C6 | Permissions | as an unauthorised user | Marketplace admin screens refused |
| **C7** | **Find a resource in another city — the case you ratified on 2026-10-13.** Sign in as the **Ahmedabad coordinator** (D8). Open **Marketplace → Talent search**, type **Chennai** in the city box | You get Chennai people even though you are an Ahmedabad user — **the marketplace is deliberately not limited to your own branch.** Each result carries a 📍 reason (*Based here* / *Within N km* / *Works in Tamil Nadu* / *Available Pan-India*) and the **nearest are listed first** | ☐ ok ☐ problem |
| **C8** | On the same search, read the line above the results | It states plainly: *“Showing everyone willing to work in **Chennai**, Tamil Nadu — nearest first. People who have not offered to work there are left out.”* **Somebody who has not offered to work in Chennai must NOT appear** — a coordinator should never be handed a person who cannot take the job | ☐ ok ☐ problem |
| **C9** | Type an old city name — **Madras**. Then a typo — **Chenai** | **Madras** finds Chennai and says *(you typed “Madras”)*. **Chenai** is not silently empty: it says it is not a recognised place and offers a clickable **Did you mean: Chennai**. An empty list must never leave you unable to tell *“nobody there”* from *“I spelt it wrong”* | ☐ ok ☐ problem |
| **C10** | Still as the coordinator, try to **act** — invite that Chennai person onto a requirement, or use **🔎 Source manpower** on a job that is **not** in your branch | **Finding is not hiring.** Searching anywhere is allowed; *acting* still obeys the module right and the job's own branch scope. A refusal here is a **PASS** | ☐ ok ☐ problem |

## Journey D — Money and billing *(protected module — read only)*

| # | Step | Where | What must be true |
|---|---|---|---|
| D1 | Invoice register | `/invoices` | Lists your invoices |
| D2 | One invoice's detail | `/invoices` → **open one** | Figures, lines, tax correct |
| D3 | Payments | payment screens | Visible |
| D4 | Financial reports | `/reports`, `/financial-control` | Open, figures plausible |
| D5 | Credit notes | `/invoices` → open an invoice → **Credit note**. Not a separate address | **Screen opens — create nothing** |
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
| H9 | **Type a forbidden address straight into the browser bar** | each test user | **Still refused.** Hiding a menu item is not security. **Use Part 2★** — a refusal here bounces you to the home page with a red message rather than showing an error page, and a tester who does not know that can record a breach as a pass |
| H10 | Open another branch's client commercial terms | a branch-scoped user | **Refused or hidden** |
| H11 | **Multi-company only:** try to reach a record ID belonging to another company | any user | **Refused.** If other-company data appears, **STOP THE ENTIRE UAT**. **Use Part 2★** — it tells you first how to check whether this applies to you at all, and what to test instead if it does not |
| H12 | Sign in with a retired user | retired test user | **Refused** |
| H13 | Sign in with a wrong password three times | any | Sensibly handled; no crash, no information leak |

**H9 and H11 are the two that matter most.** H9 catches the commonest real security mistake —
hiding a button but leaving the door open. H11 is the one that would end a release.

---

# PART 2★ — THE THREE CHECKS THAT CAN STOP THE RELEASE

Everything else in this guide is worth knowing. **These three decide whether the release
ships.** They are written out click by click because a vague instruction here is worse than
no instruction: all three can be "passed" by accident if you look at the wrong screen.

---

## ★ A21 — a person who has not started cannot be given work

### What is actually being tested

When somebody accepts your offer they exist in the system, but they do not start until the
day they join. Between those two dates they must appear in your team register — you hired
them, you want to see them — and must **not** be offerable for work.

This is not hypothetical. The job screen's engineer dropdown used to ask *"who is not
inactive?"* instead of *"who is active?"*. With only two statuses those were the same
question, so it read as correct for years. The moment a "joining pending" status existed it
silently stopped being correct, and **a person who had accepted an offer could be put on an
inspection weeks before their first day.** That is the defect this release fixed, and A21 is
how you confirm the fix is really on your server.

### Before you start — you need one specific person

Somebody with the status **joining pending**: offer accepted, not yet marked joined.

1. Open `/m/inspectors` (Team register).
2. Filter or look for **Joining pending**. Note the person's name exactly.
3. **If nobody is in that state**, make one — this is **D7** from section 0.5, and it is the
   single most common reason this whole check cannot be run.

> **If the "Mark as joined" button does not appear at all**, that was a real defect and it is
> fixed. The control used to be drawn only for a candidate whose old *stage* field said
> ACCEPTED, but the configured pipeline deliberately stopped writing that field — so on the
> default pipeline the button was never rendered and nobody could ever be marked as joined.
> Because nobody could join, nobody could be *joining pending* either, which is why this
> check and G6B-02 both failed for you at the same time. On the new build the button appears
> for anybody who has reached the pipeline's final stage. If it still does not appear, check
> the person has a **team record** — the screen now says so explicitly and tells you where
> that is created.

Also note the name of one person who **is** active, in the same office. You need both.

> **On the availability board (step A21.7):** the awaited person is now **named** there, in
> their own group, marked *awaited*, with no booking controls. Previously the board showed
> only a count and a "See who" link, which is why you recorded that the name was not showing.
> Being kept off jobs is not the same as being hidden — they must be visible and
> **not bookable**. Both halves matter.

### The test

| # | Do this | What a PASS looks like |
|---|---|---|
| A21.1 | On `/m/inspectors`, confirm your joining-pending person **is listed** | Present, marked as joining pending. Being kept off jobs is not the same as being hidden — if they have vanished from the register, that is a different defect: record it |
| A21.2 | Open `/jobs` and open any job, or go straight to `/job?id=…` | The job screen opens on its Overview tab |
| A21.3 | Find the collapsible section headed **"Assignment lifecycle"** (subtitle: *hold, reschedule, reassign, no-show — with history*) and expand it | The panel opens |
| A21.4 | Open the **engineer / inspector dropdown** inside that panel | A list of people you could assign |
| A21.5 | **Read every name in that dropdown.** Your joining-pending person must **not** be there | Their name is absent |
| A21.6 | Check your **active** person **is** in the same dropdown | Present — this proves you are reading the right control and it is not simply empty |
| A21.7 | Open `/availability` | The joining-pending person is **named as awaited**, not offered as bookable |

### How to read the result

- **PASS** — absent from the dropdown in A21.5, present in the register in A21.1, and the
  active person present in A21.6. All three must hold.
- **FAIL** — their name appears in the engineer dropdown. **Stop Journey A and tell me.**
  This one blocks the release on its own.
- A21.6 failing on its own means the dropdown is empty for some other reason (no active
  people in that office, or a permission problem). That is a different finding — record it
  as such rather than calling A21 a pass.

---

## ★ H9 — a forbidden address typed straight into the browser

### What is actually being tested

Hiding a menu item is not security. The commonest real mistake in business software is to
remove the button and leave the door open, so anyone who knows or guesses the address walks
straight in. H9 types the address by hand and checks the door is actually locked.

### Know what a refusal looks like before you start

**This application does not show an error page when it refuses you.** It sends you back to
the **home page** with a **red message bar** saying why. So:

- **PASS** = you land on the home page with a red message, for example
  *"Only an administrator can configure approval rules."*
- **FAIL** = **the screen loads.** Any part of it — a heading, a table, a form — means you
  got in.

Read that twice. A tester expecting a "403 Forbidden" page can see the correct refusal and
record it as a failure, or see a real breach and think nothing happened.

### The test

Sign in as **`uat.coord`** in a **private / incognito window** (so your administrator
session is not reused). Then type each address into the browser bar by hand — do not click
a menu, do not paste a link from elsewhere.

| # | Type this address | What a PASS looks like |
|---|---|---|
| H9.1 | `/users` | Bounced home, red message |
| H9.2 | `/user-new` | Bounced home, red message |
| H9.3 | `/approval-rules` | Bounced home — message reads **"Only an administrator can configure approval rules."** |
| H9.4 | `/licence` | Bounced home, red message |
| H9.5 | `/settings` | Bounced home, red message |
| H9.6 | `/audit-log` | Bounced home, red message |
| H9.7 | Repeat all six as **`uat.finance`** | Same six refusals |
| H9.8 | Repeat all six as **`uat.inspector`** | Same six refusals |

Then one more, which is the other half of the same question:

| # | Do this | What a PASS looks like |
|---|---|---|
| H9.9 | As each of the three, look at the menu | None of those six screens is offered. Both must be true: not in the menu **and** refused by address |

### How to read the result

- **PASS** — all eighteen attempts bounce home with a message.
- **FAIL** — any one of them loads. **Stop and tell me which address, which user, and what
  you could see.** A single one is enough to hold the release.
- A screen that loads but is empty is still a FAIL. Record what it showed.

---

## ★ H11 — nothing from another company

> **You recorded H11 as a FAIL. It should have been "not applicable", and that is my fault
> for not making the first step unmissable.** H11 only applies if you run *several companies*
> on one installation, each on its own web address. You run one. Step H11.0 says exactly
> that, and your own answers to H11.0–H11.4 correctly read *"Not applicable"* — only the
> summary line said FAIL. **Do H10 instead** (below), which is the one-company version of the
> same question and the one that matters to you. It needs **D8**, the second branch.

### First, decide whether this applies to you

H11 only means something if **this installation serves more than one company**.

1. Open `/settings` and find **cloud mode / base domain**.
2. **Blank** → you run one company. **H11 is Not applicable — record it as such, not as a
   pass.** Then do the office-boundary test below instead, which is the equivalent risk that
   *does* apply to you.
3. **Set** → you serve several companies. Do the full test below.

Recording "pass" on a test that could not have failed is how an untested boundary gets
signed off. If it does not apply, say so.

### If you run more than one company

| # | Do this | What a PASS looks like |
|---|---|---|
| H11.1 | Sign into **company A**. Open any job, candidate or invoice. Note the number at the end of the address — e.g. `/job?id=412` | You have a real record id belonging to company A |
| H11.2 | Sign out completely. Sign into **company B** as any user | You are in the other company |
| H11.3 | Type that exact address from H11.1 into the browser bar | **Refused, or "not found".** Nothing of company A appears |
| H11.4 | Repeat with a candidate id and an invoice id | Same — refused or not found |

**If any company A data appears in company B — even a name, even a number — STOP THE ENTIRE
UAT and tell me immediately.** Do not continue testing. That is the only finding in this
whole guide that ends a release outright.

### If you run one company — do this instead (H10)

The equivalent boundary for you is between **offices**, not companies.

| # | Do this | What a PASS looks like |
|---|---|---|
| H10.1 | Sign in as a user whose home office is branch A | You are scoped to that branch |
| H10.2 | Open `/clients` and find a customer belonging to branch B | You may see the customer exists — that is deliberate |
| H10.3 | Try to open that customer's **commercial terms** and contacts | **Refused or hidden.** Basic identity visible, commercial detail not |
| H10.4 | Open `/reports` and look for branch B's revenue | Branch B's revenue figures are not shown |

---

# PART 3 — THE TWO CHECKS NEW IN THIS RELEASE

## UAT-G6B-01 — The Review Required "Redefined" switch

**What it is, in business terms.** When an approved job requirement changes, the people already
in the process may no longer be the right people. Where that's so, each gets a **Review
Required** flag somebody must decide before they can be offered a job. One part of that rule is
now yours to control: whether a *redefined* requirement — the role became Electrician where it
said Welder — raises the flag. Until this release it could only be changed by editing the
database.

> **Why you could not find this last time.** Two screens were both called "Approval rules".
> You opened the one under Admin, which governs quotations and inspection reports, and this
> panel is on the other one. They are now named for what they govern and each links to the
> other, so the trail is no longer a dead end.

Sign in as `uat.admin`. Go to **Recruitment approvals** — `/recruit-approvals`, or type
*recruitment approvals* into **Find a screen**. (Not `/approval-rules`: that is the
quotation and report chain.) Scroll to the panel described below.

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

> **This whole check needs D7** — the person who is hired but has **not** been marked as
> joined. Every step below asks you to find them. Your last run recorded seven failures here
> reading *"not described"* and *"nothing such found"*, and the honest cause is that no such
> person existed to find, and I never told you to make one. Make D7 first (0.5). If you did
> not, stop and do that now rather than recording eight failures that are not failures.
>
> **Where each screen is:** the Utilisation panel is on **Insights → Dashboards**
> (`/reports`), not on a screen called "Reports". The team register is
> `/m/inspectors`. The availability board is `/availability`.

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

---

# PART 4 — EVERY SCREEN, EVERY BUTTON

This part exists because the last two runs kept stopping at *"nothing found"* and *"not described"*. It is generated by opening every screen in the running application and reading what is actually on it — not written from memory: **156 screens, 555 controls, 792 form fields, 54 tabs.**

Two honest limits on that. It was swept **as the Master Admin on a clean workspace**, so a screen another role cannot see is still listed here, and a screen your subscription does not include is listed too — it will refuse politely rather than open (row 72 is the example, and that refusal is a **pass**). And a control that only appears once a record is in a particular state cannot be seen by a sweep, so the **Needs first** column tells you what to create before you judge a screen empty.

For each screen: open it, check every control listed does what its label says, and record one line. Where a screen says **needs** something, create that first (Part 0.5) or the screen will be legitimately empty and you will record a failure that is not one.


### Home — 7 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 1 | **Command Centre** | `/command-centre` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 2 | **Dashboard** | `/` | may look empty until there is data | **Actions:** Open Settings · Open offices · Open the list · Add a person · Open People · Open Roles · Open approval rules · Command Centre →<br>**Fields:** 1 | ☐ ok ☐ problem |
| 3 | **My tasks** | `/tasks` | — | **Actions:** Add task · Back to dashboard<br>**Fields:** 4 | ☐ ok ☐ problem |
| 4 | **My Work** | `/my-work` | — | **Actions:** Operations home · Reports register<br>**Fields:** 1 | ☐ ok ☐ problem |
| 5 | **Search records** | `/search` | — | **Actions:** Search<br>**Fields:** 1 | ☐ ok ☐ problem |
| 6 | **What to fix** | `/advisor` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 7 | **Where the flow is broken** | `/flow-gaps` | may look empty until there is data | **Actions:** ⬇ CSV<br>**Fields:** 1 | ☐ ok ☐ problem |

### Sales — 9 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 8 | **Approvals** | `/approvals` | something awaiting your approval | **Actions:** Approval rules · Waiting · Approved · Rejected · Withdrawn<br>**Fields:** 1 | ☐ ok ☐ problem |
| 9 | **Inquiries** | `/inquiries` | an enquiry | **Actions:** + New inquiry · Search<br>**Fields:** 3 | ☐ ok ☐ problem |
| 10 | **Leads** | `/leads` | a lead | **Actions:** + New lead · ⬇ CSV · Board · List · Find<br>**Fields:** 2 | ☐ ok ☐ problem |
| 11 | **Opportunities** | `/opportunities` | — | **Actions:** + New opportunity · ⬇ CSV · Board · List · Find<br>**Fields:** 2 | ☐ ok ☐ problem |
| 12 | **Pipelines & funnels** | `/pipelines` | — | **Actions:** Start from an industry template · Edit stages · Create it<br>**Fields:** 5 | ☐ ok ☐ problem |
| 13 | **Pre-order checklist** | `/preorder-checklist` | — | **Actions:** Save pre-order checklist · Back to Quotes<br>**Fields:** 4 | ☐ ok ☐ problem |
| 14 | **Quote templates** | `/templates?kind=quote` | may look empty until there is data | **Tabs:** Report formats · Quote & e-mail<br>**Actions:** + Add template · Report formats · Quote & e-mail · Save letterhead · Save signature<br>**Fields:** 9 | ☐ ok ☐ problem |
| 15 | **Quotes** | `/quotes` | a quotation | **Actions:** ⬇ Export · Approval rules · Templates · + External · + New quote · Search<br>**Fields:** 2 | ☐ ok ☐ problem |
| 16 | **Sales** | `/sales` | — | **Actions:** Command Centre →<br>**Fields:** 1 | ☐ ok ☐ problem |

### Operations — 34 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 17 | **Add candidate** | `/candidate-new` | — | **Tabs:** Requirement & person · Role & where · Sourcing & money · Paperwork & outcome<br>**Actions:** ← Recruitment · Extract → · Requirement & person · Role & where · Sourcing & money · Paperwork & outcome · Next → · Add candidate · Add & select<br>**Careful:** Cancel · Cancel<br>**Fields:** 20 | ☐ ok ☐ problem |
| 18 | **Approval delegation** | `/approval-delegations` | — | **Actions:** Add delegation<br>**Fields:** 6 | ☐ ok ☐ problem |
| 19 | **Attendance reconciliation** | `/attendance-recon` | — | **Actions:** Reconcile<br>**Fields:** 3 | ☐ ok ☐ problem |
| 20 | **Candidates** | `/candidates` | a candidate | **Actions:** + Add candidate CV · All · CV received (0) · Submitted to client (0) · Shortlisted (0) · Interview scheduled (0) · Offer released (0) · Offer declined (backed out) (0) · On hold (0) · Rejected (0) · Accepted (Hired) (0) · Withdrawn (0) · Search<br>**Fields:** 1 | ☐ ok ☐ problem |
| 21 | **Capacity outlook** | `/capacity-outlook` | may look empty until there is data | **Actions:** Show<br>**Fields:** 3 | ☐ ok ☐ problem |
| 22 | **Careers page** | `/careers-admin` | may look empty until there is data | **Actions:** Open public page ↗ · Save settings · Save posting defaults<br>**Fields:** 7 | ☐ ok ☐ problem |
| 23 | **Compensation setup** | `/comp-setup` | — | **Actions:** Save · Off · Add<br>**Fields:** 3 | ☐ ok ☐ problem |
| 24 | **Contract exceptions** | `/contract-overrides` | — | **Actions:** ← Work orders<br>**Fields:** 1 | ☐ ok ☐ problem |
| 25 | **Contract openings** | `/contract-openings` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 26 | **Departments** | `/departments` | may look empty until there is data | **Actions:** 🏛️ Department list · 🗂️ Org chart · ⬆ Import organogram<br>**Fields:** 2 | ☐ ok ☐ problem |
| 27 | **Document templates** | `/doc-templates` | — | **Actions:** ＋ New template · Save letterhead<br>**Fields:** 4 | ☐ ok ☐ problem |
| 28 | **Export data** | `/recruit-export?dataset=candidates` | — | Read-only — nothing to press | ☐ ok ☐ problem |
| 29 | **Hiring requests** | `/hiring-requests` | a hiring request | **Actions:** + New request · Requisitions →<br>**Fields:** 1 | ☐ ok ☐ problem |
| 30 | **Hiring workflows** | `/recruit-pipelines` | — | **Actions:** ＋ New workflow · Save workflow · Save · ✕ · Add stage<br>**Fields:** 16 | ☐ ok ☐ problem |
| 31 | **Import org chart** | `/positions-import` | — | **Actions:** ← Positions · Read & preview<br>**Fields:** 3 | ☐ ok ☐ problem |
| 32 | **Inspector ratings** | `/ratings` | may look empty until there is data | **Actions:** Save<br>**Fields:** 8 | ☐ ok ☐ problem |
| 33 | **Jobs** | `/jobs` | a job | **Actions:** ⬇ Download CSV · Search<br>**Fields:** 6 | ☐ ok ☐ problem |
| 34 | **My approvals** | `/my-approvals` | something awaiting your approval | **Fields:** 1 | ☐ ok ☐ problem |
| 35 | **New hiring request** | `/hiring-request` | — | **Actions:** Create request<br>**Fields:** 19 | ☐ ok ☐ problem |
| 36 | **New requisition** | `/requisition-new` | — | **Refused on purpose, and this is a PASS.** You get a plain-English page, not an error: *"This workspace starts recruitment from an approved hiring request. Raise a hiring request, get it approved, then use “Start recruiting” on it."* That is the deliberate one-way street — headcount is approved **before** anyone recruits against it. PASS = you see that explanation and the route out of it. FAIL = a blank page, a stack trace, or a bare "forbidden" with no explanation | ☐ ok ☐ problem |
| 37 | **New work order** | `/call-new` | — | **Tabs:** Client & quote · What's inspected · When · Offices & money · Purchase order · Reporting<br>**Actions:** ← Work Orders · Client & quote · What's inspected · When · Offices & money · Purchase order · Reporting · + Add another date · Next → · Save work order · Add & select<br>**Careful:** Cancel · Cancel<br>**Fields:** 34 | ☐ ok ☐ problem |
| 38 | **Operations** | `/operations` | may look empty until there is data | **Tabs:** Backlog & registers · Work intake & delivery · TPIA service lines · Controls, time & people<br>**Actions:** ＋ New work order · Scheduling board · 🏢 Clients · 🚚 Vendors · Command Centre → · Backlog & registers · Work intake & delivery · TPIA service lines · Controls, time & people · Recurring · SLA targets<br>**Fields:** 1 | ☐ ok ☐ problem |
| 39 | **Org chart** | `/positions-org` | may look empty until there is data | **Actions:** 🏛️ Departments · ⬆ Import organogram · ← Positions<br>**Fields:** 1 | ☐ ok ☐ problem |
| 40 | **Positions** | `/positions` | — | **Actions:** Org chart → · ＋ New position · Save position<br>**Fields:** 13 | ☐ ok ☐ problem |
| 41 | **Project costing** | `/project-costings` | may look empty until there is data | **Actions:** Create costing<br>**Fields:** 6 | ☐ ok ☐ problem |
| 42 | **Project Deputation** | `/calls` | a work order | **Actions:** ⬇ CSV · ➕ New work order · Search<br>**Fields:** 2 | ☐ ok ☐ problem |
| 43 | **Recruitment** | `/recruitment-cc` | may look empty until there is data | **Actions:** ＋ New hiring request · ＋ Add candidate · Requisitions · Hiring requests · Candidates · 🧮 Project costing · Action view · ⚙️ Setup ▾ · ⬇️ Export ▾<br>**Fields:** 6 | ☐ ok ☐ problem |
| 44 | **Recruitment approvals** | `/recruit-approvals` | — | **Actions:** ＋ New rule · Save policy · Save triggers<br>**Fields:** 4 | ☐ ok ☐ problem |
| 45 | **Recurring services** | `/recurring` | may look empty until there is data | **Actions:** Generate due now · Create schedule<br>**Fields:** 9 | ☐ ok ☐ problem |
| 46 | **Requisitions** | `/requisitions` | a recruitment requirement | **Actions:** ➕ New requisition · Search<br>**Fields:** 1 | ☐ ok ☐ problem |
| 47 | **Scheduling board** | `/schedule` | a scheduled job | **Actions:** ‹ Earlier · Today · Later › · Find<br>**Fields:** 4 | ☐ ok ☐ problem |
| 48 | **Team member availability** | `/availability` | a team member | **Actions:** ‹ · › · Apply · Check<br>**Fields:** 6 | ☐ ok ☐ problem |
| 49 | **Timesheet** | `/timesheet` | a timesheet entry | **Actions:** Show<br>**Fields:** 2 | ☐ ok ☐ problem |
| 50 | **Vouchers** | `/vouchers` | an expense voucher | **Actions:** Open / create<br>**Fields:** 2 | ☐ ok ☐ problem |

### Recruitment — 1 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 51 | **Recruitment** | `/recruitment` | — | **Actions:** Save · 📊 Command centre · ＋ New requisition · ＋ Add candidate · Requisitions · Candidates · 🔗 Candidate pool<br>**Fields:** 2 | ☐ ok ☐ problem |

### Reporting — 9 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 52 | **Endorsement register** | `/endorsements` | may look empty until there is data | **Actions:** + New endorsement · Search<br>**Fields:** 3 | ☐ ok ☐ problem |
| 53 | **Expediting register** | `/expediting` | may look empty until there is data | **Actions:** Projects view · Reports · + New expediting report · Search<br>**Fields:** 2 | ☐ ok ☐ problem |
| 54 | **Learning insights** | `/learning` | may look empty until there is data | **Actions:** Phrase library<br>**Fields:** 1 | ☐ ok ☐ problem |
| 55 | **New report** | `/document-new` | may look empty until there is data | **Actions:** Load · Generate report<br>**Careful:** Cancel<br>**Fields:** 6 | ☐ ok ☐ problem |
| 56 | **Project delivery** | `/expediting-projects` | may look empty until there is data | **Actions:** PO register · Search<br>**Fields:** 1 | ☐ ok ☐ problem |
| 57 | **Report register** | `/documents` | an inspection report | **Actions:** Release Notes · Vendors · Report types · IRN rules · Audit log · + New report · Search<br>**Fields:** 3 | ☐ ok ☐ problem |
| 58 | **Reporting** | `/reporting` | — | **Tabs:** Reports · Expediting · Writing · Governance<br>**Actions:** Command Centre → · Reports · Expediting · Writing · Governance<br>**Fields:** 1 | ☐ ok ☐ problem |
| 59 | **Technical writing** | `/writing-assistant` | — | **Actions:** Phrase library · Convert to report language<br>**Fields:** 2 | ☐ ok ☐ problem |
| 60 | **Where we stand** | `/compliance` | — | **Actions:** Incident register · Consent register · Requests about personal data · Audit trail · + Record one · Trim what is past retention<br>**Fields:** 1 | ☐ ok ☐ problem |

### Quality & Accreditation — 24 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 61 | **Client acceptance** | `/report-reviews` | may look empty until there is data | **Actions:** ⬇ CSV<br>**Fields:** 1 | ☐ ok ☐ problem |
| 62 | **Competence & authorisation** | `/competence` | may look empty until there is data | **Actions:** ← Quality & Accreditation · Switch enforcement on<br>**Fields:** 1 | ☐ ok ☐ problem |
| 63 | **Complaints & appeals** | `/complaints` | a complaint | **Actions:** Published description · Record one · Save<br>**Fields:** 4 | ☐ ok ☐ problem |
| 64 | **Confidentiality** | `/confidentiality` | — | **Actions:** Record it<br>**Fields:** 8 | ☐ ok ☐ problem |
| 65 | **Controlled documents** | `/cdocs` | — | **Actions:** + Add a document · Search · Show all revisions<br>**Fields:** 1 | ☐ ok ☐ problem |
| 66 | **Customer satisfaction** | `/satisfaction` | may look empty until there is data | **Actions:** + Request feedback · Search · All · Awaiting reply · Needs follow-up<br>**Fields:** 1 | ☐ ok ☐ problem |
| 67 | **Data & information control** | `/data-control` | — | **Actions:** Compliance overview · Record it · Run them now<br>**Fields:** 18 | ☐ ok ☐ problem |
| 68 | **Decision rules** | `/drules` | — | **Actions:** + Add a rule · Search · Show all revisions<br>**Fields:** 1 | ☐ ok ☐ problem |
| 69 | **Disclosure consent** | `/disclosure` | — | **Actions:** Search · Add entry<br>**Fields:** 8 | ☐ ok ☐ problem |
| 70 | **Equipment & calibration** | `/equipment` | may look empty until there is data | **Actions:** + Add an instrument<br>**Careful:** Show retired<br>**Fields:** 1 | ☐ ok ☐ problem |
| 71 | **Evidence review** | `/evidence-review` | — | **Actions:** ← Reports (IDEMS) · Save<br>**Fields:** 6 | ☐ ok ☐ problem |
| 72 | **Hold & witness points** | `/hold-points` | — | **Refused because the module is not switched on for this workspace — a PASS.** You get the locked-feature page: *"This feature isn’t available in this workspace. If you believe this is wrong, contact your provider,"* with **Review your subscription** and **Back to your dashboard**. This is the subscription answer, not a permission answer — signing in as Master Admin will **not** change it, because nobody in the workspace has the module. PASS = that polite page with a way out. FAIL = a blank page or a stack trace | ☐ ok ☐ problem |
| 73 | **Identity documents** | `/identity` | — | **Actions:** 🔎 Access review (DPO) · Agency-staff roster → · ← Quality & Accreditation · Save & sweep<br>**Fields:** 4 | ☐ ok ☐ problem |
| 74 | **Impartiality** | `/impartiality` | — | **Actions:** ← Quality & Accreditation · Save · Raise it<br>**Fields:** 5 | ☐ ok ☐ problem |
| 75 | **Internal audits** | `/internal-audits` | — | **Actions:** Management reviews · Plan one · Save<br>**Fields:** 2 | ☐ ok ☐ problem |
| 76 | **Items & samples** | `/samples` | — | **Actions:** + Receive an item · Search · Show all<br>**Fields:** 1 | ☐ ok ☐ problem |
| 77 | **Management review** | `/management-reviews` | — | **Actions:** ← Internal audits · Open it<br>**Fields:** 6 | ☐ ok ☐ problem |
| 78 | **Method library** | `/methods` | — | **Actions:** + Add a method · Search · Show all revisions<br>**Fields:** 1 | ☐ ok ☐ problem |
| 79 | **Nonconformity workspace** | `/issues` | a nonconformity | **Actions:** Deviations & concessions · NCR register · Issue reports · Filter<br>**Fields:** 2 | ☐ ok ☐ problem |
| 80 | **Quality & Accreditation** | `/quality` | — | **Tabs:** Everyday quality · Accreditation registers<br>**Actions:** Command Centre → · Everyday quality · Accreditation registers<br>**Fields:** 1 | ☐ ok ☐ problem |
| 81 | **Retention schedule** | `/retention` | — | **Actions:** Edit · Add row<br>**Careful:** Remove<br>**Fields:** 7 | ☐ ok ☐ problem |
| 82 | **Risks & opportunities** | `/risks` | — | **Actions:** + Add an entry · Search · Show all<br>**Fields:** 1 | ☐ ok ☐ problem |
| 83 | **Site entry documents** | `/site-docs` | — | **Actions:** Add it<br>**Fields:** 7 | ☐ ok ☐ problem |
| 84 | **SLA targets** | `/sla-targets` | may look empty until there is data | **Actions:** Save target<br>**Fields:** 5 | ☐ ok ☐ problem |

### Money — 12 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 85 | **Billable events** | `/billable-events` | may look empty until there is data | **Actions:** ⟳ Sync from closed work · ← Money<br>**Fields:** 2 | ☐ ok ☐ problem |
| 86 | **Billing workspace** | `/to-bill` | — | **Fields:** 2 | ☐ ok ☐ problem |
| 87 | **Business Unit profit & loss** | `/sbu-pl` | may look empty until there is data | **Actions:** Management dashboard · Month-end run · Enter office costs · Show<br>**Fields:** 4 | ☐ ok ☐ problem |
| 88 | **Contract number register** | `/profitability` | may look empty until there is data | **Actions:** ⬇ Download CSV · Management dashboard · Business Unit P&L<br>**Fields:** 1 | ☐ ok ☐ problem |
| 89 | **Cost reconciliation** | `/cost-reconciliation` | — | **Actions:** ← Money · Apply<br>**Fields:** 2 | ☐ ok ☐ problem |
| 90 | **Invoice tracker** | `/invoicing` | an invoice | **Actions:** ⏳ Ageing · 📤 Tally export · ⬇ Download CSV<br>**Fields:** 1 | ☐ ok ☐ problem |
| 91 | **Money** | `/money` | — | **Tabs:** Billing · Costs & margins<br>**Actions:** Command Centre → · Billing · Costs & margins<br>**Fields:** 1 | ☐ ok ☐ problem |
| 92 | **Month-end cost run** | `/cost-run` | — | **Actions:** Enter office costs · Business Unit P&L · Management dashboard · Show<br>**Fields:** 2 | ☐ ok ☐ problem |
| 93 | **Office costs & overheads** | `/office-finance` | may look empty until there is data | **Actions:** Edit expense heads · Show · Save October 2026 · Copy last month<br>**Fields:** 24 | ☐ ok ☐ problem |
| 94 | **Profit by work order** | `/call-profit` | — | **Actions:** Management dashboard · Business Unit profit & loss · Show<br>**Fields:** 4 | ☐ ok ☐ problem |
| 95 | **Reimbursable duplication** | `/reimbursable-dedup` | — | **Actions:** ← Money · Apply<br>**Fields:** 2 | ☐ ok ☐ problem |
| 96 | **Revenue reconciliation** | `/revenue-reconciliation` | — | **Actions:** ← Money · Apply<br>**Fields:** 2 | ☐ ok ☐ problem |

### Insights — 5 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 97 | **Analytics & performance** | `/analytics` | — | **Actions:** Executive · Operations · Quality · Service · Vendor · Client & portal · Scorecard · Review · Alerts · Periods · KPI library · Data quality · Apply<br>**Fields:** 6 | ☐ ok ☐ problem |
| 98 | **Dashboards** | `/reports` | — | **Actions:** Reset · Job register · Call register · Invoicing & payments · Profitability by Contract Number<br>**Fields:** 8 | ☐ ok ☐ problem |
| 99 | **Insights** | `/insights` | — | **Actions:** Command Centre →<br>**Fields:** 1 | ☐ ok ☐ problem |
| 100 | **Management dashboard** | `/mis` | may look empty until there is data | **Actions:** Apply · Clear · ⬇ Download as CSV<br>**Fields:** 3 | ☐ ok ☐ problem |
| 101 | **Sales dashboard** | `/crm-dashboard` | — | **Actions:** ⬇ Funnel CSV · Pipeline board · 30 days · 90 days · 6 months · 12 months · 2 years<br>**Fields:** 1 | ☐ ok ☐ problem |

### Directory — 9 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 102 | **Activity** | `/activities` | — | **Actions:** Everything · Note · Telephone call · Meeting · Site visit · E-mail · WhatsApp · Task · Recorded by the system · Identity linked · Identity unlinked · Identity change refused · Joined · Typed by a person · …+4 more<br>**Careful:** Joining removed<br>**Fields:** 10 | ☐ ok ☐ problem |
| 103 | **Asset issuance** | `/asset-register` | may look empty until there is data | **Actions:** Search · × · + Add another asset · Issue them<br>**Fields:** 11 | ☐ ok ☐ problem |
| 104 | **Client holds** | `/client-holds` | — | **Actions:** Save<br>**Fields:** 3 | ☐ ok ☐ problem |
| 105 | **Client portal** | `/portal-users` | — | **Actions:** Switch it on · Create the invitation<br>**Fields:** 4 | ☐ ok ☐ problem |
| 106 | **Client register** | `/clients` | — | **Actions:** + Add Client · ⬆ Import from a spreadsheet · ⬇ Export · 🔍 Possible duplicates · Search · 360 · Open · Edit · Next →<br>**Fields:** 2 | ☐ ok ☐ problem |
| 107 | **Directory** | `/directory` | — | **Tabs:** Directory · Portals<br>**Actions:** Command Centre → · Directory · Portals<br>**Fields:** 1 | ☐ ok ☐ problem |
| 108 | **Find duplicates** | `/duplicates` | may look empty until there is data | **Actions:** ← Client register · Vendor register<br>**Careful:** Merge them<br>**Fields:** 3 | ☐ ok ☐ problem |
| 109 | **Vendor portal** | `/vendor-users` | — | **Actions:** Switch it on · Create the invitation<br>**Fields:** 4 | ☐ ok ☐ problem |
| 110 | **Vendor register** | `/vendors` | — | **Actions:** + Add Vendor · ⬆ Import from a spreadsheet · ⬇ Export · 🔍 Possible duplicates · Search · 360 · Open · Edit · Next →<br>**Fields:** 2 | ☐ ok ☐ problem |

### Marketplace — 15 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 111 | **Access requests** | `/access-requests` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 112 | **Agency bench** | `/connect-bench` | may look empty until there is data | **Fields:** 2 | ☐ ok ☐ problem |
| 113 | **Channels** | `/connect-channels` | — | **Actions:** Off (record only) · Log (simulate) · Live (provider) · Save<br>**Fields:** 5 | ☐ ok ☐ problem |
| 114 | **Guided post** | `/connect-concierge` | — | **Actions:** Next →<br>**Careful:** Cancel<br>**Fields:** 2 | ☐ ok ☐ problem |
| 115 | **Industry taxonomy** | `/connect-taxonomy` | — | **Actions:** ⚙ Manage graph →<br>**Fields:** 1 | ☐ ok ☐ problem |
| 116 | **Market analytics** | `/connect-analytics` | may look empty until there is data | **Fields:** 1 | ☐ ok ☐ problem |
| 117 | **Marketplace** | `/marketplace` | — | **Actions:** Command Centre →<br>**Fields:** 1 | ☐ ok ☐ problem |
| 118 | **Messages** | `/connect-messages` | may look empty until there is data | **Fields:** 1 | ☐ ok ☐ problem |
| 119 | **Organisations** | `/connect-orgs` | — | **Actions:** Register<br>**Fields:** 3 | ☐ ok ☐ problem |
| 120 | **Passports** | `/passport-share` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 121 | **Qualification taxonomy** | `/connect-qualifications` | — | **Actions:** Job families · Roles · Qualification levels · ITI trades · Certifications · Add job family · Save changes · Switch off · Add role · Add qualification level · Add iti trade · Add certification<br>**Careful:** Delete<br>**Fields:** 16 | ☐ ok ☐ problem |
| 122 | **Rating-integrity desk** | `/rating-disputes` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 123 | **Requirements** | `/connect-requirements` | may look empty until there is data | **Actions:** Save · 💬 Guided post — answer a few questions · Post it — open for applications · Save as draft<br>**Fields:** 14 | ☐ ok ☐ problem |
| 124 | **Talent search** | `/connect-talent` | — | **Actions:** Search<br>**Fields:** 5 | ☐ ok ☐ problem |
| 125 | **Verification desk** | `/connect-verify` | — | **Fields:** 1 | ☐ ok ☐ problem |

### Admin — 28 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 126 | **Admin** | `/admin` | — | **Tabs:** Set up your workspace · Masters · People · Configuration · Report configuration · Super admin · Connections<br>**Actions:** Command Centre → · Set up your workspace · Masters · People · Configuration · Report configuration · Super admin · Connections<br>**Fields:** 1 | ☐ ok ☐ problem |
| 127 | **Ads Pro connection** | `/adspro` | may look empty until there is data | **Actions:** Show password · Save · Test the connection · Bring leads across · Refresh 90 days of spend · ⇄ Sync now, both ways · Queue every lead we already have · Queue every enquiry we already have · Waiting to go · Sent · Failed · Not sent<br>**Fields:** 5 | ☐ ok ☐ problem |
| 128 | **AI settings** | `/ai-settings` | — | **Actions:** ← Settings · ↻ Refresh models · Save all providers<br>**Fields:** 21 | ☐ ok ☐ problem |
| 129 | **Approver mapping** | `/approver-map` | — | **Actions:** Approval rules →<br>**Fields:** 1 | ☐ ok ☐ problem |
| 130 | **Backup & restore** | `/backup` | — | **Actions:** 💾 Back up now · ⬇️ Download · ↺ Restore · ↺ Upload & restore<br>**Fields:** 2 | ☐ ok ☐ problem |
| 131 | **Build forms with AI** | `/ai-forms` | — | **Actions:** Open AI settings · Back to dashboard<br>**Fields:** 1 | ☐ ok ☐ problem |
| 132 | **Companies** | `/companies` | may look empty until there is data | **Actions:** Show password · Create company · Save prices<br>**Fields:** 26 | ☐ ok ☐ problem |
| 133 | **Company profile** | `/company-profile` | — | **Actions:** Save company profile<br>**Fields:** 14 | ☐ ok ☐ problem |
| 134 | **Company setup** | `/workspace/setup` | — | **Actions:** Search · Fix · Do this · + Add a form field · ✨ Build forms with AI · + Add a dropdown value · + Add a user · + Add a branch · Configure features · Change wording<br>**Fields:** 1 | ☐ ok ☐ problem |
| 135 | **Control panel** | `/super-admin` | — | **Actions:** Turn OFF · ＋ Load demo data<br>**Careful:** Remove demo data<br>**Fields:** 1 | ☐ ok ☐ problem |
| 136 | **Form Designer** | `/form-designer` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 137 | **Licence** | `/licence` | — | **Actions:** Licence console → · Apply the key · Save provider key<br>**Fields:** 3 | ☐ ok ☐ problem |
| 138 | **Masters** | `/masters` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 139 | **MGH Books** | `/books-bridge` | — | **Actions:** Show password · Save connection<br>**Fields:** 6 | ☐ ok ☐ problem |
| 140 | **Modules & features** | `/workspace/setup/modules` | — | **Actions:** Turn off this feature<br>**Fields:** 1 | ☐ ok ☐ problem |
| 141 | **Organisation** | `/hierarchy` | — | **Tabs:** Chart · Offices · People · Login accounts · Excel upload · Gaps<br>**Actions:** ← Home · Print · Chart · Offices · People · Login accounts · Excel upload · Gaps · Open offices · Open the master · Add a person · Open People · Open the chart · Arrange these 1 automatically · …+6 more<br>**Fields:** 2 | ☐ ok ☐ problem |
| 142 | **Product package** | `/product-package` | — | **Actions:** ← Control panel · Set up now · Apply EXAACT TPIA · Apply EXAACT Technical Staffing · Apply EXAACT Recruitment · Apply EXAACT Recruitment — In-house HR · In use<br>**Fields:** 1 | ☐ ok ☐ problem |
| 143 | **Quote & report approvals** | `/approval-rules` | — | **Tabs:** Quote approvals · Report approvals<br>**Actions:** Approver mapping → · Quote approvals · Report approvals · Add rule<br>**Fields:** 6 | ☐ ok ☐ problem |
| 144 | **Report audit trail** | `/audit-log` | — | **Actions:** ⭳ Export CSV · Filter · Clear<br>**Fields:** 7 | ☐ ok ☐ problem |
| 145 | **Report formats by service** | `/service-formats` | — | **Actions:** Save report formats · Back to service scope<br>**Fields:** 15 | ☐ ok ☐ problem |
| 146 | **Report templates** | `/templates?kind=report` | — | **Tabs:** Report formats · Quote & e-mail<br>**Actions:** Report formats · Quote & e-mail · Upload my Word file & build the form → · Add template<br>**Fields:** 11 | ☐ ok ☐ problem |
| 147 | **Role workspaces** | `/role-workspaces` | — | **Actions:** Save workspace<br>**Fields:** 3 | ☐ ok ☐ problem |
| 148 | **Roles & permissions** | `/access` | — | **Actions:** ← Settings · ✨ Apply recommended set · Reset to default · Save access for Coordinator · Undo<br>**Careful:** Cancel changes<br>**Fields:** 40 | ☐ ok ☐ problem |
| 149 | **Service scope** | `/service-scope` | — | **Actions:** Save services offered · Add dependency<br>**Fields:** 23 | ☐ ok ☐ problem |
| 150 | **Single sign-on** | `/sso` | — | **Fields:** 1 | ☐ ok ☐ problem |
| 151 | **System settings** | `/settings` | may look empty until there is data | **Tabs:** Branding & theme · Financial & norms · Display & terms · Numbering & reports · Security<br>**Actions:** Save modules · Branding & theme · Financial & norms · Display & terms · Numbering & reports · Security · Corporate Blue · Corporate Orange · Forest Green · Royal Purple · Teal · Crimson · Midnight (dark) · Open terminology · …+16 more<br>**Fields:** 40 | ☐ ok ☐ problem |
| 152 | **Terminology / wording** | `/terminology` | — | **Actions:** Use this wording · Save terminology · Back to settings<br>**Careful:** Reset to standard wording<br>**Fields:** 40 | ☐ ok ☐ problem |
| 153 | **User register** | `/users` | may look empty until there is data | **Tabs:** Chart · Offices · People · Login accounts · Excel upload · Gaps<br>**Actions:** ← Back · Bulk (Excel) · + Add a person · Print · Chart · Offices · People · Login accounts · Excel upload · Gaps · Add all 1 to the team list · Edit · Reactivate<br>**Careful:** Deactivate<br>**Fields:** 1 | ☐ ok ☐ problem |

### Me — 3 screens

| # | Screen | Open | Needs first | Buttons and actions to check | Result |
|---|---|---|---|---|---|
| 154 | **Change password** | `/change-password` | — | **Actions:** Show password · Update password<br>**Careful:** Cancel<br>**Fields:** 4 | ☐ ok ☐ problem |
| 155 | **My signature** | `/my-signature` | — | **Actions:** Clear · Save signature<br>**Fields:** 2 | ☐ ok ☐ problem |
| 156 | **Two-step sign-in** | `/two-factor` | — | **Actions:** Set it up →<br>**Fields:** 1 | ☐ ok ☐ problem |

---

# PART 5 — RECORDING AND SIGN-OFF

## 5.1 Your result sheet

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
| ★ A21 — unstarted person cannot be given work | | | | | |
| ★ H9 — forbidden address typed in | | | | | |
| ★ H11 — nothing from another company | | | | | |
| **Part 4 — every screen** *(156)* | | | | | |

**Before you record a single result, confirm you are on the new build.** The sign-in footer
reads **`v2026.10.1`** and `/users` has an **Office** column. If not, everything below is a test
of code that has already been replaced.

**And confirm the nine test records exist** (0.5). Twelve of the steps in your last run could
not be executed because D7 — the hired-but-not-joined person — did not exist. If D7 is
missing, A21 and G6B-02 are *blocked*, not *failed*, and they are different things.

## 5.2 How I will classify each failure

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

## 5.3 What "B-5 PASS" requires

All eight journeys, both Gate 6B checks, **the three ★ checks in Part 2★**, **and the Part 4
screen sweep** actually executed and accepted by you. Not inferred, not assumed, not
substituted with my local test runs. If any journey is BLOCKED we decide together whether it
blocks the release.

**The three ★ checks are not optional and must be run from Part 2★, step by step** — A21
(a person who has not started cannot be given work), H9 (a forbidden address typed straight
into the browser) and H11 (nothing from another company). Each one passes by accident if you
test it from the one-line version in Part 2, which is exactly why they are written out
longhand.

**Journey H is special:** a genuine permission bypass or any cross-company data leak is a
release blocker until understood, no matter how good everything else looks.

## 5.4 After B-5

Still open after UAT, and not part of it:

- **B-2** — a production backup taken, restored into a scratch database, and proven
- **B-3** — production environment verification
- **B-4** — deployment, checksum verification, production smoke test

**Deployment happens after all of those, not before.** Your live site will keep reporting
`2026.07.1` until then, and that is correct.

---

## Appendix — screens referenced, by address

> **Read this first.** Addresses in the **plural** (`/candidates`, `/jobs`, `/invoices`) are
> lists you can type straight into the address bar. Addresses in the **singular**
> (`/candidate`, `/job`, `/invoice`, `/requisition`, `/call`) are **detail screens of one
> record** — you reach them by clicking a row in the list. Typing one on its own shows
> *not found*, because you haven't said *which* record. **That is correct behaviour, not a
> defect — do not record it as a failure.** The same is true of the action addresses
> (`/candidate-offer`, `/job-edit`, `/credit-note-new`): they are buttons on a record, not
> places you visit.

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

**Marketplace:** `/connect` · `/connect-requirements` · `/connect-bench` · `/connect-match-weights` ·
`/connect-source`

**Reporting:** `/` (home) · `/reports` · `/mis` · `/search` (or Ctrl+K)

*Addresses are taken from the application's own route table at commit `0f2123d`. If one does
not exist on your live site, that itself is a finding — tell me which.*
