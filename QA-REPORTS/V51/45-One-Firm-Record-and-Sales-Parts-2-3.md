# One firm record + Sales parts 2 and 3: report and opstest update (18 Nov 2026)

**You asked for**

1. *"Proceed with part 2 and 3."*
2. *"Client and account creation and contacts related to it."*
3. *"We already have customer and vendor registration form in the operations and directory … make it unified and centralized so nothing is duplicate or multiple entries."*
4. *"Role is ok but birthday and anniversary is not professional. The firm shall be universal. Can be opened from sales as well as from directory … added as both client and vendors."*
5. *"In sales module we have switched off sales setup, approvals etc right?"*

**This zip contains** everything since your last test update: the Expense setup (report 39), the Feature switches (report 44), Simple Sales part 1 (Home, Today, Pipeline board, Dashboard), and everything in this report.

---

## 1. Clients and vendors: one firm record

### What I found when I compared every way a firm gets added

Good news: the app already keeps clients and vendors in **one list**. The Operations "+ Add client / vendor" link already opens the **same** Directory form.

| Where a firm gets added | Before | Now |
|---|---|---|
| Directory → New (the full form) | number `AHM-26-TATAP-00007` | same |
| Operations job/call → "+ Add" | opens the same form | same |
| Lead → "Convert to customer" | number `C-TATAPOWE-0042` ❌ | `AHM-26-TATAP-00008` ✅ |
| Deal → "Add customer" | `C-TATAPOWE-0042` ❌ | one rule ✅ |
| Quote accepted for a new name | `GEN-TATA-0001` ❌ | one rule ✅ |
| Excel import | `GEN-TATA-0001` ❌ | one rule ✅ |
| Customer 360 → add a branch company | `C-00001` ❌ | one rule ✅ |
| Connect registration | no number at all ❌ | one rule ✅ |
| Old "quick add" box (no screen used it) | made a firm with fewer checks ❌ | retired; points to the form ✅ |

**A real fault found in the test copy.** *TB Group Parent* and *TB Group Child* both carry `AHM-26-TBGRO-00003`. Two other firms share the running number 00002. The old rule took the "last" code alphabetically, not the highest number. New firms now always get the highest number + 1, so a number can never repeat.

**Existing numbers are NOT changed.** They are printed on invoices and quotations. See question Q1 below.

### What changed for you

- **One firm, many roles.** Tick *Client*, *Vendor* and/or *Sub-contractor* on the same record. A firm that is both is entered once.
- **Contacts: new "Buying role"** — Decision maker, Influencer / technical, User of our service, Purchase, Finance, Gatekeeper. It is an editable list in Masters, and the values are only a start. Each contact also gets a 💬 WhatsApp link. As you asked, there is **no** birthday or anniversary.
- **Same warning everywhere.** If a contact's mobile or e-mail is already listed at another firm, you get a warning (not a block) on every door, now including lead → customer.
- **Sales sees the same list.** Sales → **Client register** opens Directory → Clients with a **💼 Sales view** switch. The extra columns are: open deals, open pipeline, won this year, amount owed (only for people allowed to see receivables), last contact, and WhatsApp / call. There is **no second customer list**.
- **Pictures** of every client and vendor tab: the PDF *"Clients & vendors — every screen and tab"* (sent earlier).

## 2. Sales part 2: the two daily actions

### ➕ New lead (Sales → New lead, or "➕ New lead" on Sales home)

Three boxes: company, mobile, source (contact person and value are optional).

- **Example.** You type *Shree Engineering, 98250 12345, Referral* and press Save. The lead is saved, and its first follow-up is the **next working day**.
- **Who gets it.** It goes to the salesperson set for that office in Sales settings; otherwise to whoever typed it. A manager can hand it to someone else.
- **Possible duplicates.** If the company name, or the mobile in any format (+91 98250-12345 = 9825012345), is already on an open lead or a firm's contact, you see *"This may already be on file"* with a link. **Save anyway** is there for a genuinely new lead.

### 📝 Log activity (a "📝 Log" button on every row of Today)

