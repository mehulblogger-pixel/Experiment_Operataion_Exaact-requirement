# Batch 2 — fix report and opstest update (17 Nov 2026)

You approved plan 29 (`29-Next-Changes-and-Open-Points.md`) on 17 Nov 2026:
- Change 1 and Change 2;
- your answers A–D;
- the other points as recommended;
- "Q1. Let it be at present but it is good to have. Q2 Ok. Q3 Ok."

This report says what was built, how it was tested, how to install it on the **test copy (opstest)**, and what to check.

**What this batch does NOT touch:**
- prices, GST, invoice amounts or any billing rule;
- quotation or costing approvals;
- voucher amounts. No amount is preset anywhere; you set every "needed above ₹" yourself.

**No new status and no new permission.** The report rules use the statuses that already exist. Every "who may do what" change follows your words in plan 29 and is written into the rule documents (docs 01–04) in the same change.

## What changed, in plain words

### Reports

| # | What you will see | Example |
| --- | --- | --- |
| Change 1 (DEF-REP-016) | When an approver **rejects** or **sends back** a report, every approval step still waiting after them is **closed at once**. The report leaves the later approvers' pending lists, dashboards and reminders. The History says "Rejected at level 1 by Neha: <reason>". A report can be approved **only while it is under review**; an old browser tab gets a plain refusal. Resubmitting starts again from level 1 | Neha (level 1) sends Amit's report back. Sunil (level 2) no longer sees it. When Amit resubmits, Neha gets it first again |
| Point 4 | A new revision of an **issued** report may also be started by a Coordinator (or above) of the **issuing** office, or their delegate, when it differs from the executing office | Mumbai issued the report, Ahmedabad carried out the job: both offices' Coordinators can press "Reissue as new revision" |
| Point 5 | An Inspector **opens only their own reports**: the page, PDF, Word copy, photos, register and search. "Own" means they are the report's inspector or created it. Two exceptions: a report whose approval chain names them, and the Senior Inspector's finalising work | Amit cannot find or open Rahul's report, even by typing its address |
| Point 6 | The **contracting** office may edit a draft but **not submit** it. The issuing or executing office, or the report's own inspector, submits | Mumbai (contracting) corrects a title; Ahmedabad submits |
| B — new | **"⏭ Start next stage report"** on an approved or issued report. It makes a **new draft** with the **next number in the same series**. The draft is filled in from the earlier report: client, PO, item, specifications, form fields and earlier findings. Left empty: result, conclusion, inspection date, photos, files, signatures and approvals. The two are linked: "Continues from …/IR/000001" and "Next stage: …/IR/000002". **The earlier report never changes.** Who may press it: the inspector allocated to a job on the same work order (from their job page), or a Coordinator (or above) of the issuing or executing office, who picks the inspector | Stage 1: Amit issues `…/IR/000001`. Stage 2 is allocated to Rahul. On his job page he presses the button and gets `…/IR/000002`, filled in, to complete and submit |

### Vouchers

