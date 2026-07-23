# Pending / parked items — SGS Ahmedabad Inspection Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## 🚧 Expense / Inspector-Voucher module (IN PROGRESS — the profitability engine)

Modelled on the real "Statement of Travelling Expenses" the inspectors use.
Wide grid: one column per configurable expense head, TOTAL row at bottom + grand
total. Auto-fills from Jobs; inspector only enters hours + km + bills.

- [x] **P1 · Masters & codes** — `expense_heads` master (code, type PER_KM/BILL/
      ALLOWANCE, default rate, needs-receipt, column order — each is a voucher
      column); `travel_modes` master (Bike ₹6, Car ₹12, Own-car, Ola/Uber, Auto,
      Bus, Train, Air — per-km vs actual); `leave_type` + `day_code` lookups
      (CL/SL/PL/LWP/COMPOFF/ML + OFFICE/WFH/TRAINING/HOLIDAY/WEEKOFF). Seeded on
      fresh + upgrade; both masters on the Masters page; boot probes added.
- [x] **P2 · Inspector entitlements (Super-Admin only)** — DONE.
      `inspector_allowances` table + a "🔒 Allowances & rates" panel on the
      inspector edit page. Per inspector: tick which travel modes & expense heads
      they may claim, and set a **personal rate override** (blank = master
      default). Helpers `inspector_mode_rate()` / `inspector_head_allowed()` /
      `inspector_mode_allowed()` drive the voucher later. Verified: panel + save
      work for Super Admin; a normal inspector save does NOT wipe the
      entitlements; and a Branch Manager cannot see the panel nor POST to it
      (0 rows written). Boot probe added.
- [x] **P3 · Voucher auto-fill** — DONE. `vouchers` + `voucher_entries` tables;
      new **Vouchers** tab (inspectors see "My Voucher"). Open/create a voucher
      per inspector+month; **"Pull working days from jobs"** auto-fills one row per
      inspection day (date, **vendor display name** as site, **File No = BOSS**,
      SBU, 8h, tagged `auto`) — idempotent, never duplicates. **Multiple rows per
      date** supported with a per-day **hours subtotal** + month total; hours
      **editable**; **Line No editable** (from Accounts), File No editable on work
      rows. Add non-inspection days (Office / Leave-with-code / Holiday / Week-off).
      Access: inspector = own; coordinator+ = any. Verified end-to-end; boot probe
      added. (KM/expense columns + totals arrive in P4.)
- [x] **P4 · Fast entry** — DONE. Wide grid: per row a **Mode** select + **KM**
      (auto-filled from vendor memory ↺, editable) → **Travel ₹ = km × the
      inspector's entitled rate**, plus one **bill column per entitled expense
      head** (only the heads/modes the Super Admin allowed appear). **Bottom TOTAL
      row** sums every column + **Grand Total**; JS recomputes live as you type,
      server recomputes authoritatively on **Save all**. `vendor_km_memory`
      remembers km per inspector+vendor. Verified: Bike ₹6 → 38/40/38 km, Food/
      Hotel bills, grand total ₹1,839, memory stored, Bus/Train hidden (not
      entitled). Boot probe added. *(One supporting file per voucher lands in P5.)*
- [x] **P5 · Output & workflow** — DONE. On the voucher: a **Summary — particulars**
      panel (Travel + each head → Grand Total, Less Advance, Less Office-incurred,
      **Balance to be paid/recovered**); a **single supporting file** per voucher
      (one upload backs all bills; streamed via `/voucher-file`); a **printable
      "Statement of Travelling Expenses"** (`/voucher-print`, standalone, browser
      print) matching the real format with the 3 signature blocks; and the
      **status workflow** DRAFT → SUBMITTED → APPROVED → PAID with **Checked /
      Approved / Authorized** captured, edit-locked once out of DRAFT, and Reopen.
      Verified: total ₹1,611, balance ₹1,011, file round-trip, print page, all
      transitions. `supporting_mime` migration + boot-safe.
- [ ] **P6 · Attendance reconciliation** — upload HR payroll export, parse **in
      memory only** (do NOT store the company doc), match by Employee Code, flag
      day-type mismatches (claimed visit vs HR leave); save only the result.
- [ ] **P7 · Profitability by BOSS/Contract** — roll voucher lines → job → BOSS;
      Revenue − labour − expenses − subcon = margin %; **+ icon drill-down** per
      expense/invoice line (date, inspector, vendor, hours, cost). Clickable
      File/Line + profitability visible ONLY to Super Admin / Branch Manager /
      manager-under-branch-manager (new `data.profitability` perm) — not
      inspector/coordinator.
- [ ] **P8 · Contract/BOSS carry-forward** — renew/supersede a BOSS/contract
      (Open/ARC) → new number, carry PO/open jobs/lines forward, show old number.

## Additional features (user will provide details / build later)

- [ ] **Inspector expenses linked strictly to the job done** — an inspector's
      expenses must attach only to the job they performed (fuller rules to be
      provided by the user).
