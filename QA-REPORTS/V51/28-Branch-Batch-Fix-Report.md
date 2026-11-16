# Branch & access batch — fix report and opstest update (16 Nov 2026)

You approved the plan (`27-Branch-Batch-Change-Plan.md`) with: *"Ok now please fix the things as per your recommendation."* Your later points are included:
- the voucher chain with office Finance and central Finance;
- the Coordinator's delegate may reopen a report;
- the internal candidate pool with the hard block;
- the Marketplace stays separate (the team-passport change was withdrawn).

**What this batch does NOT touch:** no price, GST, invoice amount or billing rule, and no quotation or costing approval rule. Your quotation and costing chains work exactly as before; a test proves it.

**It does change:**
- who may see or change records, always to match your permission list or your own words in the plan;
- **one report lifecycle step you approved (C2):** "Approved → back to Draft" by the executing office;
- the **voucher approval chain (R5)**, which is new.

## What changed, in plain words

| # | What you will see | Example |
| --- | --- | --- |
| B1 | A person in a branch-limited role whose home office is empty sees **no** work orders, with the "no branch yet" message. Top roles still see everything | Coordinator Ravi with no office sees nothing until the admin sets Mumbai |
| B2 | Changing a work order's status, priority or criticality checks the branch **before** saving | A Mumbai Coordinator changing Ahmedabad's WO is refused; nothing is saved |
| B3 | The Operations home and scheduling board show only your branch's work orders. The deputation register is limited by branch. The **contracting office can open** a work order it passed on, but only the executing office runs it | Mumbai sold WO-50 and Ahmedabad carries it out: Mumbai can open it; Pune cannot |
| B4 | A report can be opened and edited by the **issuing, executing and contracting** offices only. Every contracting-office edit is written in History. Saving no longer wipes the client and vendor | "Edited by Priya (Mumbai — contracting office): Title, Location changed" |
| B5 | "Start from an inspection call" offers only your branch's work orders | Ahmedabad no longer loads Mumbai's client and PO |
| B6 | **Internal candidate pool:** every office sees not-yet-hired candidates. A candidate in process shows **"Recruitment in process — Ahmedabad (Interview)"**, and **no other office can do anything with them** until that process ends. Master Admin switch: Hiring workflows → "Offices can see each other's candidate pool" (on). "Today" and "Risks" stay each office's own | Mumbai can see Asha in the pool, but cannot schedule her interview while Ahmedabad is hiring her |
| B7 | Your company's postings record which office posted them. Only that office (and company-wide roles) can award, book or bill them. **Nothing changes for professionals or customers** | Mumbai sees Ahmedabad's posting but has no Award button |
| R1 | Business Director and Business Unit Head may change recruitment records (the permission list now says so) | No screen change |
| R2 | An Inspector edits only their **own** reports | Amit can no longer change Rahul's draft |
| R3 | Containment, disposition ("accept as it stands") and reopening of a nonconformity are for whoever may **close** it (Quality / Technical Head, admins). Branch and Operation Managers still record | — |
| R4 | The Business Development Manager no longer opens the Operations home. The Branch Application Manager no longer allocates jobs | — |
| R5 | **Expense vouchers — new approval chain** (details below) | ₹8,000: Neha (N+1) → Sunil (N+2) → Finance → Approved |
| C1 | Finance and Marketing Manager now see **the offices ticked on their record**. Everyone holding these roles **today was given all offices**, so nothing changes for them. New people need offices ticked | — |
| C2 | A Coordinator (or above) of the **executing office**, or their delegate, can **send a report back to the inspector** with a reason: from submitted, under review or **approved**. The approval chain then runs again. An **issued** report is corrected by **"Reissue as new revision"**, which works again (R1, R2 …) | Report approved by mistake with a wrong date → the Ahmedabad Coordinator sends it back; the inspector corrects and resubmits |
| P1 | "Change permissions" on a client portal user edits **that** person, within that client only. The same root fix repairs the method library pages and the audit-log office filter | — |
| P2 | A client person limited to one site sees and approves deputations of **that site only** | — |
| P3 | Portal alerts show only what the person may open | — |
| P4 | A Coordinator manages portal people of **their own office's clients** only. **"New link" is e-mailed** to the client person; staff never see it | — |
| P5 | A portal request from a client **on hold** cannot be turned into a work order | — |
| X1 | **Hold & witness points work again** (Reporting module) | — |