| # | What you will see | Example |
| --- | --- | --- |
| Change 2 | On **Admin → Approvals → Expense vouchers**, besides N+1 … N+4 and Office / Central Finance, you can add levels **by role**: any role, your custom roles included (not Admin or Finance). Each level has its own **"needed above ₹"**. You set the **order** with ↑ ↓. The "Reports to" levels must go upwards, and **Finance always stays last** | With N+1 (always), N+2 (above ₹5,000), Business Director (above ₹20,000) and Office Finance: ₹3,000 → N+1 → Finance; ₹25,000 → N+1 → N+2 → Director → Finance |
| Which holder signs | Any active holder of that role whose offices cover the voucher's office. The first to approve signs that level | — |
| No double signing (point 1) | If the person who must sign a level **already signed an earlier level** of the same voucher, that level counts as signed and they are not asked again. The History says "already signed at N+2". This never applies to Finance | Sunil is the claimant's N+2 **and** a Business Director: he signs once |
| Nobody holds the role | The voucher waits for an administrator. A clear warning shows on the voucher, in the approvals list and on the setup screen | — |
| A (your words) and Q3 | **Everyone** creates, edits and submits **their own** vouchers. **Each person sees only:** their own; those waiting for them now; those they have approved or sent back. **Nobody sees a whole branch** because of their role, not even a Branch Manager. Master Admin and Admin see all. Finance sees a voucher once it reaches Finance, in the offices it covers | Asst. Manager Ravi sees his own vouchers and his two reportees'. He does not see other inspectors' vouchers in the same branch |
| Point 3 | **Reopen:** an **Approved** voucher can be reopened by Finance (its offices), Master Admin or Admin; a **Paid** one by Master Admin or Admin only. The Business Director and Business Unit Head never reopen. Nobody reopens their own voucher | — |
| Desk logins | "My vouchers" can be found with **Find a screen** | — |
| Opening a month for someone else | Master Admin and Admin only (everyone else opens their own) | — |
| Operations home | The **"Vouchers — Pending"** tile counts only **your own** vouchers and those **waiting for you**. Before, it counted every voucher in the company for anyone who could open that page. Master Admin and Admin still see the full count | Kiran (Branch Manager, in no chain) sees his own count, not the company's |

### Candidates (your answer C; Q2: lock at the first interview)

| # | What you will see | Example |
| --- | --- | --- |
| Before any process | Every office sees the **full** candidate, and can take them on by linking them to its own requisition. That step is written in History | Mumbai links Asha to its requisition |
| From the first interview | Once an office **schedules the first interview**, every other office sees only the name and **"Locked — in recruitment with Ahmedabad"** (or "Locked — onboarding with …"). They **cannot open** the candidate or act on them in any way, even by typing an address. This replaces the "reduced view" idea (point 7). Company-wide roles are not blocked | Pune sees "Asha — Locked — in recruitment with Ahmedabad" and nothing else |
| The lock follows the person | A second record with the **same mobile or e-mail** is locked too | — |
| Point 8 | Creating a new record for someone locked elsewhere is **blocked**, naming the office. A look-alike who is not locked keeps the existing soft warning | "This person is already in recruitment with Ahmedabad" |
| When the process ends | When it ends (rejected, withdrawn, did not join), the lock is lifted. The candidate **appears again in every office's "Best matches"** (point 9). Only people already hired are left out | — |

### Client and vendor portal, Marketplace

| # | What you will see |
| --- | --- |
| Point 10 | The **vendor** portal's "New link" is **e-mailed** to the vendor person; staff never see the link. The vendor people list covers your own office's vendors (company-wide roles: all) |
| Point 11 | "What clients have asked for" and the Directory "new requests" count cover **your own office's clients** only |
| Point 12 | A client person limited to one site sees only that site in the portal assistant's report list, the "reports to accept or reject" count, and the deputation and report tiles |
| Point 13 | "Put forward one of your own team" on a Marketplace posting offers **only your own office's people** (company-wide roles: all). Professionals and their discovery are unchanged |
| D / point 14 | **No passports for the internal team.** The "Team passports" tile is gone, internal staff cannot create a share link, and an old internal link shows "This passport is not available." **A Marketplace professional's own passport is unchanged.** No data was deleted |

### Kept as it was

- **Point 15:** a Branch or Operation Manager may still assign a nonconformity's owner and open a corrective action. Containment, disposition and closing stay with the Quality / Technical Head.
- **Q1:** the contract number inside the report number is **recorded for later** ("good to have"). The series stays as it is.

## How it was tested

