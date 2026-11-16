# Recruitment — decision pack (V51.1)

16 Nov 2026 · I ran all 133 Recruitment tests (REC-001 to REC-116 and 17 screen checks) on private copies with made-up data. They cover:
- hiring requests and their approvals;
- requisitions, candidates and the hiring workflow;
- interviews, salary structures and offers;
- joining, positions and the org chart;
- letters (Document Studio), approval delegation;
- the careers page and the candidate pool.

Each test ran twice, and the second run counts.

- **Copies used:** private copies only, no real e-mail, no real person's data.
- **Code check:** I checked all 4 serious findings in the program code myself. All 4 are confirmed.
- **Held v53:** it changes nothing in Recruitment except number formatting, so none of these defects is fixed there.
- **App changes:** none.

**How to answer:** reply with item numbers, for example *"A1 view only, A2 yes, A3 yes, B ok, C1 agree"*. Anything you don't mention stays untouched.

## Where Recruitment stands

| | Tests |
| --- | --- |
| Passed | 57 |
| Failed: 34 defects | 45 |
| Need your decision (section C) | 23 |
| Blocked (test wording out of date) | 4 |
| The robot cannot do it | 4 |
| **Total** | **133** |

The 34 defects are **4 serious (P1), 15 medium (P2) and 15 small (P3)**.

**What works well:**
- the careers page, from opening to application to thank-you, also on a phone;
- erasing a candidate's personal details;
- the approval chain itself, when the branch is filled in.

## A — Fixes that need your yes (permission, money, approvals, security)

| # | Defect | What happens today | Proposed fix |
| --- | --- | --- | --- |
| A1 | **DEF-REC-002 (P1)** | **The Business Director and the Business Unit Head can change recruitment, although the permission list gives them "View".** They can edit candidates, move them on, schedule interviews, draft offers, record a joining and save positions. *Confirmed in the code: the recruitment "change" check lets every manager role through. The permission list contradicts itself: its table says View, its footnote names the check that lets them in.* | **Follow the table: Business Director and Business Unit Head may view, not change.** Or tell me they should be able to change, and I correct the permission list instead. **Permission: needs your choice** |
| A2 | **DEF-REC-003 (P1)** | **Candidates of other branches show to a branch-limited person** in three places: the Recruitment home "Today" and "Risks" panels, a requisition's "Best matches from the pool", and the candidate pool. *Confirmed in the code: those three lists check the business unit only, not the branch.* | Apply the same branch limit as the candidate register. This belongs in the **"branch and access"** batch with the other packs. **Scope: needs your yes** |
| A3 | **DEF-REC-026 (P1)** | **A script typed into the letter letterhead runs in every letter.** A Branch Manager saved a harmless test script in Document Studio → Letterhead. It then ran when a Coordinator opened any candidate letter. This is the same kind of hole as the Messages one you approved (Marketplace A1). *Confirmed in the code: the letterhead and footer are put in the page as code.* | Keep letterhead formatting (bold, images, logo, tables, colours) but **strip anything that can run**. **Security: needs your yes** |
| A4 | **DEF-REC-005 (P1)** + DEF-REC-034 (P2) | **Editing a requisition can never be saved.** Change control sees two changes that were never made (the requisition type, and "minimum experience" blank versus 0). It then asks for "a reason for the change", but the form has no box to type one in. **Worse:** that refused save, which says "nothing was saved", has already changed Responsible 1 and 2 and written an ownership record. | (1) Stop the two false changes. (2) Add a **"Reason for the change"** box that shows when an approved field really changes. (3) Check change control **first**, so a refused save changes nothing. **Approval workflow: needs your yes** |
| A5 | DEF-REC-014 (P2) | While a change to an approved hiring request waits for re-approval, **interviews cannot be scheduled**. But the request's own screen says "Continue screening and interviewing", and the lifecycle document says that is the default | **Follow the documented default:** screening and interviews go on, while offers and joining wait. **Approval workflow: needs your yes** |
| A6 | DEF-REC-015 (P3) | A manager can approve an offer that is **still a draft**, which was never submitted | Only a submitted offer can be approved. **Approval workflow: needs your yes** |
| A7 | DEF-REC-017 (P2) | After an offer is **withdrawn or declined**, no new offer can be made for that candidate | Show "Draft a new offer". The old one stays in the history. **Offer rule: needs your yes** |
| A8 | DEF-REC-018 (P3) | Revising a saved salary structure with a **new CTC** is refused until every amount box is cleared by hand | Typing a new CTC recalculates the structure. Amounts you typed yourself are kept. **Salary: needs your yes** |
| A9 | DEF-REC-025 (P2) | Compensation setup accepts a salary heading with a **negative rate** and a **duplicate code** | Refuse both with a plain sentence. **Salary: needs your yes** |
| A10 | DEF-REC-013 (P2) | **Erasing a candidate's personal details asks for no reason.** The Branch Manager is not offered the action at all | Ask for a reason, kept in the history. Your choice of who may erase: **my proposal is Admin and Branch Manager**. **Personal data: needs your yes** |
| A11 | DEF-REC-019 (P2) + DEF-REC-020 (P3) | (1) A hiring request's **Branch** is blank by default, so a branch-limited approval delegation never applies. The acting manager never sees the approval. A branch-limited Coordinator is also offered every branch. (2) When a delegate approves, the history names only the delegate, not whom they signed for | (1) Fill the Branch from the person's own branch, and offer only their branches. (2) Show "signed for Branch Manager …" in the history. **Approvals: needs your yes** |
| A12 | DEF-REC-028 (P3) | A panel member without recruitment access, for example a Senior Inspector, **cannot find "Interviews to attend"**. The page is open to them, but no menu or search leads there | Show "Interviews to attend" to anyone who is on an interview panel. **Menu access: needs your yes** |

