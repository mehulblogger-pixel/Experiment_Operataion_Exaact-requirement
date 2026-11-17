# What to do next — a simple step-by-step guide (17 Nov 2026)

You have received many files. **This guide puts everything in order.** Do the parts in this order, one at a time. You do not need to understand programming or testing; just follow the steps.

**The big picture in one line:** we put the newest version on the **test copy (opstest)**, you try it there, you tell me what is wrong, I fix it, and only when you are happy do we update the **live site**.

| Part | What | Time | When |
| --- | --- | --- | --- |
| **A** | Put the new version on opstest (upload one zip) | 20 minutes | Today, on a computer |
| **B** | Send me one screenshot to confirm it worked | 2 minutes | Right after A |
| **C** | Quick check of the new changes (8 small checks) | 30 minutes | After B |
| **D** | The edge-case tests, a few each day | 20–30 minutes a day | From tomorrow |
| **E** | Answer my two question sheets | 10 minutes each | Any time |

**The live site (operations.mghaiapps.com) is not touched in any of these parts.**

---

## Part A — Put the new version on opstest (about 20 minutes)

**Use a computer (laptop or desktop), not your phone.** Uploading files is much easier on a computer.

### A1. Download the zip file
1. In this chat, find the file **`EXAACT-V51.1-Batch2-TEST-COPY-update.zip`** (I have attached it again with this guide).
2. Click it and **download** it. It goes into your computer's **Downloads** folder.
3. **Do not open or unzip it on your computer.** We upload it as it is.

*This one zip contains everything: the security fix, India time, the branch & access changes and batch 2. You do not need any older zip.*

### A2. Take a backup of opstest first (safety net)
If anything goes wrong, a backup lets us go back to how it was.
1. Log in to **mPanel** (your hosting control panel).
2. Open **Backups** and start a **full backup**. Wait until it says it is finished.
3. Then open **https://opstest.mghaiapps.com**, sign in as your Master Admin.
4. Go to **Admin → Backup & restore** and press **Take a backup now**.

### A3. Upload the zip
1. In mPanel, open **File Manager**.
2. Go into this folder: **`opstest.mghaiapps.com` → `exaact_test` → `app`**.
   - You are in the right folder when you can see the files **`index.php`** and **`config.local.php`** in it.
3. Press **Upload**, choose the zip from your **Downloads** folder, and wait until the upload reaches 100%.

### A4. Unzip it in that folder
1. In File Manager, **right-click** the zip file → **Extract**.
2. If it asks whether to **overwrite** existing files, choose **Yes / Overwrite all**.
3. When it finishes, **delete the zip file** from that folder (right-click → Delete). Only the zip, nothing else.

### A5. Do NOT touch these
Leave these exactly as they are. Do not delete, rename or replace them:
- `config.local.php`
- `tenants.php`
- the folders `exaact_data_test` and `exaact_backups`

*The zip does not contain any of them, so nothing should ask to replace them. If something does, stop and send me a screenshot.*

---

## Part B — Send me one screenshot (2 minutes)

1. Open **https://opstest.mghaiapps.com/deploy-check.php**.
2. At the top you should see:
   - **Release 74a263f**;
   - a **green ✓** ("all files match");
   - a line saying the app uses **India time**.
3. **Take a screenshot of the top of that page and send it to me here.**

**If you see a red mark or "old" / "missing" files:** upload and extract the zip again (A3–A4). If it is still red, in mPanel go to **Developer Tools → Restart PHP container**, wait one minute, and reload the page. Then send me the screenshot either way.

---

## Part C — Quick check of the new changes (about 30 minutes)

These 8 checks make sure the changes of this update work. They are also in report 30. Sign in with the test logins (Test Coord, Test Inspector and so on) as you normally do on opstest.

| # | Sign in as | Do this | You should see |
| --- | --- | --- | --- |
| 1 | Test Inspector, then the approvers | Needs a report type with **two** approval levels (if yours has only one, skip this check and tell me). Submit a report. As the **first** approver, press **Send back** with a reason. Then sign in as the **second** approver | The report is **not** in the second approver's pending list |
| 2 | Test Coord | Open an approved report → **⏭ Start next stage report**, pick an inspector | A new draft with the **next number**; client and PO filled in; result, date and photos empty |
| 3 | Test Inspector | Search for **Test Inspector2's** report | It is not found |
| 4 | Master Admin | Admin → Approvals → **Expense vouchers** → add a **Business Director** level with an amount, e.g. ₹20,000 → Save | Saved; Finance stays last |
| 5 | Test Inspector | Submit one voucher of **₹5,000** and one of **₹25,000** | Only the ₹25,000 one reaches the Director |
| 6 | Test Coord (Ahmedabad) | Schedule a **first interview** for a pool candidate. Then sign in as **Test Coord2 (Mumbai)** | Mumbai sees "**Locked — in recruitment with Ahmedabad**" and cannot open the candidate |
| 7 | Any staff login | Look for the **"Team passports"** tile | It is gone |
| 8 | Test BDM | Submit one quotation; approve it as Test BranchMgr | Works as before |