### The voucher chain (R5), step by step

1. **Set it up:** Admin → Approvals → **Expense vouchers** (Master Admin). Tick the levels to use: N+1 … N+4 from "Reports to", Office Finance and Central Finance. For each, set "needed above ₹" (blank = always). **No amounts are preset.**
2. **Until you set it:** every voucher goes N+1 → Finance.
3. **A worked example, with your bands set:** a voucher of ₹8,000 goes N+1 → N+2 → Finance (office, then central if ticked) → **Approved**.
4. **Who does what:**
   - Each approver sees it in **Approvals** and **"Your pending tasks"**.
   - Anyone in the chain can **send it back** with a reason. It returns to Draft, the inspector sees why, and resubmitting starts again.
   - **Finance marks Paid.**
   - The Business Director views only.
   - An approver who is away can be covered through **Admin → Approvals → "Who covers for a voucher approver"**.
5. **The voucher page** shows where it stands ("Waiting for: Sunil (N+2) → Office Finance") and a **History** tab.
6. **Everyone's own vouchers:** staff who are also team members (every login on the test copy is) see **their own** vouchers on the voucher page. They never see anyone else's unless they hold voucher rights.

## How it was tested

| Check | Result |
| --- | --- |
| **New automatic tests for this batch** (13 files) | **373 checks, all pass.** On the old code most of them fail: 59 (access), 51 (recruitment), 45 (portal), 27 (vouchers), 13 + 13 + 5 + 8 + 3 (reports), 13 (marketplace), 4 (deputations) |
| **Full suite, SQLite, first run** | Found **8 real integration problems** that each part's own tests could not see. **All fixed** (see "What the full run caught") |
| **Full suite, second run after the fixes** | **SQLite: 20,787 passed, 0 failed.** **MariaDB: 20,776 passed** before the same known test-harness stop as every earlier MariaDB run. The 24 failures are exactly the known MariaDB test-order ones from the India-time run, line for line. **No new failure on either database** |
| **Robot, 59 real-screen tests, before vs after the batch** on identical copies | **Before: 21 passed, 21 failed. After: 29 passed, 9 failed.** The other 17, in both runs, are "needs you", out of date or incomplete. **13 tests improved:**<br>• **now pass:** ADM-014, DIR-036, OPS-083, OPS-085, QA-056, QA-091, REC-002, REP-007, REP-018;<br>• **failure now gone, a "needs you" step left:** DIR-022, QA-040;<br>• **fewer failing steps:** OPS-004, and the portal-permissions screen check. Its failures are gone; one step's wording is out of date.<br>**No screen got worse.** Two tests now read "out of date" because of your new rules, not a fault: the voucher list now opens for any team member with **their own** vouchers (Q4), and a Coordinator is no longer offered Approve unless they are in that voucher's chain (R5). The remaining failures are known defects in other packs, or tests needing data their tester prepared, and they fail the same way before and after |

**What the full run caught (all fixed before this zip):**
1. The new voucher approval table was not created at start-up. It is now.
2. Two new permission checks were not yet listed in the licence safety test. They are, with the reason.
3. Three code-wording tests followed functions this batch renamed. They were updated; the protections they look for are all still there.
4. The new Marketplace and report tests left test records behind that changed other tests' counts. They now clean up after themselves.
5. A release-note test assumed anyone may start a revision. It now checks both that the right person can and that others cannot.

**Known test weakness, not this batch:** one time-of-day test ("due later today") fails close to Indian midnight. It fails the same way on the code from before this batch.

## The update zip — for the TEST COPY (opstest) only