## B — Fixes I will make once you say "B ok" (small, no rule change)

- **B1 DEF-REC-001 (P2):** the Recruitment home filters do nothing (the filter is lost on the way). I would make them work.
- **B2 DEF-REC-004 (P2):** after a rejected hiring request is reopened, its approval history shows the rejection as "Draft" and drops the reopen reason. I would show the real decision, who made it and why, and the reopen reason.
- **B3 DEF-REC-007 (P2):** a first name of only spaces creates a "(no name)" candidate. I would refuse it.
- **B4 DEF-REC-009 (P2):** two people editing one candidate: the last save silently wins. I would add the same "someone else changed this, please reload" check that jobs and calls already have.
- **B5 DEF-REC-011 (P2):** for a candidate on a hiring workflow (every candidate by default), "Offer declined", "Accepted (Hired)" or "Issue offer" does not update the candidate's stage. So the heading still says "CV received" and the register tabs miss them. I would update the stage as the lifecycle document already says.
- **B6 DEF-REC-012 (P2):** moving a candidate on a **cancelled** requisition says "Moved to …" but nothing moves. I would say why it was refused.
- **B7 DEF-REC-016 (P2):** a requisition with some seats already filled is no longer offered on "Add candidate CV", so no more CVs can be added for its open seats. I would offer it.
- **B8 DEF-REC-021 (P2):** the hiring-workflow "Employment type" filter never matches. I would fix the match.
- **B9 DEF-REC-023 (P2):** the org-chart import ignores the "Reports-to" column header. I would read it.
- **B10 DEF-REC-006 (P3):** the duplicate-candidate warning drops the requisition you had chosen. I would keep it.
- **B11 DEF-REC-008 (P3):** negative experience is accepted. I would refuse it. *(A negative rate is the same question as Marketplace C12; I follow your answer there.)*
- **B12 DEF-REC-022 (P3):** saving an empty hiring workflow blanks its name and code, and a stage with an empty name is accepted. I would refuse both. *(Removing a stage that is in use is question C14.)*
- **B13 DEF-REC-024 (P3):** a position can report to itself through a loop (A → B → A). I would refuse the loop.
- **B14 DEF-REC-027 (P3):** a letter template with an empty body is saved, and misspelt fields like `{candidte_name}` are not flagged. I would refuse the empty body and show a warning listing unknown fields. Saving is still allowed, which answers REC-092.
- **B15 DEF-REC-029 (P3):** with the Marketplace switched off, the "link to a professional" option and the pool are still shown. I would hide them.
- **B16 DEF-REC-031 (P3):** the org-chart check gives no warning when a position is already full. I would warn.
- **B17 DEF-REC-032 (P3):** an empty "Add to checklist" form saves a nameless "Other" document. I would refuse it.
- **B18 DEF-REC-033 (P3):** the approval delegation list shows dates as 2026-11-16 instead of your date format. I would use your format.