1. Choose how: Call, WhatsApp, E-mail, Meeting, Visit or Note.
2. Choose what happened. The **next follow-up date fills itself in**, in **working days**: your weekly off day and public holidays (national, or that office's) are skipped.
3. Save. It goes on the lead's or deal's own timeline, the same place its own page writes.

**Example.** It is Friday and the weekly off is Sunday. You pick *"Spoke — interested"* (3 working days). The next follow-up becomes **Wednesday**: Saturday, Monday, Tuesday, Wednesday, skipping Sunday. If Monday is a public holiday, it becomes Thursday.

## 3. Sales part 3: money, renewals, targets, settings

| Screen | What it shows | Example (test copy) |
|---|---|---|
| **💰 Orders & payments** | Every accepted quote this year → billed → received → still to bill. Cards: orders won, still to bill, received, received this month, overdue, average days to get paid | QT-DEMO-203 ₹9,20,400: billed ₹9,20,400, received ₹9,20,400 → **Fully paid**. QT-DEMO-201 ₹5,90,000: billed ₹2,95,000 → **Payment due** |
| **🔁 Renewals** | Contracts by days left (30/60/90/180), plus those ended in the last 30 days, with a "🧾 Renewal quote" button | CT-DEMO-102 *ended 6 days ago* (red); CT-DEMO-101 *12 days* (amber) |
| **🎯 Targets** | One row per salesperson, one box per month of the financial year; what they won shows under each box; year total and % | Test BDM: Apr–Sep ₹5 L, ₹5 L, ₹6 L, ₹6 L, ₹7.5 L, ₹7.5 L. The dashboard's "target" now reads these |
| **⚙️ Sales settings** | What happened → days; weekly off; "stale" after N days; which salesperson gets each office's new leads. Links to the lists that already exist (sources, lost reasons, buying roles, holidays, who approves quotes) | Starting values are marked **"Example values — edit them"** |
| **Quote screen** | Under the GST line: *of which CGST 9% + SGST 9% (same state)* or *IGST 18% (client in another state)*, using the same rule as the invoice | *of which IGST 18% · client in Maharashtra, another state*. **No figure changes.** |

**Targets are never overwritten.** Changing April from ₹5 L to ₹5.5 L adds a new line, and the old ₹5 L stays in the history below the grid.

**Who can do what** (no new permission):

| Action | Who |
|---|---|
| Add a lead / log | people who can edit leads or deals |
| See billed / received figures | only people who can see receivables |
| Set targets | managers with "Sales reports" |
| Change Sales settings | Master Admin or whoever manages settings |

## 4. Your question: "switched off sales setup, approvals?"

**No.** Nothing in Sales is off unless you turn it off.

- **Approvals are always on.** They have no switch, so a quotation or costing can never go out unsigned. I will not change that without your clear OK.
- **Set up Sales** (who approves quotes) is on. It is visible only to the Master Admin, settings managers and quote approvers.
- **The "Simple start" button** (only if you press it) hides extras: costing, stage gates, the stage editor, ads ROI, pre-order checklist, Books bridge, SBU P&L, cost reconciliation, advisor, flow gaps, command centre, plus the HR and Connect modules. If the stage editor is hidden, Sales settings shows each stage's win % read-only and says how to switch the editor back on.

Every new screen in this report has **its own switch** in Admin → Modules & features → Sales.

## 5. Questions for you (nothing done on these)

- **Q1 — Repeated firm numbers already on file.** On the test copy, TB Group Parent and TB Group Child share one number. Shall I give the second one a new number? The old number would be kept in its history, and anything already printed keeps the old number. **Recommendation:** yes, but only for firms with no invoice yet.
- **Q2 — Vendor breadcrumb.** A vendor record's path reads *Home › Reporting › Inspection reports (IDEMS) › Vendor register*, although the Vendors tile is in Directory. This is how it was before. **Recommendation:** fix it in step B (simple menu), so it reads *Home › Directory › Vendor register*.

## 6. Tests run before this zip

| Check | Result |
|---|---|
| New tests for this work | **One firm record: 21/21 ✅ · Sales parts 2–3: 50/50 ✅** · Sales part 1: 31/31 ✅ · Feature switches: 53/53 ✅ |
| Full automatic suite, SQLite (all 762 test files) | **21,499 passed**, 6 not passed. 2 were real and are now fixed: the targets table is created at start-up, and a new access check is recorded. 2 are the release stamp, refreshed in this zip. 2 are old timing tests, unrelated (see below) |
| Full suite, MariaDB (the live database type) | All the new tests ran and passed. The other failures are the same ones already known from earlier rounds, and the run stops at the same known spot as before |
| Robot: 98 screen-by-screen cases, compared with the last run | **No case got worse.** 5 admin cases went from failed to passed |
| Screens opened by the robot, desktop and phone (390 px) | All new screens: no PHP error, no script error, no sideways scrolling on the phone |
| Duplicate guard | The same firm a second time is refused; a repeated mobile on a new lead is flagged |

**The two old timing tests.**

- An approvals check that "due in one hour" counts as "due today" fails if it runs within an hour of midnight India time. It ran at 23:58.
- A recruitment check where four people convert the same candidate at the same instant fails when the machine is under heavy load. It passed when re-run on its own.

Neither touches Sales, clients or vendors.

## 7. Your check list on opstest (about 12 checks)

1. Directory → Clients → **💼 Sales view**: open deals, won this year and last contact show. Press a name: the same record opens.
2. Sales → **Client register**: the same list opens, already in Sales view.
3. Directory → New: tick **Client and Vendor** together and save. One record appears in both lists, and its number looks like `AHM-26-NAME-000xx`.
4. Try adding the same firm again: you get *"This company already exists"*.
5. A client → Contacts: add a person with **Buying role = Decision maker**. It shows in the list with 💬.
6. Sales → **New lead**: type a company, mobile and source, then Save. The lead opens; the first follow-up is the next working day.
7. Type the same mobile again on a new lead: *"This may already be on file"* appears. **Save anyway** works.
8. Sales → Today → **📝 Log** on a row: pick "Spoke — interested". The date fills in, skipping Sunday. Save, and the row's date changes.
9. Sales → **Orders & payments**: your accepted quotes show, with billed and received.
10. Sales → **Renewals**: contracts ending soon show, with days left.
11. Sales → **Targets**: type two months for one person and Save. Change one and Save again. Both figures show in History, and the Dashboard target changes.
12. Sales → **Sales settings**: change "Spoke — interested" to 2 days and Save. On Log activity it now says "→ 2 working days".
13. Open an accepted quote with a client whose state is set. Under GST you see the CGST + SGST or IGST line, and the Total is unchanged.

If anything looks wrong, tell me the check number. Switching a screen off in Modules & features brings back what you had before.