| Check | Result |
| --- | --- |
| **New automatic tests for this batch** (11 files) | **347 checks, all pass.** On the old code most of them fail. Examples: 47 (candidate lock), 43 (portal and Marketplace), and most of the voucher and report checks |
| **Full suite, first run** | Found **3 problems** that each part's own tests could not see. While checking the report, I found a **fourth** (the voucher tile). **All fixed** (see below) |
| **Full suite, second run after the fixes** | **SQLite: 21,130 passed, 1 failed.** The one failure is the known duplicate-check test that is unreliable when the machine is busy (X14); it fails the same way on older code. **MariaDB: 21,120 passed** before the same known test-harness stop as every earlier MariaDB run. Its 24 failures are exactly the known MariaDB test-order ones, line for line, and all 5 new failures from the first run are gone. **No new failure on either database.** The voucher-tile fix came after these runs started, so I ran its 350 related checks on SQLite and 244 on MariaDB separately: all pass |
| **Robot, 59 real-screen tests, before vs after the batch** on identical copies | **Before: 29 passed, 9 failed. After: 27 passed, 11 failed.** The other 21 are "needs you", out of date or incomplete, and the 9 failures before are the same 9 after (known defects in other packs, or tests needing data their tester prepared). **Only 3 tests changed, and all 3 are your new rules, not faults:**<br>• **REP-007:** an inspector searching for another inspector's report no longer finds it (point 5, "own reports only, for reading too");<br>• **OPS-083:** a Coordinator who is not in a voucher's chain can no longer open it (Q3, "strictly own + chain"). The test expected a maker ≠ checker note on that page;<br>• **SCR-vouchers:** two steps are now out of date: the page heading reads "Expense vouchers", and the "Open a voucher for" box is shown only to Master Admin / Admin (A).<br>**No other screen changed.** I will reword these test steps (and the six passport steps) in the robot test book |

**What the full run caught (all fixed before this zip):**
1. **A real program fault, found by chance.** The "Find a screen" list was worked out once and then reused for the next person served by the same server process. That person could be shown the first person's list of screens. Pages still refused anything that person may not open, so nothing leaked, but the list could be wrong. **The list is now worked out for each person.**
2. **The recruitment test left history rows behind.** Two older tests then reported "a history row points at a link that no longer exists". The test now cleans up after itself.
3. **The Marketplace test did not restore the signed-in person.** It now does.
4. **The Operations home voucher tile counted the whole company's vouchers** for everyone, against your rule "own + chain" (Q3). It now counts each person's own and those waiting for them. A test proves it.

**Known test weaknesses, not this batch** (they fail the same way on the code from before):
- one time-of-day test ("due later today") fails close to Indian midnight;
- one duplicate-check test is slow under load;
- two licence tests behave the same before and after.

## The update zip — for the TEST COPY (opstest) only

`EXAACT-V51.1-Batch2-TEST-COPY-update.zip`
- **Contents:** 818 entries (803 files), at the top of the zip. Compared with the branch & access zip, **35 files differ**: 33 changed program files, the deploy-check page and `VERSION.txt`. No file was added or removed.
- **Release stamp:** 74a263f. Deploy-check on the build: **723 of 723 files match.**
- **SHA-256:** `8350a42a4edbd4a0a05985aa762c2cf3c62bdfd1402bd0c4bba9b4a8b3526542`
- **Left out:** no `tests/` folder, `config.local.php`, `tenants.php`, database file, or `exaact_data` / `exaact_backups` folder.
- **Includes** everything from the earlier zips: the security fix, India time and the branch & access batch. **Install the branch & access zip first?** Not needed: this zip has all of it.
- **Database:** only **additions**, made automatically on first page load. Nothing is deleted.
  - Reports: a new "continues from" link (`report_docs.prev_stage_doc_id`).
  - Interviews: the requisition each interview belongs to (`interviews.requisition_id`). Existing interviews are filled in once from their candidate's requisition.
  - The voucher approval setting is saved in a new shape the first time you press Save. Until then, your earlier setting still works.

**To install on opstest:**
1. **Back up opstest:** mPanel → Backups, then Admin → Backup & restore → Take a backup now.
2. File Manager → the opstest **app** folder → upload the zip → Extract → overwrite → delete the zip.
3. Open `https://opstest.mghaiapps.com/deploy-check.php`. You should see:
   - Release **74a263f**;
   - the green ✓;
   - the India time line.

   **Send me that screenshot.**

## What to check on opstest (about 30 minutes)

Use your test logins from the test book.

