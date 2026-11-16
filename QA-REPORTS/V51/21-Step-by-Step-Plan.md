# Step-by-step plan from here (16 Nov 2026)

You sent the deploy-check screenshots of both sites. This plan says what they show, and then exactly what to do, in order, so nothing is missed. **Do the steps in order.** Do not jump ahead to the live site.

## What your screenshots show

| | Test copy (opstest) | Live site (operations) |
| --- | --- | --- |
| Code it runs | **v51** (release 52eca39, made 14 Nov 18:08) | **20 Oct** (release ce236a0) |
| How far behind the newest code | Missing **V51.1, fix batch 1, Sales batch B and the security fix** | About **4 weeks behind**: more than 120 changes that opstest already has, plus everything opstest is missing |
| Files uploaded correctly? | ✓ All 719 match | 689 of 690 match. The one "old" file is `config.local.sample.php`, a blank example form, but yours holds a copy of your database password |
| Where the database password is kept | In `config.local.php` ✓ (safe: updates never touch it) | **In `config.php`** ✗. Every update zip replaces that file, so **any update would take the live site down** until the password is typed in again |
| Messages security hole (Marketplace A1) | Present (fixed by the zip I sent) | Present (I checked the 20 Oct code) |
| Time zone | Not shown yet; the line arrives with the new zip | Not shown yet; the line arrives with the next live update |

**About the 4th screenshot:** it shows the password kept in `config.local.php` and taken at 07:15, the same minute as the opstest screenshot. So I have read it as **opstest**, not live. Your live screenshots say the password is only in `config.php`. If the 4th one was really from the live site, tell me, because it changes Step 4.

---

## Step 1 — Install the security-fix zip on the TEST COPY (about 15 minutes)

File: `EXAACT-V51.1-SecurityA1-TEST-COPY-update.zip` (sent earlier today).

1. **Back up opstest first.**
   - mPanel → **Backups** → full backup.
   - Then sign in to opstest → Admin → **Backup & restore** → **Take a backup now**.
2. mPanel → **File Manager** → open the opstest **app** folder: `opstest.mghaiapps.com/exaact_test/app`, the folder that holds `index.php` and `config.local.php`.
3. **Upload** the zip there.
4. Right-click → **Extract** → choose **overwrite**.
5. **Delete the zip** from the folder afterwards.
6. **Leave alone:** `config.local.php`, `tenants.php`, and the `exaact_data_test` and `exaact_backups` folders. The zip contains none of them, so nothing should ask to replace them. If anything does, stop and tell me.

**What changes on opstest:** it jumps from v51 to v51.1 + fix batch 1 + Sales batch B + the security fix, all in one go. The Sales changes you will notice are listed in `11-Sales-Fix-Batch-B.md`. Examples: money in lakh/crore (₹5,00,000), paise on quotations, clearer error messages, and a quotation not duplicated by Back.

## Step 2 — Check opstest and send me one screenshot (10 minutes)

1. Open `https://opstest.mghaiapps.com/deploy-check.php`. You should see:
   - **Release 815edb8**;
   - the green ✓ with **719** files;
   - a new **Time zone** line.

   **Send me a screenshot of the top of the page.** The Time zone line answers the time-zone question (Reporting C18, Quality C14).
2. If it says a file is "old" or "missing", upload the zip again (overwrite). If it still says so, mPanel → **Developer Tools → Restart PHP container**, and reload.
3. Open the opstest dashboard. The **red TEST COPY bar** must be there.
4. A few quick checks. Tell me anything that looks wrong:
   - As Coordinator, open **Marketplace → Messages**. It opens and works as before.
   - Open any quotation. Amounts show as ₹5,00,000 style.
   - Make one test enquiry and a quotation from it. "Create quotation" asks whether to revise or make a new one (v51.1).

## Step 3 — Answer the decision packs (when you can; in this order)

You have 10 packs. Answering in this order lets me fix the most important things first.

1. **"Branch and access" items: the most serious, all one root cause** (who may see or change a record):
   - Admin **A1**;
   - Operations **A1–A5**;
   - Reporting **A1–A3**;
   - Directory **A1**;
   - Quality **A1, A2**;
   - Marketplace **A4**;
   - Client portal **A1, A2, A4**.
2. **The "B ok" lists:** small fixes with no rule change. You can say "B ok" for all packs at once.
3. **Money items:**
   - Money pack A1–A6 and C14 (your billing rules);
   - Directory A2, A3, A5, A6;
   - Marketplace A6, A7, C5–C7;
   - Client portal A3;
   - Operations A11–A13.
4. **The other A items and the C questions:** anything left in sections A and C of each pack.

**How to answer:** you can write it all in one message, for example:
> *Admin A1 yes. Ops A1–A5 yes, A6 reason. Rep A1–A3 yes. Dir A1 yes. Qua A1 yes, A2 yes. Mkt A4 agree. Portal A1 yes, A2 yes, A4 agree. B ok for all packs.*

Anything you don't mention stays untouched. For each batch you approve, I fix it, run all the tests and send a zip for the **test copy**. You check it there, the same way as Steps 1–2.

## Step 4 — Make the live site safe for updates (one time; before any live update)