**If something does not match:** take a screenshot and send it to me with the check number, e.g. "Check 5: the ₹5,000 voucher also went to the Director". That is all you need to do; I find the cause and fix it.

---

## Part D — The edge-case tests (20–30 minutes a day)

### What is an "edge-case test"?
It is a small exercise where you try something **unusual** to see if the program behaves correctly. For example: typing a price of minus ₹1,000, pressing Save twice, or using a date like 30 February. A good program refuses these with a clear message.

### Where are they?
Open the **[EXAACT Edge-Case Book](https://claude.ai/artifact/AMNvwuesa6xgqpEAFkFbCk)** (it works on your phone). It has 991 tests. **You do not need to do all of them.** Even 10 a day helps a lot.

### How to do ONE test (example)
1. In the book, type **EC-SALA-056** in the search box and open the test. It says:
   - **Sign in as:** Test BDM
   - **Steps:** make a costing, add a role "Site QA/QC Inspector", 1 person, per man-month, margin 20%, 12 man-months, salary ₹50,000 per month, press **Add role**.
   - **You should see:** quoted rate **₹71,531.25**, revenue **₹8,58,375.00**, cost **₹6,54,000.00**, profit **₹2,04,375.00**, margin **23.81%**.
2. On opstest, sign in as **Test BDM** and do exactly those steps.
3. Compare the numbers on the screen with the book:
   - **Same?** Tap **Passed**.
   - **Different?** Tap **Failed** and write what you saw in the note box, e.g. "Revenue showed ₹8,58,000". Press **Save note**.
   - **Could not do it** (a button missing, an error)? Tap **Can't test** and write why.
4. That's it. I can read your results and notes, so you don't need to send them separately.

### Tips so you don't get confused
- **Start with the tests that make their own new records.** Their "Before you start" says something like "Make a costing…" or names records starting with **EC-**. Their numbers will match exactly.
- **Some tests say "Open deals: OPP-0006 ₹7,50,000…" or "as left by the cases above".** They were written on my private copy, whose records differ from opstest. On opstest the record numbers and totals will differ, so **skip these for now** or treat the numbers as an example.
- **Use the "Sign in as" filter** in the book to see only the tests for the login you are using, so you don't keep signing in and out.
- **Do one area at a time**, e.g. Sales today, Money tomorrow.
- **A test that fails is good news:** it means we found a problem before your customers did.
- Many faults are **already known** (the book's "Faults found" tab, and answer sheet 3). You do not need to report those again; just tap Failed.

---

## Part E — Answer my two question sheets (any time)

These are decisions only you can make. Each question has my recommendation, so the quickest answer is "as recommended".

1. **Answer sheet 2 — Money** (file `31-Money-Answer-Sheet.pdf`): 18 money fixes and 4 billing rules.
   - Quickest answer: *"Money sheet: M1–M18 as proposed."* Then, for the 4 rules R1–R4, write your rule in a few words, or say "ask my accountant first".
2. **Answer sheet 3 — Edge cases** (file `32-Edge-Case-Findings-Decision-Pack.pdf`): 281 faults grouped into 14 causes, and 23 questions.
   - Quickest answer: *"Edge pack: themes 1–14 as proposed; Q1–Q23 as recommended."*

**What happens after you answer:** before I change anything, I explain each change with an example (as you asked). After your OK, I build it, test it and send a new opstest zip. You then repeat Parts A–C with that zip.

---

## What I do while you test
- I read your test results from the book and look into every **Failed** one.
- I prepare the next batch of fixes as soon as you answer the sheets.

## The live site
Nothing goes to the live site until **you** tell me testing on opstest is complete. I will then ask: **"Shall I prepare the live update?"** Only after your yes do I prepare it, with a separate checklist.

## Your checklist
- [ ] A — zip uploaded and extracted on opstest
- [ ] B — deploy-check screenshot sent to me
- [ ] C — 8 quick checks done (send screenshots of anything wrong)
- [ ] D — edge-case tests, a few each day
- [ ] E — answer sheet 2 (Money) and answer sheet 3 (Edge cases)
