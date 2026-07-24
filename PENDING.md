# Pending / parked items — SGS Ahmedabad Inspection Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## 🆕 Requested — to build next (noted 2026-07, owner)

### 1. Complete demo / seed dataset (uploadable, all expected values) — ✅ DONE
- [x] **200+ edge cases** — DONE. The seed now generates **332 edge-case records**
      (150 calls + 150 jobs + 32 vouchers) covering same/cross office, missing
      vendor/dates, overdue, zero & large amounts, billable-vs-credit, every
      invoice/payment/credit state, sub-con, zero man-days, 0-day/null TAT, all
      stages, and voucher statuses incl. leave-only zero-total. Count shown on load.
- [x] **DONE** — `lib/seed_demo.php` + a Master-Admin-only **"Load demo data"**
      button in **Settings** (POST `/seed-demo`, idempotent via `demo_seeded`).
      One click inserts 3 peer offices (Mumbai HO + Ahmedabad + Pune), 11 users
      (every role, password `demo12345`), 4 inspectors (incl. an agency sub-con)
      with entitlements, 3 clients + 2 vendors, 3 BOSS numbers, 6 calls, 6 jobs
      across the full lifecycle (paid / awaiting / overdue / unbilled / in-progress
      / sub-con), closure expenses, and 2 vouchers (DRAFT + APPROVED). Every
      screen shows live figures immediately. *Follow-ups when the credit rules
      below land: extend the seed with same-vs-different-office credit examples.*
- [ ] *(original ask, for reference)* A **ready-made sample dataset** that can be loaded into a fresh install so
      the whole system can be explored end-to-end with realistic values —
      **from user creation → multiple offices → clients/vendors → BOSS/contract
      numbers → calls → job allocation & scheduling → inspection → voucher
      (km + bills) → closure → invoicing → payment → inter-office credit.**
      Purpose: demos, training, and testing every screen with data already in
      place. Should include: several **offices** (peer offices + Mumbai as the
      commercial HO), **users of every role** (Master Admin, Business Director,
      SBU Head, Branch/Branch-App Manager, Operation/Asst. Manager, Coordinator,
      Accountant, Inspector), **inspectors** with salary + entitlements, a few
      **clients/vendors/sites**, **BOSS numbers**, a spread of **calls & jobs**
      (some same-office, some cross-office), **completed vouchers**, and
      **invoiced + paid + credit-settled** examples so profitability, dashboards
      and the money desk all show live figures immediately. Delivered as a
      one-click "Load demo data" action or an importable seed the owner can run.

### 1b. Full access / permission control (every module & feature) — ✅ DONE
- [x] **DONE** — per-module **View + Edit** permissions for all 14 modules
      (Calls, Jobs, Vouchers, Invoicing, Profitability, Hiring, Reconcile,
      Clients, Vendors, Masters, Overheads, Reports, Users, Settings) plus the
      fine data/feature perms. Managed **both** ways: a **Settings → Roles &
      access** editor (per-role, "edit implies view", stored as an override) and
      the **per-user** panel (full checklist). Sidebar + every module route are
      gated on the view perm; inspector My Jobs/My Voucher stay exempt;
      backward-compatible for users saved before the change. Verified across all
      demo roles. *Follow-up ideas: office-scoped module grants; an audit log of
      access changes.*
- [ ] *(original ask, for reference)* **Comprehensive access matrix** — today only ~17 permissions exist and most
      screens are gated by *role level* (coordinator/admin), not fine permissions.
      Owner wants the super admin to grant/deny **each and every module and
      feature**, not a limited set, and to manage it **in Settings** (like the
      permission checkboxes already in the user-create panel, but complete).
      Build: (a) expand the `PERMISSIONS` catalogue to one "can access" entry per
      module — Calls, Jobs, Vouchers, Invoicing, Profitability, Hiring, Reconcile,
      Clients, Vendors, Masters, Overheads, Reports, Users, Settings — plus the
      finer action perms (create call, allocate/close job, see credit, see salary,
      manage masters, reconcile, etc.), grouped by module in the user panel;
      (b) gate the sidebar nav + each route on these perms (defaulting them ON for
      roles that have access today, so nothing locks out); (c) a **Settings →
      Roles & access** editor — a role × permission grid the super admin edits,
      stored in settings and overriding `role_defaults()`. Backbone change —
      scope confirmed with owner before building.
- [x] **Clearer "inspector not linked" message** — DONE. An Inspector login with
      no linked inspector profile now gets one actionable message on My Jobs and
      My Voucher (Users → Linked inspector) instead of "You cannot view vouchers".