Plus new wording for the 4 out-of-date tests:
- **REC-014:** step 7 should first set up a hiring-request change rule. Without a rule, a change waits, as the lifecycle document says.
- **SCR-recruitment:**
  - "Setup ▾" and "Export ▾" are now drop-down menus;
  - the recruiter-performance table was replaced by the requisition table.
- **SCR-requisition:** "Add source" is now "Link position".
- **REC-001 step 6:** the "Available now" counter was removed. The new wording is "Working on site now". This test still fails because of B1.

The wrong message for a too-large upload is the same as Sales DEF-SAL-003, already in the Sales pack. It is not counted again here.

## C — Questions only you can answer (my proposal in **bold**)

| # | Test | Question | Proposal |
| --- | --- | --- | --- |
| C1 | REC-006 | Should Finance see what a hire costs? | **No. Give Finance a hiring-cost summary under Money instead** |
| C2 | REC-013 | A requisition for 9999 people, or "needed by" a past date, is accepted | **Warn above a headcount you set, and on a past date. Tell me the headcount** |
| C3 | REC-023 | An end date before the start date is accepted | **Refuse** |
| C4 | REC-031 | A candidate's e-mail and mobile are not checked | **Check them: a valid e-mail; a 10-digit mobile, +91 optional** |
| C5 | REC-038 | What happens when a candidate is switched to another hiring workflow? | **Restart at its first stage, keep the history, and ask for a reason** |
| C6 | REC-041 | On a phone, "Move this candidate" is far down the page | **Move it up** |
| C7 | REC-045 | Interviews in the past, with no panel, or twice at the same time are accepted | **Warn on a past date; refuse no panel; refuse a duplicate** |
| C8 | REC-050, REC-116 | A Coordinator cannot build the salary structure, because they may not see salaries | **Keep the rule. I fix the test wording** |
| C9 | REC-054 | A CTC of 0, past expiry or joining dates, and going over the hiring budget are all accepted | **Refuse 0, warn on past dates. The budget rule is yours to set: warn only, or need an approval?** |
| C10 | REC-061 | An approved headcount increase does not raise the requisition's number of seats | **Raise it automatically** |
| C11 | REC-066 | A direct request with no approval chain is not stamped with the self-approval rule | **Stamp it** |
| C12 | REC-069 | A Coordinator's approval delegation is refused, without saying why | **Keep it refused, and say why** |
| C13 | REC-071 | Two stages may have the same order number | **Refuse** |
| C14 | REC-074 | A stage that candidates are in (or the final stage) can be removed. Where do those candidates go? | **Do not allow removing a stage while it is in use** |
| C15 | REC-077 | A position can show more people than it has seats | **Warn** |
| C16 | REC-082 | The "Basic" heading can be switched Off, but it has no effect | **Either make Off work or remove the switch. Which?** |
| C17 | REC-083 | Compensation setup opens even when Recruitment is not bought | **Hide it unless Recruitment is bought** |
| C18 | REC-093 | A relieving letter can be made for someone who never joined | **Block it** |
| C19 | REC-094 | Letters are made in three different places | **Merge them into one Document Studio (bigger work, later)** |
| C20 | REC-097 | The candidate pool has no menu entry | **Add one** |
| C21 | REC-100 | Two candidates are flagged as the same person on the name alone | **Need a second match: mobile, e-mail or date of birth** |
| C22 | REC-102 | When two agencies send the same person, which one earns the agency fee? | **The first agency to submit. Money: your rule** |
| C23 | REC-104 | Site entry documents are not shown on agency staff | **Show them** |
| C24 | REC-109 | Interview panel members may come from other branches | **Allow it, with a note on the interview** |
| C25 | REC-111 | A "% of Gross" salary heading: which formula? | **Please confirm the formula you use. I will not guess it** |
| C26 | REC-113 | Look-alike pairs nobody dealt with stay forever | **Let them expire after a period you choose. Tell me how long** |
| C27 | REC-114 | Matching treats "José" and "Jose", or +91 98… and 98…, as different | **Treat them as the same** |
| C28 | REC-030 | Recording "CV sent to client" and the client's answer does not move the candidate | **Show a "With client" marker the register counts, without moving the stage** |
| C29 | REC-022 | The lifecycle document has no requisition statuses | **May I write down the statuses the code already uses, as documentation only?** |
| C30 | settings screens | A settings form saved with everything empty saves silently, for example a requisition's position link is cleared | **Keep saving, but say what was cleared or switched off. I fix the test wording** |
| C31 | HF-153 | Is the careers page "branded" enough for you? It works and fits a phone | **Your look-and-feel call** |

