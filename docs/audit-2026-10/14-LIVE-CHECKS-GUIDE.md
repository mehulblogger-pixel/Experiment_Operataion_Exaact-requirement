# D14 — Going live, and the checks to run on the live system

**For:** the owner and the accounts person · **Written:** 19 Oct 2026
**Live site:** `https://operations.mghaiapps.com`

Everything in this guide is read-only on your data except Part 2, which is the
normal "upload the new files" job described in `phpapp/DEPLOY-CHECKLIST.md`.
Every menu name below was checked against the code on 19 Oct 2026.

---

## Part 1 — Why the version number cannot tell you anything

The footer and *Settings → Server check* both say **2026.10.1**. That number has
not changed all month, through dozens of releases, so it reads the same whether
the new files are on the server or not. The only reliable way to know is Part 3.

---

## Part 2 — Put the new version live (about 10 minutes)

All the fixes are on the branch **`claude/testing-branch-setup-0gqe8n`** of the
GitHub repository `mehulblogger-pixel/Experiment_Operataion_Exaact-requirement`.
None of them is on the live site yet.

1. **Back up first.** cPanel → **phpMyAdmin** → choose the EXAACT database →
   **Export** → **Go**. Keep the file. *(If the site uses the built-in database
   instead, cPanel → File Manager → download `data.sqlite`.)*
2. **Get the files.** On GitHub, open the repository, switch the branch
   selector to `claude/testing-branch-setup-0gqe8n`, then **Code ▾ → Download
   ZIP**. Inside the ZIP is the `phpapp` folder.
3. **Upload over the old files.** cPanel → **File Manager** → the folder that
   serves `operations.mghaiapps.com` → **Upload** the ZIP → select it →
   **Extract**, allowing it to overwrite. If it made a `phpapp` sub-folder, move
   its contents up one level.
4. **Never overwrite** `config.local.php` (your settings) or `data.sqlite`
   (your data, built-in database only).
5. **Open any page once.** The application upgrades its own database on that
   first visit. You will just see your normal screen.

If you would rather the changes were merged into the main branch first, ask
and a pull request will be opened for review.

---

## Part 3 — Confirm the upload landed (2 minutes)

1. Sign in to EXAACT as an administrator, as usual.
2. In the **same browser**, change the address to
   `https://operations.mghaiapps.com/deploy-check.php` and press Enter.
3. Read the top of the page:
   - **✓ "Every file on this server matches the release. All files are up to
     date. The upload landed correctly."** — done.
   - **⚠ "… file did not upload. The application is still running older code
     for these."** — the page lists the folders and files. Upload those again,
     overwriting, then reload the page.
4. To anyone who is not a signed-in administrator the page answers "Not Found",
   so it is safe to leave on the server.

---

## Part 4 — The three reconciliation screens (accounts person, 5 minutes)

**Who can open them:** Finance, anyone who may see salaries, or an
administrator. They only read; nothing on them changes your books unless you
press a button on a specific row.

| Screen | Where | What it finds |
|---|---|---|
| **Revenue reconciliation** | **Money → Billing → Revenue reconciliation** | Jobs whose invoice figure stamped on the job disagrees with the real invoice in the books |
| **Cost reconciliation** | **Money → Costs & margins → Cost reconciliation** | Jobs whose sub-contractor cost disagrees with the committed cost ledger |
| **Reimbursable duplication** | **Money → Costs & margins → Reimbursable duplication** | Jobs whose travel and similar costs are recorded twice, which would double-charge profit |

**Step by step, for each of the three:**

1. Sign in → **Money** (left menu) → find the tile named above → open it.
2. **All clear** reads **"Nothing to reconcile."** Note the date you checked.
3. **If there are rows,** do not change anything yet. Take a screenshot of
   the list (these screens have no download button) and send it.
   Each row is a job where two parts of the system tell a different story; it
   needs one decision per job, and that is a conversation, not a click.

**Why this matters:** the dashboard figures were reconciled on test data on
19 Oct. These three screens are how you prove the **live** figures are clean.
Until all three say "Nothing to reconcile", treat the profit figures on the
management dashboards as provisional.

