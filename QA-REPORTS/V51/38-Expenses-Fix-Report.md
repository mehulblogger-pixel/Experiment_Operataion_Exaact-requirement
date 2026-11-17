# Expenses — fix report and opstest update (18 Nov 2026)

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
| Check | Result |
| --- | --- |
| New automatic tests for the expense rules (2 files) | **135 checks, all pass**: periods, bands and person amounts with dates, day close, bill photo, no-bill note and limit, duplicates (amount and photo), distance, deadlines and reopen by N+1, approvers cannot edit, Finance advance, ₹0 totals, Save not wiping amounts, old vouchers unchanged, counted once |
| Full suite, SQLite | **21,266 passed.** 3 failed in a database-lock **timing** test that needs a quiet machine; it passes on every rerun (18 of 18) and has nothing to do with expenses |
| Full suite, MariaDB (your server's database type) | **21,258 passed.** The 24 failures are exactly the known MariaDB test-order list, line for line, the same as before this change. **No new failure** |
| Robot: 83 real-screen tests that touch vouchers, job closing, profit, money and the whole-journey flows, before vs after | **Only the voucher screens changed, as intended.** Every job-closing, profit, invoice and journey screen behaves exactly as before. One old test (OPS-085) now finds the "Add an expense" box moved to "＋ Add a bill" on the day page; its checks (amount 0 or minus refused, photo over 6 MB refused) are still there. The earlier fault on the voucher screen is gone |
| I also opened the new screens myself | Close the day (phone), My expenses, and the Expense rules page |

## The zip — for opstest only
`EXAACT-V51.1-Expenses-TEST-COPY-update.zip`
- **Contents:** 808 files at the top of the zip. Compared with the batch 2 zip, **19 differ**: 5 new screens/files (`lib/expenses.php`, `voucher_day.php`, `voucher_rules.php`, `voucher_grid.php`, `_exp_day_fields.php`) and 14 changed.
- **Release stamp:** 24028c5. Deploy-check on the build: **728 of 728 files match.**
- **SHA-256:** `929b6335b6cecffe49a4ea3ff93857612ca0dd65593de4ecb3e80446bb5e3287`
- **Left out, as always:** `config.local.php`, `tenants.php`, `tests/`, database files, `exaact_data*` / `exaact_backups*`.

**Install** (the same steps as last time, see guide 33):
1. Back up opstest.
2. File Manager → opstest app folder → upload → Extract (overwrite) → delete the zip.
3. Open `deploy-check.php`: Release **24028c5**, green ✓. Send me the screenshot.

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