Two more cases need you only because the robot logged a defect as a question: SCR-candidate-new steps 19/20 (C4), and SCR-candidate-edit steps 13/28 (B11).

## D — Older items checked again on V51.1

- **Fixed:**
  - **HF-042:** a candidate's personal details can be erased. The reference and the reason stay.
  - **HF-096:** fixed for candidates. Its second half, inspectors without a login, was not checked (F).
  - **HF-187:** recruitment figures reach the reporting interface (code reading).
- **Not reproduced:** HF-175. A CV uploaded on the careers page is checked by its content, not by its name (code reading).
- **Still exists:**
  - **HF-100, in part:** the server accepts a candidate with no requisition, although the form requires one. The permission list allows direct candidates (decision ADR-001), so please confirm that is intended. Also: no e-mail is sent when a hiring request is approved or a requisition filled. (The requisition export now exists, so that part is fixed.)
  - **HF-101 (code reading):** the inspector form has no roll type or placement fee, and there is no agency-renewal reminder.
  - **HF-190:** a spaces-only name is accepted (B3).
- **Not built yet (backlog, not defects):**
  - HF-149: a status page where a candidate can follow their application;
  - HF-150: a structured reference check and medical check;
  - HF-151: calendar invitations for interviews;
  - HF-152: importing many candidates at once.

## E — Not built

The four backlog items in D (HF-149 to HF-152). None is a defect.

## F — What is NOT tested yet

- **REC-106, the agency bench:** it lives in the Marketplace and needs that area's data.
- **Erasing inspectors who have no login** (the second half of HF-096): outside Recruitment.
- **Screen-check steps the robot could not judge:**
  - 191 stage-row steps on the workflow editor. The editor was tested by hand in REC-071 and REC-074.
  - **73 requisition-edit steps:** every save is refused (A4). I will run them after that fix.
  - steps 3–7 on the requisition ("No longer needed" form).
  - the hidden "Deployment group" box on Add / Edit candidate.
- **The robot cannot do these:**
  - two presses at exactly the same moment (REC-021);
  - a real inbox (REC-067);
  - how a printed letter looks on paper (REC-091);
  - a real phone's file picker (REC-112).
- **Only SQLite was used.** The MariaDB run comes with the fix batch.

**A note on the tracker, for honesty:** at one point the tester's own check command posted its results early. It then posted the changed results again. The tracker keeps the latest result for each test, so the totals above are right. The raw log still holds those earlier lines.

So the Recruitment picture is: **4 serious defects (wrong permission for two manager roles, other branches' candidates on show, a script hole in letters, requisition edit that can never save); 15 medium and 15 small defects; 31 questions; 4 out-of-date tests.**

## After you answer

- **A2** goes in the **"branch and access"** batch, with the other packs. **A1** joins it if you choose "view only".
- **A3** is a security fix. I recommend it goes **with the Messages fix** to the live site.
- **A4–A12** form one "recruitment approvals and offers" batch.
- **B** goes in a small Recruitment batch.
- Each batch gets:
  - the full suite on SQLite and MariaDB;
  - the robot tests again;
  - a zip for the test copy;
  - the changed-files list;
  - the rollback steps.
