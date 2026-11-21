# Report 54 — Permission foundation A: Sales rights that you can now switch on and off

**Date:** 21 November 2026
**Your instruction:** "All as recommended", in reply to the permission-screen proposal (questions Q1–Q9 and Q11), DEF-25, and "Overdue" following the salesperson. On the same day: "Data shown shall be as per the permission set in our settings."
**What this update does:**
- Nine Sales rights that used to ride on another tick, or on a job title, become switches of their own (13 switches in all).
- A person's own access is stored as *differences from their role*, so later changes to the role still reach them (Q11).
- Customer 360 money follows the viewer's branches (DEF-25).
- On Orders & payments, "Overdue" follows the salesperson picked.

**Rollback point:** update `c41919a` (Stage 1 + DEF-01 + defects batch 1 + the record of your answers). Install the earlier zip to go back.

**The promise, and how it was checked:** on the day this is installed, **nobody gains or loses anything**. Each new switch starts *on* for exactly the people who could already do that thing. This was not done by hand from a list. The program applies the old rule to every saved set, and a test proves the result person by person (see "Tests" below).

**What this update does not do yet:** the new "Who can do what" screen from the proposal pictures. That is the next batch. Until then, the new switches appear on today's screens:
- **Admin → Roles & permissions**, under *Marketing & Sales (CRM)*, *Project costing* and *Money*;
- **Users → a person → Permissions**, under *Sales* and *Money*.

---

## 1. The new switches, and who has them on day one

| # | Switch (as it appears on the screen) | What it used to depend on |
|---|---|---|
| Q1 | **See other salespeople's lists** (Everyone / Choose a person) | A management job title |
| Q2 | **Set sales targets for others** | A management job title, plus Sales reports |
| Q3 | **View deals** · **Add and edit deals** | Leads, Quotations or Enquiries |
| Q4 | **Change pipelines and stages** | Leads edit, or "Manage system settings" |
| Q5 | **See what customers were billed and paid** (Sales screens and Receivables) | "Inter-office credit figures" or "Credit reconciliation" |
| Q6 | **Download leads / deals / quotations to Excel / CSV** (three switches) | Anyone who could open the list |
| Q7 | **View project costings** · **Prepare project costings** | Quotations, Enquiries or Recruitment, or a management job title |
| Q8 | **Manage the cost-head library** | A management job title |
| Q9 | **Change Sales settings** | "Manage system settings", the same tick as every company setting |

**Who has each switch on day one** (the built-in roles; MA = Master Admin, who always has everything):

| Switch | AD | BD | SBU | BM | BAM | OM | CO | AM | FIN | BDM | KAM | MM | ME | QH/TH | Inspectors |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| See other salespeople's lists | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | — | — |
| Set sales targets for others | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | — | — | — | — |
| View deals | ✓ | ✓ | ✓ | ✓ | — | — | — | — | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Add and edit deals | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | ✓ | ✓ | ✓ | ✓ | — | — |
| Change pipelines and stages | ✓ | ✓ | — | — | — | — | — | — | — | — | — | — | — | — | — |
| Download leads | ✓ | ✓ | — | — | — | — | — | — | — | ✓ | ✓ | ✓ | ✓ | — | — |
| Download deals | ✓ | ✓ | — | — | — | — | — | — | — | ✓ | ✓ | ✓ | ✓ | — | — |
| Download quotations | ✓ | ✓ | ✓ | ✓ | — | — | — | — | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Change Sales settings | ✓ | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| See what customers were billed and paid | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | — | ✓ | ✓ | ✓ | ✓ | — | — | — |
| View project costings | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Prepare project costings | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | ✓ | ✓ | ✓ | ✓ | — | — |
| Manage the cost-head library | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — | — | — | — | — | — | — |

If your company has already changed a role on Roles & permissions, or given a person their own access, that company's day-one answer comes from the same old rule applied to what was saved. So it may differ from this table, but it is still exactly what that person could do before. Custom roles follow the role they were built on.

**A few cells that may surprise you, and why they are right.**
- **The Business Director can change pipelines.** Their "Delete leads" tick has always carried "edit leads", and editing leads was what let a person change pipelines.
- **Finance can view deals.** Finance can view quotations, and that was enough. Finance still cannot open the Deals register itself, because it needs Leads, as before.
- **The Coordinator can view and prepare costings.** They hold Recruitment edit.

---

## 2. Examples: what you can now do that you could not before

