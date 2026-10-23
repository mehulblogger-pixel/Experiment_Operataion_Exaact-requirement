# Start full testing — step by step

The test book: https://claude.ai/artifact/BMuHaXZZDyxYAzxdJevTRW
It has 1,403 tests and 14,904 steps in 14 areas. Every result, note and screenshot is saved in the
book itself.

## The one rule: test on a TEST COPY, never on the live site

Many tests change company-wide settings, create and delete companies, send e-mails and take
payments. Run them on a separate copy of the application with its own empty database and
made-up data. On a copy marked as a test copy:

- **Every page carries a red banner:** "TEST COPY — not the live system".
- **E-mail goes only to addresses you list.** Your testers' inboxes receive mail, marked
  "[TEST COPY]"; mail to anyone else is blocked and logged.
- **Payments work only with Razorpay TEST keys.** With live keys it refuses to start a payment,
  so no real money can move.
- **It keeps its own data folder** (`exaact_data_test`), so it can never take a live invoice
  number or touch the live platform e-mail, backups or company files.

## Step 1 — Put the test copy on your server

**Easiest: use the ready-made test copy** (`exaact-test-copy.zip`, about 15 minutes). It is
already set up and "Start here" is done:
- the made-up company "TB Inspection Services (TEST)";
- the 19 test people (one per role) and the 8 test customers;
- approval levels, Set up Sales answers and the pre-order checklist;
- prices, GST (with a made-up GSTIN), the free trial and storage, and self sign-up switched on.

Use an ordinary sub-domain such as `opstest.mghaiapps.com`, not one nested under
`operations.` (e.g. `test.operations.mghaiapps.com`): the hosting creates the DNS record
and SSL certificate only for one-level sub-domains, so a nested one does not open
("DNS address could not be found").

On the MilesWeb panel, Document Root is typed relative to the sub-domain's own
folder and without a slash at the start: upload and extract the zip inside the
`opstest.mghaiapps.com` folder, then set Document Root to `exaact_test/app`.

It has its own database inside it, so there is no database to create. Follow its
`README-FIRST.txt`:
1. Upload it to your cPanel home folder (not public_html) and extract it.
2. Create the address `opstest.mghaiapps.com` with the document root `exaact_test/app`.
3. Sign in.
4. On Platform setup: enter your own e-mail, the mailbox details and your Razorpay TEST keys.

How it was built: `docs/test-book/test-copy/`.

**Or build one by hand** (about 30 minutes plus "Start here"):

1. **Create the address.** cPanel → **Domains** (or **Subdomains**) → create
   `opstest.mghaiapps.com` with its **own new folder**, e.g. `/home/USER/exaact_test/app`.
   Do not use the live site's folder.
2. **Create an empty database.** cPanel → **MySQL Databases** → create a new database and a user,
   and give the user all privileges on it.
3. **Upload the application.** Put the same upload you use for the live site into that folder.
4. **Create `config.local.php`** in that folder (copy `config.local.sample.php`) with:

   ```php
   return [
       'db'    => ['name' => 'USER_exaacttest', 'user' => 'USER_exaacttest', 'pass' => '…'],
       'admin' => ['user' => 'admin', 'pass' => 'choose-a-test-password'],
       'test_copy'    => true,
       'test_mail_to' => ['you@yourcompany.com', '*@yourcompany.com'],   // your testers' own inboxes
   ];
   ```

5. **Check it.** Open **https://opstest.mghaiapps.com/login** and sign in as `admin`. The
   red TEST COPY banner must show on every page.
6. **Set up payments and e-mail.**
   - Platform setup → paste Razorpay **TEST** keys (`rzp_test_…`) under Billing.
   - Fill in the platform e-mail.
   - Use **Send a test e-mail** to one of your own addresses.
7. **Only if you will test "New company"** (sign-up, trials, plans, GST invoices): go to Super Admin
   → **Cloud workspaces** → set the base domain to `opstest.mghaiapps.com`.

Wherever a test says `operations.mghaiapps.com`, use `opstest.mghaiapps.com`.

## Step 2 — Prepare: "Start here" in the test book (already done in the ready-made copy; just check it)

1. Open the test book → **Start here — users & settings** and follow it top to bottom. It covers
   company details, two branches (Ahmedabad and Mumbai), business units and the **19 test users**,
   one per role.
2. Tick off **Start here → Set up for testing** (SET-01 … SET-12) as each is done.
3. Do not change any tick on a test user's **Permissions** tab. Each test checks the role's own
   rules.

## Step 3 — Test in waves (do one wave at a time)

| Wave | Areas | Tests | Who tests |
|---|---|---|---|
| 1 | Start here · New company | 12 + 48 | You (platform owner) |
| 2 | Sales · Operations · Money | 143 + 146 + 152 | Sales person, coordinator, finance |
| 3 | Recruitment · Reporting · Quality & Accreditation | 133 + 119 + 235 | HR, inspectors, quality head |
| 4 | Marketplace · Directory · Insights · Admin · Your own pages | 136 + 58 + 46 + 150 + 10 | Admin, managers |
| 5 | Across modules (start-to-end flows) and every **phone** test | 15 + about 40 | Mixed team; an inspector on a real phone |

**Within each area,** run the tests in this order:

1. **Scenario** (a real day's work, best case first).
2. **Who may** (each role's limits).
3. **Edge case** (empty, too long, duplicates, two people at once).
4. **Screen check** (every tab, field and button).

**Short on time?** Use the filters to run the tests tagged **best** and **rule** first. These are
your normal day and the rules you decided.

## Step 4 — Who tests what

- **Sharing the book.** Share it from its Share menu:
  - **Contributor** access to record results.
  - **Editor** access to add screenshots.
- **Which account.** Each test names **who** does it (e.g. "Coordinator"). Sign in to the test copy
  as that test user.
- **Desk or phone.** Inspectors work phone-first, so do phone tests on a real phone. Office roles
  test on a laptop.

## Step 5 — Record every test

- **Mark each step:**
  - ✓ as expected;
  - ✗ not as expected;
  - ! could not do it.
- **Write what happened:** the exact message, the record number (e.g. Q-2607-001) and what you
  expected instead.
- **Add screenshots:** drop them in, paste with Ctrl+V, or choose files.
- **It saves by itself.** You can stop half-way and continue another day.

## Step 6 — Every evening: hand the failures to Claude

1. Open **What failed or is blocked** in the book.
2. Tell Claude: **"read the test book"**. Claude reads every failure, note and screenshot from
   the book, fixes the cause, and tells you which test ids to run again.
3. Upload the fixed version to the **test copy**, re-run those tests and mark them again.
4. **Download Excel** (top right) whenever you want a copy for your records.

## Step 7 — Ready for the live site when

- **Start here** and **New company** have all passed.
- **The modules you sell** have no open ✗ on their scenario and rule tests.
- **Every "gap" test** (something the software does not do today) is fixed or accepted by you.
- **On the live site:**
  - switch Platform setup to Razorpay **live** keys;
  - run a short check by hand: sign in, open the main screens, make one real low-value payment
    and refund it.

## How long it takes

There are 1,403 tests. At about 2–4 minutes per test, that is roughly **50–90 hours of testing**.
With 3–4 testers spending part of each day, plan on about **2–3 weeks**, plus re-testing fixes.

## Never

- **Test on the live site,** or put real customers' data into the test copy. Use made-up names.
- **Use live Razorpay keys on the test copy.** It refuses them anyway.
- **Change a test user's permission ticks** or Admin → Roles & permissions.