1. **Report rejected (Change 1):**
   - Set up two approval levels for one report type.
   - As the inspector, submit a report.
   - As level 1, **send it back** with a reason.
   - Sign in as level 2: the report is **not** in your pending list.
   - Open the report's History: the send-back line is there.
2. **Start next stage report (B):**
   - Open an approved or issued report.
   - As its Coordinator, press **⏭ Start next stage report** and pick an inspector.
   - The new draft has the **next number**. Client, PO and form are filled in; result, date, photos and signature are empty.
   - The earlier report is unchanged.
3. **Inspectors read only their own reports (point 5):** as Inspector 1, search for Inspector 2's report. It is not found, and its address is refused.
4. **Vouchers (Change 2 and A):**
   - As **Master Admin**: Admin → Approvals → Expense vouchers. Add a **Business Director** level with an amount you choose, keep Finance last, and save.
   - As an inspector, submit one voucher **below** that amount and one **above** it.
   - Check that only the one above reaches the Director.
   - As a Coordinator who is **not** in that inspector's line, open Vouchers: you see only **your own**.
5. **Candidate lock (C):**
   - In Ahmedabad, schedule a first interview for a pool candidate.
   - Sign in as **another office**: the candidate shows "Locked — in recruitment with Ahmedabad" and does not open.
   - Try to add a new candidate with the **same mobile**: it is refused, naming Ahmedabad.
6. **Passports (D):** as staff, the "Team passports" tile is gone. As a **Marketplace professional**, your own passport still opens and shares.
7. **Vendor portal (point 10):** press "New link" for a vendor person. The screen says it was e-mailed; no link is shown.
8. **Your quotation approvals** work as before: submit one quotation and approve it.

Tell me anything that looks wrong; a screenshot helps.

## Rollback

1. **Safest:** restore the backup taken before uploading.
2. Or upload the previous zip, `EXAACT-V51.1-BranchBatch-TEST-COPY-update.zip`, over it. Then:
   - the two added columns stay but are unused;
   - next-stage reports already made stay as ordinary drafts;
   - if you saved the voucher setup with role levels, the older program cannot read it and **falls back to N+1 → Finance** until you save the setup again there.

## Points for you to decide later (none blocks this batch)

These came up while building. Each has my recommendation; nothing below was changed without your OK.

**Candidates**
1. When an office takes on a candidate from another office's requisition **before** the lock, the candidate moves to the new requisition. **The first office is not notified.** Recommendation: notify that office's recruiter. That is a new notification, so it needs your OK.
2. Should the **careers page** refuse an application from someone locked with another office? Today it accepts it, and the lock then applies. Recommendation: **keep accepting**; refusing could tell an outsider that the person is in a process.

**Vouchers**

3. Finance is never counted as "already signed", even if the Finance person signed an earlier level. Recommendation: **keep it so**. Finance pays and should check last.
4. Only Master Admin and Admin can open a voucher month **for someone else**. Recommendation: **keep it so**, as you wrote "every individual … their own".

**Reports**

5. The existing **"Continue"** from an earlier inspection copies the earlier report, even if another inspector wrote it. Recommendation: **keep it**; it is the same work order.
6. **"Start next stage report"** cannot be pressed by a Coordinator's **delegate**. Recommendation: **allow the delegate** (next batch, your OK).
7. **"One report per P.O."**: a next-stage report is allowed even when that lock is on, because it continues the same report. Recommendation: **keep it so**.
8. **Year rollover:** a next-stage report made after 1 April takes the **new financial year's** number, from the same numbering engine as any new report. Recommendation: **keep it**. This is how the series already works.
9. The **title and remarks** are copied into the next-stage report. Recommendation: **keep them** (the inspector can edit them).

**Portal and Marketplace**

10. The client and vendor **"Invite somebody"** is not limited to your own office's clients. Recommendation: **limit it**, like "New link".
11. Portal request e-mails still go to **all** Coordinators. Recommendation: **only the client's own office**.
12. **Robot test book:** six passport steps describe the internal "Team passports" screen, which no longer exists. I will reword them.

Your "B ok" lists and the money answer sheet come next.
