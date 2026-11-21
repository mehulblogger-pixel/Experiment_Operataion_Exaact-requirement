# Report 53 — Sales defects, batch 1

**Date:** 21 November 2026
**Your instruction:** "Let us fix all the defects you highlighted. Where there is my decision pending, ask me." On the same day: "Data shown shall be as per the permission set in our settings."
**What this batch fixes:** six items from the defect register (report 52) that needed no decision from you: DEF-08, DEF-09, DEF-02, DEF-04, DEF-19 and DEF-03.
**Rollback point:** update `62d55c3` (Stage 1 + DEF-01). Install the earlier zip to go back.

**What did not change**
- No new permission, no new status, no new table or column.
- No figure changes except the ones described below.
- Nobody can see or do anything they could not before. Two figures now show *less* to people limited to some branches.

---

## DEF-08 — collection figures now follow the person's branches (High)

**What was wrong.** Three money figures in Sales counted the whole company, whatever branches the person works for and whichever salesperson was picked:
- *Orders & payments* → **Received this month**
- *Orders & payments* → **Days to get paid**
- *Client register, Sales view* → **Owed to us**

**Example (from the test).** Meera is in Finance and works for Mumbai only. One customer was billed ₹1,000 from Mumbai and ₹2,000 from Ahmedabad, and both were paid this month. Mumbai's invoice took 20 days to be paid; Ahmedabad's took 40. The same customer still owes ₹500 to Mumbai and ₹700 to Ahmedabad.