### 2. Credit tab — driven by contracting vs executing office — ✅ DONE
- [x] **DONE** — calls carry a **contracting office** + executing office. On the
      call form the credit section toggles: **same office → "Billable value
      (ex-GST)" + basis** (no inter-office credit); **different office → "Credit
      to executing office" + type** (mandatory). Call detail shows a "Credit /
      billing & cost" panel; for cross-office the **executing office can revert
      with the credit it requires** (COUNTERED / AGREED). **Cost incurred**
      (vouchers + expenses) is shown to **both** offices, and the calls list is
      visible to both contracting & executing offices with a **cost column +
      min-cost filter**. (Also fixed a latent scope bug so branch users actually
      see their office's records.) *Follow-ups: voucher auto-download+submit step;
      email the executing office when credit is proposed/countered.*
- [ ] *(original ask, for reference)* **Same contracting & executing office** → the **Credit tab is DISABLED**
      (no inter-office credit to record), BUT the call must still **show the
      billable value** — invoice / **man-day** / **man-month** value —
      **excluding GST**. (So a single-office job still shows what it's worth,
      just with no credit hand-off.)
- [ ] **Different contracting & executing office** → the **Credit tab is FULLY
      OPEN**. The **credit to be given to the executing office** is **clearly
      stated** on the call. The **executing office can revert with the value of
      credit it requires** for that call (a counter-value / negotiation back to
      the contracting office), so both sides agree the credit.
- [ ] **Voucher officially downloaded & submitted** — the prepared voucher
      (Statement of Travelling Expenses) must be **officially downloadable** and
      **submitted** as the record for the call (PDF download + submit step).
- [ ] **Both offices see the spend** — the **contracting office AND the executing
      office** can both **check the amount spent on the call**. Both can **filter
      the inspection list and see the cost incurred** (including **all expenses**)
      for each inspection, shown to each office **according to its scope**
      (contracting sees its calls; executing sees the calls it executed).
- [ ] **Invoicing filters** — the Invoicing / money desk (`/invoicing`) needs a
      **filter bar** like the Dashboards: **Financial Year, Month**, plus office,
      SBU, client and status bucket (pending / awaiting / overdue / credit). The
      counts and worklist recompute for the chosen period so an accountant can
      pull, e.g., "unpaid invoices for FY 2026-27, July, Ahmedabad." Filters
      respect the user's scope, and the filtered view should also be exportable
      (ties into the downloadable-reports item below).

### 3. Downloadable reports — ✅ PHASE 1 DONE (CSV exports)
- [x] **DONE** — dependency-free CSV export (UTF-8 BOM for Excel). "Download CSV"
      buttons on **Jobs, Calls (with cost incurred), Invoicing, Profitability**
      (each respects the current scope + filters), a **Download-reports** section
      on the Dashboards page (permission-gated), and **voucher download**
      (`/voucher-csv` → full Statement of Travelling Expenses, plus Print/Save-PDF).
      *Remaining from the catalogue below (future): TAT report, office/SBU P&L,
      utilization/productivity, overdue-aging, inter-office credit statement,
      and PDF statements for invoices/credit notes.*

### 3b. Downloadable reports — remaining catalogue (research, for later)
Goal: let every function pull the data it needs as a file (Excel/CSV for
analysis, PDF for official statements). Proposed catalogue to build:

**Operations**
- [ ] Call register (with lead-times, status, pending-scheduling flag)
- [ ] Job register / allocation report (inspector, dates, BOSS, status)
- [ ] **TAT report** — on-time vs late, average TAT, by office / SBU / inspector
- [ ] Overdue-closure report (jobs past scheduled/required date)
- [ ] Scheduling / dispatch board export (what's due, who's free)
- [ ] Inspection volume by client / vendor / site / inspection-type

**Finance**
- [ ] **Profitability by BOSS / contract** (revenue − labour − exp − subcon − OH − contingency)
- [ ] **Office P&L** (per peer office; own targets vs achieved)
- [ ] **SBU P&L** (credit vs distributed loaded cost vs net)
- [ ] **Voucher / expense register** (per inspector/month, per expense head)
- [ ] **Invoicing & payment** — raised / received / outstanding / **overdue aging** (30/60/90)
- [ ] **Inter-office credit statement** — given vs received, expected vs actual, reconciliation
- [ ] Expense analysis by head (travel, food, lodging, bills, …)
- [ ] Cost-per-call and cost-per-man-day
- [ ] **Billable value (ex-GST)** per call — man-day / man-month / invoice value

**Efficiency / People**
- [ ] **Inspector utilization** (man-days, % of working days) monthly + trend
- [ ] Attendance / leave summary (from voucher-derived present/leave)
- [ ] Inspector productivity (jobs, man-days, credit, cost, net)
- [ ] Work-type mix (day-based vs deputation vs sub-con)
- [ ] Certificate-expiry / compliance report

**Formats & mechanics to decide**: CSV + Excel (`.xls` via HTML table or
`.csv`, no library needed on MilesWeb) for data; **PDF/print** for official
statements (voucher, credit note, invoice summary) using the existing
print-page approach; every report **respects the user's office/SBU scope** and
the current dashboard **filters** (FY, month, office, SBU, inspector).


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
- [x] **P6 · Attendance reconciliation** — DONE. New **Reconcile** tab. Upload the
      HR payroll Leave &amp; Attendance export **saved as CSV**; it is parsed **in
      memory only and never stored** (respects "don't copy the company doc").
      Auto-detects the header row + Employee Code / Present / Leave columns, matches
      by Employee Code to the inspector master, and compares **HR present/leave vs
      the app's voucher-derived present/leave** for the month — flagging OK /
      MISMATCH / In-HR-only / In-app-only with the differing cells highlighted.
      Verified: match=OK, HR4-vs-app2=MISMATCH, unknown code=In-HR-only.
- [x] **P7 · Profitability by BOSS/Contract** — DONE. New **Profitability** tab
      (gated by new `data.profitability` perm — granted to Master Admin, Business
      Director, SBU Head, Branch Manager, **Operation Manager** [manager under the
      branch manager] and Finance; **not** Coordinator/Inspector). List of BOSS
      numbers with Revenue / Expenses / Sub-con / Labour / **Margin ₹ + %**;
      detail page with stat row + **expense drill-down** (each line shows which
      inspector visited which vendor, hours, travel + bills + line total, with a
      **+ toggle** for the per-head breakdown) + invoice/job lines. Expenses roll
      voucher `row_total` (by boss_id) + job-closure expenses. Labour counted only
      when salary is visible (else "Contribution"). Verified: revenue 50k, expenses
      668, margin/%, drill-down. Super Admin can grant/revoke the perm per user.
- [x] **P8 · Contract/BOSS carry-forward** — DONE. On a BOSS profitability page,
      **Renew / change contract number (ARC/Open)** creates a new BOSS number
      linked to the old (`supersedes`/`superseded_by`), carries the **open jobs
      (and their voucher lines) forward** to the new number, closes the old, and
      shows the chain both ways ("continues from…", "renewed as…"). Closed/
      historical jobs stay on the old number. Verified: open job → new, closed job
      stays, chain linked.

## UI/UX rebuild v2 (staged, signed off) — DONE

Full app-wide rebuild on the new design system, done screen-by-screen with
sign-off between stages. Sidebar + slim top bar replace the old header; a
role-aware dashboard, an accountant money desk, and every core screen now share
the same cards / status pills / summary chips / clean tables — all driven by
the theme builder (no colour hardcoded, no CSS variable renamed).

- [x] **Sidebar shell** — grouped left rail (Operations / Money / Insights /
      Directory / Admin) with active highlighting + per-role visibility; slim
      top bar (office + FY chips, search, user); mobile drawer + scrim.
- [x] **Role-aware dashboard** — one template filled per role & scope (Director,
      SBU Head, Branch/Branch-App Manager, Manager/Asst, Coordinator, Accountant,
      Inspector); KPI tiles, money desk, expected-credit-by-office bars, job
      donut, quick actions, pending-scheduling — sections shown by permission,
      ordered per role.
- [x] **Accountant money desk** (`/invoicing`) — confirm-cards (Invoice pending /
      Awaiting payment / Overdue / Credit not received) over a worklist that
      writes the invoice/payment/credit fields already on jobs; office-scoped.
- [x] **Inspector phone view** — card-based My Jobs (To do / Completed, overdue
      flag) + a mobile bottom tab bar (Home / My Jobs / Voucher).
- [x] **Jobs & Calls lists** — summary chips + clean tables + status/money pills.
- [x] **Voucher grid** — status pill + headline KPI strip; mechanics (form=,
      live recalc, totals) untouched.
- [x] **Profitability list + detail** — KPI cards, margin pills, clean drill-down.
- [x] **Reports/Dashboards** — theme-variable colours; sticky filter re-aligned.

## UI/UX refresh — DONE

- [x] **Role-appropriate landing** — every user lands on the **home dashboard**
      after login (login → `/`); it is role-aware (managers get New Call / Jobs /
      Vouchers / Profitability / Dashboards / Masters; inspectors get My Jobs /
      My Voucher) with KPI cards + a live status chart, and all other screens are
      reached from there.
- [x] **Agency hiring cost on inspector** — when an engineer is engaged via an
      external agency, capture the **hiring agency** + **annual agency cost** on
      the inspector (salary-gated). It adds to that engineer's loaded labour
      (`salary_ctc + agency_cost`) so profitability/dashboards reflect the true
      cost. Verified: ₹240k agency cost on ₹600k CTC raised loaded labour
      correctly.
- [x] **Dashboards visual polish** — filter bar is now a sticky card; the four
      family sections (Operations / Financial / Utilization / People) have bold
      accent-underlined headers; chart panel sub-headers tidied.


- [x] **Professional UI refresh** — a design layer on top of `app.css` (sticky
      top bar with pill-hover nav, softer card radii + real elevation, gradient
      buttons with coloured shadow, soft form fields with focus rings, hover-lift
      stat/master cards, gentle table row-hover). Kept as a layer so it restyles
      **every existing screen** while the **theme builder** still drives all
      colours. `theme_style_tag()` now also emits `--field` and a luminance-aware
      `--shadow` (dark themes get proper dark fields/shadows).
- [x] **Landing / sign-in redesign** — `views/login_page.php`: a branded
      split-screen sign-in (value story + live chips on the left, clean sign-in
      card on the right, show/hide password), rendered standalone (no top-bar) and
      fully theme-driven (brand-gradient from `--brand`, accent glow from
      `--accent`). Verified: renders 200, theme (Forest) applies app-wide, no
      warnings.

## Per-office finance (overhead / contingency) — DONE

- [x] **Per-office Overhead % + Contingency %** — new **Overheads** screen
      (`/office-finance`). Each office sets its own Overhead % and Contingency %
      (Branch Application Manager edits their own office; global managers edit any
      office + the global default). Loaded labour = (CTC/12 × (1 + Overhead%)) /
      working days; Contingency % adds a buffer on (labour + expenses + sub-con).
      Both flow into `job_profit` and `boss_profit` → Profitability + Financial
      dashboards. Verified: OH 20% + contingency 5% raised labour 40k→44.4k, added
      ₹2,472 contingency, margin 55k→48.1k. (Replaces the flat 8% constant, which
      remains only as the ultimate fallback.)

## 🔮 Future phases (noted 2026-07, owner)

### Reports — Phase 2 (advanced, downloadable)
- [ ] Beyond the Phase-1 CSV exports (Jobs / Calls / Invoicing / Profitability /
      voucher), build the deeper analytics from the catalogue: **TAT report**
      (on-time vs late, avg, by office/SBU/inspector), **Office P&L** and **SBU
      P&L**, **inspector utilization & productivity**, **overdue aging (30/60/90)**
      on receivables, **inter-office credit statement** (given/received, expected
      vs actual reconciliation), and **true PDF** documents for invoices / credit
      notes / the signed voucher (currently print-to-PDF). Reuse the same
      scope + dashboard-filter pattern; add FY/Month/office/SBU pickers to each.

### CRM system (new module — before the Call in the chain)
- [ ] **CRM / quotation pipeline** — a front-end sales process that feeds the
      existing operations spine: **Lead / Enquiry → Quotation → Follow-up →
      Won/Lost → (on Won) auto-create a Call/BOSS**. Scope to define with owner,
      but likely includes: enquiry capture (client, contact, SBU, scope, source),
      **quotation builder** (line items, rates, GST, validity, revisions,
      PDF/print + email to client), **follow-up reminders & status** (open /
      quoted / negotiating / won / lost with reason), a **sales pipeline board**
      + conversion dashboard, and a hand-off that turns a won quotation into a
      **BOSS number + Call** (carrying client, SBU, PO, agreed value) so nothing
      is re-keyed. Reuses clients/contacts, offices, SBUs, access control and the
      CSV/PDF export already built. Sits *before* Calls in the Enquiry → Quotation
      → Call → Job → Voucher → Profitability chain. **Note:** the git branch is
      already named `…quotation-management-workflow…`, but no CRM/quotation code
      exists yet — this item is that module.

## 💡 Separate product idea (future — not part of this app)

- [ ] **Freelancer ⇄ Agency connect platform** — a standalone application (its own
      product, separate from the SGS inspection system) where **freelancers and
      agencies can find and connect with each other**: freelancers publish
      profiles/skills/availability/rates, agencies post requirements, and the two
      sides discover, message and engage each other (a two-sided marketplace).
      Could reuse concepts from our CV/hiring pipeline (candidate profiles, trade/
      skill masters, shortlisting) but is a NEW app for a broader audience — to be
      scoped separately later. Owner's idea, parked here so it isn't forgotten.

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
- [x] **Office 365 automatic email sending (SMTP)** — DONE. A **Settings → Email
      (Office 365 SMTP)** section takes host / port / username / app-password /
      from. When filled, assignment/closure/forward/reminder emails **auto-send**
      via a built-in SMTP client (STARTTLS + AUTH LOGIN, no library — works on
      MilesWeb). Left blank = current behaviour (logged + Open-in-Outlook). Safe:
      SMTP failures are caught and logged, never crash. Password blank-keeps the
      stored one. *(User just needs to enter their mailbox + app password.)*
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