---

## Part 5 — Was any invoice ever credited beyond its value? (1 minute)

Before 19 Oct the system let a credit note be raised again and again on one
invoice. That is now refused. To see whether it ever happened:

1. Sign in as Finance or an administrator → **Money → Billing workspace** →
   the **Invoices** tab.
2. **If any invoice is affected**, a red panel appears at the top:
   **"⚠ Invoices credited beyond their value"**, with each invoice number and
   the amount it is over. **"Show them as a list →"** opens just those. The
   same item also appears on the home screen under *needs attention*.
3. **If nothing appears, nothing happened.** There is no green message; the
   absence of the red panel is the all-clear.
4. For each affected invoice, decide with your accountant: either the
   customer is owed the difference, or a credit note was raised in error and
   needs reversing.

No database access is needed for this. It replaces the query in the gap
register.

---

## Part 6 — One sentence for your own quotation terms (2 minutes)

You decided that bank charges are the customer's to bear. New installations
now print that in the default quotation terms. **Your live site keeps the terms
you saved yourself**, so add the sentence once:

1. Sign in as an administrator → **Settings** → scroll to **Default terms &
   conditions**.
2. Add at the end:
   *"All bank and remittance charges are to the payer's account; payments from
   outside India are to be remitted with charges OUR. The amount credited to our
   account must equal the invoice."*
3. Save. It applies to new quotations; existing ones are not altered.

---

## Part 7 — What is needed for a test copy of the live system

A test copy lets every future change be checked on your real data before your
staff see it. Nothing in it can affect the live site. On cPanel hosting this is
normally possible on your existing plan.

**What I need from you (or your hosting provider):**

1. **A sub-address**, for example `test.operations.mghaiapps.com`.
   cPanel → **Domains** (or **Subdomains**) → create it. cPanel creates an
   empty folder for it.
2. **A second, empty database** and a user for it.
   cPanel → **MySQL Databases** → create the database → create a user → add
   the user to the database with **All privileges**. Note the three names and
   the password.
3. **A copy of the live data in it.** cPanel → **phpMyAdmin** → the live
   database → **Export**; then the new database → **Import** that file.
4. **The files.** Copy the live site's folder into the new sub-address folder
   (File Manager → select all → **Copy**).
5. **Point the copy at its own database, and switch off its e-mail.** In the
   copy's folder, edit `config.local.php`:
   - change only the database **name**, **user** and **password** to the new ones;
   - add one line just after `return [`:  `'test_copy' => true,`

   The first change keeps the copy from touching live data. The second stops
   every e-mail from the copy (each one is logged as *"not sent: this is a test
   copy"*) and puts a **TEST COPY** banner on every page, so nobody mistakes it
   for the live system. *Please let me check this file with you before the copy
   is first opened.* **Never add that line on the live site.**
6. **Send me** the test address only. Do not send passwords in chat; create a
   separate administrator login on the **test** copy for testing.

**Two cautions for the copy:**
- It holds real customer data, so give it its own password and do not share
  the address.
- **Do not add a scheduled task (cron job) for the copy.** The copy sends no
  e-mail anyway once `'test_copy' => true` is in place, but there is no reason
  for it to run the daily jobs at all.

---

## Part 8 — After going live: a five-minute spot check of today's changes

| Who signs in | Do this | You should see |
|---|---|---|
| A coordinator | Open a job → *Quality — nonconformities* → **+ Raise a nonconformity** | The form opens and saves |
| The same coordinator | On the nonconformity just raised, try to record containment | Refused — containment is for the Quality Head |
| Finance | **Directory → Vendor register** | The list opens, read only |
| Finance | Record a receipt with **Bank charges** filled in | The receipt page explains the invoice stays short by that amount |
| Finance | **Money → Billing workspace → Export** tab, export one month, import into Tally | Tally accepts every sales voucher, including invoices with rounding |
| An engineer | On the claim sheet, enter a negative number of hours | Refused with *"Hours cannot be negative"* |