**Why:** today the live database password sits in `config.php`. The update zip always contains a clean `config.php`, so uploading it would remove the password and take the live site down. The fix is to move the settings into `config.local.php`, a file no update ever touches. Opstest already works this way.

**Do this only after a backup, at a quiet time. It takes 10 minutes.**

1. **Back up live.**
   - mPanel → **Backups** → full backup.
   - Then on operations.mghaiapps.com: Admin → **Backup & restore** → **Take a backup now**.
2. mPanel → **File Manager** → the live **app** folder (mPanel → Domains → operations.mghaiapps.com → its Document Root).
3. **Open `config.local.sample.php`** with the editor. **Do not rename it yet.**
4. Check these things in the file:
   - **(a) Database name and user** are the same as in "In use right now" on the live deploy-check page.
   - **(b) Database password** is your real one. If (a) and (b) are not right, correct them and save.
   - **(b2) Database host:** look at the `$DB = [` block near the top of the live `config.php`. If its `'host'` is anything other than `localhost`, add the same `'host' => '…'` line to the `'db'` block of this file.
   - **(c) The `'admin'` block: very important.** Open the live `config.php` too. Near the top, it has a line starting `$ADMIN = ['user' => …, 'pass' => …]`.
     - Copy that **user and password exactly** into the `'admin'` block of this file, character for character, **even if you sign in with a different password today**.
     - The app re-applies the admin password only when this value **changes**. Copying it unchanged means nothing happens to your admin login now or after the update.
     - If the block still says `admin12345`, or anything different from `config.php`, your live admin password would be changed.
     - If you are unsure, stop and ask me.
   - **(d) The line `'test_copy' => true`** must stay commented out (starting with `//`) or be absent. If it were active, the live site would turn into a test copy.
5. **Rename** `config.local.sample.php` to **`config.local.php`**.
6. **Reload the live site.** Sign in as admin exactly as you do today. It must work, with **no red TEST COPY bar**.
7. Open `https://operations.mghaiapps.com/deploy-check.php` and send me a screenshot of "Where your database settings live". It should now say `config.local.php`: **has real details: yes, read by the app: yes**.
8. **Do not upload anything else by hand.** The deploy-check page also suggests uploading a clean `config.php`. Leave that: the live update zip does it in Step 6.

**If anything goes wrong at step 6:** rename the file back to `config.local.sample.php`. The site returns to how it was, because `config.php` still holds the password. If that does not work, restore the backup from step 1.

## Step 5 — Tell me: is the Marketplace switched on on the live site?

Sign in to the live site as admin. Is there a **Marketplace** entry in the left menu?

- **No:** the Messages hole cannot be reached on live. The security fix can go live together with the next batch.
- **Yes:** please tell me. I would then recommend the security fix goes live soon after Steps 1–2 and 4. I would also ask your permission to look on the live site, read-only, for planted messages. I will not open the live site without that permission.

## Step 6 — The live update (only when you say testing is complete)

When you tell me testing on opstest is complete, I will ask: **"Shall I prepare the live update?"** Only after your yes do I build the live zip and give you the go-live checklist from `docs/go-live-guide.md`. In short:
1. Back up live (both ways).
2. Upload and extract the zip in the **live app folder only**.
3. Leave `config.local.php`, `tenants.php`, `exaact_data` and `exaact_backups` alone.
4. Open `/deploy-check.php`. Check:
   - all files match;
   - **no** red TEST COPY bar;
   - the Time zone line.
5. Platform setup → send a test e-mail.
6. Razorpay: **live** keys → Check payment keys → one small real payment, then refund it.
7. If anything is wrong, restore the backup.

**Keep in mind:** live is 4 weeks behind. The first live update brings **everything** you have tested on opstest since 20 Oct, in one step. That is normal, but it is a big step. That is why the backup and Step 4 come first, and why opstest should be fully checked before it.

**Never copy files from the opstest folder to the live folder.** Always use the zip.

---

## What I do meanwhile

- **Two testers still running:** Recruitment, and New company + Across modules. Each gets its own decision pack.
- **Then:**
  - the bigger security review (about 5,000 places where the app shows stored text);
  - reruns of tests whose wording is out of date (after your "B ok");
  - the database-integrity and phone checks.
- **On every batch you approve:**
  - fix it;
  - run the full tests on both database types;
  - rerun the robot tests;
  - send a zip for the test copy, with the changed-files list and the rollback steps.

## The small things that are easy to miss — checklist

- [ ] Back up **before** every upload, on both sites.
- [ ] Upload the zip into the **app** folder (the one with `index.php`), not one level above.
- [ ] Extract with **overwrite**; never delete files first.
- [ ] Delete the zip after extracting.
- [ ] Never touch `config.local.php`, `tenants.php`, `exaact_data*` or `exaact_backups*`.
- [ ] After every upload: `/deploy-check.php` shows the green ✓ and the release number I gave you.
- [ ] Opstest **has** the red TEST COPY bar; live **never** has it.
- [ ] Live: Step 4 (password into `config.local.php`, with the admin line copied **exactly** from `config.php`) before the first live update.
- [ ] Never copy files from opstest to live.
- [ ] Send me the deploy-check screenshot after each upload.
