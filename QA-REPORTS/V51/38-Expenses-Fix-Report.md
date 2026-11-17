# Expenses — fix report and opstest update ({{DATE}})

**What this zip contains.** The new expense module you approved: design v3 and the "existing vs needed" table (document 36). Nothing else changed. The foundation fix (themes 1–2) is **not** in this zip; it waits for your OK on plan 37.

## What you will see

| Your rule | What the program now does |
| --- | --- |
| Paid for the work he does | The inspector confirms each day's expenses when he **closes the day** on his own allocated job ("Close job" on the job page). They go into his claim by themselves, with job number, client, site and date |
| Weekly, fortnightly or monthly (E1) | **Admin → Expense vouchers → Expense rules**: Claim period. Starts as **Monthly**, so nothing changes until you choose. A week never crosses a month end |
| Nothing hard-coded (E2) | On the same page you set:<br>• **Expense types**, 5 kinds: per km, bill with photo, no bill (amount + note), per day, fixed per month;<br>• **Bands**, with each type's amount, limit and "from date".<br>A person's own amount goes on their team-member record (Allowances & rates). No amount is built into the program; today's km rates are kept as starting values |
| Bills need a photo; some have none | A "bill" type cannot be **submitted** without its photo. A "no bill" type asks for a note and stays within its daily limit |
| Above a limit (E3) | Shown to the approver in **amber**, not refused |
| Duplicates | The same date + type + amount is refused, and so is the same photo used twice |
| 8 days (E4) | A day's expenses can be added for **8 days**, and a claim must be submitted within **8 days** after its period ends (both are settings). After that it is locked; the claimant presses "Ask to reopen" and only his N+1 can reopen it, with a reason. **Older drafts are never locked by these new rules** |
| Usual distance | Remembered once a claim is approved. A different distance needs a reason and shows in amber. The tolerance % is a setting (starts at 0 = any difference) |
| Approvers never edit (V2) | Only the claimant edits his own draft. Approvers, Finance and admins **approve or send back**; admins can still view all |
| Advance (E6) | **Finance** enters it when pressing "Mark paid" |
| Approval chain | Unchanged: the one you tested tonight |
| Counted once | A day-close expense is now written **once**, as a claim line, not also as a separate job expense |

**Faults from 17 Nov, fixed:**
- the ₹0 totals on a submitted claim;
- "Save all rows" wiping amounts;
- the empty "What for".

**Your old vouchers** (for example the ₹30,000 one) open with all their figures and history, exactly as before.

## How it was tested
{{TESTS}}

## The zip — for opstest only
{{ZIP}}

**Install** (the same steps as last time, see guide 33):
1. Back up opstest.
2. File Manager → opstest app folder → upload → Extract (overwrite) → delete the zip.
3. Open `deploy-check.php`: Release **{{REL}}**, green ✓. Send me the screenshot.

**Database:** only additions, made automatically:
- one new list (bands);
- new boxes on existing records: period dates, band, limit, from-date, photo check, reason;
- the claim-rule settings, starting at Monthly / 8 / 8 / 0%.

Nothing is deleted or rewritten.

## Your 10 checks (about 40 minutes)

Do these in the **Mehul Blogger** company, the one with your settings, where you corrected the roles. As Coordinator, first allocate one job for **today** to Test Inspector.

| # | Sign in as | Do | You should see |
| --- | --- | --- | --- |
| 1 | admin | Admin → Expense vouchers → **Expense rules**. Choose **Weekly**. Check the two "8" boxes. Save | "Saved". The claim rules show Weekly, 8, 8 |
| 2 | admin | Same page → "Add or change an expense type". Change **Auto / local conveyance** to **No bill**. Change **Food allowance** to "added by itself" (existing allowances start as "added when he ticks it", so nobody gets money automatically until you decide) | Auto shows "No bill · amount + note"; Food allowance shows "added by itself" |
| 3 | admin | Bands → add **"Field Inspector"**. Give it: own bike ₹ __/km, food allowance ₹ __/day, hotel limit ₹ __ (your figures). Then open **Test Inspector's** team-member record → Allowances & rates → band **Field Inspector** → Save | Saved. Test Inspector belongs to the band |
| 4 | Test Inspector (phone) | Open today's job → **Close job**. Type the km | Travel = km × your bike rate; food allowance added by itself; the total for the day |
| 5 | Test Inspector | Press **Close the day**, then "＋ Add a bill": a **hotel above your limit, without a photo** | Saved, but it says the photo is needed before you can submit |
| 6 | Test Inspector | Add an **Auto-rickshaw ₹80** with the note "station to site". Then add the same again | The first is saved; the second is **refused as a repeat** |
| 7 | Test Inspector | Open **My expenses** → this week's claim | One card for today with job number, client, travel, allowance, hotel and auto; the total on top; "It will go to: …" |
| 8 | Test Inspector | Add the hotel photo, then **Submit** | Submitted |
| 9 | Test SrInspector | Open the claim from Approvals | Read only. The hotel is shown **in amber** (above the limit). Only **Approve** and **Send back**. No edit |
| 10 | Test Finance | After the chain: **Mark paid**, typing an advance of ₹500 | Paid. The balance is less ₹500. History shows who and when |

**Also look once:** open your old ₹30,000 voucher. All its lines and totals are unchanged.

## Questions for you (each with my recommendation)

| # | Question | Recommended |
| --- | --- | --- |
| X1 | (Sheet 3, Q3) Do **draft or sent-back** claims count as cost in a job's profit? Today they do | **Count only from Submitted onwards**; drafts are not yet claimed |
| X2 | Who reopens a locked day when the person **has no "Reports to"** (or that person is inactive)? | **An administrator**, in that case only, as the approval chain already does |
| X3 | Old expenses entered at job close **before this update**: keep counting them in profit? | **Yes, keep them** until each old month is checked. Only new records follow the "once" rule |
| X4 | Who may change the **expense types** list? Today any Admin | **Master Admin only**, like bands and rules |
| X5 | The inspector closes **his own** visit day. This is your permission list's "Close own" on jobs | **Yes**: it is where he confirms the expenses |

**One-line answer:** *"Expenses installed; X1–X5 as recommended"*, plus anything that did not match in the 10 checks (a screenshot helps).

## Rollback
1. Restore the backup taken before uploading.
2. Or upload the previous zip (`EXAACT-V51.1-Batch2-TEST-COPY-update.zip`).

The added boxes and the band list then stay unused. Claims made with the new screens stay in the records, but show in the old month sheet.