**Example 1. A Sales Manager role who can see the team.** You create a role called "Sales Manager", built on the Business Development Manager. Before, nobody in that role could ever look at a colleague's list, because only a management job title could. Now tick **See other salespeople's lists** for that role. Sales home, Today, the board, Orders and Targets then offer "Everyone" and "Choose a person" to them.

**Example 2. Payments, separately from inter-office credit.** You untick *inter-office credit figures* for a Coordinator. Before, they would silently also lose customer payments on the Sales screens. Now the two are separate. **See what customers were billed and paid** stays on until you switch it off.

**Example 3. A salesperson who must not download the lead list.** Switch off **Download leads to Excel / CSV** for that person (or role). They still see and work every lead on screen; the CSV button disappears. If they type the download address, the download is refused and they are told which permission it needs. *(Checked as a real sign-in.)*

**Example 4. A Sales Head who sets working days without becoming an administrator.** Switch on **Change Sales settings** for them. They can set follow-up days, weekly off, stale days and who gets new leads. They still cannot touch any other company setting. *(Checked as a real sign-in. Before this, only "Manage system settings" opened Sales settings.)*

**Example 5. Exceptions now follow the role (Q11).** A Business Development Manager has one exception: no quotation download. Later you give the whole BDM role "Approve quotations".
- **Before:** that person's access was a frozen copy, so they would *not* get approving.
- **Now:** they get it, and they still keep their own exception. *(Checked in the test, both ways.)*

---

## 3. Q11: own access stored as differences

- **How it is stored now.** A person given their own access is stored as `@diff,+right,-right`: what they have beyond the role, and what is taken from it. Everything else follows the role, today and after any later change. Ticking a person back to their role's ticks makes them simply follow the role again.
- **The one-time move.** The first time the updated site is opened, each person still on a frozen copy is moved to the new storage. Before anything is written, each move is checked: the person must hold exactly the same rights afterwards. If one did not, that person would be left as they are. In the test, none had to be left.
- **History is kept.** The value that was there before is written to the sealed audit trail ("access stored as differences"), with the new value beside it.
- **The move happens only once.** A second run moves nobody.
- **Old frozen copies still work.** A frozen copy written by anything else (for example an old import) is still read exactly as before.
- **A side benefit.** The stored value is much shorter. A full copy of all ticks could be over a thousand characters, in a box built for 600.

---

## 4. DEF-25 and "Overdue"

**Customer 360 money now follows the viewer's branches.** Example from the test: one customer has an unpaid ₹59,000 invoice from Mumbai and ₹82,600 from Ahmedabad, both overdue.

| Who opens the customer | Owed, before | Owed, after |
|---|---|---|
| Meera, Finance, Mumbai only | ₹1,41,600 (both branches) | **₹59,000** (Mumbai only) |
| Rakesh, central Finance, every office | ₹1,41,600 | ₹1,41,600 (unchanged) |

- The overdue figure, the ageing and the invoices listed on Customer 360 follow the same rule.
- This is the same rule as the client register's Sales view, which links to Customer 360. The two no longer disagree.
- The customer ledger and the advisor ("What to fix") still show the whole-customer figure. That is noted in the pending list.

**Overdue on Orders & payments follows the salesperson picked.**
- With "Everyone", Overdue lists every overdue invoice of your branches, as before.
- With one person picked, it lists only the invoices of that person's orders. This is the same rule *Received this month* and *Days to get paid* have followed since batch 1.
- Rows from the older job-based register have no order behind them, so they are not counted for one person.

---

## 5. What did NOT change

- **Nobody's access** changed on day one. Every gate, every older right, the Sales tiles and 190 screens and downloads were proved identical as real sign-ins (section 6).
- **No figure changed**, except the two branch-limited figures in section 4. They show *less*, never more.
- **No new status, no table, no column.**
- **Approving a costing** is unchanged; the approval levels decide it.
- **Deleting a deal** is unchanged (a management job title).
- **"Inter-office credit figures" and "Credit reconciliation"** keep their own meanings everywhere else.
- **Opening the Deals register** still also needs Leads view, as before. Separating it fully would let today's Finance (who can view quotations) open deals. That would be a change of who can do what, so it waits for you (pending list).
- **The Sales reports CSV** (/crm-reports) is a report, not one of the three lists, and is unchanged.
- **The permission document (docs/02)** is updated:
  - items 19r (the switches) and 19s (DEF-25, Overdue);
  - the day-one table under the Sales roles;
  - the note on own access stored as differences.

