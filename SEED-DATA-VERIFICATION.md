# Demo / Seed Data — Expected Results & Verification Checklist

Use this to confirm the app is working after you click **Settings → Demo / sample
data → Load demo data**. Log in as **admin** (Master Admin) to see the "all
offices" totals below. (Branch users see only their own slice, so their numbers
are smaller — that's correct.)

> **Note on dates & numbers:** the seed uses *relative* dates (e.g. "3 days ago"),
> so date-based counts (overdue, renewals) are measured from the day you load it.
> The figures below are what a freshly-loaded dataset produces for the admin.

---

## 1. What gets loaded (record counts)

| Thing | Expected (demo adds) |
|---|---|
| Offices | 3 demo offices (Mumbai, Ahmedabad, Pune) added |
| Users | 11 demo logins added (see below) |
| Inspectors | 4 (Ravi EMP01, Anil EMP02, Priya EMP03 + 1 sub-con Mohan **SC-001**) |
| Clients + Vendors | ~21 demo (5 named + 16 edge) |
| Contract numbers | 15 (3 named: 40231, 40198, 40155 + 12 edge) |
| Calls | 156 (6 named + 150 edge cases) |
| Jobs | 156 (6 named + 150 edge cases) |
| Vouchers | 34 (2 named + 32 across inspectors/months) |
| Agencies | 2 (TalentFirst Recruitment, SiteForce Manpower) |
| Requisitions | 2 (one New, one Replacement) |
| **Total generated edge-case records** | **332** (shown in the load message) |

The "Load demo data" confirmation message should read roughly:
*"Demo data loaded — 3 offices, 11 users, 4 inspectors, ~21 clients/vendors,
3 contract numbers, 6 calls, 6 jobs, 2 vouchers, plus 332 generated edge-case records."*

---

## 2. Demo logins (all password `demo12345`)

| Login | Role | Lands on |
|---|---|---|
| `director` | Business Director | All-office analytics |
| `sbuhead` | Business Unit Head | business unit dashboard |
| `bmanager` | Branch Manager (Ahmedabad) | Branch dashboard |
| `appmanager` | Branch Application Manager | Branch + overheads |
| `opmanager` | Operation Manager | Ops dashboard |
| `asstmgr` | Asst. Manager (Pune) | Ops dashboard |
| `coord.amd` | Coordinator (Ahmedabad) | Scheduling |
| `coord.pun` | Coordinator (Pune) | Scheduling |
| `account` | Accountant / Finance | Money desk |
| `insp.ravi` | Inspector (Ahmedabad) | Phone "My Jobs" |
| `insp.anil` | Inspector (Pune) | Phone "My Jobs" |

**Check:** log in as each — the sidebar/menu should differ per role (e.g. `account`
sees Invoicing/Profitability but not Users/Settings; `insp.ravi` sees only My
Jobs / My Voucher).

---

## 3. Screen-by-screen expected results (as **admin**, all offices)

### Home dashboard
| KPI | Expected |
|---|---|
| Open calls | **53** |
| Open jobs | **53** (with **13** overdue) |
| Closed jobs | **103** |
| "Money desk" cards | Invoice pending **51** · Awaiting payment **51** · Overdue **26** · Credit not received **12** |
| Recruitment placement fees card | Provisional **₹50,000** (1) · Confirmed **₹45,000** (1) |
| Open manpower requisitions card | **2** |
| Agency contracts renewing soon | **1** (TalentFirst, ~20 days left) |

### Invoicing (money desk)
| Bucket | Expected |
|---|---|
| Invoice pending | **51** |
| Awaiting payment | **51** |
| Overdue | **26** |
| Credit not received | **12** |
| Unbilled (₹) | **₹9,46,000** |
| Outstanding (₹) | **₹36,64,000** |

### Jobs list
- **156** rows; a mix of **Open / Overdue** pills and **Closed** with money pills
  (**Unbilled / Awaiting pay / Paid**). Use the **⬇ Download CSV** to open in Excel.

### Calls list
- **156** rows; status pills **To schedule / In progress / Closed**; a
  **Cost incurred** column; try the **Min cost ₹** filter.

### Profitability → contract numbers list
- **15** contract numbers in one accessible list with **Sr No · contract number · Client ·
  Status · Created on · Expires on · Renewed into · Jobs · Invoicing done ·
  Expenses booked** and (salary-cleared roles only) **Salary costing · Profit INR ·
  Profit %**. The 3 named ones (40231, 40198, 40155) have full job + voucher history.
  Expiring/expired contracts show an amber/red pill; a renewed contract links to the
  newer number that continues it. **⬇ Download CSV** exports all columns.

### Vouchers screen (role-scoped cards)
- Top cards show **Total expense claimed · This month · Awaiting approval · Paid**.
  As `insp.ravi` the cards say **"Total I have claimed" / "only your own claims"**
  (scoped to that inspector); a coordinator/manager sees the team-wide totals.

### Masters → Expense heads
- Includes both **Food allowance (meals)** (fixed allowance) and **Food bills
  (actual)** (actual bill, needs receipt) — the latter is new; existing installs
  gain it automatically on the next page load.

### Hiring → Requisitions
- **2** requisitions. Open **REQ-2607-02** (Replacement) → it shows a
  **cost comparison** (Outgoing **Anil Sharma** vs Budgeted vs Hired).

### Masters → Recruitment / manpower agencies
- **2** agencies: **TalentFirst Recruitment** (renewal ~20 days out) and
  **SiteForce Manpower**.

### Inspector phone view (log in as `insp.ravi`)
- Cards: **Open jobs to do / Reports pending / Overdue / Voucher**; tap a card
  to open the filtered "My Jobs" list.

---

## 4. Quick pass/fail checklist
- [ ] Load message shows **"…plus 332 generated edge-case records."**
- [ ] Admin dashboard shows the KPI numbers above.
- [ ] Money desk shows **51 / 51 / 26 / 12**.
- [ ] Placement-fee card shows **₹50,000 provisional / ₹45,000 confirmed**.
- [ ] Requisitions list has **2**; the Replacement one shows the cost comparison.
- [ ] Each demo login lands on a different, role-appropriate view.
- [ ] **⬇ Download CSV** on Jobs/Calls/Invoicing/Profitability downloads a file.
- [ ] contract-number list shows the new columns; sub-con **Mohan** has code **SC-001**.
- [ ] A new inspector saved with a **blank** Employee code auto-gets **SC-###**
      (sub-con), **FL-###** (freelancer) or **EMP##** (staff).
- [ ] Vouchers screen shows the role-scoped cards (inspector = own only).
- [ ] Expense heads include **Food bills (actual)**.
- [ ] **Remove demo data** (Settings) clears it all back to zero.

If any number is very different, note which screen and tell the developer — it
usually points at a specific area to check.
