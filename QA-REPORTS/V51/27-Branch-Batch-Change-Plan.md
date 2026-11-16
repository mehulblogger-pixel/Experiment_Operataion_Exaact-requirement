# Branch & access batch — what exactly will change (for your OK before any coding)

16 Nov 2026 · You answered answer sheet 1 and asked me to explain every change in detail, with examples, **before** I touch the test-copy code. This is that explanation. **Nothing has been changed yet.**

The examples use two offices, **Ahmedabad** and **Mumbai**, and people with names like the test copy's.

**How to answer:** *"Plan OK"* if everything below is right, plus your answers to the **6 questions** at the end (Q1–Q6). Or tell me any item that is wrong.

---

## Part 0 — Update after your second reply (16 Nov 2026)

You added five points. Here is how each changes the plan. Where a point goes beyond today's rules, it is marked **NEEDS YOUR YES**.

**0.1 Finance and Marketing Manager are central teams: office chosen per person.**
- **Today:** both roles automatically see every office.
- **After:** they see **the offices ticked on their record**. Tick all offices for someone who needs the company-wide picture. They still see only finance data (Finance) or sales data (Marketing Manager), as today.
- *Example:* Finance person Meera has Ahmedabad and Mumbai ticked. She sees invoices, receipts and vouchers of those two offices only. Finance head Rakesh has all offices ticked and sees everything.
- **NEEDS YOUR YES (C1):** if a Finance or Marketing Manager has **no** office ticked, they see nothing. Quotation approvals waiting for the Marketing Manager (your level 2, ₹5–25 lakh) would then have nobody to approve them. **My recommendation:** when the update is installed, **everyone who holds these roles today is given "all offices"**, so nothing changes for current people. Only new people need offices ticked. Your admin can narrow anyone at any time.

**0.2 Reports: the inspector who did the inspection opens, edits and submits it.**
- **Yes, that stays exactly so:** the inspector who conducted it (or created it) works on their own report, and no other inspector can (R2).

**0.3 Reports: after submission, approval or issue, the inspector can edit again only if a Coordinator of the executing office opens it.**

| Report stage | Today | After |
| --- | --- | --- |
| Draft, Sent back | Inspector edits | Unchanged |
| Submitted / under review | Only the approver can send it back | **Also** a Coordinator (or above) **of the executing office** can send it back to the inspector, with a reason |
| Approved, not yet issued | **Nobody can send it back** | A Coordinator (or above) of the executing office can send it back to Draft, with a reason. The approval chain then runs again |
| Issued to the client | Locked; "Reissue as new revision" (broken today, Reporting A3) | The executing-office Coordinator starts a new revision (R1, R2 …). The inspector edits the revision, and it goes through approval and issue again. The issued original never changes |

- **NEEDS YOUR YES (C2):** "Approved → back to Draft" is a **new step in the report lifecycle** (today an approved report can only be issued). I will write it into `docs/03`. Each send-back records who, when and why in the History tab.
- Reporting A3 (repairing "Reissue") is needed for the last row, so it is now part of this batch.

**0.4 Vouchers: Finance approves last, after the reporting chain, with all attachments.**
- *Example:* Amit's ₹8,000 voucher → Neha (N+1) approves → Sunil (N+2) approves → **Finance** checks the attachments and approves → **Approved**.
- **Which Finance person:** any Finance person whose ticked offices include the voucher's office (0.1). If there is none, the admin sees a warning.
- Finance can also send it back with a reason, like any approver (Q3).
- This gives Finance an approval right on vouchers; your permission list says "View" today. I will update the list.
- **Question C3:** who marks a voucher **Paid**? Today a Coordinator-level person does. **My recommendation: Finance**, since they approve last and make the payment.

**0.5 Candidate pool: central, not owned by a branch, with a master switch.**
- **The candidate pool and "Best matches":** every office sees all candidates who are **not yet hired**, from any branch.
- **A badge:** once a candidate is in process, every office sees "**Recruitment in process — Ahmedabad (Interview stage)**" on the candidate. "Onboarding in process" shows once joining has started.
- **One switch for the Master Admin:** "Offices can see each other's candidate pool", **on** by default as you described. Off = each office sees only its own pool.
- **Stays branch-limited:** the Recruitment home "Today" and "Risks" panels, because they are each office's own to-do list (interviews today, offers expiring), and the candidate register's own records.
- **Question C4:** can another office **also** start a process with a candidate who already carries the "in process" badge? **My recommendation: yes, but with a warning** naming the office already working with them, so two offices never make offers to the same person unknowingly.