---

## 6. Tests

| Test | Result |
|---|---|
| **Proof of "nobody gains or loses anything"** (`test_perm_a_equivalence`). The same test first ran on the old code (c41919a) and saved its answers. For 41 people, it covered every role (branch-limited and all-branch), four custom roles, a Master Admin, and own access stored in every format the program has ever written. For each person it recorded 25 gates, all 194 older rights, their Sales tiles and their branches, twice: with built-in defaults and with a saved Roles & permissions grid. It also opened 10 screens and downloads as 19 real sign-ins. On the new code every answer must be identical, before *and* after the Q11 move. | **11 / 11 — identical.** Checked that it really catches mistakes: one rule was deliberately broken, and it reported 20 differences. |
| **The switches work** (`test_perm_a_switches`). Each switch is named, explained and on both screens. Off for one role: only that role loses it. On for one person: only that person gains it. Q11 role changes reach a person with an exception. The one-time move keeps rights and writes the audit. Reports read the new storage. DEF-25 and Overdue (section 4 numbers). Real sign-ins: refused download, hidden CSV button, refused pipeline change, Sales settings opened and refused. | **68 / 68** |
| **Related existing test files** (214 files: access, permissions, scope, roles, route gates, entitlements, Sales, quotations, leads, deals, pipelines, costing, receivables, books, clients, Stage 1, DEF-01, batch 1 …) | **7,165 checks pass.** 9 fail, and they fail in exactly the same way on the code before this update (c41919a): 3 are DEF-24 (background jobs under a plan limit) and 6 are a sibling of it (export addresses under a plan limit). Neither is caused by this update. Three older tests that described how own access used to be *stored* (a frozen copy with a module stamp) were updated to the new storage, keeping what they protect; one gate test was told about the four new gate names. |
| **Robot** (104 test-book cases on a full copy of the site, compared one by one with the previous run) | **Identical, case by case**: 46 passed, 23 failed, 24 incomplete, 10 need you, 1 out of date. No case worse, none better. The robot's failures are the same older test-book items as before. |

---

## 7. Your check list on opstest (10 checks)

1. **Admin → Roles & permissions → Branch Manager.** Under *Marketing & Sales (CRM)*, "See other salespeople's lists" and "Set sales targets for others" are ticked. Under *Project costing*, "Manage the cost-head library" is ticked.
2. **Sign in as a Branch Manager → Sales home.** "Everyone / Choose a person" is there, as before.
3. **Untick "See other salespeople's lists" for the Branch Manager and Save.** The Branch Manager now sees only their own list on Sales home and Today. Tick it again.
4. **Untick "Download leads to Excel / CSV" for the Marketing Executive.** As a Marketing Executive, open Leads: no CSV button. Type `/leads?export=csv`: refused, with the reason. Tick it again.
5. **Users → pick a Marketing Executive → Permissions → tick "Change Sales settings" → Save.** That person opens Sales → Sales settings, but not Admin → Settings. Another Marketing Executive cannot open Sales settings.
6. **On the user list, that person shows "own access".** Untick the box again and Save. "Own access" disappears: they follow the role again.
7. **Give the whole Marketing Executive role one more tick** (for example "Send quotation to the customer"). The person from check 5, if they still have their own access, gets it too. *(Before this update they would not have.)* Take it off again.
8. **As a Mumbai-only Finance user, open a customer who has invoices in two branches (Customer 360).** "Owed" counts only Mumbai's invoices. As central Finance (every office) it counts both.
9. **Sales → Orders & payments → pick one salesperson.** "Overdue" lists only that person's orders' invoices. Pick "Everyone": all of your branches' overdue invoices.
10. **Admin → Audit trail** (or the compliance audit log). For anyone who had their own access, there is one "ACCESS_STORED_AS_DIFFERENCES" line from the day of the update, with the old value and the new value.

**Commits:** `6f4c7cb` (code), `9eba8f7` (release stamp), this report.
**Update zip:** `EXAACT-V51.1-Permissions-A-TEST-COPY-update.zip` — 839 files, sha256 `73c20445388061a99c26ef8d55e5f45ce1acf6d789bbd98a0dbdc526c3d99593`. It contains no `config.local.php`, no root `tenants.php`, no `tests/`, no database and no `exaact_data*` / `exaact_backups*` folders.