- [x] **CV / hiring pipeline (deputation resourcing)** — DONE. New "Hiring" tab.
      Add a candidate CV (name, trade→skill, client, against-call, proposed site,
      SBU, designation, source [asset/freelancer/sub-con], experience, rate, CV
      link, CV-received date). Move through **CV received → Submitted to client →
      Shortlisted → Interview → Hold / Reject / Accept(=Hired) / Withdrawn**, each
      transition logged with a remark + who/when (full history on the candidate).
      On **Accept** you can tick "add to Inspectors" and the person is created as
      an inspector (carrying trade/skill/SBU/designation and the freelancer/
      sub-con type) ready for deputation-job allocation. Stage filter chips +
      counts on the list. Tables: `candidates`, `candidate_events`.

## Parked (agreed to do later)

- [ ] **Full organisation structure** — model **independent, peer offices**.
      IMPORTANT org model (confirmed by owner): commercially the **HO is Mumbai**,
      but **operationally there is NO head office** — every office is its own unit
      with **its own targets, operations and P&L**. Offices are peers; inter-office
      work is a **credit handoff between equals**, never HQ→branch. Build: users
      linked to their office; **multiple** Operation Managers / Coordinators per
      office; per-office targets; each office's dashboards default to *its own*
      numbers, with cross-office roll-ups only for roles whose scope spans offices
      (e.g. a commercial/Director view from Mumbai). Do **not** treat Ahmedabad (or
      any office) as a managing HQ — the old `is_ahmedabad` "managing office" idea
      is being unwound. This is a role/permission + org redesign for a dedicated
      pass; needs the owner's intended per-office roles before building.
- [x] **Multi-SBU cost distribution in dashboards** — DONE. The Financial
      dashboard's "By SBU" panel now shows Credit vs **distributed loaded cost**
      vs Net per SBU. Each active engineer's monthly loaded cost (CTC/12 + 8%
      overhead) is split equally across the SBUs they're tagged to, respecting
      SBU scope + the SBU/inspector filter. Salary-gated (`data.salary`).


- [ ] **Reminder cron jobs** — set up the two cPanel Cron entries (07:00 report-due,
      18:00 overdue-closure) pointing at `cron.php`. Deferred by user; steps are in
      `README-MilesWeb-PHP.md`.
- [ ] **Office 365 automatic email sending (SMTP)** — send assignment/closure/
      forward/reminder emails automatically from a real mailbox, no clicking.
      Needs a sending mailbox + 3 settings in `config.php`. (Today: emails are
      logged, and each Call/Job has an **Open in Outlook** button that pre-fills
      the mail so the user can attach the original client email and send.)
- [ ] **License server + per-user billing** — support **both** deployment models
      (client's own server **and** our hosted server) for different industries,
      with remote seat-limit enforcement, subscription expiry, module toggles, and
      a control panel we run from our side. Ties to Razorpay/Stripe for the
      one-time setup fee + monthly per-user recurring. (Roadmap artifact already
      shared.)

## Module A/B sub-items — DONE (this session)

- [x] PO/line-item selection on a call + qty tracking + near-completion alert.
- [x] Project deputation → client sites dropdown (shown only for deputation).
- [x] Executing-branch confirmation status on the call.
- [x] PO line items: manpower/site/trade→subcategory + GST/Tax/Total + rollup;
      activity per line respecting the PO's SBU; multi-SBU on the PO.
- [x] Projects tab lists the partner's calls.
- [x] City light auto-correct; Type-of-inspection 'Other' free text on the call.

## Modules C / D / E — not yet started

- [ ] C: Logo upload + editable theme (kept legible); per-SBU expense headings.
- [ ] D: inspection lifecycle/status flow; designations master (Inspector,
      Sr. Inspector, Sr. Executive…); back-office staff with costing (CTC,
      allowances).
- [ ] E: add your own dropdown/text box to any master form (custom fields on
      masters).

## Dashboards — refinements to follow

- [x] **Configurable expense headings** — DONE (global). The 5 base headings can
      now be **renamed** via the `expense_heading` list, and **any extra headings**
      you add there appear automatically on the job-close form, flow into the job
      total/profit, the job-detail expense table, and the Financial dashboard's
      "Expenses by heading" breakdown. Extras are stored per expense row as JSON
      (`expenses.extra`), so nothing about the fixed 5 columns changed — fully
      backward-compatible. *Remaining refinement:* scope headings **per SBU**
      (make `expense_heading` a child list under SBU) — small follow-up.
- [ ] **Persona landing pages** — today all four dashboard families live on one
      /reports page with each section gated by permission (so each person sees
      only their allowed sections). A future refinement gives each role a
      tailored default landing layout (Director = office comparison, SBU Head =
      SBU-across-offices, etc.).

## Nice-to-have / minor

- [ ] Generic master "checkbox" fields default to ticked on new records (fine for
      "Active" on sub-cons, not ideal for "is Ahmedabad" on a new office). Low impact.

## Done recently (for reference)

- [x] Configurable master lists + dependent (hierarchical) lists + custom fields.
- [x] New Call: quick-add client/vendor/office/product/activity, executing-branch
      forwarding with mandatory credit + coordinator/manager email, lead times.
- [x] Readable error page instead of blank 500.
- [x] Full operations system (Calls, Jobs, Closure, Expenses, SubCon, Attendance,
      Holidays, Comp-off, Credit, Dashboards) with 4 roles + salary security.
