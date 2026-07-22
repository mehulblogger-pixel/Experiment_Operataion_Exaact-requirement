# Pending / parked items — SGS Ahmedabad Inspection Management System

Living list of things explicitly deferred, so nothing is forgotten. Newest on top.

## Additional features (user will provide details / build later)

- [ ] **Inspector expenses linked strictly to the job done** — an inspector's
      expenses must attach only to the job they performed (fuller rules to be
      provided by the user).
- [ ] **CV / hiring pipeline (deputation resourcing)** — for projects/clients
      needing a deputed engineer: submit CV → client shortlists → interview →
      Hold / Reject / Accept(=Hired). Track submitted candidates with trade,
      proposed site, CV-received date, current stage. Additional module.

## Parked (agreed to do later)

- [ ] **Full organisation structure** — model the hierarchy Business Director →
      SBU Heads → Branch Managers → Managers → Asst. Managers → Coordinators →
      Inspectors, with **multiple** Operation Managers and Coordinators **per
      office**, and users linked to their office. Today each office stores a
      single coordinator + manager email (used for forwarding/notifications), and
      there are 4 access roles. This is a role/permission + org redesign for a
      dedicated pass.
- [ ] **Multi-SBU cost distribution in dashboards** — inspectors can now be
      tagged to multiple SBUs; split their monthly loaded cost across those SBUs
      in the profitability/utilization reports (currently cost sits on the job's
      single SBU). The data (inspectors.sbus) is captured; the report split is
      pending.


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

## Module B (Calls) — remaining sub-items

- [ ] PO / line-item selection on a call (open PO ok; where line items/project
      defined, make them selectable and track each qty; e-mail manager + branch
      manager when a qty nears completion before validity).
- [ ] Project/site deputation → when inspection type = deputation and a client is
      chosen, show that client's sites in a dropdown that only appears then.
- [ ] Executing-branch confirmation status on the call ("assigned to X inspector
      for Y date; engineer is SGS asset / freelancer / sub-contractor").

## Module A (Client/Vendor) — remaining sub-items

- [ ] PO line items: manpower, site, trade→subcategory (Other→add), and
      GST / Tax / Total columns reflecting to contract & PO value; activity per
      line respecting the PO's SBU (multi-SBU).
- [ ] Projects tab: list the actual inspection calls linked to this client.
- [ ] City/State "auto-correct" of near-duplicate spellings (currently: State is
      a fixed dropdown; City has autocomplete from prior entries).
- [ ] Types-of-inspection "Other" free text on the call (multiple others).

## Modules C / D / E — not yet started

- [ ] C: Logo upload + editable theme (kept legible); per-SBU expense headings.
- [ ] D: inspection lifecycle/status flow; designations master (Inspector,
      Sr. Inspector, Sr. Executive…); back-office staff with costing (CTC,
      allowances).
- [ ] E: add your own dropdown/text box to any master form (custom fields on
      masters).

## Dashboards — refinements to follow

- [ ] **Per-SBU configurable expense headings** — expenses are currently entered
      under 5 fixed headings (travel/local/food/lodging/misc) and the financial
      dashboard breaks them down accordingly. An `expense_heading` master now
      exists; the remaining work is dynamic heading entry configurable per SBU.
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