| What Meera sees | Before | After |
|---|---|---|
| Received this month (this customer's part) | ₹3,000 | **₹1,000** |
| Days to get paid | 30 days (both branches) | **20 days** (Mumbai only) |
| Owed to us | ₹1,200 | **₹500** |

Rakesh (central Finance, all branches) still sees ₹3,000, both branches' days to get paid, and ₹1,200 — exactly as before.

**When one salesperson is picked** on Orders & payments, *Received this month* and *Days to get paid* now follow that person's orders: the same invoices the "Received" column already uses. A salesperson with no paid invoices shows "—" instead of the company's average.

**What changed.**
- Each figure uses the branch rule of the register it comes from: receipts follow the receipt register, invoices follow the invoice register.
- The small grey hint under each figure now says whose figure it is: "your branches", "all customers" or "these orders".
- Who may see these figures is unchanged (the receivables right).
- Customer 360 and the ledger keep the whole-customer figure they always showed. See "Found during this batch" below.
- Permission document updated (docs/02, item 19q).

**Note for later (DEF-07, your decision A):** "Received" will become receipts plus TDS in Stage 5. This batch kept today's meaning (money received) so that only the branch rule changed.

**Test evidence:** 13 checks in `tests/test_def_batch1.php`. On the old code 7 of them fail with exactly the numbers in the table; on the new code all pass.

**Check on opstest**
1. Sign in as a Finance user limited to one branch.
2. Open **Sales → Orders & payments**. *Received this month* shows only that branch's receipts, and the hint under it says "your branches".
3. Pick one salesperson. The hint says "these orders".
4. Open **Client register → Sales view**. *Owed to us* is the figure for that branch's invoices only.

---

## DEF-09 — Receivables: "Open →" opens the invoice, and "Due" shows its date (Medium)

**What was wrong.** In the receivables detail for one customer:
- **Open →** sent you to a *job* page, using the invoice's number as if it were the job's number. You landed on the wrong job, or on "not found".
- The **Due** column said "not set" on every invoice, even when the invoice had a due date.

**Example.** An invoice due on 1 Dec 2026:

| | Before | After |
|---|---|---|
| Due | "not set" | **the due date** (1 Dec 2026, in your date format) |
| Open → | `/job?id=…` (wrong page) | **the invoice itself** |

Older rows that still come from jobs (companies that have not moved to the invoice book) behave exactly as before.

**Test evidence:** 3 checks. They also confirm that a Mumbai-only person still does not see an Ahmedabad invoice.

**Check on opstest:** **Money → Receivables** → click a customer → the Due column shows dates → **Open →** opens the invoice.

---

## DEF-02 — Editing a quotation no longer wipes line sub-types and deliverables (High)

**What was wrong.** Each quotation line can carry a *sub-type* (for example "Stage-wise") and *deliverables* (for example "Inspection report, release note"). The edit form stopped showing these two, but saving rewrites every line from what the form sends. So **every save of an edited quotation blanked them**. That matters because deliverables are copied to the job when the work order is raised.

**Example.**

| Line | Before saving | After saving — old code | After saving — new code |
|---|---|---|---|
| Sub-type | Stage-wise | *(blank)* | **Stage-wise** |
| Deliverables | Inspection report, release note | *(blank)* | **Inspection report, release note** |

**What changed:** the edit form now carries both values unchanged and invisibly with each line. A new line added with **+ Add line** still starts with both empty.

**Test evidence:** 4 checks.

**Check on opstest:** open a quotation whose lines have deliverables (or check one in the Word/PDF output) → **Edit** → **Save** without changing anything → the deliverables are still on the quotation and its documents.

---

## DEF-04 — Rewording a checklist point no longer clears its ticks (High)

**What was wrong.** Each tick on a quotation's pre-order checklist was linked to the exact wording of its point. Rewording a point under *Sales → Pre-order checklist* broke that link on **every** quotation, old ones included. A quotation reviewed in full suddenly showed "1 of 2 done", and could even be held back from approval when "every point required" is on.

**Example.**
- **Before the change:** quotation Q-1 has both points ticked by Kiran on 10 Nov: "Scope reviewed" and "Client not on hold". Then point 1 is reworded to "Scope, standards and deliverables reviewed".
- **Old code:** Q-1 shows 1 of 2, and Kiran's tick on point 1 is gone from view.
- **New code:** Q-1 still shows 2 of 2, against the wording it was ticked on, with Kiran's name and time.
- **A new quotation** (checklist not started yet) gets the new wording.

**What changed.**
- The rule now is: **a quotation keeps the points it was ticked against; a changed list applies to quotations whose checklist has not been started.** The settings screen says this in one sentence under the box.
- The list each quotation was ticked against is stored inside the checklist it already has. No new table or column.
- When the list is reworded, quotations already ticked keep the list they had until then. Their ticks, and who ticked them when, are not touched.

**Please note.** Adding a new point later also applies only to quotations not yet started. If you would ever want an added point to reach quotations already in review, tell me and I will add a button for that.

**Test evidence:** 7 checks.

**Check on opstest**
1. Tick both points on one quotation.
2. Reword one point under **Sales → Pre-order checklist**.
3. Re-open the quotation: still all ticked.
4. A new quotation shows the new wording.

---

## DEF-19 — An old lead form can no longer wipe the manpower deputation (Medium)

**What was wrong.** A lead can carry a *manpower deputation*: man-day or man-month, how many people, skills, and site. The lead page sends these with every save. An older form or bookmark that posted the lead's details *without* them saved them as blank, wiping the deputation.

**Example.** A lead with "Man-month, 3 people, NDT Level II, Plant 4, Hazira" is saved from a form that only has the company name and value:
- **Old code:** the deputation is wiped.
- **New code:** the deputation stays as it was, and the company name and value are saved.

**What changed.**
- Fields a form does not send are left as they are.
- The lead page, which does send them, can still change or clear them.
- The man-month rules (a site and at least 1 person) apply exactly as before.

**Test evidence:** 5 checks.

**Check on opstest:** edit a lead with a man-month deputation from the lead page → Save → the deputation is still there.

---

## DEF-03 — Retention choice on the phone (High, made safe)

**What was reported.** In the milestone billing editor, the "Releases retention" choice was hidden on phones (under 640 px wide). A schedule with retention could not be saved from a phone.

**What I found.**
- I checked in a real browser at phone width (390 px) and laptop width (1366 px): on the current code the choice **is** visible on both. The phone-hiding rule only reaches the read-only milestone list, not the editor.
- But the editor's cell still carried the phone-hiding mark. One small widening of that rule would have hidden the only way to mark the retention milestone on a phone.

**What changed:** the phone-hiding mark is removed from the editor, so the choice cannot be hidden by accident.

**Test evidence:** 1 check, plus the browser check.

**Check on opstest:** on a phone, open a quotation → **Milestones** → each row shows the "Releases retention" circle.

---

## Found during this batch (not changed — added to the register)

**DEF-25 (Medium).** *Owed* on **Customer 360** and the customer screen is the whole customer's figure across every branch. The Sales view's "Owed to us" now shows only the person's branches and links to that page, so a branch-limited person sees a larger figure one click later.
- **Recommended:** apply the same branch rule there too, under your principle "data shown as per the permission set".
- That page is outside Sales, so it waits for your OK.

**Not changed, noted:** on Orders & payments, **Overdue** already follows the person's branches but not the salesperson picked. It was not part of DEF-08 and is left as it was.

---

## Tests

| What | Result |
|---|---|
| `tests/test_def_batch1.php`: new code | **33 / 33 pass** |
| The same test on the old code `62d55c3` | 16 fail — every defect above reproduced |

### Regression

| Check | Result |
|---|---|
| Related test files (117 files: Sales, quotations, leads, receivables, orders, books, invoices, branch, scope, clients and partners, Stage 1, DEF-01, this batch) | **3,345 checks, 0 failures** |
| Robot: the same 104 cases as the Stage 1 run, signed in as the real test users | **None worse, none better.** 46 passed, 23 failed, 24 incomplete, 10 need you, 1 out of date — exactly as before this batch (compared case by case). |
| Real browser, quotation milestones, 390 px and 1366 px | The "Releases retention" choice is visible at both widths |

## Your owner decisions recorded (21 Nov)

Recorded in the register (report 52), to be built in their stages:

| Decision | Your answer |
|---|---|
| How Stages 2–6 are approved | **B:** one picture pack, one OK, then build stage by stage with evidence after each |
| DEF-05 | **A:** files of the earlier revision shown, marked "from Rev 01", no copy |
| DEF-06 | **A:** before-GST as the main figure everywhere, incl. GST labelled beside it |
| DEF-07 | **A:** Received = receipts + TDS; credit notes separate; outstanding = invoice − received − credit notes. Confirm with Finance first. |
| DEF-10 | Active contracts only; one look-ahead window in Sales settings, starting at 60 days |
| DEF-11 | **A:** management plus sales managers set targets for people in their branches; blank = no target; full history |
| DEF-14 | Yes: read-only enquiry page (Stage 3) |
| DEF-15 | Yes: SBU on the forms; deal probability follows its stage |
| DEF-17 | Yes: /crm-dashboard renamed "Sales funnel report" |
| DEF-23 | **A:** the two dead addresses go to the quotation register |

## Files changed

| Area | Files |
|---|---|
| Logic | `lib/sales_more.php`, `lib/books.php` (optional branch rule on the customer figure; default unchanged), `lib/sales_simple.php`, `lib/crm.php`, `lib/ops.php`, `lib/leads.php` |
| Screens | `views/ops/sx_orders.php`, `views/ops/receivables.php`, `views/ops/crm/quote_form.php`, `views/ops/preorder_checklist.php`, `views/ops/_milestones.php` |
| Docs | `docs/02-permission-matrix.md` (19q), `QA-REPORTS/V51/52-Sales-Defect-Register.md` |
| Test | `tests/test_def_batch1.php` (new) |
