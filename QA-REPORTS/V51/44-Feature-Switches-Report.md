# Feature switches: report and opstest update (step A of plan 42, 18 Nov 2026)

**You asked for** *"switches to make the features on and off … for all the features and sub features under each module … easy to get back all the things as it is today."* You approved the picture (artifact "Feature Switches Design").

**This zip contains** the Expense setup update (report 39), if not installed yet, **plus** the switches. Nothing else changed.

## What you will see

**Admin → Modules & features**, the same screen as today, with three additions:

1. **Quick start**, at the top:
   - **"Back to 18 Nov 2026":** every module, screen and switch exactly as it was the first time this update ran.
   - **"Simple start (recommended)":** the core that earns money; the rest off.

   Both buttons first show the exact list of what changes, and say "Nothing is deleted".
2. **"Screens in this module"** in every module card: every screen of that module with its own on/off switch, and one **Save switches** button per card. There is also a card **"Home & insights"** for the cross-module screens. In total about **130 screens** have a switch.
3. **The switches you already had** on other screens now also appear in their module card: pre-order checklist, customer satisfaction, client portal, vendor portal and careers page. These are the same switches, not copies.

**When a screen is switched off:**

- its tile and its "Find a screen" entry disappear;
- links and buttons that open it are hidden on other pages;
- typing its address shows *"… is switched off for your company"*;
- its records are kept.

Switching it on brings everything back.

**Never switched off** (no switch), so work can never get stuck:

- Jobs, Work orders, Clients, Vendors and Vouchers
- every approvals inbox and its setup
- Users, Settings, Masters and this screen
- each person's own password and signature
- the platform-owner screens

**Simple start switches off:**

- **Modules:** People & hiring and Marketplace.
- **Screens:**
  - Project costing
  - Pipelines & funnels
  - Pre-order checklist
  - MGH Books
  - Business Unit profit & loss
  - Cost reconciliation
  - Command Centre
  - What to fix
  - Where the flow is broken

**Installing the update changes nothing.** Both your companies stay exactly as they are until you press a switch. Every change is recorded: the screen shows "Last change: date, by whom", and the audit log keeps every change.

**Permissions:** no new permission. The same people as today (Master Admin, or "manage settings") use this screen. `docs/02` and `docs/04` are updated.

## How it was tested

| Check | Result |
| --- | --- |
| New automatic tests (`test_feature_switches.php`) | **53 checks, all pass.** They cover: about 130 screens with a switch, each on its module card; core screens without a switch; off = no tile, not in "Find a screen", refused by its address together with its family (/leads also /lead), but **never** another module's screen (/reports does not take /report-approve); a forged setting cannot switch off jobs or approvals; one card saved at a time; the existing switches saved from here; Simple start asks first, then switches off exactly its list; "Back to …" restores modules, screens and switches exactly; no record is deleted; the baseline is never overwritten |
| Full suite, SQLite | **21,397 passed.** Only the release stamp "failed" (it is renewed at release time; the zip shows 729 of 729) |
| Full suite, MariaDB (your server's database type) | **21,387 passed.** The failures are exactly the **24 known** MariaDB test-order ones, plus the same release stamp. **No new failure** |
| Robot: 98 real-screen tests (the 83 from reports 38/39 + 15 that use Modules & features), before vs after | **All identical.** The first run found 5 tests on Modules & features failing. I had renamed the button "Turn off this feature" (your test book uses those words), and the new "Home & insights" card was being counted as a module. Both are fixed: the button keeps its name, and the card is no longer counted as a module. All 98 now behave exactly as before. FLOW-15 still has the one step it had before (the known DEF-DIR-009, unrelated) |
| I used it in a browser | Simple start (Recruitment, Marketplace, What to fix and Where the flow is broken left the menu; /project-costings refused), then "Back to 18 Nov 2026" (everything back) |

## The zip (opstest only)

`EXAACT-V51.1-Switches-TEST-COPY-update.zip`
- **Release stamp:** 8c158fe. Deploy-check on the build: **729 of 729 files match** (one new file: `lib/features.php`).
- **SHA-256:** `1a1facaee6e887db69d5397bfb38d0c5536b7e107ffe29eb3698dfe9a9d60218`
- **Includes** the Expense setup update (report 39). If you have not installed that yet, this zip covers both.
- **Left out, as always:** `config.local.php`, `tenants.php`, `tests/`, database files, `exaact_data*` / `exaact_backups*`.

**Install** (the same steps as last time):
1. Back up opstest.
2. File Manager → opstest app folder → upload → Extract (overwrite) → delete the zip.
3. Open `deploy-check.php`: Release **8c158fe**, green ✓. Send me the screenshot.

## Your checks (about 10 minutes, in the Mehul Blogger company, as admin)

| # | Do | You should see |
| --- | --- | --- |
| 1 | Admin → **Modules & features** | **Quick start** at the top; in each module card "Screens in this module · N on" |
| 2 | Sales card → open "Screens in this module" → switch **Pipelines & funnels** off → Save switches | "Saved. 1 switched off. Nothing is deleted." |
| 3 | Left menu → **Sales** | No "Pipelines & funnels" tile. Type `/pipelines` in the address bar: "Pipelines & funnels is switched off for your company" |
| 4 | Back to Modules & features → switch it on → Save | The tile is back, with everything as before |
| 5 | Quick start → **Simple start** → Use this | A list of what will be switched off, and "Nothing is deleted" |
| 6 | **Yes, use Simple start** | The left menu no longer shows Recruitment, Marketplace, What to fix or Where the flow is broken |
| 7 | Quick start → **Back to 18 Nov 2026** → Use this → **Yes, go back** | Everything exactly as before step 5 |

**One-line answer:** *"Switches OK"*, plus anything that did not match (a screenshot helps). Next is **step B: the simple menu**, picture first.

## Rollback
1. Restore the backup taken before uploading.
2. Or upload the previous zip (`EXAACT-V51.1-ExpenseSetup-TEST-COPY-update.zip`, d1bf06e).

The switch settings then stay unused and are ignored.