**So the questions now are C1–C4 (above) plus Q1–Q6 (Part 4).** The one-line answer, if you agree with my recommendations:
> **"Plan OK. C1–C4 and Q1–Q6 as recommended, A3 yes."**

---

## Part 0b — Your final answers (16 Nov 2026): "Ok now please fix the things as per your recommendation"

- **Approved as recommended:** C1, C2, C3, Q1–Q6 and Reporting A3.
- **0.1, in your words:** the existing quotation and costing approval chains (Admin → Approvals: N+1, then N+2 … up to the SBU Head and the Director) must keep working after Finance and the Marketing Manager become office-based. **Vouchers get the same kind of chain**, and **can have an office-level Finance approval and then a central Finance team approval**, in addition to the reporting-line levels.
- **0.3:** if the executing office's Coordinator is not available, **their delegate** (existing Approval delegation) can reopen or send back the report.
- **0.5, C4 replaced by your rule:**
  - The candidate pool is the **company's own internal pool** (CVs received directly, from consultants, HR and so on), **not the Marketplace**, which is a separate paid module.
  - It is shared by all offices; no branch owns a candidate.
  - **No other office can start anything with a candidate whose recruitment or onboarding is already in process with another office.** The candidate shows the in-process badge. Other offices are refused, not just warned.
  - The Master Admin switch (offices see each other's pool) stays, on by default.

---

## Part 1 — Your question about the Director (B1)

**Question:** with "no office → sees nothing", which office do we set for the Director, Managing Director or head of the company?

**Answer: none. The top roles already see every office, with no office set and nothing to tick.** Each role has a built-in reach:

| Role | Sees | Office to set? |
| --- | --- | --- |
| Master Admin, Admin | Every office, every business unit | No |
| **Business Director** (use this for a Managing Director or company head) | **Every office, every business unit** | **No** |
| Business Unit Head | Every office, but only their own business unit | No |
| Finance, Marketing Manager | **The offices ticked on their record**; tick all for the company-wide picture (see 0.1) | **Yes: tick their offices** |
| Branch Manager, Operation Manager, Asst. Manager, Coordinator, sales and marketing executives, inspectors | **Their own office only** | **Yes: their home office** |

- **Someone who covers 2 or 3 branches** (a regional manager, for example): an admin ticks those offices in the person's record ("Offices covered"). That already exists.
- **The B1 fix only affects the last row:** a branch-limited person whose home office was left empty. Today the work-order register shows them **every branch**. After the fix it shows **none**, with the existing message "your account has not been given a branch yet". The User register already marks such people, so an admin can fix them in a minute.

*Example:* Ravi is a Coordinator created without a home office.
- **Today:** he sees Ahmedabad's and Mumbai's work orders.
- **After:** he sees none, with the "no branch yet" message. The admin sets Home office = Mumbai, and he sees Mumbai's work orders only.
- **Unchanged:** the Business Director sees everything, before and after.

---

## Part 2 — The items you changed

### B4 — Who may edit a report (your rule)

**Your rule:** a report can be edited only by the **executing office** or the **office that issued it**. The **contracting office** may also edit it if needed, but every such edit must be **recorded in the History tab**.

**What I will do:**
1. **View and PDF:**
   - **Today:** only the report's own office can open a report.
   - **After:** the issuing office, the executing office and the contracting office of its work order can all open it. Anyone else is still refused.
2. **Edit and fill:** the same three offices, and nobody else. Today anyone who types the edit address can edit; that is the hole being closed.
3. **Contracting-office edits** are allowed, and each one writes a History line:
   > *"Edited by Priya Shah (Mumbai — contracting office): Location, Remarks changed"*

   Edits by the issuing or executing office keep being recorded as today.
4. **The data-loss bug:** saving the edit form no longer wipes the report's client and vendor.
5. **Issue, approve and the other rules stay exactly as they are.**

*Example:* a work order sold by **Mumbai** (contracting office) and inspected by **Ahmedabad** (executing office, which also issues the report).
- **Ahmedabad Coordinator:** opens, edits and fills the report, as today.
- **Mumbai Coordinator:** may now open and edit it; the History tab shows that Mumbai changed it.
- **A Pune Coordinator:** refused everywhere, including the edit address that works today.

### B7 — The Marketplace (rewritten after your explanation)

You explained that the Marketplace is a separate place. Professionals find work there, and clients or companies find manpower. Your company's offices mean nothing to outsiders. I agree, and I have changed the proposal accordingly.

**What outsiders see: nothing changes.** Professionals and outside clients never see your offices, staff or branches.

**Inside your company, two things change:**
1. **A posting remembers which of your offices posted it.** This is filled in automatically from the poster's home office. Every office can still **see** every posting, so discovery stays company-wide, as you decided on 13 Oct. But only the posting office (plus the company-wide roles in Part 1) can **award, book, bill or close** it.

   *Example:* Ahmedabad posts "Need 2 NDT Level II for 10 days".
   - **Today:** a Mumbai Coordinator can award it and bill it, and Ahmedabad finds out later.
   - **After:** Mumbai can see the posting and the applicants, but the Award, Book and Bill buttons are only for Ahmedabad and the company-wide roles.
2. **Team passports** (the shareable profile of one of your own team members):
   - **Today:** any Coordinator sees every branch's people, and can cancel another branch's shared link.
   - **After:** they see only their own office's people; company-wide roles see all.
   - Also, a share link is created only when someone presses the button, not just by opening the page.

**Your badge idea** (once an office hires a professional, internal users see a badge with the company and office) is **new work, not a fix**. I propose it as Q5 below.

### R1 — Business Director and Business Unit Head in Recruitment: allowed to change

You chose **"R1 allowed to change"**. So **no code changes here.** Instead I correct the permission list (`docs/02`), so the list and the app agree:
- **Hiring / Candidates:** Business Director = **Edit**, Business Unit Head = **Edit**.
- They can add and edit candidates, schedule interviews, draft offers, record joining and manage positions.
- **Approving an offer stays a manager decision, as today.**
- The Business Unit Head only works on candidates of **their own business unit**, as their reach already says.

### R5 — Expense vouchers: an approval chain by amount, from the reporting line

**Your rule:**
- The inspector creates, edits, views and submits.
- The person they report to approves or rejects.
- Above a set amount, more levels must approve: N+1, N+2, N+3, N+4, taken from the organisation chart.
- The Master Admin sets the amounts and the levels.

**How it will work:**
1. **The organisation chart is each person's "Reports to"**, which you already fill in on the team-member and user records.
   - **N+1** is the inspector's "Reports to";
   - **N+2** is that person's "Reports to";
   - and so on upwards.
2. **Master Admin sets the bands** on a new **Admin → Approvals → Expense vouchers** page. Your example becomes:

   | Voucher total | Must be approved up to |
   | --- | --- |
   | up to ₹5,000 | N+1 |
   | ₹5,001 – ₹10,000 | N+2 |
   | ₹10,001 – ₹20,000 | N+3 |
   | above ₹20,000 | N+4 |

   The amounts and levels are yours to change at any time. I use your example only to explain; **I will not set any amount myself.** Until the Master Admin saves bands, every voucher needs N+1 only.
3. **Worked example:** Inspector **Amit** (Mumbai) reports to Coordinator **Neha**. Neha reports to Operation Manager **Sunil**. Sunil reports to Branch Manager **Kiran**. Amit submits a voucher of **₹8,000** (band N+2).
   - Neha (N+1) sees it in her pending tasks → approves.
   - Sunil (N+2) then sees it → approves → the voucher is **Approved**. Kiran is not asked, because ₹8,000 only needs N+2.
   - If Neha **rejects** it, she must give a reason. Amit sees the reason, corrects the voucher and submits again (see Q3).
   - Each approval shows in the voucher's History: who approved, at which level, and when.
4. **The safeguards stay:**
   - nobody approves their own voucher;
   - the person who submitted it cannot approve it;
   - an approver on leave can be covered through the existing **Approval delegation**.
5. **Who no longer approves:** today any Coordinator-level person in the branch can approve any voucher. After the change, **only the people in that inspector's own chain** can.
6. **Unchanged:**
   - **Finance** can **view** vouchers (your decision D-07; refused today), and now also **approves last** (see 0.4).
   - **Reopening** stays as today. Who marks **Paid** is question C3.
   - A **frozen month** still locks editing.
7. **Documents updated in the same change:** the permission list (`docs/02`) and the voucher lifecycle (`docs/03`).

---

## Part 3 — The items you accepted as proposed (what each one means)

| # | What changes | Example |
| --- | --- | --- |
| B2 | Changing a work order's status, priority or criticality checks the branch **before** saving | A Mumbai Coordinator who changes Ahmedabad work order WO-0045 to "On hold" is refused, and **nothing is saved**. Today the change is saved, and only then does the message say "outside your scope" |
| B3 | The Operations home and scheduling board show only your branch's work orders, also when the executing office is blank. The deputations list is limited by branch. The **contracting office can open** a work order it passed to another office | Mumbai sold WO-0050 and passed it to Ahmedabad to carry out. Mumbai can now open it (today it is refused). Pune cannot |
| B5 | "New inspection report → Start from an inspection call" offers only your branch's work orders | An Ahmedabad Coordinator no longer sees, or loads, Mumbai's client, vendor and PO |
| B6 | **Changed by you (see 0.5):** the "Today" and "Risks" panels stay each office's own. The candidate pool and "Best matches" are central, with the in-process badge and the Master Admin switch | A Mumbai recruiter no longer sees Ahmedabad's interviews in "Today", but does see every not-yet-hired candidate in the pool |
| R2 | An Inspector edits only their **own** reports (where they are the inspector or the creator) | Inspector Amit can no longer open Inspector Rahul's draft and change it |
| R3 | A nonconformity's containment, disposition ("accept as it stands") and reopening go to whoever may **close** it: the Quality / Technical Head and admins. Branch and Operation Managers keep View and **Record** | A Branch Manager can still raise a nonconformity, but can no longer accept it as it stands |
| R4 | The Business Development Manager no longer opens the Operations home. The Branch Application Manager no longer allocates jobs | Both as your permission list already says |
| P1 | "Change permissions" on a client portal user edits **that** user, within that client only | Changing TB Steel's user no longer changes a different client's user |
| P2 | A client person limited to one site sees and approves deputations for **that site only** | A TB Refinery user for the Jamnagar site cannot approve attendance at the Vadodara site, which feeds your billing |
| P3 | Portal alerts show only what the person may open | An accounts-only client user is not told "Report X issued to you" |
| P4 | A Coordinator manages portal people for **their own office's clients** only. **"New link" is e-mailed to the client person**, and staff see only "sent" | The Mumbai Coordinator no longer sees Ahmedabad's client logins, or a password-setting link on screen |
| P5 | "Accept & raise the call" from a portal request is refused for a client **on hold**, with the usual hold message | A request from a client on hold stays open; no work order is created |
| X1 | Hold & witness points start working (the screen was linked to a module name that does not exist) | Admin, Quality and Coordinators can open the register again. The 7 blocked tests are then run |

**All of the above:**
- **gives no new right to anyone:** each change brings the app back to your permission list, or follows your own words here;
- **comes with tests:** a new automatic test for each item, the full suite on both database types, the robot tests of every affected module, and then a **zip for opstest only**, with the changed-files list and the rollback steps.

---

## Part 4 — Six questions (my recommendation in **bold**)

| # | Question | Recommendation |
| --- | --- | --- |
| Q1 | Voucher of ₹8,000 (band N+2): must **both** N+1 and N+2 approve, one after the other, or **only** N+2? | **Both, in order (N+1 then N+2).** The direct manager checks the details; the higher level approves the amount. This is the usual practice |
| Q2 | The chain is shorter than the band. For example, Neha reports directly to the Director, so there is no N+3 or N+4. Who approves then? | **The highest person in that inspector's line approves, and the chain ends there.** If the inspector has no "Reports to" at all, the voucher waits for an admin, and the admin sees a warning to fill in "Reports to" |
| Q3 | When a voucher is **rejected**: send it back to Draft with the reason (the inspector corrects and resubmits), or add a new final status "Rejected"? | **Send back to Draft with the reason.** No new status is needed; the History keeps the rejection and its reason |
| Q4 | Should the same amount bands also apply to **coordinators' and other staff's own vouchers**, or only to inspectors'? | **Everyone's vouchers, the same way: N+1 is whoever the claimant reports to** |
| Q5 | Your badge idea: once an office engages a professional, internal staff see on that professional's profile "Working with us — Ahmedabad office (since 12 Nov)". Outsiders see nothing. Add it now (new work) or later? | **Later, as its own small piece of work**, so this batch stays fixes only |
| Q6 | B4: when the **contracting** office edits a report that has already been **issued** to the client, is that allowed, or only before issue? | **Only before issue.** After issue, a correction should go through "Reissue as new revision" (R1, R2), so the client always gets a properly numbered revision. That button is broken today (Reporting **A3**, "Not found"). **Reporting A3 is now part of this batch (see 0.3)** |

**After your "Plan OK" and Q1–Q6:**
1. I build the batch.
2. I test everything.
3. I send the opstest zip, with a plain list of what to check on screen.
