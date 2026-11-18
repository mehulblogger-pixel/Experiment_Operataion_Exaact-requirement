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

TBD-RESULTS

## The zip (opstest only)

TBD-ZIP

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