`EXAACT-V51.1-BranchBatch-TEST-COPY-update.zip`
- **Contents:** 818 files, at the top of the zip. Compared with the India-time zip, 46 files differ: the changed program files, 3 new files (`lib/voucher_approval.php`, `lib/recruit_pool.php`, `views/ops/voucher_approvals.php`), the deploy-check page and `VERSION.txt`.
- **Release stamp:** f4b848d. Deploy-check on the build: **723 of 723 files match.**
- **SHA-256:** `f797827cec09e40a171ab7cc91938812a562b6574ce460a0f8f97e02168117c9`
- **Left out:** no `tests/` folder, `config.local.php`, `tenants.php`, database file, or `exaact_data` / `exaact_backups` folder.
- **Includes** everything already on opstest (security fix and India time).
- **Database:** only **additions**, made automatically on first page load. Nothing is deleted or rewritten:
  - a new table for voucher approvals;
  - new voucher columns (approval round and send-back reason);
  - an office on Marketplace postings;
  - "created by" on reports;
  - three settings.
  - **One data change (C1):** current Finance and Marketing Manager users get "all offices", so nothing changes for them.

**To install on opstest:**
1. **Back up opstest:** mPanel → Backups, then Admin → Backup & restore → Take a backup now.
2. File Manager → the opstest **app** folder → upload the zip → Extract → overwrite → delete the zip.
3. Open `https://opstest.mghaiapps.com/deploy-check.php`. You should see:
   - Release **f4b848d**;
   - the green ✓;
   - the India time line.

   **Send me that screenshot.**

## What to check on opstest (about 30 minutes)

Use your test logins from the test book.
1. **Work orders:**
   - As a **Mumbai Coordinator**, open the work-order list: only Mumbai's (and ones Mumbai sold).
   - Open one Ahmedabad carries out for Mumbai: it opens, but status and priority can't be changed from Mumbai.
2. **Reports:**
   - As **Inspector 1**, open Inspector 2's draft: refused.
   - As the **executing-office Coordinator**, open an **approved** report → "Send back to the inspector", with a reason. The inspector gets it back in Draft.
   - Open an **issued** report → "Reissue as new revision": it creates R1.
3. **Vouchers:**
   - As **Master Admin**, Admin → Approvals → **Expense vouchers**: tick levels and save.
   - As an **inspector**, submit a voucher.
   - As their **"Reports to"** person, approve it from Approvals.
   - As **Finance**, approve it, then mark it Paid.
   - Try "Send back" once: a reason is required, and the inspector sees it.
4. **Candidates:** with a candidate in an interview at one office, sign in as the **other office**: the badge shows, and scheduling or moving the candidate is refused.
5. **Client portal:** as a Coordinator, open Client people. Only your office's clients are listed. "New link" says "sent".
6. **Hold & witness points:** open the register from Quality. It opens.
7. **Your quotation approvals** work as before: submit one quotation and approve it.

Tell me anything that looks wrong; a screenshot helps.

## Rollback

1. **Safest:** restore the backup taken before uploading.
2. Or upload the previous zip (`EXAACT-V51.1-IndiaTime-TEST-COPY-update.zip`) over it. The added table and columns stay but are simply unused. Finance and Marketing Manager users given "all offices" keep it, which matches how they worked before.

## Points for you to decide later (none blocks this batch)

**Vouchers**
1. A Director who is someone's N+2 signs that level. Agree?
2. Should an Asst. Manager see the whole branch's vouchers, or only their own chain's?
3. Should the Business Unit Head lose "reopen" like the Director?

**Reports**

4. May the **issuing** office's Coordinator also start a revision when it differs from the executing office?
5. Should inspectors also be unable to **read** other inspectors' reports?
6. Should the contracting office be unable to **submit** a draft?

**Candidates**

7. Should another office see the full candidate page, including offer and salary, or a reduced view?
8. Should a second record for the same mobile be **blocked** while the person is in process elsewhere?
9. Should a candidate whose process ended appear in other offices' "Best matches"?

**Client and vendor portal**

10. Should the **vendor** portal's "New link" get the same e-mail-only fix?
11. Should client requests be limited by office?
12. Should the remaining client-dashboard counts follow the site limit?

**Marketplace**

13. Should "Put forward one of your own team" on a posting be limited by office?
14. Keep or remove the staff **"Team passports"** screen?

**Nonconformities**

15. Should a Branch / Operation Manager still be able to assign a nonconformity's owner and open a corrective action from it?

**New defect found during this batch**

16. **DEF-REP-016:** an approver can still approve a report that an earlier approver sent back for correction. Fix it?

Your "B ok" lists and the money answer sheet come next.
